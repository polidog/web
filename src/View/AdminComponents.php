<?php

declare(strict_types=1);

namespace App\View;

use App\Support\Paginated;
use Polidog\UsePhp\Html\H;
use Polidog\UsePhp\Runtime\Element;

/**
 * 管理画面の部品。記事とページで同じフォームを使う（違いは kind と
 * タグ・カテゴリ欄の有無だけ）。
 */
final class AdminComponents
{
    private const string INPUT_CLASS =
        'mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm '
        . 'focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 '
        . 'dark:border-gray-600 dark:bg-slate-700 dark:text-white';

    private const string LABEL_CLASS = 'block text-sm font-medium text-gray-700 dark:text-gray-300';

    /**
     * 記事 / 固定ページの編集フォーム。
     *
     * @param array<string, mixed> $post   既存の行（新規なら空配列）
     * @param list<string>         $tags
     * @param list<string>         $categories
     * @param list<string>         $errors
     */
    public static function postForm(
        string $saveAction,
        array $post,
        array $tags = [],
        array $categories = [],
        array $errors = [],
        bool $withTaxonomy = true,
        ?string $deleteAction = null,
    ): Element {
        // isset() は値が null のときも false になるので、null チェックは要らない。
        $value = static fn (string $key, string $default = ''): string => isset($post[$key])
            ? (string) $post[$key]
            : $default;

        $fields = [
            self::field('タイトル', H::input(
                type: 'text',
                name: 'title',
                value: $value('title'),
                required: true,
                className: self::INPUT_CLASS,
            )),
            self::field(
                'URL（パス）',
                H::input(
                    type: 'text',
                    name: 'path',
                    value: $value('path'),
                    required: true,
                    placeholder: '/blog/2026/08/example',
                    className: self::INPUT_CLASS,
                ),
                'サイト内の絶対パス。公開後に変えると古い URL は 404 になる。',
            ),
            self::field('本文（Markdown）', H::textarea(
                name: 'body',
                rows: 24,
                className: self::INPUT_CLASS . ' font-mono',
                children: $value('body'),
            )),
        ];

        if ($withTaxonomy) {
            $fields[] = self::field(
                'タグ',
                H::input(
                    type: 'text',
                    name: 'tags',
                    value: \implode(', ', $tags),
                    className: self::INPUT_CLASS,
                ),
                'カンマ区切り。URL は Hugo と同じ規則（小文字化・空白をハイフン）で作られる。',
            );
            $fields[] = self::field(
                'カテゴリ',
                H::input(
                    type: 'text',
                    name: 'categories',
                    value: \implode(', ', $categories),
                    className: self::INPUT_CLASS,
                ),
                'カンマ区切り。',
            );
            $fields[] = self::field(
                'アイキャッチ画像の URL',
                H::input(
                    type: 'text',
                    name: 'eyecatch',
                    value: $value('eyecatch'),
                    placeholder: '/images/2026/08/cover.jpg',
                    className: self::INPUT_CLASS,
                ),
            );
        }

        $fields[] = self::field('公開状態', H::select(
            name: 'status',
            className: self::INPUT_CLASS,
            children: [
                H::option(
                    value: 'draft',
                    selected: 'published' !== $value('status', 'draft'),
                    children: '下書き',
                ),
                H::option(
                    value: 'published',
                    selected: 'published' === $value('status', 'draft'),
                    children: '公開',
                ),
            ],
        ));

        $fields[] = self::field(
            '公開日時',
            H::input(
                type: 'datetime-local',
                name: 'publishedAt',
                value: self::toLocalDateTime($value('publishedAt')),
                className: self::INPUT_CLASS,
            ),
            '空のまま公開すると、保存した時刻が入る。',
        );

        $buttons = [
            H::button(
                type: 'submit',
                className: 'rounded-md bg-sky-600 px-5 py-2 text-sm font-semibold text-white hover:bg-sky-500',
                children: '保存',
            ),
        ];

        if (isset($post['path'])) {
            $buttons[] = H::a(
                href: (string) $post['path'] . '/',
                target: '_blank',
                rel: 'noopener',
                className: 'rounded-md border border-gray-300 px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-slate-700',
                children: '表示を確認',
            );
        }

        $children = [];

        if ([] !== $errors) {
            $children[] = H::div(
                className: 'rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-300',
                children: \array_map(
                    static fn (string $error): Element => H::p(children: $error),
                    $errors,
                ),
            );
        }

        $children[] = H::form(
            action: $saveAction,
            method: 'post',
            className: 'space-y-6',
            children: [
                ...$fields,
                H::div(className: 'flex items-center gap-3 pt-2', children: $buttons),
            ],
        );

        if (null !== $deleteAction) {
            // 削除だけは別フォームにする。保存フォームに入れると、Enter で
            // 送信したときにどちらが走るか分からなくなる。
            $children[] = H::form(
                action: $deleteAction,
                method: 'post',
                className: 'mt-10 border-t border-gray-200 pt-6 dark:border-gray-800',
                children: H::button(
                    type: 'submit',
                    className: 'text-sm font-medium text-red-600 hover:text-red-500 dark:text-red-400',
                    children: 'この記事を削除する',
                ),
            );
        }

        return H::div(className: 'max-w-3xl', children: $children);
    }

    /**
     * 管理画面の一覧テーブル。
     *
     * @param Paginated<array<string, mixed>> $list
     */
    public static function postTable(Paginated $list, string $editBase): Element
    {
        if ([] === $list->items) {
            return H::p(
                className: 'rounded-lg border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400',
                children: '該当する記事がありません。',
            );
        }

        return H::div(
            className: 'overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-slate-800',
            children: \array_map(
                static fn (array $post): Element => H::a(
                    href: \sprintf('%s/%d', $editBase, (int) $post['id']),
                    className: 'flex items-center gap-4 border-b border-gray-100 px-4 py-3 last:border-0 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-slate-700',
                    children: [
                        H::span(
                            className: 'published' === $post['status']
                                ? 'shrink-0 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/40 dark:text-green-300'
                                : 'shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300',
                            children: 'published' === $post['status'] ? '公開' : '下書き',
                        ),
                        H::span(
                            className: 'flex-1 truncate text-sm text-gray-900 dark:text-white',
                            children: (string) $post['title'],
                        ),
                        H::span(
                            className: 'hidden shrink-0 text-xs text-gray-500 sm:block dark:text-gray-400',
                            children: (string) $post['path'],
                        ),
                    ],
                ),
                $list->items,
            ),
        );
    }

    /**
     * @param Paginated<array<string, mixed>> $list
     */
    public static function adminPagination(Paginated $list, string $basePath, string $query = ''): Element
    {
        if ($list->pages() <= 1) {
            return H::div();
        }

        $href = static function (int $page) use ($basePath, $query): string {
            $suffix = '' !== $query ? '&' . $query : '';

            return \sprintf('%s?page=%d%s', $basePath, $page, $suffix);
        };

        $children = [];

        if ($list->hasPrevious()) {
            $children[] = H::a(
                href: $href($list->page - 1),
                className: 'rounded-md border border-gray-300 px-3 py-1.5 text-sm dark:border-gray-600 dark:text-gray-300',
                children: '前へ',
            );
        }

        $children[] = H::span(
            className: 'text-sm text-gray-500 dark:text-gray-400',
            children: \sprintf('%d / %d（全 %d 件）', $list->page, $list->pages(), $list->total),
        );

        if ($list->hasNext()) {
            $children[] = H::a(
                href: $href($list->page + 1),
                className: 'rounded-md border border-gray-300 px-3 py-1.5 text-sm dark:border-gray-600 dark:text-gray-300',
                children: '次へ',
            );
        }

        return H::div(className: 'mt-6 flex items-center gap-4', children: $children);
    }

    private static function field(string $label, Element $control, string $hint = ''): Element
    {
        $children = [
            H::span(className: self::LABEL_CLASS, children: $label),
            $control,
        ];

        if ('' !== $hint) {
            $children[] = H::p(
                className: 'mt-1 text-xs text-gray-500 dark:text-gray-400',
                children: $hint,
            );
        }

        return H::label(className: 'block', children: $children);
    }

    /**
     * DB は 'Y-m-d H:i:s'、`<input type="datetime-local">` は 'Y-m-d\TH:i'。
     */
    private static function toLocalDateTime(string $value): string
    {
        if ('' === $value) {
            return '';
        }

        $timestamp = \strtotime($value);

        return false === $timestamp ? '' : \date('Y-m-d\TH:i', $timestamp);
    }
}
