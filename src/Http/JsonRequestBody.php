<?php

declare(strict_types=1);

namespace App\Http;

/**
 * `php://input` を読む唯一の場所。
 *
 * Relayer の `Request` が運ぶのはクエリ・フォームボディ・ヘッダ・クッキーだけで、
 * `application/json` の POST は `$_POST` が空になる。それでも「ページとハンドラは
 * superglobals も生ストリームも触らない」という規約は守りたいので、
 * `UploadedFiles` と同じく責務をこのサービスに閉じ込め、型で受け取る。
 *
 * 読んだ結果をプロパティに持たないのは、このクラスが DI のシングルトンで、
 * FrankenPHP の worker モードではリクエストをまたいで生き続けるため。
 * 一度メモ化すると 2 リクエスト目以降がずっと最初の本文を読むことになる。
 * `php://input` は PHP 5.6 以降なら何度でも読み直せるので、呼ぶたびに読む。
 */
final class JsonRequestBody
{
    public function raw(): string
    {
        $contents = \file_get_contents('php://input');

        return false === $contents ? '' : $contents;
    }

    /**
     * JSON オブジェクトとして読めなければ null。配列（JSON の `[...]`）も
     * null 扱いにする —— 受け側が期待するのは常にオブジェクト 1 つで、
     * JSON-RPC のバッチは 2025-06-18 の仕様で廃止されている。
     *
     * @return null|array<string, mixed>
     */
    public function decode(): ?array
    {
        $raw = $this->raw();
        if ('' === $raw) {
            return null;
        }

        /** @var mixed $decoded */
        $decoded = \json_decode($raw, true);

        if (!\is_array($decoded) || \array_is_list($decoded)) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
