<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Service\MediaStorage;
use RuntimeException;

/**
 * 画像まわりの MCP ツール。
 *
 * 会話に貼られた画像を base64 で受けるのではなく URL で受けるのは、
 * 画像 1 枚が数十万トークンになるため。URL なら取得はサーバー側の
 * 仕事で済み、会話には 1 行しか流れない。
 */
final readonly class MediaTools
{
    public function __construct(
        private MediaStorage $media,
        private ImageFetcher $fetcher,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            [
                'name' => 'list_media',
                'description' => 'アップロード済みの画像を新しい順に一覧する。'
                    . '記事に画像を差し込む前に、既にある画像を探すのに使う。',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'description' => '返す件数。既定は 50。'],
                    ],
                ],
            ],
            [
                'name' => 'upload_media_from_url',
                'description' => '画像を URL から取り込み、polidog.jp で配信できるようにする。'
                    . '返ってきた URL（/images/... の形）を記事の本文や eyecatch に使う。'
                    . '受け付けるのは https の URL と jpeg / png / gif / webp / avif のみ（SVG は不可）。',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'url' => ['type' => 'string', 'description' => '取り込みたい画像の https URL。'],
                        'filename' => [
                            'type' => 'string',
                            'description' => '保存するファイル名。省略すると URL の末尾から決める。',
                        ],
                    ],
                    'required' => ['url'],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return null|array<string, mixed>
     */
    public function call(string $name, array $arguments): ?array
    {
        $args = new Arguments($arguments);

        return match ($name) {
            'list_media' => $this->listMedia($args),
            'upload_media_from_url' => $this->upload($args),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function listMedia(Arguments $args): array
    {
        $limit = \max(1, \min(200, $args->int('limit', 50)));

        return ['images' => $this->media->recent($limit)];
    }

    /**
     * @return array<string, mixed>
     */
    private function upload(Arguments $args): array
    {
        $fetched = $this->fetcher->fetch($args->requiredString('url'), $args->string('filename'));

        try {
            // 形式の判定はここ。MediaStorage は Content-Type や拡張子ではなく
            // 中身を見て決め、対応外なら例外を投げる。
            $url = $this->media->store($fetched['path'], $fetched['filename']);
        } catch (RuntimeException $exception) {
            throw new McpToolException($exception->getMessage(), 0, $exception);
        } finally {
            // store() が成功していれば移動済みなので、残っていたときだけ消す。
            if (\is_file($fetched['path'])) {
                @\unlink($fetched['path']);
            }
        }

        return ['url' => $url];
    }
}
