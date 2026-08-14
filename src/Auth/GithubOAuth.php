<?php

declare(strict_types=1);

namespace App\Auth;

use App\Tehilim\TehilimClient;
use DateTimeImmutable;
use Polidog\Relayer\Auth\Authenticator;
use Polidog\Relayer\Auth\Identity;
use Polidog\Relayer\Auth\SessionStorage;
use Polidog\Relayer\Http\Client\HttpClient;
use Polidog\Relayer\Http\Client\HttpClientException;

/**
 * 管理画面のログイン。GitHub の Authorization Code フローを素で実装する。
 *
 * OAuth ライブラリを足さないのは、必要なのが「authorize へ飛ばす」
 * 「code をトークンに換える」「/user を 1 回叩く」の 3 つだけで、
 * ライブラリが引き受けてくれる複雑さ（複数プロバイダ・リフレッシュ・
 * PKCE・トークン保管）をこのサイトが 1 つも使わないため。
 * CSRF 対策の state だけは自前で持つ。
 *
 * @phpstan-import-type UserRow from \App\Tehilim\Model\User
 */
final class GithubOAuth
{
    private const string SESSION_STATE_KEY = 'app.github.oauth_state';
    private const string AUTHORIZE_URL = 'https://github.com/login/oauth/authorize';
    private const string TOKEN_URL = 'https://github.com/login/oauth/access_token';
    private const string USER_URL = 'https://api.github.com/user';

    public function __construct(
        private readonly HttpClient $http,
        private readonly SessionStorage $session,
        private readonly Authenticator $authenticator,
        private readonly TehilimClient $db,
        private readonly string $clientId = '',
        private readonly string $clientSecret = '',
        private readonly string $allowedLogins = '',
    ) {}

    public function configured(): bool
    {
        return '' !== $this->clientId && '' !== $this->clientSecret;
    }

    /**
     * ログインボタンの遷移先。state をセッションに残してから返す。
     */
    public function authorizeUrl(string $redirectUri): string
    {
        $state = \bin2hex(\random_bytes(16));
        $this->session->set(self::SESSION_STATE_KEY, $state);

        return self::AUTHORIZE_URL . '?' . \http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'scope' => 'read:user',
            'state' => $state,
            'allow_signup' => 'false',
        ]);
    }

    /**
     * コールバックの処理。成功したらセッションにログイン状態を作って
     * Identity を返す。失敗理由は呼び出し側に文字列で返さず null にする
     * （ログイン画面には一律の文言だけ出す）。
     */
    public function completeLogin(string $code, string $state, string $redirectUri): ?Identity
    {
        $expected = $this->session->get(self::SESSION_STATE_KEY);
        $this->session->remove(self::SESSION_STATE_KEY);

        if (!\is_string($expected) || '' === $expected || !\hash_equals($expected, $state)) {
            return null;
        }

        $token = $this->exchangeCode($code, $redirectUri);
        if (null === $token) {
            return null;
        }

        $profile = $this->fetchUser($token);
        if (null === $profile || !$this->isAllowed($profile['login'])) {
            return null;
        }

        $user = $this->upsertUser($profile);
        $identity = new Identity(
            id: $user['id'],
            displayName: $user['name'] ?? $user['login'],
            roles: [$user['role']],
        );

        $this->authenticator->login($identity);

        return $identity;
    }

    private function exchangeCode(string $code, string $redirectUri): ?string
    {
        try {
            $response = $this->http->request(
                'POST',
                self::TOKEN_URL,
                ['Accept' => 'application/json', 'Content-Type' => 'application/x-www-form-urlencoded'],
                \http_build_query([
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'code' => $code,
                    'redirect_uri' => $redirectUri,
                ]),
            );
        } catch (HttpClientException) {
            return null;
        }

        if (!$response->ok()) {
            return null;
        }

        try {
            $payload = $response->json();
        } catch (HttpClientException) {
            return null;
        }

        $token = \is_array($payload) ? ($payload['access_token'] ?? null) : null;

        return \is_string($token) && '' !== $token ? $token : null;
    }

    /**
     * @return null|array{id: int, login: string, name: null|string, avatar_url: null|string}
     */
    private function fetchUser(string $token): ?array
    {
        try {
            $response = $this->http->get(self::USER_URL, [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/vnd.github+json',
                // GitHub API は User-Agent 必須。
                'User-Agent' => 'polidog.jp-cms',
            ]);
        } catch (HttpClientException) {
            return null;
        }

        if (!$response->ok()) {
            return null;
        }

        try {
            $payload = $response->json();
        } catch (HttpClientException) {
            return null;
        }

        if (!\is_array($payload) || !isset($payload['id'], $payload['login'])) {
            return null;
        }

        return [
            'id' => (int) $payload['id'],
            'login' => (string) $payload['login'],
            'name' => isset($payload['name']) && \is_string($payload['name']) ? $payload['name'] : null,
            'avatar_url' => isset($payload['avatar_url']) && \is_string($payload['avatar_url'])
                ? $payload['avatar_url']
                : null,
        ];
    }

    /**
     * 許可リストが空なら誰も入れない。設定漏れで管理画面が全開になるより、
     * 誰も入れないほうが安全側に倒れる。
     */
    private function isAllowed(string $login): bool
    {
        $allowed = \array_filter(\array_map('trim', \explode(',', $this->allowedLogins)));
        if ([] === $allowed) {
            return false;
        }

        foreach ($allowed as $candidate) {
            if (0 === \strcasecmp($candidate, $login)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{id: int, login: string, name: null|string, avatar_url: null|string} $profile
     *
     * @return UserRow
     */
    private function upsertUser(array $profile): array
    {
        return $this->db->user->upsert([
            'where' => ['githubId' => $profile['id']],
            'insert' => [
                'githubId' => $profile['id'],
                'login' => $profile['login'],
                'name' => $profile['name'],
                'avatarUrl' => $profile['avatar_url'],
                'role' => 'admin',
                'createdAt' => new DateTimeImmutable(),
            ],
            'update' => [
                'login' => $profile['login'],
                'name' => $profile['name'],
                'avatarUrl' => $profile['avatar_url'],
            ],
        ]);
    }
}
