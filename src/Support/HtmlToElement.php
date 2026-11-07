<?php

declare(strict_types=1);

namespace App\Support;

use Dom\Element as DomElement;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\Text;
use Polidog\Rosanna\Runtime\Element;

/**
 * レンダリング済みの記事 HTML を Rosanna の Element ツリーに変換する。
 *
 * **なぜ必要か**: Rosanna の Renderer は children に来た文字列を必ず
 * `htmlspecialchars` する（Runtime/Renderer.php の renderChildren）。
 * これは設計であって設定ではなく、生 HTML を差し込む口が無い。
 * CMS は「Markdown から起こした HTML」を本文として出すので、
 * そのままでは `&lt;p&gt;` が画面に見えてしまう。
 *
 * そこで HTML を一度パースして Element に組み直す。テキストは
 * テキストノードとして渡るので Renderer が改めてエスケープし、
 * 二重エスケープにも生 HTML 混入にもならない。PHP 8.4 以降の
 * `Dom\HTMLDocument` は本物の HTML5 パーサなので、`<figure>` や
 * 閉じ忘れタグを含む 20 年ぶんの記事も仕様どおりに読める。
 *
 * `<script>` と `<style>` は落とす。中身は Renderer にとって
 * ただのテキストなのでエスケープされて壊れるし、記事本文から任意の
 * JS を走らせたい理由がこのサイトには無い（古い記事に残っている
 * Instagram / Twitter の埋め込みスクリプトは、隣の blockquote が
 * リンクとして読める形で残る）。
 */
final class HtmlToElement
{
    private const array DROPPED_TAGS = ['script', 'style'];

    public static function convert(string $html): Element
    {
        if ('' === \trim($html)) {
            return new Element('Fragment', [], []);
        }

        $document = HTMLDocument::createFromString(
            '<!DOCTYPE html><html><body>' . $html . '</body></html>',
            \LIBXML_NOERROR | \LIBXML_COMPACT,
        );

        $body = $document->body;
        if (null === $body) {
            return new Element('Fragment', [], []);
        }

        return new Element('Fragment', [], self::children($body));
    }

    /**
     * @return list<Element|string>
     */
    private static function children(Node $node): array
    {
        $children = [];

        foreach ($node->childNodes as $child) {
            $converted = self::node($child);
            if (null !== $converted) {
                $children[] = $converted;
            }
        }

        return $children;
    }

    private static function node(Node $node): Element|string|null
    {
        if ($node instanceof Text) {
            $text = $node->data;

            return '' === $text ? null : $text;
        }

        if (!$node instanceof DomElement) {
            // コメント・処理命令・DOCTYPE は出力しない。
            return null;
        }

        $tag = \strtolower($node->tagName);
        if (\in_array($tag, self::DROPPED_TAGS, true)) {
            return null;
        }

        $props = [];
        foreach ($node->attributes as $attribute) {
            if (!self::renderableAttribute($attribute->name)) {
                continue;
            }
            // 値のない属性（allowfullscreen など）は DOM 上では空文字。
            // Renderer は空文字でも `attr=""` を出すので、HTML としては同義。
            $props[$attribute->name] = $attribute->value;
        }

        return new Element($tag, $props, self::children($node));
    }

    /**
     * Rosanna の Renderer が受け付ける属性名かどうか。
     *
     * Renderer は `on*`（インラインのイベントハンドラ）と、HTML の属性名と
     * して不正な名前を見ると**例外を投げる**（rosanna の
     * `assertValidAttributeName()`）。記事本文 1 本のせいでページごと 500 に
     * なるので、ここで黙って落とす。`/2008/12/29/firefox/` の `onclick` が
     * これに当たった。`<script>` を落とすのと同じく、記事から JS を
     * 走らせる理由はこのサイトに無い。
     */
    private static function renderableAttribute(string $name): bool
    {
        return 1 === \preg_match('/^[A-Za-z_:][A-Za-z0-9:_.\-]*$/D', $name)
            && 1 !== \preg_match('/^on[a-z]/i', $name);
    }
}
