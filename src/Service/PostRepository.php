<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\Paginated;
use PDO;

/**
 * 公開側の読み取り専用クエリ。
 *
 * ここだけ tehilim ではなく素の PDO を使う。tehilim の `include` は
 * where/take/skip は取れるが **orderBy を受け付けない** ので、
 * 「このタグの記事を新しい順に 25 件」のような M2M + ソート + ページングが
 * 1 クエリで書けないため。書き込み側（PostWriter）は connect/set が
 * 効くので tehilim を使う — 読みは SQL、書きは tehilim、という住み分け。
 *
 * 公開判定は `status = 'published'` だけで、publishedAt が未来かどうかは
 * 見ない。CDN にキャッシュさせる構成では「時間が来たら勝手に公開される」
 * ものを正しく配信できない（purge の契機が無い）ため、予約投稿は
 * 意図的に持たない。
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
     * @return null|array<string, mixed>
     */
    public function findPublishedByPath(string $path, string $kind): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM "Post" WHERE path = :path AND kind = :kind AND status = \'published\' LIMIT 1',
        );
        $statement->execute(['path' => $path, 'kind' => $kind]);
        $row = $statement->fetch();

        return false === $row ? null : $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM "Post" WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return false === $row ? null : $row;
    }

    /**
     * 下書きも含めて URL で引く。保存時の「既存かどうか」判定と、
     * 管理画面のプレビューに使う。
     *
     * @return null|array<string, mixed>
     */
    public function findByPathAnyStatus(string $path): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM "Post" WHERE path = :path LIMIT 1');
        $statement->execute(['path' => $path]);
        $row = $statement->fetch();

        return false === $row ? null : $row;
    }

    /**
     * 公開済みの全 URL。移行後の ETag 一括生成に使う。
     *
     * @return list<string>
     */
    public function allPaths(): array
    {
        /** @var list<string> */
        return $this->pdo
            ->query('SELECT path FROM "Post" WHERE status = \'published\'')
            ->fetchAll(PDO::FETCH_COLUMN)
        ;
    }

    /**
     * 管理画面の一覧。下書きも含め、検索と種別・状態での絞り込みができる。
     *
     * @return Paginated<array<string, mixed>>
     */
    public function listForAdmin(
        string $kind,
        int $page,
        ?string $status = null,
        string $query = '',
        int $perPage = 30,
    ): Paginated {
        $where = 'WHERE p.kind = :kind';
        $params = ['kind' => $kind];

        if (null !== $status && '' !== $status) {
            $where .= ' AND p.status = :status';
            $params['status'] = $status;
        }
        if ('' !== $query) {
            $where .= ' AND (p.title LIKE :query OR p.path LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        $countStatement = $this->pdo->prepare('SELECT COUNT(*) FROM "Post" p ' . $where);
        $countStatement->execute($params);
        $total = (int) $countStatement->fetchColumn();

        $statement = $this->pdo->prepare(
            'SELECT p.id, p.kind, p.path, p.title, p.status, p.publishedAt, p.updatedAt FROM "Post" p '
            . $where
            . ' ORDER BY COALESCE(p.publishedAt, p.updatedAt) DESC, p.id DESC LIMIT :limit OFFSET :offset',
        );
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $this->bindWindow($statement, $page, $perPage);
        $statement->execute();

        return new Paginated($statement->fetchAll(), $total, $page, $perPage);
    }

    /**
     * ダッシュボードの件数表示。
     *
     * @return array{posts: int, drafts: int, pages: int}
     */
    public function counts(): array
    {
        $row = $this->pdo->query(
            'SELECT
               SUM(CASE WHEN kind = \'post\' AND status = \'published\' THEN 1 ELSE 0 END) AS posts,
               SUM(CASE WHEN status = \'draft\' THEN 1 ELSE 0 END) AS drafts,
               SUM(CASE WHEN kind = \'page\' THEN 1 ELSE 0 END) AS pages
             FROM "Post"',
        )->fetch();

        return [
            'posts' => (int) ($row['posts'] ?? 0),
            'drafts' => (int) ($row['drafts'] ?? 0),
            'pages' => (int) ($row['pages'] ?? 0),
        ];
    }

    /**
     * @return list<array{id: int, name: string, slug: string}>
     */
    public function tagsOf(int $postId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT t.id, t.name, t.slug FROM "Tag" t
             JOIN "_PostToTag" pt ON pt."B" = t.id
             WHERE pt."A" = :id ORDER BY t.name',
        );
        $statement->execute(['id' => $postId]);

        return $statement->fetchAll();
    }

    /**
     * @return list<array{id: int, name: string, slug: string}>
     */
    public function categoriesOf(int $postId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT c.id, c.name, c.slug FROM "Category" c
             JOIN "_CategoryToPost" cp ON cp."A" = c.id
             WHERE cp."B" = :id ORDER BY c.name',
        );
        $statement->execute(['id' => $postId]);

        return $statement->fetchAll();
    }

    /**
     * トップと /page/N の一覧。
     *
     * @return Paginated<array<string, mixed>>
     */
    public function listPublished(int $page, int $perPage = self::PER_PAGE): Paginated
    {
        $total = (int) $this->pdo->query(
            'SELECT COUNT(*) FROM "Post" WHERE kind = \'post\' AND status = \'published\'',
        )->fetchColumn();

        $statement = $this->pdo->prepare(
            self::SELECT_LIST . ' FROM "Post" p
             WHERE p.kind = \'post\' AND p.status = \'published\'
             ORDER BY p.publishedAt DESC, p.id DESC
             LIMIT :limit OFFSET :offset',
        );
        $this->bindWindow($statement, $page, $perPage);
        $statement->execute();

        return new Paginated($statement->fetchAll(), $total, $page, $perPage);
    }

    /**
     * @return Paginated<array<string, mixed>>
     */
    public function listByTag(string $slug, int $page, int $perPage = self::PER_PAGE): Paginated
    {
        return $this->listByTerm('Tag', '_PostToTag', 'B', 'A', $slug, $page, $perPage);
    }

    /**
     * @return Paginated<array<string, mixed>>
     */
    public function listByCategory(string $slug, int $page, int $perPage = self::PER_PAGE): Paginated
    {
        return $this->listByTerm('Category', '_CategoryToPost', 'A', 'B', $slug, $page, $perPage);
    }

    /**
     * タグ・カテゴリの一覧ページ（/tech-tags など）。記事数付き。
     *
     * @return list<array{id: int, name: string, slug: string, count: int}>
     */
    public function terms(string $table): array
    {
        [$joinTable, $termColumn, $postColumn] = 'Tag' === $table
            ? ['_PostToTag', 'B', 'A']
            : ['_CategoryToPost', 'A', 'B'];

        return $this->pdo->query(
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
        )->fetchAll();
    }

    /**
     * @return null|array{id: int, name: string, slug: string}
     */
    public function findTerm(string $table, string $slug): ?array
    {
        $statement = $this->pdo->prepare(
            \sprintf('SELECT id, name, slug FROM "%s" WHERE slug = :slug LIMIT 1', 'Tag' === $table ? 'Tag' : 'Category'),
        );
        $statement->execute(['slug' => $slug]);
        $row = $statement->fetch();

        return false === $row ? null : $row;
    }

    /**
     * 月別アーカイブ（`/archives/` と `/archives/page/N/`）。
     *
     * Hugo は「月の見出しで束ねた記事のリンク一覧」を 30 件ずつページングして
     * いた。束ねるのは 1 ページに載る 30 件の中だけなので、月の区切りは
     * ページをまたぐ（同じ月の見出しが 2 ページに出ることがある）——これも
     * Hugo と同じ挙動。
     *
     * @return Paginated<array<string, mixed>>
     */
    public function archive(int $page, int $perPage = self::ARCHIVE_PER_PAGE): Paginated
    {
        $total = (int) $this->pdo->query(
            'SELECT COUNT(*) FROM "Post"
             WHERE kind = \'post\' AND status = \'published\' AND publishedAt IS NOT NULL',
        )->fetchColumn();

        $statement = $this->pdo->prepare(
            'SELECT p.id, p.path, p.title, p.publishedAt FROM "Post" p
             WHERE p.kind = \'post\' AND p.status = \'published\' AND p.publishedAt IS NOT NULL
             ORDER BY p.publishedAt DESC, p.id DESC
             LIMIT :limit OFFSET :offset',
        );
        $this->bindWindow($statement, $page, $perPage);
        $statement->execute();

        return new Paginated($statement->fetchAll(), $total, $page, $perPage);
    }

    /**
     * 記事詳細の「前の記事 / 次の記事」。
     *
     * @return array{previous: null|array<string, mixed>, next: null|array<string, mixed>}
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
     * @return list<array<string, mixed>>
     */
    public function feed(int $limit = 20): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, path, title, excerpt, html, publishedAt FROM "Post"
             WHERE kind = \'post\' AND status = \'published\'
             ORDER BY publishedAt DESC, id DESC LIMIT :limit',
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * ETag のフォールバック値。EtagStore に値が無い（デプロイ直後・
     * キャッシュ破棄後）ときに、コンテンツ全体の版を表す種として使う。
     */
    public function contentVersion(): string
    {
        $row = $this->pdo->query(
            'SELECT COUNT(*) AS n, COALESCE(MAX(updatedAt), \'\') AS latest FROM "Post" WHERE status = \'published\'',
        )->fetch();

        return \sha1(($row['n'] ?? '0') . '|' . ($row['latest'] ?? ''));
    }

    /**
     * @return Paginated<array<string, mixed>>
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

        $countStatement = $this->pdo->prepare('SELECT COUNT(*) ' . $from);
        $countStatement->execute(['slug' => $slug]);
        $total = (int) $countStatement->fetchColumn();

        $statement = $this->pdo->prepare(
            self::SELECT_LIST . ' ' . $from . ' ORDER BY p.publishedAt DESC, p.id DESC LIMIT :limit OFFSET :offset',
        );
        $statement->bindValue('slug', $slug);
        $this->bindWindow($statement, $page, $perPage);
        $statement->execute();

        return new Paginated($statement->fetchAll(), $total, $page, $perPage);
    }

    /**
     * @return null|array<string, mixed>
     */
    private function neighbour(string $publishedAt, int $id, string $comparison, string $direction): ?array
    {
        // 同じ publishedAt の記事が複数ある（日付だけの古い記事が該当）ので
        // id を第 2 キーにして順序を一意にする。
        $statement = $this->pdo->prepare(
            \sprintf(
                'SELECT p.id, p.path, p.title FROM "Post" p
                 WHERE p.kind = \'post\' AND p.status = \'published\'
                   AND (p.publishedAt %1$s :at OR (p.publishedAt = :at AND p.id %1$s :id))
                 ORDER BY p.publishedAt %2$s, p.id %2$s LIMIT 1',
                $comparison,
                $direction,
            ),
        );
        $statement->execute(['at' => $publishedAt, 'id' => $id]);
        $row = $statement->fetch();

        return false === $row ? null : $row;
    }

    private function bindWindow(\PDOStatement $statement, int $page, int $perPage): void
    {
        $statement->bindValue('limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue('offset', (\max(1, $page) - 1) * $perPage, PDO::PARAM_INT);
    }
}
