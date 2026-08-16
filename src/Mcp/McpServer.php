<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Auth\Oauth\OauthMetadata;
use App\Support\SiteConfig;
use Polidog\Relayer\Http\Response;
use stdClass;

/**
 * MCP の JSON-RPC を捌く。
 *
 * トランスポートは Streamable HTTP の**ステートレス構成**。仕様は POST への
 * 応答を SSE ではなく単一の JSON で返すことを認めていて、セッション ID も
 * 任意なので、この形なら Relayer の Response（本文は文字列 1 つ）にそのまま
 * 収まる。サーバーから勝手に喋る必要も無い —— ここが持っているのは
 * ツールだけで、進捗通知も購読も無い。
 */
final readonly class McpServer
{
    /** initialize でクライアントの版が分からないときに名乗る版。 */
    public const string PROTOCOL_VERSION = '2025-06-18';

    /** @var list<string> */
    private const array SUPPORTED = ['2025-11-25', '2025-06-18', '2025-03-26'];

    private const int PARSE_ERROR = -32700;

    private const int INVALID_REQUEST = -32600;

    private const int METHOD_NOT_FOUND = -32601;

    private const int INVALID_PARAMS = -32602;

    public function __construct(
        private ToolRegistry $tools,
        private OauthMetadata $metadata,
        private SiteConfig $site,
    ) {}

    /**
     * @param null|array<string, mixed> $message JSON デコード済みのボディ
     */
    public function handle(?array $message): Response
    {
        if (null === $message) {
            return self::error(null, self::PARSE_ERROR, 'JSON として読めませんでした。');
        }

        if ('2.0' !== ($message['jsonrpc'] ?? null)) {
            return self::error(null, self::INVALID_REQUEST, 'jsonrpc は "2.0" である必要があります。');
        }

        /** @var mixed $method */
        $method = $message['method'] ?? null;
        if (!\is_string($method)) {
            return self::error(null, self::INVALID_REQUEST, 'method がありません。');
        }

        /** @var mixed $id */
        $id = $message['id'] ?? null;

        // id が無いものは通知。応答を返してはいけないので、受け取ったことだけ伝える。
        if (null === $id) {
            return Response::make(null, 202, ['Cache-Control' => 'no-store']);
        }

        /** @var array<string, mixed> $params */
        $params = \is_array($message['params'] ?? null) ? $message['params'] : [];

        return match ($method) {
            'initialize' => self::result($id, $this->initialize($params)),
            'tools/list' => self::result($id, ['tools' => $this->tools->definitions()]),
            'tools/call' => $this->callTool($id, $params),
            'ping' => self::result($id, new stdClass()),
            default => self::error($id, self::METHOD_NOT_FOUND, \sprintf('%s は実装されていません。', $method)),
        };
    }

    /**
     * 認証が無い（または無効な）ときの応答。
     *
     * **200 に isError を載せてはいけない。** クライアントが OAuth を
     * 始めるのは 401 を受けたときだけで、200 で「ログインしてください」と
     * 書いて返すと、その文言がそのまま会話に流れて終わる。
     */
    public function unauthorized(): Response
    {
        return Response::json(
            ['error' => 'invalid_token', 'error_description' => 'この MCP サーバーには認証が必要です。'],
            401,
            [
                'WWW-Authenticate' => $this->metadata->challenge(),
                'Cache-Control' => 'no-store',
            ],
        );
    }

    /**
     * ブラウザから直接叩かれていないことを確かめる。
     *
     * MCP クライアントはサーバー間で繋ぐので Origin を送ってこない。
     * 送ってくるのはブラウザ経由の場合で、その中で通してよいのは
     * 自サイトと開発中の loopback だけ —— DNS リバインディングで
     * 手元のサーバーを外部サイトから操作されるのを防ぐため、
     * 仕様が検証を必須にしている。
     */
    public function originAllowed(?string $origin): bool
    {
        if (null === $origin || '' === $origin) {
            return true;
        }

        $parts = \parse_url($origin);
        if (!\is_array($parts) || !isset($parts['host'])) {
            return false;
        }

        if (\in_array(\strtolower($parts['host']), ['127.0.0.1', 'localhost', '[::1]'], true)) {
            return true;
        }

        $siteHost = \parse_url($this->site->siteUrl, \PHP_URL_HOST);

        return \is_string($siteHost) && 0 === \strcasecmp($siteHost, $parts['host']);
    }

    /**
     * `MCP-Protocol-Version` ヘッダの検査。
     *
     * ヘッダが無い場合は 2025-03-26 とみなす（仕様が定めた後方互換）。
     * 知らない版が来たら 400 —— 黙って最新版として扱うと、挙動の違いが
     * 分かりにくい形で表に出る。
     */
    public function protocolVersionAllowed(?string $header): bool
    {
        return null === $header || '' === $header || \in_array($header, self::SUPPORTED, true);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function initialize(array $params): array
    {
        /** @var mixed $requested */
        $requested = $params['protocolVersion'] ?? null;

        // クライアントの版に合わせられるなら合わせる。合わせられなければ
        // こちらの版を名乗り、受けるかどうかは相手に決めてもらう。
        $version = \is_string($requested) && \in_array($requested, self::SUPPORTED, true)
            ? $requested
            : self::PROTOCOL_VERSION;

        return [
            'protocolVersion' => $version,
            'capabilities' => ['tools' => ['listChanged' => false]],
            'serverInfo' => [
                'name' => 'polidog.jp',
                'title' => 'polidog.jp CMS',
                'version' => '1.0.0',
            ],
            'instructions' => <<<'TEXT'
                polidog.jp（個人ブログ）の記事を読み書きするためのサーバーです。

                - 記事の URL は path が持ちます。ブログ記事は /YYYY/MM/DD/スラッグ/ の形が
                  慣例ですが、20 年ぶんの URL がそのまま生きているので、既存記事の path は
                  頼まれない限り変えないでください。
                - 更新・削除の前には get_post で対象を確かめてください。update_post は
                  渡したフィールドだけを変更します。
                - delete_post は元に戻せません。サイトから隠すだけなら unpublish_post を
                  使ってください。
                - 記事に画像を使うときは upload_media_from_url で取り込み、返ってきた
                  /images/... の URL を本文に書いてください。
                TEXT,
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function callTool(mixed $id, array $params): Response
    {
        /** @var mixed $name */
        $name = $params['name'] ?? null;

        if (!\is_string($name) || '' === $name) {
            return self::error($id, self::INVALID_PARAMS, 'params.name がありません。');
        }

        // 存在しないツール名は「呼び方の誤り」なので、ツールの失敗ではなく
        // プロトコルのエラーとして返す。
        if (!$this->tools->has($name)) {
            return self::error($id, self::INVALID_PARAMS, \sprintf('%s というツールはありません。', $name));
        }

        /** @var array<string, mixed> $arguments */
        $arguments = \is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        return self::result($id, $this->tools->call($name, $arguments));
    }

    /**
     * @param array<string, mixed>|stdClass $result
     */
    private static function result(mixed $id, array|stdClass $result): Response
    {
        return Response::json(
            ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result],
            200,
            ['Cache-Control' => 'no-store'],
        );
    }

    private static function error(mixed $id, int $code, string $message): Response
    {
        return Response::json(
            ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]],
            200,
            ['Cache-Control' => 'no-store'],
        );
    }
}
