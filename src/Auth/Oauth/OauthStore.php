<?php

declare(strict_types=1);

namespace App\Auth\Oauth;

use App\Tehilim\TehilimClient;
use DateTimeImmutable;

/**
 * OAuth のクライアント・認可コード・トークンの置き場。
 *
 * **平文の秘密は 1 つも保存しない。** 認可コードもアクセストークンも
 * リフレッシュトークンも、DB に入るのは SHA-256 だけ。DB ファイルは記事と
 * 同じ volume に載っていて、バックアップにも入るため。ハッシュに salt を
 * 付けないのは、元が 256bit の乱数で総当たりも辞書攻撃も成立しないから。
 *
 * 期限切れの判定は SQL ではなく PHP 側でやる。tehilim は DateTime を
 * TEXT に書くので `where` の大小比較が文字列比較になり、書式が 1 文字でも
 * ずれると黙って誤判定する。掃除（gc）だけは取りこぼしても実害が無いので
 * SQL 側の比較に任せている。
 */
final class OauthStore
{
    /** 認可コードは往復 1 回ぶんしか生きない。 */
    private const int CODE_TTL = 60;

    private const int ACCESS_TTL = 3600;

    private const int REFRESH_TTL = 2592000;

    /**
     * Dynamic Client Registration は無認証で叩ける（仕様がそう定めている）。
     * Claude は接続のたびに新しいクライアントを登録しうるので、放っておくと
     * 際限なく増える。上限を決めて古い順に捨てる。
     */
    private const int MAX_CLIENTS = 200;

    public function __construct(
        private readonly TehilimClient $db,
    ) {}

    /**
     * @param list<string> $redirectUris
     *
     * @return array{clientId: string, clientName: string, redirectUris: list<string>, tokenEndpointAuthMethod: string}
     */
    public function registerClient(string $clientName, array $redirectUris, string $tokenEndpointAuthMethod): array
    {
        $this->pruneClients();

        $clientId = \bin2hex(\random_bytes(16));

        $this->db->oauthClient->insert([
            'data' => [
                'clientId' => $clientId,
                'clientName' => $clientName,
                'redirectUris' => (string) \json_encode($redirectUris, \JSON_UNESCAPED_SLASHES),
                'tokenEndpointAuthMethod' => $tokenEndpointAuthMethod,
                'createdAt' => new DateTimeImmutable(),
            ],
        ]);

        return [
            'clientId' => $clientId,
            'clientName' => $clientName,
            'redirectUris' => $redirectUris,
            'tokenEndpointAuthMethod' => $tokenEndpointAuthMethod,
        ];
    }

    /**
     * @return null|array{clientId: string, clientName: string, redirectUris: list<string>}
     */
    public function findClient(string $clientId): ?array
    {
        $row = $this->db->oauthClient->findUnique(['where' => ['clientId' => $clientId]]);
        if (null === $row) {
            return null;
        }

        /** @var mixed $uris */
        $uris = \json_decode($row['redirectUris'], true);

        return [
            'clientId' => $row['clientId'],
            'clientName' => $row['clientName'],
            'redirectUris' => \is_array($uris)
                ? \array_values(\array_filter($uris, static fn (mixed $v): bool => \is_string($v)))
                : [],
        ];
    }

    /**
     * 認可コードを発行し、**平文**を返す（保存されるのはハッシュだけなので、
     * 呼び出し側がリダイレクトに載せる機会はこの 1 度きり）。
     */
    public function issueCode(
        string $clientId,
        string $redirectUri,
        string $codeChallenge,
        string $scope,
        string $resource,
    ): string {
        $this->gc();

        $code = \bin2hex(\random_bytes(32));

        $this->db->oauthAuthCode->insert([
            'data' => [
                'codeHash' => self::hash($code),
                'clientId' => $clientId,
                'redirectUri' => $redirectUri,
                'codeChallenge' => $codeChallenge,
                'scope' => $scope,
                'resource' => $resource,
                'expiresAt' => new DateTimeImmutable('+' . self::CODE_TTL . ' seconds'),
                'createdAt' => new DateTimeImmutable(),
            ],
        ]);

        return $code;
    }

    /**
     * 認可コードを引き換える。成功したら二度と使えない。
     *
     * **使用済みのコードが再提示されたら、そのクライアントに出したトークンを
     * すべて失効させる。** コードが横取りされた可能性があり、正規のクライアントと
     * 攻撃者のどちらが先に引き換えたか分からないため（OAuth 2.1 の推奨）。
     *
     * @return null|array{clientId: string, redirectUri: string, codeChallenge: string, scope: string, resource: string}
     */
    public function consumeCode(string $code): ?array
    {
        $row = $this->db->oauthAuthCode->findUnique(['where' => ['codeHash' => self::hash($code)]]);
        if (null === $row) {
            return null;
        }

        if (null !== $row['usedAt']) {
            $this->revokeClientTokens($row['clientId']);

            return null;
        }

        if ($row['expiresAt'] < new DateTimeImmutable()) {
            return null;
        }

        $this->db->oauthAuthCode->update([
            'where' => ['id' => $row['id']],
            'data' => ['usedAt' => new DateTimeImmutable()],
        ]);

        return [
            'clientId' => $row['clientId'],
            'redirectUri' => $row['redirectUri'],
            'codeChallenge' => $row['codeChallenge'],
            'scope' => $row['scope'],
            'resource' => $row['resource'],
        ];
    }

    /**
     * アクセストークンとリフレッシュトークンを 1 組発行し、平文を返す。
     *
     * @return array{accessToken: string, refreshToken: string, expiresIn: int}
     */
    public function issueTokens(string $clientId, string $scope, string $resource): array
    {
        $this->gc();

        $access = \bin2hex(\random_bytes(32));
        $refresh = \bin2hex(\random_bytes(32));

        $this->db->oauthToken->insert([
            'data' => [
                'accessTokenHash' => self::hash($access),
                'refreshTokenHash' => self::hash($refresh),
                'clientId' => $clientId,
                'scope' => $scope,
                'resource' => $resource,
                'accessExpiresAt' => new DateTimeImmutable('+' . self::ACCESS_TTL . ' seconds'),
                'refreshExpiresAt' => new DateTimeImmutable('+' . self::REFRESH_TTL . ' seconds'),
                'createdAt' => new DateTimeImmutable(),
            ],
        ]);

        return ['accessToken' => $access, 'refreshToken' => $refresh, 'expiresIn' => self::ACCESS_TTL];
    }

    /**
     * @return null|array{clientId: string, scope: string, resource: string}
     */
    public function findAccessToken(string $token): ?array
    {
        $row = $this->db->oauthToken->findUnique(['where' => ['accessTokenHash' => self::hash($token)]]);

        if (null === $row || null !== $row['revokedAt'] || $row['accessExpiresAt'] < new DateTimeImmutable()) {
            return null;
        }

        return ['clientId' => $row['clientId'], 'scope' => $row['scope'], 'resource' => $row['resource']];
    }

    /**
     * リフレッシュトークンを新しい 1 組と交換する。古い組はその場で失効。
     *
     * 公開クライアント（client_secret を持たない = Claude）にはローテーションが
     * 必須で、盗まれたトークンが使われれば正規のクライアントが次の更新で
     * 弾かれ、異常に気づける。
     *
     * @return null|array{accessToken: string, refreshToken: string, expiresIn: int, scope: string}
     */
    public function rotateRefreshToken(string $refreshToken, string $clientId): ?array
    {
        $row = $this->db->oauthToken->findUnique(['where' => ['refreshTokenHash' => self::hash($refreshToken)]]);

        if (null === $row
            || null !== $row['revokedAt']
            || $row['clientId'] !== $clientId
            || null === $row['refreshExpiresAt']
            || $row['refreshExpiresAt'] < new DateTimeImmutable()
        ) {
            return null;
        }

        $this->db->oauthToken->update([
            'where' => ['id' => $row['id']],
            'data' => ['revokedAt' => new DateTimeImmutable()],
        ]);

        $issued = $this->issueTokens($row['clientId'], $row['scope'], $row['resource']);

        return $issued + ['scope' => $row['scope']];
    }

    public function revokeClientTokens(string $clientId): void
    {
        $this->db->oauthToken->updateMany([
            'where' => ['clientId' => $clientId, 'revokedAt' => null],
            'data' => ['revokedAt' => new DateTimeImmutable()],
        ]);
    }

    private static function hash(string $value): string
    {
        return \hash('sha256', $value);
    }

    /**
     * 期限切れの認可コードと失効済みトークンを捨てる。行数が少ないので
     * 書き込みのたびに走らせて構わない（cron を増やさない）。
     */
    private function gc(): void
    {
        $now = new DateTimeImmutable();

        $this->db->oauthAuthCode->deleteMany(['where' => ['expiresAt' => ['lt' => $now]]]);
        $this->db->oauthToken->deleteMany(['where' => ['refreshExpiresAt' => ['lt' => $now]]]);
    }

    /**
     * 登録済みクライアントが上限を超えていたら、古いものから消す。
     * ぶら下がっているトークンも一緒に失効させないと、消えたクライアントの
     * トークンだけが生き残る。
     */
    private function pruneClients(): void
    {
        $total = $this->db->oauthClient->count();
        if ($total < self::MAX_CLIENTS) {
            return;
        }

        $stale = $this->db->oauthClient->findMany([
            'orderBy' => ['createdAt' => 'asc'],
            'take' => $total - self::MAX_CLIENTS + 1,
        ]);

        foreach ($stale as $client) {
            $this->db->oauthToken->deleteMany(['where' => ['clientId' => $client['clientId']]]);
            $this->db->oauthAuthCode->deleteMany(['where' => ['clientId' => $client['clientId']]]);
            $this->db->oauthClient->delete(['where' => ['id' => $client['id']]]);
        }
    }
}
