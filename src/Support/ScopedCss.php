<?php

declare(strict_types=1);

namespace App\Support;

/**
 * CSS の各ルールのセレクタに接頭辞を付けて、効く範囲をデッキの中に閉じ込める。
 *
 * Marp のテーマや `style:` ディレクティブは `section h1 { … }` のように
 * デッキの中だけを想定して書かれる。それをこのサイトのページにそのまま
 * 置くと、レイアウトの `h1` や本文の `section` まで染まるので、
 * Marpit が postcss でやっているのと同じ「全セレクタに容器を前置する」を
 * ここで行う（`h1` → `.marp h1`、`section.lead` → `.marp section.lead`）。
 *
 * 本物の CSS パーサではない。波括弧・引用符・丸括弧の対応だけを見て
 * ルールを切り出す簡易実装で、対象は自分が書くスライドの CSS だけ。
 * `@media` などの条件付きブロックは中のルールに再帰し、`@keyframes` や
 * `@font-face` のようにセレクタを持たないものは触らない。
 */
final class ScopedCss
{
    /** 中のルールにも接頭辞を付ける at-rule。 */
    private const array NESTING_AT_RULES = ['media', 'supports', 'container', 'layer', 'document', 'scope'];

    public static function scope(string $css, string $prefix): string
    {
        $css = (string) \preg_replace('#/\*.*?\*/#s', '', $css);

        return \trim(self::block($css, $prefix));
    }

    private static function block(string $css, string $prefix): string
    {
        $out = '';
        $length = \strlen($css);
        $i = 0;

        while ($i < $length) {
            $j = self::find($css, $i, ['{', ';']);
            if (null === $j) {
                break;
            }

            $head = \trim(\substr($css, $i, $j - $i));

            if (';' === $css[$j]) {
                // `@import` / `@charset` のような文。そのまま通す。
                if ('' !== $head) {
                    $out .= $head . ";\n";
                }
                $i = $j + 1;

                continue;
            }

            $k = self::matchBrace($css, $j);
            $body = \substr($css, $j + 1, $k - $j - 1);
            $i = $k + 1;

            if ('' === $head) {
                continue;
            }

            if ('@' === $head[0]) {
                $name = \strtolower((string) \preg_replace('/^@([a-z-]+).*/is', '$1', $head));
                $out .= \in_array($name, self::NESTING_AT_RULES, true)
                    ? $head . " {\n" . self::block($body, $prefix) . "}\n"
                    : $head . ' {' . $body . "}\n";

                continue;
            }

            $selectors = \array_map(
                static fn (string $selector): string => self::prefix(\trim($selector), $prefix),
                self::splitSelectors($head),
            );

            $out .= \implode(', ', $selectors) . ' {' . $body . "}\n";
        }

        return $out;
    }

    private static function prefix(string $selector, string $prefix): string
    {
        if ('' === $selector) {
            return $prefix;
        }

        // Marpit は `:root` をスライド（section）の意味で扱う。
        if (\str_starts_with($selector, ':root')) {
            $selector = 'section' . \substr($selector, 5);
        }

        return $prefix . ' ' . $selector;
    }

    /**
     * 引用符と丸括弧の外にあるカンマでだけ切る（`:is(a, b)` を壊さない）。
     *
     * @return list<string>
     */
    private static function splitSelectors(string $head): array
    {
        $parts = [];
        $depth = 0;
        $quote = null;
        $current = '';
        $length = \strlen($head);

        for ($i = 0; $i < $length; ++$i) {
            $char = $head[$i];

            if (null !== $quote) {
                $current .= $char;
                if ('\\' === $char && $i + 1 < $length) {
                    $current .= $head[++$i];
                } elseif ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ('"' === $char || "'" === $char) {
                $quote = $char;
            } elseif ('(' === $char || '[' === $char) {
                ++$depth;
            } elseif (')' === $char || ']' === $char) {
                $depth = \max(0, $depth - 1);
            } elseif (',' === $char && 0 === $depth) {
                $parts[] = $current;
                $current = '';

                continue;
            }

            $current .= $char;
        }

        $parts[] = $current;

        return $parts;
    }

    /**
     * 引用符と丸括弧の外で最初に現れる指定文字の位置。
     *
     * @param list<string> $needles
     */
    private static function find(string $css, int $from, array $needles): ?int
    {
        $depth = 0;
        $quote = null;
        $length = \strlen($css);

        for ($i = $from; $i < $length; ++$i) {
            $char = $css[$i];

            if (null !== $quote) {
                if ('\\' === $char) {
                    ++$i;
                } elseif ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ('"' === $char || "'" === $char) {
                $quote = $char;
            } elseif ('(' === $char) {
                ++$depth;
            } elseif (')' === $char) {
                $depth = \max(0, $depth - 1);
            } elseif (0 === $depth && \in_array($char, $needles, true)) {
                return $i;
            }
        }

        return null;
    }

    /**
     * `$open` の位置にある `{` に対応する `}` の位置。閉じていなければ末尾。
     */
    private static function matchBrace(string $css, int $open): int
    {
        $depth = 0;
        $quote = null;
        $length = \strlen($css);

        for ($i = $open; $i < $length; ++$i) {
            $char = $css[$i];

            if (null !== $quote) {
                if ('\\' === $char) {
                    ++$i;
                } elseif ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ('"' === $char || "'" === $char) {
                $quote = $char;
            } elseif ('{' === $char) {
                ++$depth;
            } elseif ('}' === $char) {
                --$depth;
                if (0 === $depth) {
                    return $i;
                }
            }
        }

        return $length;
    }
}
