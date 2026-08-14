<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\PostInput;
use DateTimeImmutable;
use Polidog\Relayer\Validation\Validator;

/**
 * 管理画面のフォーム → PostInput。検証もここで済ませる。
 *
 * 記事と固定ページでフォームがほぼ同じなので、両方これを通す。
 */
final class PostFormMapper
{
    /**
     * @param array<string, mixed> $form
     *
     * @return array{input: null|PostInput, errors: array<string, string>}
     */
    public function map(array $form, string $kind, ?int $authorId): array
    {
        $schema = Validator::object([
            'title' => Validator::string()->trim()->min(1, 'タイトルを入力してください。'),
            'path' => Validator::string()->trim()->min(1, 'URL（パス）を入力してください。')
                ->regex('#^/[^\s?\#]*$#', 'URL はスラッシュで始まり、空白・? ・# を含まない形にしてください。'),
            'body' => Validator::string()->default(''),
            'status' => Validator::enum(['draft', 'published'])->default('draft'),
            'publishedAt' => Validator::string()->trim()->optional(),
            'eyecatch' => Validator::string()->trim()->optional(),
            'tags' => Validator::string()->default(''),
            'categories' => Validator::string()->default(''),
        ]);

        $result = $schema->safeParse($form);
        if (!$result->success) {
            return ['input' => null, 'errors' => $result->errors];
        }

        /** @var array<string, mixed> $data */
        $data = $result->data;

        $publishedAt = null;
        $rawPublishedAt = $data['publishedAt'] ?? null;
        if (\is_string($rawPublishedAt) && '' !== $rawPublishedAt) {
            $timestamp = \strtotime($rawPublishedAt);
            if (false === $timestamp) {
                return ['input' => null, 'errors' => ['publishedAt' => '公開日時の形式が正しくありません。']];
            }
            $publishedAt = new DateTimeImmutable('@' . $timestamp);
            $publishedAt = $publishedAt->setTimezone(new \DateTimeZone(\date_default_timezone_get()));
        }

        return [
            'input' => new PostInput(
                kind: $kind,
                path: PostInput::normalizePath((string) $data['path']),
                title: (string) $data['title'],
                body: (string) $data['body'],
                status: (string) $data['status'],
                publishedAt: $publishedAt,
                eyecatch: self::nullIfEmpty($data['eyecatch'] ?? null),
                tags: self::splitList((string) $data['tags']),
                categories: self::splitList((string) $data['categories']),
                authorId: $authorId,
            ),
            'errors' => [],
        ];
    }

    /**
     * @return list<string>
     */
    private static function splitList(string $value): array
    {
        // 全角カンマも受ける。手で打つ欄なので、区切り記号で弾かれると煩わしい。
        $parts = \preg_split('/[,、]/u', $value);
        if (false === $parts) {
            return [];
        }

        return \array_values(\array_filter(\array_map('trim', $parts), static fn (string $v): bool => '' !== $v));
    }

    private static function nullIfEmpty(mixed $value): ?string
    {
        return \is_string($value) && '' !== $value ? $value : null;
    }
}
