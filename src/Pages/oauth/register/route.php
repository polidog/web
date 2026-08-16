<?php

declare(strict_types=1);

use App\Auth\Oauth\OauthServer;
use App\Http\JsonRequestBody;
use Polidog\Relayer\Http\Response;

/**
 * Dynamic Client Registration (RFC 7591)。
 *
 * **無認証で叩ける。** 仕様がそう定めているし、そうでないと Claude は
 * 最初の 1 回で詰まる（まだトークンを持っていないため）。登録できるのは
 * 「https か loopback の redirect_uri を持つ公開クライアント」だけで、
 * 登録しただけでは何の権限も無い —— 実際にアクセスできるようになるのは、
 * 管理者が /oauth/authorize で同意したときだけ。
 *
 * ボディは JSON（RFC 7591 §3.1）。隣の /oauth/token は
 * form-urlencoded なので、読み取り方を取り違えないこと。
 */
return [
    'POST' => function (OauthServer $oauth, JsonRequestBody $body): Response {
        $result = $oauth->register($body->decode());

        return Response::json($result['body'], $result['status'], ['Cache-Control' => 'no-store']);
    },
];
