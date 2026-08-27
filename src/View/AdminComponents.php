<?php

declare(strict_types=1);

namespace App\View;

use App\Support\Paginated;
use Polidog\UsePhp\Html\H;
use Polidog\UsePhp\Runtime\Element;

/**
 * 管理画面の部品。記事とページで同じエディタを使う（違いは kind と
 * タグ・カテゴリ欄の有無だけ）。
 *
 * ## 公開側と揃えていること
 *
 * 色は `assets/tailwind.css` の CSS 変数（surface / raised / ink / muted /
 * faint / hairline / accent）だけを使い、`dark:` は 1 つも書かない。`.dark`
 * が変数ごと差し替えるので、切り替え点は公開側と同じ 1 か所に寄る。
 * 見出しの級差・字間、日付や件数を等幅で組むところも公開側と同じ。
 *
 * ## 公開側とわざと変えていること
 *
 * - **入力欄には罫線を引く。** 公開側は「罫線を引かない」で通しているが、
 *   入力の当たり判定が見えないフォームは単に使えない。引くのは
 *   `border-hairline` 1 本まで。面で囲うのはエディタの本文欄と
 *   プレビューだけ（`bg-raised`）。
 * - **本文欄は等幅。** 公開側の「等幅は時間と数だけ」は組版の規則で、
 *   ここで扱うのは Markdown のソース。空白とインデントが意味を持つので
 *   等幅で組む。
 *
 * ## エディタの構造
 *
 * `editor()` が返す `<form>` は data 属性でだけ JS と繋がっている
 * （`public/assets/admin.js`）。usePHP の Renderer は children の文字列を
 * 必ずエスケープするのでページにインライン `<script>` を書けず、値の
 * 受け渡しは data 属性 1 本になる。プレビューの開閉も JS からクラスを
 * 足すのではなく `data-preview` の書き換えでやる —— Tailwind は
 * `src/**` しかスキャンしないので、JS の中にだけ現れるクラスは
 * そもそも CSS に出力されない。
 *
 * @phpstan-import-type PostAdminRow from \App\Service\PostRepository
 */
final class AdminComponents
{
    /** 入力欄。フォーカスリングは `:focus-visible` の共通指定に任せる。 */
    public const string INPUT =
        'block w-full rounded-md border border-hairline bg-surface px-3 py-2 text-[0.9375rem] '
        . 'text-ink transition-colors placeholder:text-faint hover:border-muted';

    public const string LABEL = 'block text-xs tracking-wide text-muted';

    public const string BUTTON =
        'inline-flex items-center justify-center rounded-md bg-accent px-5 py-2 text-sm font-semibold '
        . 'text-surface transition-opacity hover:opacity-85';

    public const string BUTTON_QUIET =
        'inline-flex items-center justify-center rounded-md border border-hairline px-4 py-2 text-sm '
        . 'text-muted transition-colors hover:text-ink';

    /**
     * Markdown の記法ボタン。`data-md` の値は admin.js が解釈する。
     *
     * ショートカットのあるものは title に併記する（押せることを知る場所が
     * ここしかない）。修飾キーの表記が「⌘ / Ctrl」の 2 通りなのは、
     * 書いている端末が Mac とは限らないため。
     */
    private const array MARKDOWN_TOOLS = [
        ['key' => 'h2', 'label' => 'H2', 'title' => '見出し'],
        ['key' => 'h3', 'label' => 'H3', 'title' => '小見出し'],
        ['key' => 'bold', 'label' => 'B', 'title' => '太字（⌘ / Ctrl + B）'],
        ['key' => 'italic', 'label' => 'I', 'title' => '斜体（⌘ / Ctrl + I）'],
        ['key' => 'link', 'label' => 'link', 'title' => 'リンク（⌘ / Ctrl + K）'],
        ['key' => 'inlineCode', 'label' => 'code', 'title' => 'インラインコード'],
        ['key' => 'code', 'label' => 'block', 'title' => 'コードブロック'],
        ['key' => 'quote', 'label' => 'quote', 'title' => '引用'],
        ['key' => 'list', 'label' => 'list', 'title' => '箇条書き'],
        ['key' => 'olist', 'label' => '1.', 'title' => '番号つき箇条書き'],
        ['key' => 'table', 'label' => 'table', 'title' => '表'],
        ['key' => 'image', 'label' => 'image', 'title' => '画像を選んでアップロード'],
    ];

    /**
     * 本文欄の下に出す操作の早見表。ツールバーに無い（キーでしか使えない）
     * ものだけを並べる —— ボタンのあるものは title に書いてある。
     */
    private const array EDITOR_HINTS = [
        'Enter でリスト・引用を継続',
        'Tab / Shift + Tab でネスト',
        'URL を貼るとリンクになる',
        '画像はドロップ・貼り付け',
        '⌘ / Ctrl + S で保存',
    ];

    /**
     * ページの見出し。公開側の `Components::heading()` と同じ組み方で、
     * 級だけ 1 段落としてある（管理画面は 1 画面に情報が多い）。
     *
     * @param list<Element> $actions 右端に並べるボタン・リンク
     */
    public static function pageHeader(string $title, string $lead = '', array $actions = []): Element
    {
        $left = [
            H::h1(
                className: 'text-[1.375rem] font-semibold leading-tight tracking-tight text-ink',
                children: $title,
            ),
        ];

        if ('' !== $lead) {
            $left[] = H::p(className: 'mt-2 font-mono text-xs tracking-wide text-muted', children: $lead);
        }

        return H::header(
            className: 'mb-8 flex flex-wrap items-end justify-between gap-4',
            children: [
                H::div(children: $left),
                [] === $actions
                    ? H::div()
                    : H::div(className: 'flex items-center gap-3', children: $actions),
            ],
        );
    }

    /**
     * エディタの上に置くパンくず。タイトル入力が見出しの役を担うので、
     * ここは「どこにいるか」だけを最小の級で示す。
     */
    public static function breadcrumb(string $href, string $label, string $current): Element
    {
        return H::nav(
            className: 'mb-6 flex items-baseline gap-2 text-[0.6875rem] text-faint',
            children: [
                H::a(href: $href, className: 'transition-colors hover:text-ink', children: $label),
                H::span(children: '/'),
                H::span(children: $current),
            ],
        );
    }

    /**
     * 記事 / 固定ページのエディタ。
     *
     * @param array<string, mixed> $post       既存の行（新規なら空配列）
     * @param list<string>         $tags
     * @param list<string>         $categories
     * @param list<string>         $errors
     */
    public static function editor(
        string $saveAction,
        array $post,
        string $indexHref,
        string $indexLabel,
        array $tags = [],
        array $categories = [],
        array $errors = [],
        bool $withTaxonomy = true,
        ?string $deleteAction = null,
        bool $saved = false,
    ): Element {
        // isset() は値が null のときも false になるので、null チェックは要らない。
        $value = static fn (string $key, string $default = ''): string => isset($post[$key])
            ? (string) $post[$key]
            : $default;

        $head = [];

        if ([] !== $errors) {
            $head[] = self::notice('error', $errors);
        } elseif ($saved) {
            $head[] = self::notice('ok', '保存しました。キャッシュも更新済みです。');
        }

        $body = [
            ...$head,
            self::titleField($value('title')),
            self::pathField($value('path')),
            self::toolbar(),
            self::draftNotice(),
            self::panes($value('body')),
            self::hints(),
            // 「画像」ボタンが開くファイル選択。name を持たせないのは、
            // 保存フォームの POST に混ぜないため（送るのは別口の
            // /admin/media/upload で、admin.js が fetch する）。
            new Element('input', [
                'type' => 'file',
                'accept' => 'image/*',
                'multiple' => true,
                'hidden' => true,
                'data-editor-file' => 'true',
            ]),
            self::metaFields($value, $tags, $categories, $withTaxonomy),
            self::actionBar($value, $post, $indexHref, $indexLabel, null !== $deleteAction),
        ];

        $children = [
            new Element('form', [
                'action' => $saveAction,
                'method' => 'post',
                'className' => 'flex flex-col gap-6',
                'data-editor' => 'true',
                // 既定は片ペイン。プレビューを開いたかどうかは admin.js が
                // localStorage に覚えていて、次に開いたときに復元する。
                'data-preview' => 'off',
                'data-preview-url' => '/admin/preview',
                'data-upload-url' => '/admin/media/upload',
                // 書きかけを localStorage に退避するときのキー。保存先 URL は
                // 記事ごとに違う（新規と編集も別）ので、そのまま識別子になる。
                'data-draft-key' => $saveAction,
            ], $body),
        ];

        if (null !== $deleteAction) {
            // 削除は別フォームにする。保存フォームに入れると Enter 送信で
            // どちらが走るか分からなくなるため。ボタンだけを操作バーに置き、
            // `form` 属性でこちらを指している（HTML の標準機能）。
            $children[] = H::form(
                action: $deleteAction,
                method: 'post',
                id: 'editor-delete',
                className: 'hidden',
            );
        }

        return H::div(children: $children);
    }

    /**
     * 管理画面の一覧。公開側の記事一覧と同じ「日付・タイトル」の組み方に
     * 揃えつつ、管理に要る 2 つ —— パスと公開状態 —— を右に足してある。
     * 年が飛ぶので日付は年から出す（公開側の年号マーカーは使わない）。
     *
     * `$selectName` を渡すと各行の頭にチェックボックスが付く（一括操作
     * 用）。そのときは行を `<a>` ではなく `<div>` で囲む —— チェック
     * ボックスをリンクの中に置くと、押すたびに編集画面へ飛んでしまう。
     *
     * @param Paginated<PostAdminRow> $list
     * @param ?string                 $selectName 選択欄の name（例 `ids[]`）
     */
    public static function postTable(Paginated $list, string $editBase, ?string $selectName = null): Element
    {
        if ([] === $list->items) {
            return self::emptyState('該当する記事がありません。');
        }

        return H::div(
            className: 'flex flex-col',
            children: \array_map(
                static function (array $post) use ($editBase, $selectName): Element {
                    $stamp = $post['publishedAt'] ?? $post['updatedAt'];
                    $row = 'group -mx-3 flex items-baseline gap-4 rounded-md px-3 py-2.5 '
                        . 'transition-colors hover:bg-raised';

                    $cells = [
                        H::time(
                            datetime: \str_replace(' ', 'T', $stamp),
                            className: 'shrink-0 font-mono text-xs text-muted',
                            children: \str_replace('-', '.', \substr($stamp, 0, 10)),
                        ),
                        H::span(
                            className: 'min-w-0 flex-1 truncate text-[0.9375rem] text-ink '
                                . 'transition-colors group-hover:text-accent',
                            children: $post['title'],
                        ),
                        H::span(
                            className: 'hidden max-w-[18rem] shrink-0 truncate font-mono text-xs '
                                . 'text-faint md:block',
                            children: $post['path'],
                        ),
                        self::statusBadge($post['status']),
                    ];

                    $href = \sprintf('%s/%d', $editBase, $post['id']);

                    if (null === $selectName) {
                        return H::a(href: $href, className: $row, children: $cells);
                    }

                    return H::div(
                        className: $row,
                        children: [
                            new Element('input', [
                                'type' => 'checkbox',
                                'name' => $selectName,
                                'value' => (string) $post['id'],
                                'aria-label' => \sprintf('「%s」を選択', $post['title']),
                                'data-bulk-item' => $post['status'],
                                'className' => 'size-4 shrink-0 accent-accent',
                            ]),
                            H::a(
                                href: $href,
                                className: 'flex min-w-0 flex-1 items-baseline gap-4',
                                children: $cells,
                            ),
                        ],
                    );
                },
                $list->items,
            ),
        );
    }

    /**
     * 一括操作のバー。`postTable()` に `$selectName` を渡したときだけ
     * 意味を持つ。挙動は admin.js（`data-bulk` 一式）。
     *
     * ボタンは選択が 0 件のあいだ `disabled` にする。押せないことを
     * クラスの付け外しではなく `disabled` 属性で表すのは、Tailwind が
     * `src/**` しかスキャンせず、JS の中にだけ現れるクラスは CSS に
     * 出力されないため（`disabled:` バリアントはここに書いてあるので出る）。
     */
    public static function bulkBar(string $submitLabel): Element
    {
        return H::div(
            className: 'mt-8 flex flex-wrap items-center gap-4',
            children: [
                H::label(
                    className: 'flex items-center gap-2 text-[0.8125rem] text-muted',
                    children: [
                        new Element('input', [
                            'type' => 'checkbox',
                            'data-bulk-all' => 'true',
                            'className' => 'size-4 accent-accent',
                        ]),
                        H::span(children: 'このページの下書きをすべて選択'),
                    ],
                ),
                new Element('span', [
                    'data-bulk-count' => 'true',
                    'className' => 'font-mono text-xs tracking-wide text-muted',
                    // 初期値。以降は選択が変わるたび admin.js が書き換える。
                ], ['0 件を選択中']),
                new Element('button', [
                    'type' => 'submit',
                    'data-bulk-submit' => 'true',
                    'disabled' => true,
                    'className' => self::BUTTON . ' disabled:cursor-not-allowed disabled:opacity-40',
                ], [$submitLabel]),
            ],
        );
    }

    /**
     * ページ送り。公開側の `Components::pagination()` と同じ組み方。
     *
     * @param Paginated<PostAdminRow> $list
     */
    public static function adminPagination(Paginated $list, string $basePath, string $query = ''): Element
    {
        if ($list->pages() <= 1) {
            return H::div();
        }

        $link = static function (int $target, string $label, bool $enabled) use ($basePath, $query): Element {
            if (!$enabled) {
                return H::span(className: 'text-[0.8125rem] text-faint', children: $label);
            }

            $suffix = '' !== $query ? '&' . $query : '';

            return H::a(
                href: \sprintf('%s?page=%d%s', $basePath, $target, $suffix),
                className: 'text-[0.8125rem] text-muted transition-colors hover:text-accent',
                children: $label,
            );
        };

        return H::nav(
            className: 'mt-10 flex items-baseline justify-between',
            children: [
                $link($list->page - 1, '← 新しい', $list->hasPrevious()),
                H::span(
                    className: 'font-mono text-xs tracking-wide text-muted',
                    children: \sprintf('%d / %d（全 %d 件）', $list->page, $list->pages(), $list->total),
                ),
                $link($list->page + 1, '古い →', $list->hasNext()),
            ],
        );
    }

    /**
     * 通知。色面は作らず `bg-raised` の上に意味色の文字を置く。
     *
     * @param 'error'|'ok'|'warn'    $tone
     * @param list<string>|string    $messages
     */
    public static function notice(string $tone, array|string $messages): Element
    {
        $color = match ($tone) {
            'error' => 'text-danger',
            'ok' => 'text-success',
            'warn' => 'text-ink',
        };

        $lines = \is_string($messages) ? [$messages] : $messages;

        return H::div(
            className: 'rounded-md bg-raised px-4 py-3 text-sm ' . $color,
            children: \array_map(
                static fn (string $line): Element => H::p(children: $line),
                $lines,
            ),
        );
    }

    /**
     * クリップボードにコピーするボタン。押した値は admin.js が
     * `data-copy` から読む（画像一覧から本文へ貼るための導線）。
     */
    public static function copyButton(string $text, string $label): Element
    {
        return new Element('button', [
            'type' => 'button',
            'data-copy' => $text,
            'className' => 'rounded px-2 py-1 font-mono text-[0.6875rem] text-muted '
                . 'transition-colors hover:bg-raised hover:text-ink',
        ], [$label]);
    }

    public static function emptyState(string $message): Element
    {
        return H::p(className: 'py-10 text-[0.9375rem] text-muted', children: $message);
    }

    private static function statusBadge(string $status): Element
    {
        // 公開は既定の状態なので目立たせない。目に留めたいのは下書きのほう。
        return 'published' === $status
            ? H::span(className: 'w-12 shrink-0 text-right text-[0.6875rem] text-faint', children: '公開')
            : H::span(
                className: 'w-12 shrink-0 rounded-full bg-raised text-center text-[0.6875rem] text-muted',
                children: '下書き',
            );
    }

    private static function titleField(string $title): Element
    {
        return new Element('input', [
            'type' => 'text',
            'name' => 'title',
            'value' => $title,
            'required' => true,
            'placeholder' => 'タイトル',
            'aria-label' => 'タイトル',
            'autocomplete' => 'off',
            // 見出しそのものとして扱う。罫線で囲うと、いちばん大きい字の
            // 周りにいちばん強い線が来て画面が入力欄の集合に見える。
            'className' => 'block w-full bg-transparent text-[1.5rem] font-semibold leading-tight '
                . 'tracking-tight text-ink placeholder:text-faint',
        ]);
    }

    private static function pathField(string $path): Element
    {
        return H::div(
            className: 'flex items-baseline gap-3',
            children: [
                H::span(className: 'shrink-0 font-mono text-[0.6875rem] tracking-wide text-faint', children: 'URL'),
                new Element('input', [
                    'type' => 'text',
                    'name' => 'path',
                    'value' => $path,
                    'required' => true,
                    'placeholder' => '/blog/2026/08/example',
                    'aria-label' => 'URL（パス）',
                    'autocomplete' => 'off',
                    'spellcheck' => 'false',
                    'className' => 'min-w-0 flex-1 bg-transparent font-mono text-xs text-muted '
                        . 'placeholder:text-faint',
                ]),
                H::span(
                    className: 'shrink-0 text-[0.6875rem] text-faint',
                    children: '公開後に変えると古い URL は 404 になる',
                ),
            ],
        );
    }

    private static function toolbar(): Element
    {
        $tools = \array_map(
            static fn (array $tool): Element => new Element('button', [
                'type' => 'button',
                'data-md' => $tool['key'],
                'title' => $tool['title'],
                'aria-label' => $tool['title'],
                'className' => 'rounded px-2 py-1 font-mono text-[0.6875rem] text-muted '
                    . 'transition-colors hover:bg-raised hover:text-ink',
            ], [$tool['label']]),
            self::MARKDOWN_TOOLS,
        );

        $right = [
            // 画像のアップロードなど、少し待たされる操作の途中経過はここに出す。
            new Element('span', [
                'data-editor-status' => 'true',
                'className' => 'text-[0.6875rem] text-muted',
            ], ['']),
            new Element('span', [
                'data-editor-count' => 'true',
                'className' => 'font-mono text-[0.6875rem] tracking-wide text-faint',
            ], ['0 字']),
            new Element('button', [
                'type' => 'button',
                'data-editor-preview-toggle' => 'true',
                'aria-pressed' => 'false',
                'className' => 'rounded px-2 py-1 text-[0.6875rem] text-muted '
                    . 'transition-colors hover:bg-raised hover:text-ink',
            ], ['プレビュー']),
        ];

        return H::div(
            className: 'flex flex-wrap items-center justify-between gap-3',
            children: [
                H::div(className: 'flex flex-wrap items-center gap-1', children: $tools),
                H::div(className: 'flex items-center gap-3', children: $right),
            ],
        );
    }

    /**
     * 本文欄とプレビュー。横に並ぶかどうかは `data-preview` を見る CSS が
     * 決める（`assets/tailwind.css` の `.editor-panes`）。
     *
     * 級と行間は読む側ではなく**書く側**に寄せてある。等幅 13px・行間 1.6 は
     * ソースの塊を眺めるには足りるが、日本語を打ち続けると行が詰まって
     * 現在行を見失う。`editor-body` で 14px / 1.9 に開き、タブ幅も 2 に
     * 揃えている（Tab は admin.js がスペース 2 個に置き換えるので、
     * 貼り付けで入った素のタブだけが tab-size を見る）。
     */
    private static function panes(string $body): Element
    {
        $textarea = new Element('textarea', [
            'name' => 'body',
            'data-editor-body' => 'true',
            'aria-label' => '本文（Markdown）',
            'spellcheck' => 'false',
            // 行間はクラスで持たせる。`.editor-body` の側に書くと、あとから
            // 来る utilities レイヤーの `text-sm`（line-height 込み）に負ける。
            'className' => 'editor-body block h-[70vh] min-h-[24rem] w-full resize-y rounded-lg bg-raised '
                . 'p-5 font-mono text-sm leading-[1.9] text-ink placeholder:text-faint',
            'placeholder' => 'Markdown で書く。画像はドロップか貼り付けでアップロードできる。',
        ], [$body]);

        $preview = new Element('div', [
            'data-editor-preview-pane' => 'true',
            'className' => 'editor-preview h-[70vh] min-h-[24rem] overflow-y-auto rounded-lg bg-raised p-6',
        ], [
            new Element('div', [
                'data-editor-preview' => 'true',
                'className' => 'prose',
            ], [
                H::p(className: 'text-sm text-muted', children: '入力するとここに表示される。'),
            ]),
        ]);

        return H::div(className: 'editor-panes', children: [$textarea, $preview]);
    }

    /**
     * 書きかけの復元を促す帯。中身は admin.js が入れるので、ここでは
     * 器だけを hidden で置く（初回描画では出さない）。
     */
    private static function draftNotice(): Element
    {
        $button = static fn (string $key, string $label): Element => new Element('button', [
            'type' => 'button',
            'data-editor-draft-action' => $key,
            'className' => 'rounded px-2 py-1 text-[0.75rem] text-muted '
                . 'transition-colors hover:bg-surface hover:text-ink',
        ], [$label]);

        return new Element('div', [
            'data-editor-draft' => 'true',
            'hidden' => true,
            'className' => 'flex flex-wrap items-center gap-3 rounded-md bg-raised px-4 py-3 text-[0.8125rem]',
        ], [
            new Element('span', [
                'data-editor-draft-label' => 'true',
                'className' => 'text-ink',
            ], ['']),
            H::div(
                className: 'ml-auto flex items-center gap-1',
                children: [$button('restore', '復元する'), $button('discard', '破棄する')],
            ),
        ]);
    }

    /**
     * 本文欄の下の早見表。キーでしか使えない操作を、書きながら目に入る
     * 位置に置く（ボタンのあるものは title に書いてある）。
     */
    private static function hints(): Element
    {
        return H::p(
            className: 'flex flex-wrap gap-x-3 gap-y-1 text-[0.6875rem] text-faint',
            children: \array_map(
                static fn (string $hint): Element => H::span(children: $hint),
                self::EDITOR_HINTS,
            ),
        );
    }

    /**
     * 本文の下に置くメタ情報。折りたたまないのは、どれも書きながら
     * 触る欄だから（隠すと開く手間が毎回かかる）。
     *
     * @param \Closure(string, string=): string $value
     * @param list<string>                      $tags
     * @param list<string>                      $categories
     */
    private static function metaFields(
        \Closure $value,
        array $tags,
        array $categories,
        bool $withTaxonomy,
    ): Element {
        $fields = [];

        if ($withTaxonomy) {
            $fields[] = self::field(
                'タグ',
                H::input(
                    type: 'text',
                    name: 'tags',
                    value: \implode(', ', $tags),
                    placeholder: 'PHP, SQLite',
                    className: self::INPUT,
                ),
                'カンマ区切り。URL は Hugo と同じ規則で作られる。',
            );
            $fields[] = self::field(
                'カテゴリ',
                H::input(
                    type: 'text',
                    name: 'categories',
                    value: \implode(', ', $categories),
                    className: self::INPUT,
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
                    className: self::INPUT,
                ),
            );
        }

        $fields[] = self::field(
            '公開日時',
            H::input(
                type: 'datetime-local',
                name: 'publishedAt',
                value: self::toLocalDateTime($value('publishedAt')),
                className: self::INPUT,
            ),
            '空のまま公開すると、保存した時刻が入る。',
        );

        return H::section(
            className: 'mt-4 flex flex-col gap-5',
            children: [
                H::h2(className: 'text-xs tracking-wide text-faint', children: 'メタ情報'),
                H::div(
                    className: 'grid grid-cols-1 gap-x-8 gap-y-5 sm:grid-cols-2',
                    children: $fields,
                ),
            ],
        );
    }

    /**
     * 画面下に貼り付く操作バー。本文がどれだけ長くても保存に手が届く。
     *
     * @param \Closure(string, string=): string $value
     * @param array<string, mixed>              $post
     */
    private static function actionBar(
        \Closure $value,
        array $post,
        string $indexHref,
        string $indexLabel,
        bool $withDelete,
    ): Element {
        $status = $value('status', 'draft');

        $left = [
            H::button(
                type: 'submit',
                className: self::BUTTON,
                children: '保存',
            ),
            H::select(
                name: 'status',
                className: 'rounded-md border border-hairline bg-surface px-3 py-2 text-sm text-ink',
                children: [
                    H::option(value: 'draft', selected: 'published' !== $status, children: '下書き'),
                    H::option(value: 'published', selected: 'published' === $status, children: '公開'),
                ],
            ),
            new Element('span', [
                'data-editor-dirty' => 'true',
                'hidden' => true,
                'className' => 'text-xs text-muted',
            ], ['未保存の変更があります']),
        ];

        $right = [
            H::a(
                href: $indexHref,
                className: 'text-[0.8125rem] text-muted transition-colors hover:text-ink',
                children: $indexLabel,
            ),
        ];

        // 「表示を確認」は保存済みのものにだけ出す。新規作成中はまだ URL が
        // 無いので、path だけで判断すると `//` を指すリンクができる。
        if (isset($post['id'], $post['path'])) {
            $right[] = H::a(
                href: \rtrim((string) $post['path'], '/') . '/',
                target: '_blank',
                rel: 'noopener',
                className: 'text-[0.8125rem] text-muted transition-colors hover:text-ink',
                children: '表示を確認',
            );
        }

        if ($withDelete) {
            $right[] = new Element('button', [
                'type' => 'submit',
                'form' => 'editor-delete',
                'data-confirm' => '削除すると元に戻せません。続けますか？',
                'className' => 'text-[0.8125rem] text-danger transition-opacity hover:opacity-75',
            ], ['削除']);
        }

        return H::div(
            className: 'sticky bottom-0 z-10 -mb-6 flex flex-wrap items-center gap-4 border-t '
                . 'border-hairline bg-surface/95 py-4 backdrop-blur',
            children: [
                H::div(className: 'flex items-center gap-3', children: $left),
                H::div(className: 'ml-auto flex items-center gap-5', children: $right),
            ],
        );
    }

    private static function field(string $label, Element $control, string $hint = ''): Element
    {
        $children = [
            H::span(className: self::LABEL, children: $label),
            H::div(className: 'mt-1.5', children: $control),
        ];

        if ('' !== $hint) {
            $children[] = H::p(className: 'mt-1.5 text-[0.6875rem] text-faint', children: $hint);
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
