<?php

declare(strict_types=1);

namespace App\Mcp;

/**
 * MCP のツール一覧と実行の受付。
 *
 * 定義と実装は同じクラス（PostTools / MediaTools）に置き、ここは
 * 順に声を掛けるだけにしてある。定義表と `match` を別々の場所に持つと、
 * 片方だけ直したときに「一覧には出るのに呼べない」ツールができる。
 */
final readonly class ToolRegistry
{
    public function __construct(
        private PostTools $posts,
        private MediaTools $media,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function definitions(): array
    {
        return [...$this->posts->definitions(), ...$this->media->definitions()];
    }

    public function has(string $name): bool
    {
        foreach ($this->definitions() as $definition) {
            if (($definition['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * ツールを実行し、MCP の `tools/call` が返す result の形にして返す。
     *
     * ツールの失敗は例外にせず `isError` で返す。JSON-RPC のエラーに
     * してしまうと、モデルは「呼び方を間違えた」と解釈して同じ呼び出しを
     * 繰り返す。isError なら失敗の理由がそのまま会話に渡り、次の手を
     * 選び直せる。
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    public function call(string $name, array $arguments): array
    {
        try {
            $result = $this->posts->call($name, $arguments) ?? $this->media->call($name, $arguments);
        } catch (McpToolException $exception) {
            return self::payload($exception->getMessage(), isError: true);
        }

        if (null === $result) {
            return self::payload(\sprintf('%s というツールはありません。', $name), isError: true);
        }

        $json = \json_encode($result, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);

        return self::payload(false === $json ? '結果を JSON にできませんでした。' : $json, isError: false === $json);
    }

    /**
     * @return array<string, mixed>
     */
    private static function payload(string $text, bool $isError): array
    {
        return [
            'content' => [['type' => 'text', 'text' => $text]],
            'isError' => $isError,
        ];
    }
}
