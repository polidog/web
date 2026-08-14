<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\Paginated;
use PDO;
use PDOStatement;
use RuntimeException;

/**
 * 公開側の読み取り専用クエリ。
 *
 * ここだけ tehilim ではなく素の PDO を使う。tehilim の `where` が解釈するのは
 * スカラー演算子と AND/OR/NOT だけで（Query\WhereCompiler）、Prisma の
 * `tags: {some: {slug: ...}}` にあたる**リレーションフィルタが無い**ため、
 * `post.findMany()` 側からタグで絞り込めない。逆にタグ側から `include` すると
 * 今度は `include` が **orderBy を受け付けない**ので「このタグの記事を新しい順に
 * 25 件」が組めない。`terms()` の GROUP BY / HAVING も集約 API が無く書けない。
 * 書き込み側（PostWriter）は connect/set が効くので tehilim を使う —
 * 読みは SQL、書きは tehilim、という住み分け。
 *
 * 公開判定は `status = 'published'` だけで、publishedAt が未来かどうかは
 * 見ない。CDN にキャッシュさせる構成では「時間が来たら勝手に公開される」
 * ものを正しく配信できない（purge の契機が無い）ため、予約投稿は
 * 意図的に持たない。
 *
 * ## 行の型について
 *
 * 各 SELECT が返す形を `@phpstan-type` で宣言し、`fetchAll()` / `fetchOne()` に
 * 渡すマッパ（`postRow()` など）で実際にその形へ組み直している。PDO は列の型を
 * 知らせてこないので、マッパを通さずに shape を名乗ると「宣言だけ正しくて中身は
 * mixed」になり、静的にも実行時にも何も保証されない。SELECT の列を増減したら
 * 対応する型とマッパの両方を直すこと。
 *
 * 日時列が `\DateTimeImmutable` ではなく `string` なのは、SQLite の TEXT が
 * そのまま返るため。tehilim の `PostRowScalar`（src/Tehilim/Model/Post.php）は
 * ここが `\DateTimeImmutable` になっていて **shape が違う**ので、混同しないよう
 * こちらは独自に宣言している。
 *
 * @phpstan-type PostRow array{id: int, kind: string, path: string, title: string, body: string, html: string, excerpt: string|null, eyecatch: string|null, disqusId: string|null, status: string, publishedAt: string|null, createdAt: string, updatedAt: string, authorId: int|null}
 * @phpstan-type PostListRow array{id: int, kind: string, path: string, title: string, excerpt: string|null, eyecatch: string|null, publishedAt: string|null, updatedAt: string}
 * @phpstan-type PostAdminRow array{id: int, kind: string, path: string, title: string, status: string, publishedAt: string|null, updatedAt: string}
 * @phpstan-type ArchiveRow array{id: int, path: string, title: string, publishedAt: string}
 * @phpstan-type NeighbourRow array{id: int, path: string, title: string}
 * @phpstan-type FeedRow array{id: int, path: string, title: string, excerpt: string|null, html: string, publishedAt: string|null}
 * @phpstan-type TermRow array{id: int, name: string, slug: string}
 * @phpstan-type TermCountRow array{id: int, name: string, slug: string, count: int}
 */
final class PostRepository
{
    public const int PER_PAGE = 25;

    /**
     * 月別アーカイブだけ 1 ページ 30 件。Hugo の archives/list.html が
     * サイト既定（25）ではなく 30 でページングしていたので、
     * `/archives/page/N/` の区切りを変えないためにこの値を使う。
     */
    public const int ARCHIVE_PER_PAGE = 30;

    private const string SELECT_LIST =
        'SELECT p.id, p.kind, p.path, p.title, p.excerpt, p.eyecatch, p.publishedAt, p.updatedAt';

    public function __construct(private readonly PDO $pdo) {}

    /**
     * 記事・自由ページを問わず、公開されている 1 件を URL で引く。
     *
     * @return null|PostRow
     */
    public function findPublishedByPath(string $path, string $kind): ?array
    {
        return $this->fetchOne(
            $this->run(
                'SELECT * FROM "Post" WHERE path = :path AND kind = :kind AND status = \'published\' LIMIT 1',
                ['path' => $path, 'kind' => $kind],
            ),
            self::postRow(...),
        );
    }

    /**
     * @return null|PostRow
     */
    public function findById(int $id): ?array
    {
        return $this->fetchOne(
            $this->run('SELECT * FROM "Post" WHERE id = :id LIMIT 1', ['id' => $id]),
            self::postRow(...),
        );
    }

    /**
     * 下書きも含めて URL で引く。保存時の「既存かどうか」判定と、
     * 管理画面のプレビューに使う。
     *
     * @return null|PostRow
     */
    public function findByPathAnyStatus(string $path): ?array
    {
        return $this->fetchOne(
            $this->run('SELECT * FROM "Post" WHERE path = :path LIMIT 1', ['path' => $path]),
            self::postRow(...),
        );
    }

    /**
     * 公開済みの全 URL。移行後の ETag 一括生成に使う。
     *
     * @return list<string>
     */
    public function allPaths(): array
    {
        $paths = [];
        foreach (
            $this->run('SELECT path FROM "Post" WHERE status = \'published\'')
                ->fetchAll(PDO::FETCH_COLUMN) as $path
        ) {
            $paths[] = (string) $path;
        }

        return $paths;
    }

    /**
     * 管理画面の一覧。下書きも含め、検索と種別・状態での絞り込みができる。
     *
     * @return Paginated<PostAdminRow>
     */
    public function listForAdmin(
        string $kind,
        int $page,
        ?string $status = null,
        string $query = '',
        int $perPage = 30,
    ): Paginated {
        $where = 'WHERE p.kind = :kind';
        /** @var array<string, int|string> $params */
        $params = ['kind' => $kind];

        if (null !== $status && '' !== $status) {
            $where .= ' AND p.status = :status';
            $params['status'] = $status;
        }
        if ('' !== $query) {
            $where .= ' AND (p.title LIKE :query OR p.path LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        $total = (int) $this->run('SELECT COUNT(*) FROM "Post" p ' . $where, $params)->fetchColumn();

        $rows = $this->fetchAll(
            $this->run(
                'SELECT p.id, p.kind, p.path, p.title, p.status, p.publishedAt, p.updatedAt FROM "Post" p '
                . $where
                . ' ORDER BY COALESCE(p.publishedAt, p.updatedAt) DESC, p.id DESC LIMIT :limit OFFSET :offset',
                $params + self::window($page, $perPage),
            ),
            self::adminRow(...),
        );

        return new Paginated($rows, $total, $page, $perPage);
    }

    /**
     * ダッシュボードの件数表示。
     *
     * @return array{posts: int, drafts: int, pages: int}
     */
    public function counts(): array
    {
        $row = $this->run(
            'SELECT
               SUM(CASE WHEN kind = \'post\' AND status = \'published\' THEN 1 ELSE 0 END) AS posts,
               SUM(CASE WHEN status = \'draft\' THEN 1 ELSE 0 END) AS drafts,
               SUM(CASE WHEN kind = \'page\' THEN 1 ELSE 0 END) AS pages
             FROM "Post"',
        )->fetch();
        $counts = \is_array($row) ? $row : [];

        return [
            'posts' => (int) ($counts['posts'] ?? 0),
            'drafts' => (int) ($counts['drafts'] ?? 0),
            'pages' => (int) ($counts['pages'] ?? 0),
        ];
    }

    /**
     * @return list<TermRow>
     */
    public function tagsOf(int $postId): array
    {
        return $this->fetchAll(
            $this->run(
                'SELECT t.id, t.name, t.slug FROM "Tag" t
                 JOIN "_PostToTag" pt ON pt."B" = t.id
                 WHERE pt."A" = :id ORDER BY t.name',
                ['id' => $postId],
            ),
            self::termRow(...),
        );
    }

    /**
     * @return list<TermRow>
     */
    public function categoriesOf(int $postId): array
    {
        return $this->fetchAll(
            $this->run(
                'SELECT c.id, c.name, c.slug FROM "Category" c
                 JOIN "_CategoryToPost" cp ON cp."A" = c.id
                 WHERE cp."B" = :id ORDER BY c.name',
                ['id' => $postId],
            ),
            self::termRow(...),
        );
    }

    /**
     * トップと /page/N の一覧。
     *
     * @return Paginated<PostListRow>
     */
    public function listPublished(int $page, int $perPage = self::PER_PAGE): Paginated
    {
        $total = (int) $this->run(
            'SELECT COUNT(*) FROM "Post" WHERE kind = \'post\' AND status = \'published\'',
        )->fetchColumn();

        $rows = $this->fetchAll(
            $this->run(
                self::SELECT_LIST . ' FROM "Post" p
                 WHERE p.kind = \'post\' AND p.status = \'published\'
                 ORDER BY p.publishedAt DESC, p.id DESC
                 LIMIT :limit OFFSET :offset',
                self::window($page, $perPage),
            ),
            self::listRow(...),
        );

        return new Paginated($rows, $total, $page, $perPage);
    }

    /**
     * @return Paginated<PostListRow>
     */
    public function listByTag(string $slug, int $page, int $perPage = self::PER_PAGE): Paginated
    {
        return $this->listByTerm('Tag', '_PostToTag', 'B', 'A', $slug, $page, $perPage);
    }

    /**
     * @return Paginated<PostListRow>
     */
    public function listByCategory(string $slug, int $page, int $perPage = self::PER_PAGE): Paginated
    {
        return $this->listByTerm('Category', '_CategoryToPost', 'A', 'B', $slug, $page, $perPage);
    }

    /**
     * タグ・カテゴリの一覧ページ（/tech-tags など）。記事数付き。
     *
     * @return list<TermCountRow>
     */
    public function terms(string $table): array
    {
        [$joinTable, $termColumn, $postColumn] = 'Tag' === $table
            ? ['_PostToTag', 'B', 'A']
            : ['_CategoryToPost', 'A', 'B'];

        return $this->fetchAll(
            $this->run(
                \sprintf(
                    'SELECT t.id, t.name, t.slug, COUNT(p.id) AS count
                     FROM "%s" t
                     JOIN "%s" j ON j."%s" = t.id
                     JOIN "Post" p ON p.id = j."%s" AND p.status = \'published\' AND p.kind = \'post\'
                     GROUP BY t.id, t.name, t.slug
                     HAVING COUNT(p.id) > 0
                     ORDER BY count DESC, t.name',
                    $table,
                    $joinTable,
                    $termColumn,
                    $postColumn,
                ),
            ),
            self::termCountRow(...),
        );
    }

    /**
     * @return null|TermRow
     */
    public function findTerm(string $table, string $slug): ?array
    {
        return $this->fetchOne(
            $this->run(
                \sprintf(
                    'SELECT id, name, slug FROM "%s" WHERE slug = :slug LIMIT 1',
                    'Tag' === $table ? 'Tag' : 'Category',
                ),
                ['slug' => $slug],
            ),
            self::termRow(...),
        );
    }

    /**
     * 月別アーカイブ（`/archives/` と `/archives/page/N/`）。
     *
     * Hugo は「月の見出しで束ねた記事のリンク一覧」を 30 件ずつページングして
     * いた。束ねるのは 1 ページに載る 30 件の中だけなので、月の区切りは
     * ページをまたぐ（同じ月の見出しが 2 ページに出ることがある）——これも
     * Hugo と同じ挙動。
     *
     * @return Paginated<ArchiveRow>
     */
    public function archive(int $page, int $perPage = self::ARCHIVE_PER_PAGE): Paginated
    {
        $total = (int) $this->run(
            'SELECT COUNT(*) FROM "Post"
             WHERE kind = \'post\' AND status = \'published\' AND publishedAt IS NOT NULL',
        )->fetchColumn();

        $rows = $this->fetchAll(
            $this->run(
                'SELECT p.id, p.path, p.title, p.publishedAt FROM "Post" p
                 WHERE p.kind = \'post\' AND p.status = \'published\' AND p.publishedAt IS NOT NULL
                 ORDER BY p.publishedAt DESC, p.id DESC
                 LIMIT :limit OFFSET :offset',
                self::window($page, $perPage),
            ),
            self::archiveRow(...),
        );

        return new Paginated($rows, $total, $page, $perPage);
    }

    /**
     * 記事詳細の「前の記事 / 次の記事」。
     *
     * @return array{previous: null|NeighbourRow, next: null|NeighbourRow}
     */
    public function neighbours(string $publishedAt, int $id): array
    {
        return [
            'previous' => $this->neighbour($publishedAt, $id, '<', 'DESC'),
            'next' => $this->neighbour($publishedAt, $id, '>', 'ASC'),
        ];
    }

    /**
     * RSS 用。本文 HTML を含める。
     *
     * @return list<FeedRow>
     */
    public function feed(int $limit = 20): array
    {
        return $this->fetchAll(
            $this->run(
                'SELECT id, path, title, excerpt, html, publishedAt FROM "Post"
                 WHERE kind = \'post\' AND status = \'published\'
                 ORDER BY publishedAt DESC, id DESC LIMIT :limit',
                ['limit' => $limit],
            ),
            self::feedRow(...),
        );
    }

    /**
     * ETag のフォールバック値。EtagStore に値が無い（デプロイ直後・
     * キャッシュ破棄後）ときに、コンテンツ全体の版を表す種として使う。
     */
    public function contentVersion(): string
    {
        $row = $this->run(
            'SELECT COUNT(*) AS n, COALESCE(MAX(updatedAt), \'\') AS latest FROM "Post" WHERE status = \'published\'',
        )->fetch();
        $version = \is_array($row) ? $row : [];

        return \sha1(((string) ($version['n'] ?? '0')) . '|' . ((string) ($version['latest'] ?? '')));
    }

    /**
     * @return Paginated<PostListRow>
     */
    private function listByTerm(
        string $termTable,
        string $joinTable,
        string $termColumn,
        string $postColumn,
        string $slug,
        int $page,
        int $perPage,
    ): Paginated {
        $from = \sprintf(
            'FROM "Post" p
             JOIN "%s" j ON j."%s" = p.id
             JOIN "%s" t ON t.id = j."%s"
             WHERE t.slug = :slug AND p.kind = \'post\' AND p.status = \'published\'',
            $joinTable,
            $postColumn,
            $termTable,
            $termColumn,
        );

        $total = (int) $this->run('SELECT COUNT(*) ' . $from, ['slug' => $slug])->fetchColumn();

        $rows = $this->fetchAll(
            $this->run(
                self::SELECT_LIST . ' ' . $from . ' ORDER BY p.publishedAt DESC, p.id DESC LIMIT :limit OFFSET :offset',
                ['slug' => $slug] + self::window($page, $perPage),
            ),
            self::listRow(...),
        );

        return new Paginated($rows, $total, $page, $perPage);
    }

    /**
     * @return null|NeighbourRow
     */
    private function neighbour(string $publishedAt, int $id, string $comparison, string $direction): ?array
    {
        // 同じ publishedAt の記事が複数ある（日付だけの古い記事が該当）ので
        // id を第 2 キーにして順序を一意にする。
        return $this->fetchOne(
            $this->run(
                \sprintf(
                    'SELECT p.id, p.path, p.title FROM "Post" p
                     WHERE p.kind = \'post\' AND p.status = \'published\'
                       AND (p.publishedAt %1$s :at OR (p.publishedAt = :at AND p.id %1$s :id))
                     ORDER BY p.publishedAt %2$s, p.id %2$s LIMIT 1',
                    $comparison,
                    $direction,
                ),
                ['at' => $publishedAt, 'id' => $id],
            ),
            self::neighbourRow(...),
        );
    }

    /**
     * prepare + execute。
     *
     * PDO は ERRMODE_EXCEPTION で組んである（PdoFactory）ので prepare() が
     * false を返すことは実際には無いが、静的解析からは接続属性が見えないので
     * ここで一度だけ潰す。
     *
     * int は PARAM_INT で束ねる。EMULATE_PREPARES を切ってあるため、
     * LIMIT / OFFSET に文字列として渡すと SQLite が構文エラーにする。
     *
     * @param array<string, int|string> $params
     */
    private function run(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        if (false === $statement) {
            throw new RuntimeException('Failed to prepare the statement: ' . $sql);
        }

        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value, \is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->execute();

        return $statement;
    }

    /**
     * @return array{limit: int, offset: int}
     */
    private static function window(int $page, int $perPage): array
    {
        return [
            'limit' => $perPage,
            'offset' => (\max(1, $page) - 1) * $perPage,
        ];
    }

    /**
     * @template TRow of array<string, mixed>
     *
     * @param callable(array<array-key, mixed>): TRow $shape
     *
     * @return list<TRow>
     */
    private function fetchAll(PDOStatement $statement, callable $shape): array
    {
        $rows = [];
        foreach ($statement->fetchAll() as $row) {
            if (\is_array($row)) {
                $rows[] = $shape($row);
            }
        }

        return $rows;
    }

    /**
     * @template TRow of array<string, mixed>
     *
     * @param callable(array<array-key, mixed>): TRow $shape
     *
     * @return null|TRow
     */
    private function fetchOne(PDOStatement $statement, callable $shape): ?array
    {
        $row = $statement->fetch();

        return \is_array($row) ? $shape($row) : null;
    }

    /**
     * @param array<array-key, mixed> $row
     *
     * @return PostRow
     */
    private static function postRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'kind' => (string) $row['kind'],
            'path' => (string) $row['path'],
            'title' => (string) $row['title'],
            'body' => (string) $row['body'],
            'html' => (string) $row['html'],
            'excerpt' => self::nullableString($row['excerpt'] ?? null),
            'eyecatch' => self::nullableString($row['eyecatch'] ?? null),
            'disqusId' => self::nullableString($row['disqusId'] ?? null),
            'status' => (string) $row['status'],
            'publishedAt' => self::nullableString($row['publishedAt'] ?? null),
            'createdAt' => (string) $row['createdAt'],
            'updatedAt' => (string) $row['updatedAt'],
            'authorId' => isset($row['authorId']) ? (int) $row['authorId'] : null,
        ];
    }

    /**
     * @param array<array-key, mixed> $row
     *
     * @return PostListRow
     */
    private static function listRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'kind' => (string) $row['kind'],
            'path' => (string) $row['path'],
            'title' => (string) $row['title'],
            'excerpt' => self::nullableString($row['excerpt'] ?? null),
            'eyecatch' => self::nullableString($row['eyecatch'] ?? null),
            'publishedAt' => self::nullableString($row['publishedAt'] ?? null),
            'updatedAt' => (string) $row['updatedAt'],
        ];
    }

    /**
     * @param array<array-key, mixed> $row
     *
     * @return PostAdminRow
     */
    private static function adminRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'kind' => (string) $row['kind'],
            'path' => (string) $row['path'],
            'title' => (string) $row['title'],
            'status' => (string) $row['status'],
            'publishedAt' => self::nullableString($row['publishedAt'] ?? null),
            'updatedAt' => (string) $row['updatedAt'],
        ];
    }

    /**
     * @param array<array-key, mixed> $row
     *
     * @return ArchiveRow
     */
    private static function archiveRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'path' => (string) $row['path'],
            'title' => (string) $row['title'],
            // publishedAt IS NOT NULL で絞っているので必ず値がある。
            'publishedAt' => (string) $row['publishedAt'],
        ];
    }

    /**
     * @param array<array-key, mixed> $row
     *
     * @return NeighbourRow
     */
    private static function neighbourRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'path' => (string) $row['path'],
            'title' => (string) $row['title'],
        ];
    }

    /**
     * @param array<array-key, mixed> $row
     *
     * @return FeedRow
     */
    private static function feedRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'path' => (string) $row['path'],
            'title' => (string) $row['title'],
            'excerpt' => self::nullableString($row['excerpt'] ?? null),
            'html' => (string) $row['html'],
            'publishedAt' => self::nullableString($row['publishedAt'] ?? null),
        ];
    }

    /**
     * @param array<array-key, mixed> $row
     *
     * @return TermRow
     */
    private static function termRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
        ];
    }

    /**
     * @param array<array-key, mixed> $row
     *
     * @return TermCountRow
     */
    private static function termCountRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
            'count' => (int) $row['count'],
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        return null === $value ? null : (string) $value;
    }
}
