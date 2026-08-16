<?php

declare(strict_types=1);

namespace App\Mcp;

/**
 * `tools/call` の arguments を型付きで取り出す。
 *
 * JSON Schema をツール定義に書いてもクライアントが必ず守る保証は無い
 * （モデルが生成した値がそのまま届く）ので、受け側でもう一度見る。
 *
 * `has()` を持っているのは部分更新のため。update_post は「渡されなかった
 * フィールドは既存値のまま」にしたいので、「空文字が来た」と「キーが無い」を
 * 区別できないと、指定しなかった項目まで消えてしまう。
 */
final readonly class Arguments
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(private array $raw) {}

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->raw) && null !== $this->raw[$key];
    }

    public function requiredString(string $key): string
    {
        $value = $this->string($key);

        if ('' === $value) {
            throw new McpToolException(\sprintf('%s は必須です。', $key));
        }

        return $value;
    }

    public function string(string $key, string $default = ''): string
    {
        /** @var mixed $value */
        $value = $this->raw[$key] ?? null;

        if (\is_string($value)) {
            return \trim($value);
        }

        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }

        return $default;
    }

    public function requiredInt(string $key): int
    {
        $value = $this->int($key, \PHP_INT_MIN);

        if (\PHP_INT_MIN === $value) {
            throw new McpToolException(\sprintf('%s は必須です（整数）。', $key));
        }

        return $value;
    }

    public function int(string $key, int $default): int
    {
        /** @var mixed $value */
        $value = $this->raw[$key] ?? null;

        if (\is_int($value)) {
            return $value;
        }

        // モデルは数値を文字列で渡してくることがある。
        if (\is_string($value) && 1 === \preg_match('/^-?\d+$/', \trim($value))) {
            return (int) \trim($value);
        }

        return $default;
    }

    /**
     * 文字列のリスト。タグやカテゴリのように「未指定」と「空にする」を
     * 区別したいので、キーが無ければ null を返す。
     *
     * @return null|list<string>
     */
    public function stringList(string $key): ?array
    {
        if (!$this->has($key)) {
            return null;
        }

        /** @var mixed $value */
        $value = $this->raw[$key];

        // 配列で渡すのが正しいが、カンマ区切りの 1 本の文字列も受ける。
        if (\is_string($value)) {
            $value = \explode(',', $value);
        }

        if (!\is_array($value)) {
            throw new McpToolException(\sprintf('%s は文字列の配列で渡してください。', $key));
        }

        $items = [];
        foreach ($value as $item) {
            if (!\is_string($item)) {
                continue;
            }
            $item = \trim($item);
            if ('' !== $item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * 決められた値のどれかであることを確かめる。
     *
     * @param list<string> $allowed
     */
    public function enum(string $key, array $allowed, string $default): string
    {
        $value = $this->string($key, $default);

        if ('' === $value) {
            return $default;
        }

        if (!\in_array($value, $allowed, true)) {
            throw new McpToolException(\sprintf(
                '%s は %s のいずれかにしてください（%s が渡されました）。',
                $key,
                \implode(' / ', $allowed),
                $value,
            ));
        }

        return $value;
    }
}
