<?php

declare(strict_types=1);

namespace App\View;

use App\Support\HugoSlug;
use App\Support\SiteConfig;
use Polidog\UsePhp\Html\H;
use Polidog\UsePhp\Runtime\Element;

/**
 * 一覧・記事詳細で共用する小さな部品。
 *
 * `.psx` ではなく素の PHP で `H::` を直接呼んでいるのは、`usephp compile`
 * が見るのが `src/Pages/` だけで、その外に置いた `.psx` はオートロードに
 * 乗らないため。部品はこの数個しかないので、JSX が使えない不便より
 * 「置き場所が素直」を取る。
 *
 * ## 見た目の約束ごと
 *
 * - **罫線を引かない。** 区切りは余白と文字の濃さで付ける。`border-*` を
 *   足したくなったら、まず余白で足りないか確かめること。
 * - **等幅（`font-mono`）は時間と数にだけ使う。** 日付・年号・件数・
 *   ページ番号がそれ。タグ名や見出しには使わない（サイト名だけは例外で、
 *   レイアウト側がロゴとして等幅にしている）。
 * - 色は CSS 変数（`assets/tailwind.css`）で切り替わるので `dark:` は書かない。
 *
 * ## 一覧の構造
 *
 * 記事一覧は各行に年を書かず、**年が変わる位置にだけ年号を置く**
 * （`yearMarker()`）。20 年ぶんの記事が 1 本の URL 体系で繋がっている
 * ことがこのサイトの中身そのものなので、それを装飾ではなく組版で示す。
 * 行の日付が「月.日」だけで済むのはこの構造のおかげで、等幅と合わせると
 * 桁が縦に揃う。
 *
 * 受け取る配列は PostRepository が返す行そのもの。`.psx` は PHPStan の
 * 検査対象に入らない（独自構文で PHP パーサが読めない）ので、ページから
 * 渡された値が実際に検査されるのはこの境界。だから引数は
 * `array<string, mixed>` ではなく行の shape で受ける。
 *
 * @phpstan-import-type ArchiveRow from \App\Service\PostRepository
 * @phpstan-import-type NeighbourRow from \App\Service\PostRepository
 * @phpstan-import-type PostListRow from \App\Service\PostRepository
 * @phpstan-import-type TermCountRow from \App\Service\PostRepository
 * @phpstan-import-type TermRow from \App\Service\PostRepository
 */
final class Components
{
    /**
     * ページ見出し。lead は「全 959 件」のような数なので等幅で組む。
     */
    public static function heading(string $text, string $lead = ''): Element
    {
        $children = [
            H::h1(
                className: 'text-[1.75rem] font-semibold leading-tight tracking-tight text-ink sm:text-[2rem]',
                children: $text,
            ),
        ];

        if ('' !== $lead) {
            $children[] = H::p(
                className: 'mt-3 font-mono text-xs tracking-wide text-muted',
                children: $lead,
            );
        }

        return H::header(className: 'mb-14', children: $children);
    }

    /**
     * 記事一覧。年の変わり目に年号を差し込む。
     *
     * @param list<PostListRow> $posts 新しい順に並んだ記事
     */
    public static function postList(array $posts): Element
    {
        if ([] === $posts) {
            return self::empty();
        }

        $children = [];
        $year = '';
        foreach ($posts as $post) {
            $publishedAt = $post['publishedAt'];
            $postYear = null === $publishedAt ? '' : \substr($publishedAt, 0, 4);

            if ('' !== $postYear && $postYear !== $year) {
                $children[] = self::yearMarker($postYear);
                $year = $postYear;
            }

            $children[] = self::postCard($post);
        }

        return H::div(className: 'flex flex-col gap-10', children: $children);
    }

    /**
     * 一覧の 1 件。左に月日、右にタイトルと抜粋。
     *
     * リンクはタイトルにだけ張り、疑似要素（`after:inset-0`）で行全体を
     * 当たり判定にしている。行ごと `<a>` で包むと読み上げのリンク名に
     * 日付と抜粋 120 字が全部入ってしまうため。的の広さと名前の短さは
     * 両立できる。
     *
     * @param PostListRow $post
     */
    public static function postCard(array $post): Element
    {
        $publishedAt = $post['publishedAt'];
        $excerpt = $post['excerpt'] ?? '';

        $body = [
            H::h2(
                className: 'text-[1.0625rem] font-semibold leading-snug tracking-tight text-ink transition-colors group-hover:text-accent',
                children: H::a(
                    href: $post['path'] . '/',
                    className: "after:absolute after:inset-0 after:content-['']",
                    children: $post['title'],
                ),
            ),
        ];

        if ('' !== $excerpt) {
            $body[] = H::p(
                className: 'mt-2 line-clamp-2 text-[0.8125rem] leading-relaxed text-muted',
                children: $excerpt,
            );
        }

        return H::article(
            className: 'group relative flex flex-col gap-1 sm:flex-row sm:gap-6',
            children: [
                H::time(
                    datetime: null === $publishedAt ? null : \str_replace(' ', 'T', $publishedAt),
                    // タイトル（17px / leading-snug ≒ 23px）と 1 行目の高さを
                    // 揃えるための leading-6。日付を上に置くモバイルでは効かない。
                    className: 'shrink-0 font-mono text-xs leading-6 text-muted sm:w-12',
                    children: null === $publishedAt ? '' : \substr($publishedAt, 5, 2) . '.' . \substr($publishedAt, 8, 2),
                ),
                H::div(className: 'min-w-0', children: $body),
            ],
        );
    }

    /**
     * 月別アーカイブの本体。
     *
     * 渡された 1 ページぶん（30 件）を月の見出しで束ねるだけで、
     * 月をまたいで集計はしない — 同じ月がページをまたいで 2 回出るのは
     * Hugo と同じ挙動なので、そこは合わせる。
     *
     * 見出しが月を持つので、行は日だけを出す。959 件を見渡すのが
     * このページの仕事なので、一覧より詰めて組む。
     *
     * @param list<ArchiveRow> $posts 新しい順に並んだ記事
     */
    public static function archiveList(array $posts): Element
    {
        if ([] === $posts) {
            return self::empty();
        }

        /** @var array<string, list<ArchiveRow>> $byMonth */
        $byMonth = [];
        foreach ($posts as $post) {
            // publishedAt は 'Y-m-d H:i:s' の TEXT なので先頭 7 文字が年月。
            $byMonth[\substr($post['publishedAt'], 0, 7)][] = $post;
        }

        return H::div(
            className: 'flex flex-col gap-14',
            children: \array_map(
                static function (string $ym) use ($byMonth): Element {
                    return H::section(
                        children: [
                            H::h2(
                                className: 'font-mono text-[1.75rem] font-light leading-none tracking-tight text-faint',
                                children: \str_replace('-', '.', $ym),
                            ),
                            H::div(
                                className: 'mt-6 flex flex-col gap-3',
                                children: \array_map(self::archiveItem(...), $byMonth[$ym]),
                            ),
                        ],
                    );
                },
                \array_keys($byMonth),
            ),
        );
    }

    /**
     * アーカイブの 1 行。左に日、右にタイトル。
     *
     * @param ArchiveRow $post
     */
    public static function archiveItem(array $post): Element
    {
        return H::a(
            href: $post['path'] . '/',
            className: 'group flex gap-6',
            children: [
                H::time(
                    datetime: \str_replace(' ', 'T', $post['publishedAt']),
                    className: 'shrink-0 font-mono text-xs leading-6 text-muted',
                    children: \substr($post['publishedAt'], 8, 2),
                ),
                H::span(
                    className: 'text-[0.9375rem] leading-6 text-ink transition-colors group-hover:text-accent',
                    children: $post['title'],
                ),
            ],
        );
    }

    /**
     * 日付。記事詳細で使う。等幅で `2026.08.10`。
     */
    public static function date(string $value): Element
    {
        $timestamp = \strtotime($value);
        if (false === $timestamp) {
            return H::time(className: 'font-mono text-xs tracking-wide text-muted', children: $value);
        }

        return H::time(
            datetime: \date('c', $timestamp),
            className: 'font-mono text-xs tracking-wide text-muted',
            children: \date('Y.m.d', $timestamp),
        );
    }

    /**
     * 記事に付いたタグ・カテゴリ。`#` を付けて語であることを示す。
     *
     * @param list<TermRow> $terms
     */
    public static function termBadges(array $terms, string $basePath): Element
    {
        return H::div(
            className: 'flex flex-wrap gap-x-4 gap-y-1',
            children: \array_map(
                static fn (array $term): Element => H::a(
                    href: HugoSlug::toPath($basePath, $term['slug']),
                    className: 'text-xs text-muted transition-colors hover:text-accent',
                    children: '#' . $term['name'],
                ),
                $terms,
            ),
        );
    }

    /**
     * タグ・カテゴリの一覧（`/tags/`・`/categories/`・`/tech-tags/`）。
     * 記事数の多い順で、件数は等幅で添える。
     *
     * @param list<TermCountRow> $terms
     */
    public static function termCloud(array $terms, string $basePath): Element
    {
        return H::div(
            className: 'flex flex-wrap gap-x-6 gap-y-3',
            children: \array_map(
                static fn (array $term): Element => H::a(
                    href: HugoSlug::toPath($basePath, $term['slug']),
                    className: 'group inline-flex items-baseline gap-1.5',
                    children: [
                        H::span(
                            className: 'text-[0.9375rem] text-ink transition-colors group-hover:text-accent',
                            children: $term['name'],
                        ),
                        H::span(
                            className: 'font-mono text-[0.6875rem] text-muted',
                            children: (string) $term['count'],
                        ),
                    ],
                ),
                $terms,
            ),
        );
    }

    /**
     * ページ送り。Hugo の paginator と同じく `/page/N/` 形式。
     * 1 ページ目だけはサイトルート（`/`）を指す — Hugo がそうだったので
     * URL を変えないため。
     */
    public static function pagination(int $page, int $pages, string $basePath = ''): Element
    {
        if ($pages <= 1) {
            return H::div();
        }

        $link = static function (int $target, string $label, bool $enabled) use ($basePath): Element {
            if (!$enabled) {
                return H::span(className: 'text-[0.8125rem] text-faint', children: $label);
            }

            $href = '' === $basePath
                ? (1 === $target ? '/' : \sprintf('/page/%d/', $target))
                : (1 === $target ? $basePath . '/' : \sprintf('%s/page/%d/', $basePath, $target));

            return H::a(
                href: $href,
                className: 'text-[0.8125rem] text-muted transition-colors hover:text-accent',
                children: $label,
            );
        };

        return H::nav(
            className: 'mt-24 flex items-baseline justify-between',
            children: [
                $link($page - 1, '← 新しい記事', $page > 1),
                H::span(
                    className: 'font-mono text-xs tracking-wide text-muted',
                    children: \sprintf('%d / %d', $page, $pages),
                ),
                $link($page + 1, '古い記事 →', $page < $pages),
            ],
        );
    }

    /**
     * 記事詳細の「前の記事 / 次の記事」。
     *
     * @param array{previous: null|NeighbourRow, next: null|NeighbourRow} $neighbours
     */
    public static function neighbourNav(array $neighbours): Element
    {
        /** @param null|NeighbourRow $post */
        $card = static function (?array $post, string $label, bool $alignRight): Element {
            if (null === $post) {
                return H::div();
            }

            return H::a(
                href: $post['path'] . '/',
                className: 'group flex flex-col gap-1' . ($alignRight ? ' sm:text-right' : ''),
                children: [
                    H::span(
                        className: 'text-xs text-muted',
                        children: $label,
                    ),
                    H::span(
                        className: 'text-[0.9375rem] leading-snug text-ink transition-colors group-hover:text-accent',
                        children: $post['title'],
                    ),
                ],
            );
        };

        return H::nav(
            className: 'mt-20 grid grid-cols-1 gap-8 sm:grid-cols-2',
            children: [
                $card($neighbours['previous'], '前の記事', false),
                $card($neighbours['next'], '次の記事', true),
            ],
        );
    }

    /**
     * Disqus のマウント先。
     *
     * スクリプトは埋め込まない — usePHP の Renderer は children の文字列を
     * 必ずエスケープするので、インライン JS を書いても壊れるため。
     * 設定は data 属性で渡し、/assets/site.js が読み取って Disqus を
     * 読み込む。`identifier` は Hugo が使っていた値（content からの相対
     * パスの md5）をそのまま渡す — これが変わると既存のコメントが記事から
     * 切り離される。
     */
    public static function disqus(
        SiteConfig $site,
        string $path,
        string $identifier,
        string $title,
    ): Element {
        if ('' === $site->disqusShortname) {
            return H::div();
        }

        return H::div(
            className: 'mt-24',
            children: new Element('div', [
                'id' => 'disqus_thread',
                'data-disqus-shortname' => $site->disqusShortname,
                'data-disqus-url' => $site->absoluteUrl($path),
                'data-disqus-identifier' => $identifier,
                'data-disqus-title' => $title,
            ]),
        );
    }

    /**
     * 年の変わり目に置く年号。一覧の中でいちばん大きい字だが、いちばん
     * 淡い。読むものではなく、どこまで遡ったかを示す目印。
     */
    private static function yearMarker(string $year): Element
    {
        return H::div(
            className: 'pt-10 first:pt-0',
            children: H::span(
                className: 'font-mono text-5xl font-light leading-none tracking-tight text-faint',
                children: $year,
            ),
        );
    }

    private static function empty(): Element
    {
        return H::p(
            className: 'text-[0.9375rem] text-muted',
            children: 'まだ記事がありません。',
        );
    }
}
