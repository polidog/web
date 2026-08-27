<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\HugoSlug;
use App\Support\PageCache;
use App\Support\PostInput;
use App\Tehilim\TehilimClient;
use DateTimeImmutable;
use Polidog\Relayer\Http\EtagStore;

/**
 * 記事・ページを保存する唯一の入口。管理画面も移行スクリプトもここを通る。
 *
 * 「保存する」に付随する 3 つの副作用——Markdown のレンダリング、
 * ETag の更新、Cloudflare の purge——を呼び出し側に散らさないための
 * クラス。どれか 1 つでも欠けるとキャッシュが古いまま残る。
 *
 * 読み取りは PostRepository（生 SQL）だが、ここは tehilim を使う。
 * タグ・カテゴリの張り替えが `set` 一発で書けるため。
 */
final class PostWriter
{
    /**
     * バルク投入中は 1 件ごとの無効化を止める。1301 件の移行で毎回
     * contentVersion() を数え直し、URL を 1 本ずつ purge するのは無駄。
     */
    private bool $deferInvalidation = false;

    public function __construct(
        private readonly TehilimClient $db,
        private readonly PostRepository $posts,
        private readonly MarkdownRenderer $markdown,
        private readonly EtagStore $etags,
        private readonly CachePurger $purger,
    ) {}

    /**
     * 移行スクリプト用。呼んだあとは最後に refreshAllCaches() を実行すること。
     */
    public function deferInvalidation(): void
    {
        $this->deferInvalidation = true;
    }

    /**
     * @return array<string, mixed> 保存後の行
     */
    public function save(PostInput $input, ?int $id = null): array
    {
        $path = PostInput::normalizePath($input->path);
        $html = $this->markdown->render($input->body);
        $now = new DateTimeImmutable();

        $previous = null !== $id
            ? $this->posts->findById($id)
            : $this->posts->findByPathAnyStatus($path);

        $publishedAt = $input->publishedAt;
        if (null === $publishedAt && $input->isPublished()) {
            // 公開に切り替えた瞬間を公開日にする。すでに日付があるものは
            // 触らない（移行した過去記事の日付を上書きしないため）。
            $publishedAt = null !== $previous && null !== $previous['publishedAt']
                ? new DateTimeImmutable((string) $previous['publishedAt'])
                : $now;
        }

        $data = [
            'kind' => $input->kind,
            'path' => $path,
            'title' => $input->title,
            'body' => $input->body,
            'html' => $html,
            'excerpt' => $this->markdown->excerpt($html),
            'eyecatch' => $input->eyecatch,
            'status' => $input->status,
            'publishedAt' => $publishedAt,
            'updatedAt' => $now,
            'authorId' => $input->authorId,
        ];

        // 移行スクリプトだけが値を持つ。管理画面からの保存で null が来ても
        // 既存の識別子を消さない（消すと過去のコメントが記事から外れる）。
        if (null !== $input->disqusId) {
            $data['disqusId'] = $input->disqusId;
        }

        $tags = $this->termRefs('tag', $input->tags);
        $categories = $this->termRefs('category', $input->categories);

        if (null !== $previous) {
            $post = $this->db->post->update([
                'where' => ['id' => (int) $previous['id']],
                'data' => $data + [
                    'tags' => ['set' => $tags],
                    'categories' => ['set' => $categories],
                ],
            ]);
        } else {
            $post = $this->db->post->insert([
                'data' => $data + [
                    'createdAt' => $now,
                    'tags' => ['connect' => $tags],
                    'categories' => ['connect' => $categories],
                ],
            ]);
        }

        $this->invalidate($path, null !== $previous ? (string) $previous['path'] : null);

        return $post;
    }

    public function delete(int $id): void
    {
        $post = $this->posts->findById($id);
        if (null === $post) {
            return;
        }

        // join テーブルの行を先に落とす。FK が ON なので残っていると消せない。
        $this->db->post->update([
            'where' => ['id' => $id],
            'data' => ['tags' => ['set' => []], 'categories' => ['set' => []]],
        ]);
        $this->db->post->delete(['where' => ['id' => $id]]);

        $this->etags->forget(PageCache::etagKey((string) $post['path']));
        $this->invalidate(null, (string) $post['path']);
    }

    /**
     * 記事本文を触らない公開状態の切り替え。一覧から 1 クリックで
     * 下書きに戻せるようにするためのショートカット。
     */
    public function setStatus(int $id, string $status): void
    {
        $post = $this->posts->findById($id);
        if (null === $post) {
            return;
        }

        $this->applyStatus($id, $post['publishedAt'], $status, new DateTimeImmutable());
        $this->invalidate((string) $post['path'], null);
    }

    /**
     * 一覧で選んだ複数記事の公開状態をまとめて切り替える。
     *
     * setStatus() を回すのではなく専用に書いてあるのは無効化のため ——
     * 1 件ごとに contentVersion() を数え直して purge を投げると、25 件
     * 選んだだけで Cloudflare の API を 25 往復する。ここでは版を 1 度
     * 数え、URL をまとめて 1 回で捨てる。
     *
     * @param list<int> $ids
     *
     * @return int 実際に変わった件数。存在しない id と、すでにその状態
     *             だったものは数えない
     */
    public function setStatusMany(array $ids, string $status): int
    {
        $now = new DateTimeImmutable();
        $changed = [];

        foreach (\array_unique($ids) as $id) {
            $post = $this->posts->findById($id);
            if (null === $post || $status === $post['status']) {
                continue;
            }

            $this->applyStatus($id, $post['publishedAt'], $status, $now);
            $changed[] = (string) $post['path'];
        }

        if ([] !== $changed) {
            $this->invalidatePaths($changed, []);
        }

        return \count($changed);
    }

    /**
     * 状態だけを書き換える。本文には触らない。
     *
     * 公開に切り替えるときだけ publishedAt を今にするが、すでに日付が
     * あるものは触らない（下書きに戻して公開し直しても、元の公開日が
     * 残る —— 日付が URL を決めるので、変わると記事の住所が変わる）。
     */
    private function applyStatus(int $id, ?string $publishedAt, string $status, DateTimeImmutable $now): void
    {
        $this->db->post->update([
            'where' => ['id' => $id],
            'data' => [
                'status' => $status,
                'updatedAt' => $now,
                'publishedAt' => 'published' === $status && null === $publishedAt
                    ? $now
                    : (null !== $publishedAt ? new DateTimeImmutable($publishedAt) : null),
            ],
        ]);
    }

    /**
     * 移行スクリプト用。1301 件を 1 件ずつ purge しても意味がないので、
     * バルク投入中は無効化を止め、最後に一度だけまとめて反映する。
     */
    public function refreshAllCaches(): void
    {
        $version = $this->posts->contentVersion();
        $this->etags->set('content-version', $version);

        foreach ($this->posts->allPaths() as $path) {
            $this->etags->set(PageCache::etagKey($path), $this->stamp($path, $version));
        }

        $this->purger->purgeEverything();
    }

    /**
     * 変更のあった URL の ETag を差し替え、エッジから捨てる。
     */
    private function invalidate(?string $path, ?string $previousPath): void
    {
        $this->invalidatePaths(
            null !== $path ? [$path] : [],
            // URL を変えた（またはページごと消した）なら、旧 URL の
            // キャッシュを残すと 404 になるべきページが生き続ける。
            null !== $previousPath && $previousPath !== $path ? [$previousPath] : [],
        );
    }

    /**
     * @param list<string> $changed 中身が変わった URL
     * @param list<string> $removed 無くなった URL
     */
    private function invalidatePaths(array $changed, array $removed): void
    {
        if ($this->deferInvalidation) {
            return;
        }

        $version = $this->posts->contentVersion();
        $this->etags->set('content-version', $version);

        $paths = ['/', '/index.xml'];

        foreach ($changed as $path) {
            $this->etags->set(PageCache::etagKey($path), $this->stamp($path, $version));
            $paths[] = $path;
        }

        foreach ($removed as $path) {
            $this->etags->forget(PageCache::etagKey($path));
            $paths[] = $path;
        }

        $this->purger->purge(\array_values(\array_unique($paths)));
    }

    private function stamp(string $path, string $version): string
    {
        return \substr(\sha1($path . '|' . $version), 0, 16);
    }

    /**
     * タグ・カテゴリ名を受け取り、無ければ作って WhereUnique のリストを返す。
     *
     * @param 'category'|'tag' $kind
     * @param list<string>     $names
     *
     * @return list<array{id: int}>
     */
    private function termRefs(string $kind, array $names): array
    {
        $refs = [];
        $seen = [];

        foreach ($names as $name) {
            $name = \trim($name);
            if ('' === $name) {
                continue;
            }

            $slug = HugoSlug::urlize($name);
            if ('' === $slug || isset($seen[$slug])) {
                continue;
            }
            $seen[$slug] = true;

            // 引き当ては name ではなく slug で行う。`JavaScript` と
            // `javascript` は表示名こそ違うが同じ URL を指すので、
            // 同じ 1 件に集約されるのが正しい（Hugo もそうしていた）。
            // 先に登録された表示名を残す。
            $client = 'tag' === $kind ? $this->db->tag : $this->db->category;
            $row = $client->upsert([
                'where' => ['slug' => $slug],
                'insert' => ['name' => $name, 'slug' => $slug],
                'update' => ['slug' => $slug],
            ]);

            $refs[] = ['id' => (int) $row['id']];
        }

        return $refs;
    }
}
