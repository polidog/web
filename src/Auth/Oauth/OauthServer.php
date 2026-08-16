<?php

declare(strict_types=1);

namespace App\Auth\Oauth;

/**
 * OAuth 2.1 認可サーバーの本体。
 *
 * 相手は Claude ただ 1 つで、入るのは管理者 1 人。それでも仕様どおりに
 * 作る必要があるのは、クライアント側の実装がここを一切忖度しないから
 * ——PKCE の宣言が無ければ接続を中断し、`invalid_grant` 以外のエラーコードを
 * 返せばリフレッシュが壊れる。
 *
 * 認可コードとトークンの保管は OauthStore。ここは「受け取った値が正しいか」
 * だけを見る。
 */
final readonly class OauthServer
{
    /**
     * 未ログインで /oauth/authorize に来た人のクエリを預けておくセッションキー。
     *
     * 管理画面のログインは「終わったら /admin へ」の一本道で、戻り先を
     * 受け取る口が無い。任意の URL を受け取れるようにするとオープン
     * リダイレクトの入口になるので、戻り先は /oauth/authorize 固定にして、
     * クエリだけをここに預ける。
     */
    public const string PENDING_SESSION_KEY = 'oauth.authorize.pending';

    /** 認可コードのフロー以外は受け付けない。 */
    private const string RESPONSE_TYPE = 'code';

    public function __construct(
        private OauthStore $store,
        private OauthMetadata $metadata,
    ) {}

    // ------------------------------------------------------------------
    // Dynamic Client Registration (RFC 7591)
    // ------------------------------------------------------------------

    /**
     * @param null|array<string, mixed> $payload JSON デコード済みのボディ
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function register(?array $payload): array
    {
        if (null === $payload) {
            return self::registrationError('invalid_client_metadata', 'JSON のボディが必要です。');
        }

        $redirectUris = [];
        /** @var mixed $rawUris */
        $rawUris = $payload['redirect_uris'] ?? null;
        if (\is_array($rawUris)) {
            foreach ($rawUris as $uri) {
                if (\is_string($uri) && self::redirectUriAcceptable($uri)) {
                    $redirectUris[] = $uri;
                }
            }
        }

        if ([] === $redirectUris) {
            return self::registrationError(
                'invalid_redirect_uri',
                'redirect_uris には https の URL か loopback アドレスが要ります。',
            );
        }

        /** @var mixed $rawName */
        $rawName = $payload['client_name'] ?? null;
        $clientName = \is_string($rawName) && '' !== \trim($rawName)
            ? \mb_substr(\trim($rawName), 0, 100)
            : 'Unnamed client';

        /** @var mixed $rawMethod */
        $rawMethod = $payload['token_endpoint_auth_method'] ?? null;
        // 公開クライアントしか受けない。client_secret を発行しないので、
        // 認証方法を選ばせる余地が無い。
        $authMethod = 'none';
        if (\is_string($rawMethod) && 'none' !== $rawMethod) {
            return self::registrationError(
                'invalid_client_metadata',
                'token_endpoint_auth_method は none のみ対応しています。',
            );
        }

        $client = $this->store->registerClient($clientName, $redirectUris, $authMethod);

        return [
            'status' => 201,
            'body' => [
                'client_id' => $client['clientId'],
                'client_id_issued_at' => \time(),
                'client_name' => $client['clientName'],
                'redirect_uris' => $client['redirectUris'],
                'token_endpoint_auth_method' => $client['tokenEndpointAuthMethod'],
                'grant_types' => ['authorization_code', 'refresh_token'],
                'response_types' => ['code'],
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Authorization endpoint
    // ------------------------------------------------------------------

    /**
     * 同意画面を出す前の検証。ここを通れば以降の値は信用してよい。
     *
     * @param array<string, mixed> $query
     *
     * @throws OauthException 画面に出す日本語のメッセージ付き
     */
    public function authorizationRequest(array $query): AuthorizationRequest
    {
        $clientId = self::str($query, 'client_id');
        if ('' === $clientId) {
            throw new OauthException('client_id がありません。');
        }

        $client = $this->store->findClient($clientId);
        if (null === $client) {
            throw new OauthException('このクライアントは登録されていません。接続をやり直してください。');
        }

        $redirectUri = self::str($query, 'redirect_uri');
        if ('' === $redirectUri) {
            throw new OauthException('redirect_uri がありません。');
        }

        if (!self::redirectUriRegistered($redirectUri, $client['redirectUris'])) {
            throw new OauthException('redirect_uri が登録された値と一致しません。');
        }

        if (self::RESPONSE_TYPE !== self::str($query, 'response_type')) {
            throw new OauthException('response_type は code のみ対応しています。');
        }

        $challenge = self::str($query, 'code_challenge');
        if ('' === $challenge || 'S256' !== self::str($query, 'code_challenge_method')) {
            throw new OauthException('PKCE（code_challenge_method=S256）が必要です。');
        }

        // resource は RFC 8707 のリソース指定。ここで縛っておくと、
        // 発行したトークンが別のサーバー宛てとして使い回されない。
        $resource = self::str($query, 'resource');
        if ('' !== $resource && !self::sameResource($resource, $this->metadata->resource())) {
            throw new OauthException(\sprintf(
                'resource が %s と一致しません。コネクタに登録した URL を確認してください。',
                $this->metadata->resource(),
            ));
        }

        return new AuthorizationRequest(
            clientId: $clientId,
            clientName: $client['clientName'],
            redirectUri: $redirectUri,
            codeChallenge: $challenge,
            scope: OauthMetadata::SCOPE,
            resource: $this->metadata->resource(),
            state: self::str($query, 'state'),
        );
    }

    /**
     * 同意を受けて認可コードを発行し、戻り先の URL を返す。
     */
    public function approve(AuthorizationRequest $request): string
    {
        $code = $this->store->issueCode(
            $request->clientId,
            $request->redirectUri,
            $request->codeChallenge,
            $request->scope,
            $request->resource,
        );

        return $request->callbackUrl(['code' => $code]);
    }

    public function deny(AuthorizationRequest $request): string
    {
        return $request->callbackUrl(['error' => 'access_denied']);
    }

    // ------------------------------------------------------------------
    // Token endpoint
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $form application/x-www-form-urlencoded のボディ
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function token(array $form): array
    {
        return match (self::str($form, 'grant_type')) {
            'authorization_code' => $this->exchangeCode($form),
            'refresh_token' => $this->refresh($form),
            default => self::tokenError('unsupported_grant_type', 'grant_type が未対応です。'),
        };
    }

    /**
     * @param array<string, mixed> $form
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    private function exchangeCode(array $form): array
    {
        $code = self::str($form, 'code');
        $clientId = self::str($form, 'client_id');
        $verifier = self::str($form, 'code_verifier');

        if ('' === $code || '' === $clientId || '' === $verifier) {
            return self::tokenError('invalid_request', 'code / client_id / code_verifier が必要です。');
        }

        $grant = $this->store->consumeCode($code);
        if (null === $grant) {
            return self::tokenError('invalid_grant', '認可コードが無効か、期限切れです。');
        }

        if (!\hash_equals($grant['clientId'], $clientId)) {
            return self::tokenError('invalid_grant', 'client_id が認可時と一致しません。');
        }

        // redirect_uri は認可時と同じでなければならない（コードの取り違えを防ぐ）。
        $redirectUri = self::str($form, 'redirect_uri');
        if ('' !== $redirectUri && !\hash_equals($grant['redirectUri'], $redirectUri)) {
            return self::tokenError('invalid_grant', 'redirect_uri が認可時と一致しません。');
        }

        if (!self::verifyPkce($verifier, $grant['codeChallenge'])) {
            return self::tokenError('invalid_grant', 'code_verifier が code_challenge と一致しません。');
        }

        $resource = self::str($form, 'resource');
        if ('' !== $resource && !self::sameResource($resource, $grant['resource'])) {
            return self::tokenError('invalid_target', 'resource が認可時と一致しません。');
        }

        $issued = $this->store->issueTokens($grant['clientId'], $grant['scope'], $grant['resource']);

        return self::tokenResponse($issued['accessToken'], $issued['refreshToken'], $issued['expiresIn'], $grant['scope']);
    }

    /**
     * @param array<string, mixed> $form
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    private function refresh(array $form): array
    {
        $refreshToken = self::str($form, 'refresh_token');
        $clientId = self::str($form, 'client_id');

        if ('' === $refreshToken || '' === $clientId) {
            return self::tokenError('invalid_request', 'refresh_token と client_id が必要です。');
        }

        $issued = $this->store->rotateRefreshToken($refreshToken, $clientId);
        if (null === $issued) {
            // ここで invalid_request や独自のコードを返すと、クライアントは
            // 「一時的な失敗」と解釈して再認証に進まない。invalid_grant が
            // 「もう一度ログインさせろ」の合図になる。
            return self::tokenError('invalid_grant', 'リフレッシュトークンが無効です。');
        }

        return self::tokenResponse($issued['accessToken'], $issued['refreshToken'], $issued['expiresIn'], $issued['scope']);
    }

    // ------------------------------------------------------------------
    // Resource server 側
    // ------------------------------------------------------------------

    /**
     * MCP エンドポイントから呼ぶトークン検証。
     *
     * audience（resource）まで見るのは仕様の必須事項。他所のサーバー向けに
     * 発行されたトークンを受け入れてしまうと、OAuth の境界が意味を失う。
     *
     * @return null|array{clientId: string, scope: string, resource: string}
     */
    public function authenticate(?string $authorizationHeader): ?array
    {
        if (null === $authorizationHeader
            || 1 !== \preg_match('/^Bearer[ \t]+(\S+)$/i', $authorizationHeader, $matches)
        ) {
            return null;
        }

        $grant = $this->store->findAccessToken($matches[1]);
        if (null === $grant || !self::sameResource($grant['resource'], $this->metadata->resource())) {
            return null;
        }

        return $grant;
    }

    // ------------------------------------------------------------------
    // 補助
    // ------------------------------------------------------------------

    private static function verifyPkce(string $verifier, string $challenge): bool
    {
        $computed = \rtrim(\strtr(\base64_encode(\hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return \hash_equals($challenge, $computed);
    }

    /**
     * 末尾スラッシュの有無だけの違いは同一とみなす。仕様も
     * 「スラッシュ無しに揃えるのが望ましい」と言うに留めていて、
     * クライアント実装によって揺れる。
     */
    private static function sameResource(string $a, string $b): bool
    {
        return \hash_equals(\rtrim($b, '/'), \rtrim($a, '/'));
    }

    /**
     * 登録できる redirect_uri か。https か loopback のみ。
     */
    private static function redirectUriAcceptable(string $uri): bool
    {
        $parts = \parse_url($uri);
        if (!\is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if ('https' === $parts['scheme']) {
            return true;
        }

        return 'http' === $parts['scheme'] && self::isLoopback($parts['host']);
    }

    /**
     * 認可時の redirect_uri が登録値のどれかと一致するか。
     *
     * loopback だけポートを無視して比べる。ネイティブアプリ（Claude Code など）は
     * 実行のたびに空いているポートを取るので、登録時のポートと一致しない
     * ——RFC 8252 §7.3 がこの照合方法を求めている。
     *
     * @param list<string> $registered
     */
    private static function redirectUriRegistered(string $candidate, array $registered): bool
    {
        foreach ($registered as $uri) {
            if (\hash_equals($uri, $candidate)) {
                return true;
            }

            if (self::sameLoopbackUri($uri, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private static function sameLoopbackUri(string $a, string $b): bool
    {
        $left = \parse_url($a);
        $right = \parse_url($b);

        if (!\is_array($left) || !\is_array($right)) {
            return false;
        }

        if (!isset($left['host'], $right['host']) || !self::isLoopback($right['host'])) {
            return false;
        }

        return ($left['scheme'] ?? '') === ($right['scheme'] ?? '')
            && $left['host'] === $right['host']
            && ($left['path'] ?? '') === ($right['path'] ?? '');
    }

    private static function isLoopback(string $host): bool
    {
        return \in_array(\strtolower($host), ['127.0.0.1', 'localhost', '[::1]', '::1'], true);
    }

    /**
     * @param array<string, mixed> $source
     */
    private static function str(array $source, string $key): string
    {
        /** @var mixed $value */
        $value = $source[$key] ?? null;

        return \is_string($value) ? \trim($value) : '';
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private static function tokenResponse(string $access, string $refresh, int $expiresIn, string $scope): array
    {
        return [
            'status' => 200,
            'body' => [
                'access_token' => $access,
                'token_type' => 'Bearer',
                'expires_in' => $expiresIn,
                'refresh_token' => $refresh,
                'scope' => $scope,
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private static function tokenError(string $error, string $description): array
    {
        return ['status' => 400, 'body' => ['error' => $error, 'error_description' => $description]];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private static function registrationError(string $error, string $description): array
    {
        return ['status' => 400, 'body' => ['error' => $error, 'error_description' => $description]];
    }
}
