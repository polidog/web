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
 * 受け取る配列は PostRepository が返す行そのもの。`.psx` は PHPStan の
 * 検査対象に入らない（独自構文で PHP パーサが読めない）ので、ページから
 * 渡された値が実際に検査されるのはこの境界。だから引数は
 * `array<string, mixed>` ではなく行の shape で受ける。
 *
 * @phpstan-import-type ArchiveRow from \App\Service\PostRepository
 * @phpstan-import-type NeighbourRow from \App\Service\PostRepository
 * @phpstan-import-type PostListRow from \App\Service\PostRepository
 */
final class Components
{
    /**
     * 一覧の 1 件。Hugo の partials/articles/list_item.html 相当。
     *
     * @param PostListRow $post
     */
    public static function postCard(array $post): Element
    {
        $publishedAt = $post['publishedAt'];

        $children = [];

        if (null !== $publishedAt && '' !== $publishedAt) {
            $children[] = self::date($publishedAt);
        }

        $children[] = H::h2(
            className: 'mt-2 text-xl font-semibold tracking-tight text-gray-900 dark:text-white',
            children: H::a(
                href: $post['path'] . '/',
                className: 'hover:text-sky-600 dark:hover:text-sky-400',
                children: $post['title'],
            ),
        );

        $excerpt = $post['excerpt'] ?? '';
        if ('' !== $excerpt) {
            $children[] = H::p(
                className: 'mt-3 text-sm leading-6 text-gray-600 dark:text-gray-400',
                children: $excerpt,
            );
        }

        return H::article(
            className: 'border-b border-gray-200 py-8 first:pt-0 dark:border-gray-800',
            children: $children,
        );
    }

    /**
     * @param list<PostListRow> $posts
     */
    public static function postList(array $posts): Element
    {
        if ([] === $posts) {
            return H::p(
                className: 'text-gray-600 dark:text-gray-400',
                children: 'まだ記事がありません。',
            );
        }

        return H::div(
            className: 'divide-y divide-gray-200 dark:divide-gray-800',
            children: \array_map(self::postCard(...), $posts),
        );
    }

    /**
     * 月別アーカイブの本体。Hugo の archives/list.html 相当。
     *
     * 渡された 1 ページぶん（30 件）を月の見出しで束ねるだけで、
     * 月をまたいで集計はしない — 同じ月がページをまたいで 2 回出るのは
     * Hugo と同じ挙動なので、そこは合わせる。
     *
     * @param list<ArchiveRow> $posts 新しい順に並んだ記事
     */
    public static function archiveList(array $posts): Element
    {
        if ([] === $posts) {
            return H::p(
                className: 'text-gray-600 dark:text-gray-400',
                children: 'まだ記事がありません。',
            );
        }

        /** @var array<string, list<ArchiveRow>> $byMonth */
        $byMonth = [];
        foreach ($posts as $post) {
            // publishedAt は 'Y-m-d H:i:s' の TEXT なので先頭 7 文字が年月。
            $byMonth[\substr($post['publishedAt'], 0, 7)][] = $post;
        }

        return H::div(
            className: 'space-y-8',
            children: \array_map(
                static function (string $ym) use ($byMonth): Element {
                    [$year, $month] = \explode('-', $ym);

                    return H::section(
                        children: [
                            // 見出しの月はゼロ埋め 2 桁（Hugo の `2026年08月`）。
                            H::h2(
                                className: 'mb-3 text-xl font-semibold text-gray-900 dark:text-white',
                                children: \sprintf('%s年%s月', $year, $month),
                            ),
                            H::ul(
                                className: 'divide-y divide-gray-200 dark:divide-gray-800',
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
     * 日付。Hugo の `2006年1月2日` 表記に合わせる（ゼロ埋めしない）。
     */
    public static function date(string $value): Element
    {
        $timestamp = \strtotime($value);
        if (false === $timestamp) {
            return H::time(children: $value);
        }

        return H::time(
            datetime: \date('c', $timestamp),
            className: 'text-sm text-gray-500 dark:text-gray-400',
            children: \sprintf(
                '%d年%d月%d日',
                (int) \date('Y', $timestamp),
                (int) \date('n', $timestamp),
                (int) \date('j', $timestamp),
            ),
        );
    }

    /**
     * アーカイブの 1 行。左にタイトル、右に日付。
     *
     * @param ArchiveRow $post
     */
    public static function archiveItem(array $post): Element
    {
        return H::li(
            className: 'py-2',
            children: H::a(
                href: $post['path'] . '/',
                className: 'group flex items-center justify-between gap-4',
                children: [
                    H::span(
                        className: 'text-gray-900 group-hover:text-sky-600 dark:text-white dark:group-hover:text-sky-400',
                        children: $post['title'],
                    ),
                    self::date($post['publishedAt']),
                ],
            ),
        );
    }

    /**
     * タグ・カテゴリのバッジ列。
     *
     * @param list<array{id: int, name: string, slug: string}> $terms
     */
    public static function termBadges(array $terms, string $basePath): Element
    {
        return H::div(
            className: 'mt-2 flex flex-wrap gap-2',
            children: \array_map(
                static fn (array $term): Element => H::a(
                    href: HugoSlug::toPath($basePath, $term['slug']),
                    className: 'inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-800 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
                    children: $term['name'],
                ),
                $terms,
            ),
        );
    }

    /**
     * タグ・カテゴリの一覧（`/tags/`・`/categories/`・`/tech-tags/`）。
     * 記事数の多い順。
     *
     * @param list<array{id: int, name: string, slug: string, count: int}> $terms
     */
    public static function termCloud(array $terms, string $basePath): Element
    {
        return H::div(
            className: 'flex flex-wrap gap-3',
            children: \array_map(
                static fn (array $term): Element => H::a(
                    href: HugoSlug::toPath($basePath, $term['slug']),
                    className: 'inline-flex items-center gap-2 rounded-full bg-gray-100 px-4 py-2 text-sm font-medium text-gray-800 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
                    children: [
                        H::span(children: $term['name']),
                        H::span(
                            className: 'text-xs text-gray-500 dark:text-gray-400',
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
                return H::span(
                    className: 'rounded-md px-4 py-2 text-sm font-medium text-gray-400 dark:text-gray-600',
                    children: $label,
                );
            }

            $href = '' === $basePath
                ? (1 === $target ? '/' : \sprintf('/page/%d/', $target))
                : (1 === $target ? $basePath . '/' : \sprintf('%s/page/%d/', $basePath, $target));

            return H::a(
                href: $href,
                className: 'rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800',
                children: $label,
            );
        };

        return H::nav(
            className: 'mt-12 flex items-center justify-between',
            children: [
                $link($page - 1, '← 新しい記事', $page > 1),
                H::span(
                    className: 'text-sm text-gray-500 dark:text-gray-400',
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
                className: 'group flex flex-col rounded-lg border border-gray-200 p-4 transition-colors hover:border-sky-300 hover:bg-sky-50 dark:border-gray-700 dark:hover:border-sky-600 dark:hover:bg-sky-900/10'
                    . ($alignRight ? ' text-right' : ''),
                children: [
                    H::span(
                        className: 'text-sm font-medium text-gray-500 dark:text-gray-400',
                        children: $label,
                    ),
                    H::span(
                        className: 'mt-1 text-base font-semibold text-gray-900 dark:text-white',
                        children: $post['title'],
                    ),
                ],
            );
        };

        return H::nav(
            className: 'mt-12 mb-8 grid grid-cols-1 gap-4 md:grid-cols-2',
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
            className: 'mt-8 border-t border-gray-200 pt-8 dark:border-gray-800',
            children: new Element('div', [
                'id' => 'disqus_thread',
                'data-disqus-shortname' => $site->disqusShortname,
                'data-disqus-url' => $site->absoluteUrl($path),
                'data-disqus-identifier' => $identifier,
                'data-disqus-title' => $title,
            ]),
        );
    }

    public static function heading(string $text, string $lead = ''): Element
    {
        $children = [
            H::h1(
                className: 'text-3xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-4xl',
                children: $text,
            ),
        ];

        if ('' !== $lead) {
            $children[] = H::p(
                className: 'mt-3 text-base text-gray-600 dark:text-gray-400',
                children: $lead,
            );
        }

        return H::header(className: 'mb-10', children: $children);
    }
}
