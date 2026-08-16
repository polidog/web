<?php

declare(strict_types=1);

use App\Auth\Oauth\OauthMetadata;
use Polidog\Relayer\Http\Response;

/**
 * RFC 9728 Protected Resource Metadata（ルート版）。
 *
 * MCP クライアントは 401 の `WWW-Authenticate` にある `resource_metadata` を
 * 最優先で使うが、それが無い場合は
 * `/.well-known/oauth-protected-resource/<mcp のパス>` → このルート版、の順に
 * 探索する。両方置いておけば、どちらの経路で来ても認可サーバーに辿り着ける。
 *
 * Cloudflare に持たせてよい（秘密を含まず、内容は滅多に変わらない）。
 * Claude 側もディスカバリ文書を 5 分ほどキャッシュするので、それに合わせる。
 */
return [
    'GET' => fn (OauthMetadata $metadata): Response => Response::json(
        $metadata->protectedResource(),
        200,
        ['Cache-Control' => 'public, max-age=300'],
    ),
];
