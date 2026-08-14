<?php

declare(strict_types=1);

use App\Auth\GithubOAuth;
use App\Support\SiteConfig;
use Polidog\Relayer\Http\Request;
use Polidog\Relayer\Http\Response;

/**
 * GitHub からの戻り。code と state を検証し、許可アカウントなら
 * セッションにログイン状態を作る。
 *
 * 失敗の理由（state 不一致・トークン交換失敗・許可外アカウント）は
 * 区別せず `?error=1` だけを返す。どれが原因か伝えると、許可されている
 * アカウント名を総当たりで探る手掛かりになる。
 */
return [
    'GET' => function (Request $request, GithubOAuth $oauth, SiteConfig $site): Response {
        $code = $request->query('code') ?? '';
        $state = $request->query('state') ?? '';

        if ('' === $code || '' === $state) {
            return Response::redirect('/admin/login?error=1', 302);
        }

        $identity = $oauth->completeLogin($code, $state, $site->rawUrl('/admin/auth/callback'));

        return Response::redirect(null !== $identity ? '/admin' : '/admin/login?error=1', 302);
    },
];
