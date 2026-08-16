<?php

declare(strict_types=1);

namespace App\Auth\Oauth;

/**
 * 検証を通り抜けた `/oauth/authorize` のリクエスト。
 *
 * このオブジェクトが存在すること自体が「client_id は登録済みで、
 * redirect_uri は登録値と一致し、PKCE の challenge も揃っている」の証明になる。
 * 同意画面はここに入っている値だけを表示すればよい。
 */
final readonly class AuthorizationRequest
{
    /**
     * @param string $clientName 同意画面に出す名前。クライアントの自称なので、
     *                           これだけを信用させず redirect_uri のホストも並べて出す
     */
    public function __construct(
        public string $clientId,
        public string $clientName,
        public string $redirectUri,
        public string $codeChallenge,
        public string $scope,
        public string $resource,
        public string $state,
    ) {}

    /**
     * 同意画面に出すリダイレクト先のホスト。
     *
     * 「どこへ戻されるのか」はクライアント名より確かな手がかりなので、
     * 仕様も認可サーバーにこれを表示するよう求めている。
     */
    public function redirectHost(): string
    {
        $host = \parse_url($this->redirectUri, \PHP_URL_HOST);

        return \is_string($host) && '' !== $host ? $host : $this->redirectUri;
    }

    /**
     * 認可コード（またはエラー）を載せた戻り先 URL を組む。
     *
     * @param array<string, string> $params
     */
    public function callbackUrl(array $params): string
    {
        if ('' !== $this->state) {
            $params['state'] = $this->state;
        }

        $separator = \str_contains($this->redirectUri, '?') ? '&' : '?';

        return $this->redirectUri . $separator . \http_build_query($params);
    }
}
