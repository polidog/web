<?php

declare(strict_types=1);

use App\Auth\Oauth\OauthMetadata;
use Polidog\Relayer\Http\Response;

/**
 * RFC 8414 Authorization Server Metadata。
 *
 * `issuer` にパスを持たせていないので、クライアントはこの 1 本だけを見る。
 * ここに `/authorize`・`/token`・`/register` の在り処と、PKCE の対応状況が書いてある。
 */
return [
    'GET' => fn (OauthMetadata $metadata): Response => Response::json(
        $metadata->authorizationServer(),
        200,
        ['Cache-Control' => 'public, max-age=300'],
    ),
];
