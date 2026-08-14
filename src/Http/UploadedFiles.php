<?php

declare(strict_types=1);

namespace App\Http;

/**
 * `$_FILES` を読む唯一の場所。
 *
 * Relayer の `Request` はクエリ・ボディ・ヘッダ・クッキーしか運ばず、
 * アップロードファイルの抽象を持たない。それでも「ページとハンドラは
 * superglobals を読まない」という規約は守りたいので、superglobal に
 * 触る責務をこのサービスに閉じ込め、ページからは型で受け取る。
 */
final class UploadedFiles
{
    /**
     * 指定フィールドのアップロードを、単一・複数どちらの form でも
     * 同じ形（1 件 1 配列のリスト）に均して返す。
     *
     * @return list<array{name: string, tmpName: string, error: int, size: int}>
     */
    public function all(string $field): array
    {
        $entry = $_FILES[$field] ?? null;
        if (!\is_array($entry) || !isset($entry['name'])) {
            return [];
        }

        // `name="file[]"` だと各キーが配列になる（PHP の仕様）。
        if (\is_array($entry['name'])) {
            $files = [];
            foreach (\array_keys($entry['name']) as $index) {
                $file = $this->one($entry, $index);
                if (null !== $file) {
                    $files[] = $file;
                }
            }

            return $files;
        }

        $file = $this->one($entry, null);

        return null === $file ? [] : [$file];
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return null|array{name: string, tmpName: string, error: int, size: int}
     */
    private function one(array $entry, int|string|null $index): ?array
    {
        $pick = static function (string $key) use ($entry, $index): mixed {
            $value = $entry[$key] ?? null;

            return null === $index ? $value : (\is_array($value) ? ($value[$index] ?? null) : null);
        };

        $error = (int) ($pick('error') ?? \UPLOAD_ERR_NO_FILE);
        if (\UPLOAD_ERR_NO_FILE === $error) {
            return null;
        }

        return [
            'name' => (string) ($pick('name') ?? ''),
            'tmpName' => (string) ($pick('tmp_name') ?? ''),
            'error' => $error,
            'size' => (int) ($pick('size') ?? 0),
        ];
    }
}
