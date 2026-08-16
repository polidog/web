<?php

declare(strict_types=1);

use App\Auth\Oauth\OauthMetadata;
use Polidog\Relayer\Http\Response;

/**
 * RFC 9728 Protected Resource Metadata（パス付き版）。
 *
 * 保護対象リソースが `https://polidog.jp/mcp` のようにパスを持つとき、
 * クライアントはまず `/.well-known/oauth-protected-resource/mcp` を試す。
 * 隣の階層にあるルート版と中身は同じで、置き場所だけが違う。
 */
return [
    'GET' => fn (OauthMetadata $metadata): Response => Response::json(
        $metadata->protectedResource(),
        200,
        ['Cache-Control' => 'public, max-age=300'],
    ),
];
