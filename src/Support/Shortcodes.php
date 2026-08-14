<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Hugo の shortcode を素の HTML に展開する。
 *
 * 記事本文は Markdown のまま DB に持つので、この展開は「移行時に一度」
 * ではなく「レンダリングのたび」に走る（= 過去記事を編集しても
 * shortcode が壊れない）。とはいえレンダリング自体が保存時にしか
 * 走らないので、表示コストには乗らない。
 *
 * 実際に使われているのは 49 ファイル・5 種類だけ:
 *   tweet(44) / figure(15) / youtube(4) / gist(3) / speakerdeck(2)
 * 未知の shortcode は展開せず原文のまま残す（黙って消すと本文が
 * 欠落したことに気づけないため）。
 */
final class Shortcodes
{
    public static function expand(string $markdown): string
    {
        return (string) \preg_replace_callback(
            '/\{\{<\s*(?<name>[a-zA-Z][a-zA-Z0-9_-]*)\s*(?<args>.*?)\s*>\}\}/s',
            static fn (array $m): string => self::render($m['name'], $m['args'], $m[0]),
            $markdown,
        );
    }

    /**
     * shortcode 本体は HTML なので、Markdown パーサに「段落に包まない
     * ブロック」と認識させる必要がある。CommonMark は行頭から始まる
     * ブロックレベルタグを HTML ブロックとして扱うので、前後を空行で
     * 挟んで返す。
     */
    private static function render(string $name, string $args, string $original): string
    {
        $params = self::parseArgs($args);

        $html = match ($name) {
            'tweet' => self::tweet($params),
            'figure' => self::figure($params),
            'youtube' => self::youtube($params),
            'gist' => self::gist($params),
            'speakerdeck' => self::speakerdeck($params),
            default => null,
        };

        return null === $html ? $original : "\n\n" . $html . "\n\n";
    }

    /**
     * `user="polidog" id="123"` 形式の名前付き引数と、`{{< youtube ID >}}`
     * のような位置引数の両方を受ける。
     *
     * @return array{named: array<string, string>, positional: list<string>}
     */
    private static function parseArgs(string $args): array
    {
        $named = [];
        $positional = [];

        \preg_match_all(
            '/(?:(?<key>[a-zA-Z_][a-zA-Z0-9_-]*)=)?(?:"(?<quoted>[^"]*)"|(?<bare>\S+))/',
            $args,
            $matches,
            \PREG_SET_ORDER,
        );

        foreach ($matches as $match) {
            $value = '' !== ($match['quoted'] ?? '') ? $match['quoted'] : ($match['bare'] ?? '');
            if ('' === $value && !isset($match['bare'])) {
                continue;
            }
            if ('' !== ($match['key'] ?? '')) {
                $named[$match['key']] = $value;

                continue;
            }
            $positional[] = $value;
        }

        return ['named' => $named, 'positional' => $positional];
    }

    /**
     * @param array{named: array<string, string>, positional: list<string>} $p
     */
    private static function tweet(array $p): ?string
    {
        $id = $p['named']['id'] ?? ($p['positional'][0] ?? '');
        $user = $p['named']['user'] ?? '';
        if ('' === $id) {
            return null;
        }

        $url = 'https://twitter.com/' . \rawurlencode('' !== $user ? $user : 'i') . '/status/' . \rawurlencode($id);

        // widgets.js は読み込まない（外部 JS を増やさない）。blockquote は
        // X 側のスクリプトが無くても引用として読める形で残る。
        return \sprintf(
            '<blockquote class="twitter-tweet"><a href="%s" rel="noopener noreferrer" target="_blank">%s</a></blockquote>',
            self::esc($url),
            self::esc($url),
        );
    }

    /**
     * @param array{named: array<string, string>, positional: list<string>} $p
     */
    private static function figure(array $p): ?string
    {
        $src = $p['named']['src'] ?? '';
        if ('' === $src) {
            return null;
        }

        $attributes = '';
        if ('' !== ($p['named']['width'] ?? '')) {
            $attributes .= \sprintf(' style="width:%s"', self::esc($p['named']['width']));
        }
        if ('' !== ($p['named']['class'] ?? '')) {
            $attributes .= \sprintf(' class="%s"', self::esc($p['named']['class']));
        }

        $caption = $p['named']['title'] ?? ($p['named']['caption'] ?? '');
        $img = \sprintf(
            '<img src="%s" alt="%s" loading="lazy"%s>',
            self::esc($src),
            self::esc($p['named']['alt'] ?? $caption),
            $attributes,
        );

        return '' !== $caption
            ? \sprintf('<figure>%s<figcaption>%s</figcaption></figure>', $img, self::esc($caption))
            : \sprintf('<figure>%s</figure>', $img);
    }

    /**
     * @param array{named: array<string, string>, positional: list<string>} $p
     */
    private static function youtube(array $p): ?string
    {
        $id = $p['named']['id'] ?? ($p['positional'][0] ?? '');
        if ('' === $id) {
            return null;
        }

        return \sprintf(
            '<div class="embed embed-youtube"><iframe src="https://www.youtube.com/embed/%s" title="YouTube" loading="lazy" allowfullscreen></iframe></div>',
            self::esc($id),
        );
    }

    /**
     * @param array{named: array<string, string>, positional: list<string>} $p
     */
    private static function gist(array $p): ?string
    {
        $user = $p['named']['user'] ?? ($p['positional'][0] ?? '');
        $id = $p['named']['id'] ?? ($p['positional'][1] ?? '');
        if ('' === $user || '' === $id) {
            return null;
        }

        $url = \sprintf('https://gist.github.com/%s/%s', \rawurlencode($user), \rawurlencode($id));

        return \sprintf(
            '<p><a href="%s" rel="noopener noreferrer" target="_blank">%s</a></p>',
            self::esc($url),
            self::esc($url),
        );
    }

    /**
     * @param array{named: array<string, string>, positional: list<string>} $p
     */
    private static function speakerdeck(array $p): ?string
    {
        $id = $p['named']['id'] ?? ($p['positional'][0] ?? '');
        if ('' === $id) {
            return null;
        }

        return \sprintf(
            '<div class="embed embed-speakerdeck"><iframe src="https://speakerdeck.com/player/%s" title="%s" loading="lazy" allowfullscreen></iframe></div>',
            self::esc($id),
            self::esc($p['named']['title'] ?? 'Speaker Deck Presentation'),
        );
    }

    private static function esc(string $value): string
    {
        return \htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }
}
