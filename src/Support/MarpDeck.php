<?php

declare(strict_types=1);

namespace App\Support;

/**
 * MarpRenderer の出力 1 回ぶん。
 *
 * デッキの HTML と、front matter の `style:` や本文の `<style>` から
 * 集めた CSS を分けて持つ。DB には `Post.html` 1 列しか無いので、
 * 保存するときは `storable()` で 1 本にまとめ（CSS は先頭の
 * `<style data-marp-style>` に入る）、表示側は `splitStored()` で
 * 元の 2 つに戻す。
 *
 * 分けておく理由は 2 つ。`MarkdownRenderer::excerpt()` が `strip_tags`
 * なので、CSS を混ぜたまま渡すと抜粋にセレクタが流れ込む。もう 1 つは
 * 公開ページの都合で、usePHP の Renderer は文字列の子を必ずエスケープする
 * うえ `HtmlToElement` は `<style>` を落とすため、CSS は本文ではなく
 * `SiteDocument::addHeadHtml()` から `<head>` に置くしかない。
 */
final readonly class MarpDeck
{
    private const string STYLE_OPEN = '<style data-marp-style>';
    private const string STYLE_CLOSE = '</style>';

    public function __construct(
        public string $html,
        public string $css,
        public int $pages,
        public ?string $title = null,
        public ?string $description = null,
    ) {}

    /**
     * DB に入れる形。CSS があれば先頭に `<style>` として抱き合わせる。
     */
    public function storable(): string
    {
        if ('' === \trim($this->css)) {
            return $this->html;
        }

        return self::STYLE_OPEN . self::escapeStyle($this->css) . self::STYLE_CLOSE . "\n" . $this->html;
    }

    /**
     * `storable()` の逆。CSS が無ければ `css` は空文字。
     *
     * @return array{css: string, html: string}
     */
    public static function splitStored(string $stored): array
    {
        if (!\str_starts_with($stored, self::STYLE_OPEN)) {
            return ['css' => '', 'html' => $stored];
        }

        $end = \strpos($stored, self::STYLE_CLOSE, \strlen(self::STYLE_OPEN));
        if (false === $end) {
            return ['css' => '', 'html' => $stored];
        }

        return [
            'css' => \substr($stored, \strlen(self::STYLE_OPEN), $end - \strlen(self::STYLE_OPEN)),
            'html' => \ltrim(\substr($stored, $end + \strlen(self::STYLE_CLOSE))),
        ];
    }

    /**
     * 保存済み HTML からスライドの枚数を数える。列を増やさずに済ませる
     * ための小技で、`data-marp-page` は 1 枚に 1 つしか出ない。
     */
    public static function countPages(string $stored): int
    {
        return \substr_count($stored, 'data-marp-page="');
    }

    /**
     * `<style>` の中に `</style>` が現れると要素が閉じてしまうので潰す。
     * 書いているのは自分だけだが、CSS の `content: "</style>"` のような
     * 正当な値でも起きるので、安全側に倒しておく。
     */
    public static function escapeStyle(string $css): string
    {
        return (string) \preg_replace('#</(style)#i', '<\\/$1', $css);
    }
}
