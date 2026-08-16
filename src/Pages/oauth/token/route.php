<?php

declare(strict_types=1);

use App\Auth\Oauth\OauthServer;
use Polidog\Relayer\Http\Request;
use Polidog\Relayer\Http\Response;

/**
 * トークンエンドポイント。認可コードの引き換えと、リフレッシュの更新。
 *
 * ボディは `application/x-www-form-urlencoded`（RFC 6749 §4.1.3）なので
 * `$request->allPost()` がそのまま使える。JSON で受けようとすると
 * 415 を返すことになり、クライアントからは「トークンが取れない」としか
 * 見えない状態になる。
 *
 * CSRF トークンは検証しない。ここに来るのはブラウザのセッションを持たない
 * サーバー間のリクエストで、守っているのは認可コードと PKCE の検証。
 */
return [
    'POST' => function (Request $request, OauthServer $oauth): Response {
        $result = $oauth->token($request->allPost());

        return Response::json($result['body'], $result['status'], [
            'Cache-Control' => 'no-store',
            'Pragma' => 'no-cache',
        ]);
    },
];
