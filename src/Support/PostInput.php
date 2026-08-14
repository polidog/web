<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

/**
 * 保存 1 回ぶんの入力。管理画面のフォームと移行スクリプトの両方が
 * これを組み立てて PostWriter に渡す — 「記事を保存する」経路を
 * 1 本にしておくと、HTML のレンダリングと ETag 更新と purge を
 * 呼び忘れる余地が無くなる。
 */
final readonly class PostInput
{
    /**
     * @param list<string> $tags       タグ名（slug ではなく表示名）
     * @param list<string> $categories カテゴリ名
     */
    public function __construct(
        public string $kind,
        public string $path,
        public string $title,
        public string $body,
        public string $status,
        public ?DateTimeImmutable $publishedAt = null,
        public ?string $eyecatch = null,
        public array $tags = [],
        public array $categories = [],
        public ?int $authorId = null,
        /**
         * Hugo が Disqus に渡していた識別子。移行スクリプトだけが値を入れ、
         * 管理画面からの保存では常に null（＝既存値を維持）。
         */
        public ?string $disqusId = null,
    ) {}

    public function isPublished(): bool
    {
        return 'published' === $this->status;
    }

    /**
     * 先頭スラッシュあり・末尾スラッシュなしに揃える。Relayer の
     * ルーターが末尾スラッシュを落とした形で照合するので、DB もその形で
     * 持たないと引けない。
     */
    public static function normalizePath(string $path): string
    {
        $path = \trim($path);
        $path = '/' . \ltrim($path, '/');
        $path = \rtrim($path, '/');

        return '' === $path ? '/' : $path;
    }
}
