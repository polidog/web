<?php

declare(strict_types=1);

use App\Auth\Oauth\OauthServer;
use App\Http\JsonRequestBody;
use App\Mcp\McpServer;
use Polidog\Relayer\Http\Request;
use Polidog\Relayer\Http\Response;

/**
 * MCP エンドポイント。Claude のカスタムコネクタにはこの URL を登録する。
 *
 * GET に SSE を返さないので、サーバーから勝手に喋ることはできない。
 * ここが提供するのはツールだけで、進捗通知も購読も無いため要らない
 * ——そして Relayer の Response は本文が文字列 1 つなので、そもそも
 * ストリームを返す手段が無い。
 *
 * CSRF は見ない（ブラウザのセッションではなく Bearer トークンで守っている）。
 * 代わりに Origin を検証する。
 */
return [
    'POST' => function (
        Request $request,
        JsonRequestBody $body,
        McpServer $mcp,
        OauthServer $oauth,
    ): Response {
        if (!$mcp->originAllowed($request->header('Origin'))) {
            return Response::json(
                ['error' => 'forbidden_origin'],
                403,
                ['Cache-Control' => 'no-store'],
            );
        }

        if (!$mcp->protocolVersionAllowed($request->header('MCP-Protocol-Version'))) {
            return Response::json(
                ['error' => 'unsupported_protocol_version'],
                400,
                ['Cache-Control' => 'no-store'],
            );
        }

        if (null === $oauth->authenticate($request->header('Authorization'))) {
            return $mcp->unauthorized();
        }

        return $mcp->handle($body->decode());
    },

    // SSE は提供しない。仕様が認めている返し方。
    'GET' => fn (): Response => Response::json(
        ['error' => 'method_not_allowed', 'error_description' => 'この MCP エンドポイントは POST のみ受け付けます。'],
        405,
        ['Allow' => 'POST', 'Cache-Control' => 'no-store'],
    ),
];
