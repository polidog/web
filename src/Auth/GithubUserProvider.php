<?php

declare(strict_types=1);

namespace App\Auth;

use Polidog\Relayer\Auth\Credentials;
use Polidog\Relayer\Auth\UserProvider;

/**
 * パスワード認証は使わない。それでもこのクラスが要るのは、Relayer が
 * `UserProvider` か `TokenVerifier` が DI にバインドされているときだけ
 * `Authenticator` を登録する作りだから
 * （vendor/polidog/relayer/src/Di/ContainerFactory.php の遅延登録）。
 * バインドしないと `#[Auth]` も `requireAuth()` も `?Identity` の注入も
 * 動かない。
 *
 * ログインは GithubOAuth が本人確認を済ませたうえで
 * `Authenticator::login(Identity)` を呼ぶ経路だけ。これはフレームワークが
 * SSO 向けに用意している正規のルートで、`attempt()` は通らない。
 * ここが常に null を返すので、仮に誰かが `attempt()` を呼んでも
 * 必ず失敗する（パスワード経路が事故で開くことはない）。
 */
final class GithubUserProvider implements UserProvider
{
    public function findByIdentifier(string $identifier): ?Credentials
    {
        return null;
    }
}
