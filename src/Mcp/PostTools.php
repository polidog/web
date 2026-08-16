<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Auth\AdminUserProvider;
use App\Service\PostFormMapper;
use App\Service\PostRepository;
use App\Service\PostWriter;
use App\Support\PostInput;

/**
 * 記事まわりの MCP ツール。
 *
 * 書き込みは自前で組み立てず、**管理画面と同じ PostFormMapper → PostWriter を
 * 通す**。こうしておくと path の形式チェックも status の値も publishedAt の
 * 解釈も 1 か所で決まり、「管理画面からは弾かれるのに MCP からは通る」が
 * 起きない。保存に伴う 3 つの副作用（Markdown のレンダリング・ETag の更新・
 * Cloudflare の purge）も PostWriter の中で完結する。
 *
 * @phpstan-import-type PostRow from PostRepository
 */
final readonly class PostTools
{
    public function __construct(
        private PostRepository $posts,
        private PostWriter $writer,
        private PostFormMapper $mapper,
        private AdminUserProvider $admin,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function definitions(): array
    {
        $writable = [
            'title' => ['type' => 'string', 'description' => '記事のタイトル。'],
            'body' => ['type' => 'string', 'description' => 'Markdown の本文。'],
            'status' => [
                'type' => 'string',
                'enum' => ['draft', 'published'],
                'description' => 'draft は下書き（サイトには出ない）、published で公開。',
            ],
            'publishedAt' => [
                'type' => 'string',
                'description' => '公開日時。ISO 8601（例 2026-08-16T10:00:00+09:00）。'
                    . '省略して published にすると、その時刻が公開日になる。',
            ],
            'eyecatch' => ['type' => 'string', 'description' => 'アイキャッチ画像の URL（/images/... 形式）。'],
            'tags' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'タグ名の配列。既存のタグ名は list_tags で確認できる。',
            ],
            'categories' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'カテゴリ名の配列。',
            ],
        ];

        return [
            [
                'name' => 'list_posts',
                'description' => '記事や固定ページを新しい順に一覧する。下書きも含む。'
                    . '本文は含まないので、中身が要るときは get_post を使う。',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'kind' => [
                            'type' => 'string',
                            'enum' => ['post', 'page'],
                            'description' => 'post はブログ記事、page は固定ページ。既定は post。',
                        ],
                        'status' => [
                            'type' => 'string',
                            'enum' => ['draft', 'published'],
                            'description' => '省略すると両方を返す。',
                        ],
                        'query' => ['type' => 'string', 'description' => 'タイトルと URL に対する部分一致検索。'],
                        'page' => ['type' => 'integer', 'description' => 'ページ番号（1 から）。既定は 1。'],
                    ],
                ],
            ],
            [
                'name' => 'get_post',
                'description' => '記事 1 件を本文つきで取得する。id か path のどちらかを指定する。'
                    . '更新や削除の前には必ずこれで対象を確かめること。',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'description' => '記事 ID。'],
                        'path' => ['type' => 'string', 'description' => 'URL のパス（例 /2026/08/16/hello/）。'],
                    ],
                ],
            ],
            [
                'name' => 'create_post',
                'description' => '記事や固定ページを新しく作る。path は既存の記事と重複できない。'
                    . 'ブログ記事の URL は /YYYY/MM/DD/スラッグ/ の形が慣例だが、指定した path が'
                    . 'そのまま URL になるので、勝手に組み替えないこと。',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'path' => [
                            'type' => 'string',
                            'description' => 'URL のパス。スラッシュで始める（例 /2026/08/16/hello/）。',
                        ],
                        'kind' => ['type' => 'string', 'enum' => ['post', 'page'], 'description' => '既定は post。'],
                    ] + $writable,
                    'required' => ['path', 'title', 'body'],
                ],
            ],
            [
                'name' => 'update_post',
                'description' => '既存の記事を更新する。**渡したフィールドだけが変わり**、'
                    . '省略したものは今の値のまま残る。path を変えると URL が変わり、'
                    . '古い URL のキャッシュは自動で捨てられる。',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'description' => '更新する記事の ID。'],
                        'path' => ['type' => 'string', 'description' => 'URL のパス。変えると URL が変わる。'],
                    ] + $writable,
                    'required' => ['id'],
                ],
            ],
            [
                'name' => 'delete_post',
                'description' => '記事を完全に削除する。**元に戻せない。**'
                    . 'サイトから隠したいだけなら unpublish_post を使うこと。'
                    . '取り違え防止のため、get_post で確認した path を confirm_path に渡す必要がある。',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'description' => '削除する記事の ID。'],
                        'confirm_path' => [
                            'type' => 'string',
                            'description' => 'その記事の path。id と一致しなければ削除しない。',
                        ],
                    ],
                    'required' => ['id', 'confirm_path'],
                ],
            ],
            [
                'name' => 'publish_post',
                'description' => '記事を公開する。本文には触れない。公開日が未設定なら今の時刻が入る。',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['id' => ['type' => 'integer']],
                    'required' => ['id'],
                ],
            ],
            [
                'name' => 'unpublish_post',
                'description' => '記事を下書きに戻してサイトから隠す。本文と公開日は残るので、'
                    . 'publish_post でそのまま戻せる。',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['id' => ['type' => 'integer']],
                    'required' => ['id'],
                ],
            ],
            [
                'name' => 'list_tags',
                'description' => '公開記事で使われているタグ（またはカテゴリ）を件数つきで一覧する。'
                    . '記事にタグを付ける前に、既存の表記を確かめるために使う。',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'kind' => [
                            'type' => 'string',
                            'enum' => ['tags', 'categories'],
                            'description' => '既定は tags。',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return null|array<string, mixed> このクラスが扱わないツールなら null
     */
    public function call(string $name, array $arguments): ?array
    {
        $args = new Arguments($arguments);

        return match ($name) {
            'list_posts' => $this->listPosts($args),
            'get_post' => $this->getPost($args),
            'create_post' => $this->createPost($args),
            'update_post' => $this->updatePost($args),
            'delete_post' => $this->deletePost($args),
            'publish_post' => $this->setStatus($args, 'published'),
            'unpublish_post' => $this->setStatus($args, 'draft'),
            'list_tags' => $this->listTags($args),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function listPosts(Arguments $args): array
    {
        $status = $args->has('status') ? $args->enum('status', ['draft', 'published'], 'draft') : null;

        $list = $this->posts->listForAdmin(
            $args->enum('kind', ['post', 'page'], 'post'),
            \max(1, $args->int('page', 1)),
            $status,
            $args->string('query'),
        );

        return [
            'total' => $list->total,
            'page' => $list->page,
            'pages' => $list->pages(),
            'posts' => $list->items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getPost(Arguments $args): array
    {
        $post = $this->resolve($args);

        return $this->describe($post, withBody: true);
    }

    /**
     * @return array<string, mixed>
     */
    private function createPost(Arguments $args): array
    {
        $path = $args->requiredString('path');

        // PostWriter::save() は id 無しで呼ぶと path 一致の行を黙って上書きする。
        // 「新規作成」のつもりの操作で既存記事が消えないよう、ここで止める。
        // 引き当てには正規化した形を使う（/foo と /foo/ は同じ記事）が、
        // **mapper に渡すのは正規化前の値**。ここで先に整えてしまうと
        // 「スラッシュで始まっていない」が管理画面では弾かれるのに
        // MCP からは通る、という食い違いができる。
        if (null !== $this->posts->findByPathAnyStatus(PostInput::normalizePath($path))) {
            throw new McpToolException(\sprintf(
                '%s は既に使われています。既存の記事を直すなら update_post を使ってください。',
                $path,
            ));
        }

        $form = [
            'path' => $path,
            'title' => $args->requiredString('title'),
            'body' => $args->requiredString('body'),
            'status' => $args->enum('status', ['draft', 'published'], 'draft'),
            'publishedAt' => $args->string('publishedAt'),
            'eyecatch' => $args->string('eyecatch'),
            'tags' => \implode(',', $args->stringList('tags') ?? []),
            'categories' => \implode(',', $args->stringList('categories') ?? []),
        ];

        $saved = $this->save($form, $args->enum('kind', ['post', 'page'], 'post'), null);

        return ['created' => true] + $saved;
    }

    /**
     * @return array<string, mixed>
     */
    private function updatePost(Arguments $args): array
    {
        $id = $args->requiredInt('id');
        $post = $this->posts->findById($id);

        if (null === $post) {
            throw new McpToolException(\sprintf('ID %d の記事が見つかりません。', $id));
        }

        // 渡されなかったフィールドは今の値で埋める。PostWriter::save() は
        // PostInput の中身で行を丸ごと上書きするので、埋めずに渡すと
        // 触っていない項目まで空になる。
        $currentTags = \array_column($this->posts->tagsOf($id), 'name');
        $currentCategories = \array_column($this->posts->categoriesOf($id), 'name');

        $form = [
            'path' => $args->has('path') ? $args->requiredString('path') : $post['path'],
            'title' => $args->has('title') ? $args->requiredString('title') : $post['title'],
            'body' => $args->has('body') ? $args->string('body') : $post['body'],
            'status' => $args->has('status')
                ? $args->enum('status', ['draft', 'published'], 'draft')
                : $post['status'],
            'publishedAt' => $args->has('publishedAt')
                ? $args->string('publishedAt')
                : (string) ($post['publishedAt'] ?? ''),
            'eyecatch' => $args->has('eyecatch') ? $args->string('eyecatch') : (string) ($post['eyecatch'] ?? ''),
            'tags' => \implode(',', $args->stringList('tags') ?? $currentTags),
            'categories' => \implode(',', $args->stringList('categories') ?? $currentCategories),
        ];

        $saved = $this->save($form, $post['kind'], $id);

        return ['updated' => true] + $saved;
    }

    /**
     * @return array<string, mixed>
     */
    private function deletePost(Arguments $args): array
    {
        $id = $args->requiredInt('id');
        $post = $this->posts->findById($id);

        if (null === $post) {
            throw new McpToolException(\sprintf('ID %d の記事が見つかりません。', $id));
        }

        // id だけで消せるようにすると、番号を 1 つ取り違えただけで
        // 別の記事が消える。path の一致を要求して、削除の前に必ず
        // 対象を 1 度読ませる。
        $confirm = PostInput::normalizePath($args->requiredString('confirm_path'));
        if ($confirm !== $post['path']) {
            throw new McpToolException(\sprintf(
                'confirm_path が一致しません（ID %d の path は %s です）。get_post で確認してください。',
                $id,
                $post['path'],
            ));
        }

        $this->writer->delete($id);

        return ['deleted' => true, 'id' => $id, 'path' => $post['path'], 'title' => $post['title']];
    }

    /**
     * @return array<string, mixed>
     */
    private function setStatus(Arguments $args, string $status): array
    {
        $id = $args->requiredInt('id');
        $post = $this->posts->findById($id);

        if (null === $post) {
            throw new McpToolException(\sprintf('ID %d の記事が見つかりません。', $id));
        }

        $this->writer->setStatus($id, $status);

        $updated = $this->posts->findById($id);

        return [
            'id' => $id,
            'path' => $post['path'],
            'title' => $post['title'],
            'status' => $status,
            'publishedAt' => null !== $updated ? $updated['publishedAt'] : null,
            'url' => $post['path'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function listTags(Arguments $args): array
    {
        $kind = $args->enum('kind', ['tags', 'categories'], 'tags');

        return [
            'kind' => $kind,
            'terms' => $this->posts->terms('tags' === $kind ? 'Tag' : 'Category'),
        ];
    }

    /**
     * 管理画面と同じ検証・保存の経路。
     *
     * @param array<string, mixed> $form
     *
     * @return array<string, mixed>
     */
    private function save(array $form, string $kind, ?int $id): array
    {
        $mapped = $this->mapper->map($form, $kind, $this->admin->adminId());

        if (null === $mapped['input']) {
            throw new McpToolException(\implode(' ', \array_values($mapped['errors'])));
        }

        $post = $this->writer->save($mapped['input'], $id);
        $savedId = \is_numeric($post['id'] ?? null) ? (int) $post['id'] : $id;

        if (null === $savedId) {
            throw new McpToolException('保存はできましたが ID を取得できませんでした。');
        }

        $fresh = $this->posts->findById($savedId);
        if (null === $fresh) {
            throw new McpToolException('保存した記事を読み直せませんでした。');
        }

        return $this->describe($fresh, withBody: false);
    }

    /**
     * id か path で 1 件引く。
     *
     * @return PostRow
     */
    private function resolve(Arguments $args): array
    {
        if ($args->has('id')) {
            $id = $args->requiredInt('id');
            $post = $this->posts->findById($id);

            if (null === $post) {
                throw new McpToolException(\sprintf('ID %d の記事が見つかりません。', $id));
            }

            return $post;
        }

        if ($args->has('path')) {
            $path = PostInput::normalizePath($args->requiredString('path'));
            $post = $this->posts->findByPathAnyStatus($path);

            if (null === $post) {
                throw new McpToolException(\sprintf('%s の記事が見つかりません。', $path));
            }

            return $post;
        }

        throw new McpToolException('id か path のどちらかを指定してください。');
    }

    /**
     * @param PostRow $post
     *
     * @return array<string, mixed>
     */
    private function describe(array $post, bool $withBody): array
    {
        $described = [
            'id' => $post['id'],
            'kind' => $post['kind'],
            'path' => $post['path'],
            'title' => $post['title'],
            'status' => $post['status'],
            'publishedAt' => $post['publishedAt'],
            'updatedAt' => $post['updatedAt'],
            'eyecatch' => $post['eyecatch'],
            'tags' => \array_column($this->posts->tagsOf($post['id']), 'name'),
            'categories' => \array_column($this->posts->categoriesOf($post['id']), 'name'),
        ];

        if ($withBody) {
            $described['body'] = $post['body'];
        }

        return $described;
    }
}
