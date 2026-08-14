<?php

declare(strict_types=1);

use App\Auth\GithubOAuth;
use App\Support\SiteConfig;
use Polidog\Relayer\Http\Response;

/**
 * GitHub の authorize へ送り出す。state をセッションに置くのは
 * GithubOAuth::authorizeUrl() の中。
 */
return [
    'GET' => function (GithubOAuth $oauth, SiteConfig $site): Response {
        if (!$oauth->configured()) {
            return Response::redirect('/admin/login?error=1', 302);
        }

        // GitHub は redirect_uri を登録値と完全一致で照合するので、
        // 末尾スラッシュを足さない rawUrl を使う。
        return Response::redirect(
            $oauth->authorizeUrl($site->rawUrl('/admin/auth/callback')),
            302,
        );
    },
];
