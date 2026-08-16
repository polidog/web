<?php

declare(strict_types=1);

namespace App\Auth;

use App\Tehilim\TehilimClient;
use DateTimeImmutable;
use Polidog\Relayer\Auth\Credentials;
use Polidog\Relayer\Auth\Identity;
use Polidog\Relayer\Auth\UserProvider;

/**
 * 管理画面のログイン。入れるのは 1 人だけで、その資格情報
 * （メールアドレスとパスワードハッシュ）は環境変数が持つ。
 *
 * DB にパスワードを置かないのは、利用者が増える見込みが無いから。
 * 「登録・変更・リセット」の導線を作るより、`fly secrets set` で
 * 差し替えるほうが面倒が少なく、漏れる面も小さい。
 * 検証そのものは Relayer の `Authenticator::attempt()` に任せる
 * （`password_verify` と、未知の識別子に対する時間差の均しが付いてくる）。
 *
 * `User` の行はパスワードのためではなく、記事の `authorId` が指す先として
 * 要る。ログインするのは常に同じ 1 人なので、その行をここで作る。
 *
 * @phpstan-import-type UserRow from \App\Tehilim\Model\User
 */
final class AdminUserProvider implements UserProvider
{
    public function __construct(
        private readonly TehilimClient $db,
        private readonly string $email = '',
        private readonly string $passwordHash = '',
    ) {}

    /**
     * どちらか一方でも空なら誰も入れない。設定漏れで管理画面が全開になる
     * より、誰も入れないほうが安全側に倒れる。
     */
    public function configured(): bool
    {
        return '' !== $this->email && '' !== $this->passwordHash;
    }

    /**
     * 管理者の User.id。行がまだ無ければ null（ここでは作らない）。
     *
     * MCP から記事を保存するときの authorId に使う。あちらにはセッションが
     * 無く Identity を受け取れないが、書き手は結局この 1 人しか居ないので、
     * 管理画面から保存したものと同じ id が入るのが正しい。
     */
    public function adminId(): ?int
    {
        if (!$this->configured()) {
            return null;
        }

        $user = $this->db->user->findUnique(['where' => ['email' => $this->email]]);

        return null !== $user ? $user['id'] : null;
    }

    public function findByIdentifier(string $identifier): ?Credentials
    {
        if (!$this->configured()) {
            return null;
        }

        // メールアドレスの大文字小文字は区別しない。ここで弾いた場合の
        // 応答時間は Authenticator がダミーハッシュで均してくれるので、
        // 「そのアドレスは存在しない」と読み取られることはない。
        if (0 !== \strcasecmp(\trim($identifier), $this->email)) {
            return null;
        }

        $user = $this->admin();

        return new Credentials(
            identity: new Identity(
                id: $user['id'],
                displayName: $user['email'],
                roles: [$user['role']],
            ),
            passwordHash: $this->passwordHash,
        );
    }

    /**
     * 管理者の行。初回ログイン時にだけ作られる。
     *
     * パスワード照合より前に走るので、アドレスさえ当てれば未ログインでも
     * この INSERT を起こせる。それでも構わないのは、入るのが設定値そのもの
     * 1 行だけで（攻撃者の入力は 1 バイトも残らない）、2 回目以降は
     * 読むだけになるため。
     *
     * @return UserRow
     */
    private function admin(): array
    {
        $found = $this->db->user->findUnique(['where' => ['email' => $this->email]]);
        if (null !== $found) {
            return $found;
        }

        return $this->db->user->insert([
            'data' => [
                'email' => $this->email,
                'role' => 'admin',
                'createdAt' => new DateTimeImmutable(),
            ],
        ]);
    }
}
