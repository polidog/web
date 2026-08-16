<?php

declare(strict_types=1);

namespace App\Auth\Oauth;

use App\Support\SiteConfig;

/**
 * OAuth のディスカバリ文書を組む。
 *
 * Claude のカスタムコネクタは、繋ぎ先の URL を叩いて 401 を受け、
 * そこに書かれた場所からこの 2 つの JSON を読んで認可サーバーを見つける。
 * 内容が実際の URL とずれていると「サーバーに到達できません」で終わり、
 * 原因がクライアント側に出ないので、URL の組み立てはここ 1 箇所に閉じる。
 */
final readonly class OauthMetadata
{
    /**
     * スコープは 1 つだけ。入れるのは管理者 1 人で、権限の境界が
     * 増えるわけでもないのに同意画面だけが複雑になる。
     */
    public const string SCOPE = 'cms';

    public function __construct(
        private SiteConfig $site,
    ) {}

    /**
     * 認可サーバーの issuer。RFC 8414 のディスカバリはこの値に
     * `/.well-known/oauth-authorization-server` を足した URL で引かれるので、
     * パスを持たせない（持たせるとクライアント側の探索パスが増える）。
     */
    public function issuer(): string
    {
        return \rtrim($this->site->siteUrl, '/');
    }

    /**
     * 保護対象リソースの識別子 = MCP エンドポイントの URL。
     *
     * **ユーザーが Claude に入力する URL と 1 文字も違ってはいけない。**
     * ここが食い違うと、トークンは発行されるのに MCP 側の audience 検証で
     * 弾かれ続ける。`SiteConfig::absoluteUrl()` を使わないのは、あちらが
     * Hugo 時代の URL に合わせて末尾スラッシュを付けるため。
     */
    public function resource(): string
    {
        return $this->issuer() . '/mcp';
    }

    public function resourceMetadataUrl(): string
    {
        return $this->issuer() . '/.well-known/oauth-protected-resource/mcp';
    }

    /**
     * RFC 9728 Protected Resource Metadata。
     *
     * @return array<string, mixed>
     */
    public function protectedResource(): array
    {
        return [
            'resource' => $this->resource(),
            'authorization_servers' => [$this->issuer()],
            'bearer_methods_supported' => ['header'],
            'scopes_supported' => [self::SCOPE],
        ];
    }

    /**
     * RFC 8414 Authorization Server Metadata。
     *
     * `code_challenge_methods_supported` が無いとクライアントは
     * 「PKCE を検証できない」と判断して接続を中断する（MCP の仕様が
     * そう定めている）ので、必ず入れる。
     * `token_endpoint_auth_methods_supported` が `none` なのは、
     * Claude が client_secret を持たない公開クライアントとして登録されるため。
     *
     * @return array<string, mixed>
     */
    public function authorizationServer(): array
    {
        $issuer = $this->issuer();

        return [
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer . '/oauth/authorize',
            'token_endpoint' => $issuer . '/oauth/token',
            'registration_endpoint' => $issuer . '/oauth/register',
            'scopes_supported' => [self::SCOPE],
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'code_challenge_methods_supported' => ['S256'],
        ];
    }

    /**
     * 401 に載せる `WWW-Authenticate` の値。
     *
     * `resource_metadata` がここに無いと、クライアントは認可サーバーの
     * 在り処を知る手段を失う（well-known の探索にフォールバックはするが、
     * 往復が増えるうえ `/.well-known/*` がホスティング側で塞がれていると
     * そこで詰む）。
     */
    public function challenge(string $error = 'invalid_token', string $description = 'Authentication required'): string
    {
        return \sprintf(
            'Bearer error="%s", error_description="%s", resource_metadata="%s", scope="%s"',
            $error,
            $description,
            $this->resourceMetadataUrl(),
            self::SCOPE,
        );
    }
}
