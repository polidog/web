<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 一覧ページの 1 画面ぶん。Hugo の paginator が持っていた情報と同じ。
 *
 * @template T
 */
final readonly class Paginated
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {}

    public function pages(): int
    {
        return \max(1, (int) \ceil($this->total / $this->perPage));
    }

    public function hasPrevious(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->page < $this->pages();
    }
}
