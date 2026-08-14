<?php

declare(strict_types=1);

namespace App\Tehilim\Model;

use Polidog\Tehilim\Client\BaseModelClient;
use Polidog\Tehilim\Client\Relation;

/**
 * @phpstan-import-type UserRowScalar from \App\Tehilim\Model\User
 * @phpstan-import-type UserWhereUnique from \App\Tehilim\Model\User
 * @phpstan-import-type TagRowScalar from \App\Tehilim\Model\Tag
 * @phpstan-import-type TagWhereUnique from \App\Tehilim\Model\Tag
 * @phpstan-import-type CategoryRowScalar from \App\Tehilim\Model\Category
 * @phpstan-import-type CategoryWhereUnique from \App\Tehilim\Model\Category
 * @phpstan-type PostRowScalar array{id: int, kind: string, path: string, title: string, body: string, html: string, excerpt: string|null, eyecatch: string|null, disqusId: string|null, status: string, publishedAt: \DateTimeImmutable|null, createdAt: \DateTimeImmutable, updatedAt: \DateTimeImmutable, authorId: int|null}
 * @phpstan-type PostRow array{id: int, kind: string, path: string, title: string, body: string, html: string, excerpt: string|null, eyecatch: string|null, disqusId: string|null, status: string, publishedAt: \DateTimeImmutable|null, createdAt: \DateTimeImmutable, updatedAt: \DateTimeImmutable, authorId: int|null, author?: UserRowScalar|null, tags?: list<TagRowScalar>, categories?: list<CategoryRowScalar>}
 * @phpstan-type PostInsertInput array{id?: int, kind?: string, path: string, title: string, body: string, html: string, excerpt?: string|null, eyecatch?: string|null, disqusId?: string|null, status?: string, publishedAt?: \DateTimeImmutable|null, createdAt?: \DateTimeImmutable, updatedAt?: \DateTimeImmutable, authorId?: int|null, tags?: array{connect?: list<TagWhereUnique>}, categories?: array{connect?: list<CategoryWhereUnique>}}
 * @phpstan-type PostUpdateInput array{id?: int, kind?: string, path?: string, title?: string, body?: string, html?: string, excerpt?: string|null, eyecatch?: string|null, disqusId?: string|null, status?: string, publishedAt?: \DateTimeImmutable|null, createdAt?: \DateTimeImmutable, updatedAt?: \DateTimeImmutable, authorId?: int|null, tags?: array{connect?: list<TagWhereUnique>, disconnect?: list<TagWhereUnique>, set?: list<TagWhereUnique>}, categories?: array{connect?: list<CategoryWhereUnique>, disconnect?: list<CategoryWhereUnique>, set?: list<CategoryWhereUnique>}}
 * @phpstan-type PostWhereUnique array{id?: int, path?: string}
 * @phpstan-type PostWhereInput array<string,mixed>
 * @phpstan-type PostOrderBy array<string,'asc'|'desc'>|list<array<string,'asc'|'desc'>>
 * @phpstan-type PostInclude array{author?: bool|array{where?: array<string,mixed>, take?: int, skip?: int}, tags?: bool|array{where?: array<string,mixed>, take?: int, skip?: int}, categories?: bool|array{where?: array<string,mixed>, take?: int, skip?: int}}
 * @phpstan-type PostSelect array{id?: bool, kind?: bool, path?: bool, title?: bool, body?: bool, html?: bool, excerpt?: bool, eyecatch?: bool, disqusId?: bool, status?: bool, publishedAt?: bool, createdAt?: bool, updatedAt?: bool, authorId?: bool}|list<'id'|'kind'|'path'|'title'|'body'|'html'|'excerpt'|'eyecatch'|'disqusId'|'status'|'publishedAt'|'createdAt'|'updatedAt'|'authorId'>
 */
final class Post extends BaseModelClient
{
    public const ?string PK = 'id';

    protected function table(): string
    {
        return 'Post';
    }

    protected function primaryKey(): string
    {
        return 'id';
    }

    /** @return list<string> */
    protected function columns(): array
    {
        return ['id', 'kind', 'path', 'title', 'body', 'html', 'excerpt', 'eyecatch', 'disqusId', 'status', 'publishedAt', 'createdAt', 'updatedAt', 'authorId'];
    }

    /** @return array<string,string> */
    protected function columnTypes(): array
    {
        return ['id' => 'int', 'kind' => 'string', 'path' => 'string', 'title' => 'string', 'body' => 'string', 'html' => 'string', 'excerpt' => 'string', 'eyecatch' => 'string', 'disqusId' => 'string', 'status' => 'string', 'publishedAt' => 'DateTime', 'createdAt' => 'DateTime', 'updatedAt' => 'DateTime', 'authorId' => 'int'];
    }

    /** @return array<string, Relation> */
    protected function relations(): array
    {
        return [
            'author' => new Relation('belongsTo', 'User', ['authorId'], ['id']),
            'tags' => new Relation('manyToMany', 'Tag', ['id'], ['id'], '_PostToTag', 'A', 'B'),
            'categories' => new Relation('manyToMany', 'Category', ['id'], ['id'], '_CategoryToPost', 'B', 'A'),
        ];
    }


    /**
     * @param array{where: PostWhereUnique, include?: PostInclude, select?: PostSelect} $args
     * @return PostRow|null
     */
    public function findUnique(array $args): ?array
    {
        return $this->narrowOptionalRow($this->doFindUnique($args));
    }

    /**
     * @param array{where?: PostWhereInput, orderBy?: PostOrderBy, take?: int, skip?: int, include?: PostInclude, select?: PostSelect} $args
     * @return PostRow|null
     */
    public function findFirst(array $args = []): ?array
    {
        return $this->narrowOptionalRow($this->doFindFirst($args));
    }

    /**
     * @param array{where?: PostWhereInput, orderBy?: PostOrderBy, take?: int, skip?: int, include?: PostInclude, select?: PostSelect} $args
     * @return list<PostRow>
     */
    public function findMany(array $args = []): array
    {
        return $this->narrowRows($this->doFindMany($args));
    }

    /**
     * @param array{data: PostInsertInput} $args
     * @return PostRow
     */
    public function insert(array $args): array
    {
        return $this->narrowRow($this->doInsert($args));
    }

    /**
     * @param array{where: PostWhereUnique, data: PostUpdateInput} $args
     * @return PostRow
     */
    public function update(array $args): array
    {
        return $this->narrowRow($this->doUpdate($args));
    }

    /**
     * @param array{where: PostWhereUnique} $args
     * @return PostRow
     */
    public function delete(array $args): array
    {
        return $this->narrowRow($this->doDelete($args));
    }

    /**
     * @param array{where?: PostWhereInput} $args
     */
    public function count(array $args = []): int
    {
        return $this->doCount($args);
    }

    /**
     * @param array{where: PostWhereUnique, update: PostUpdateInput, insert: PostInsertInput} $args
     * @return PostRow
     */
    public function upsert(array $args): array
    {
        return $this->narrowRow($this->doUpsert($args));
    }

    /**
     * @param array{data: list<PostInsertInput>, skipDuplicates?: bool} $args
     * @return array{count: int}
     */
    public function insertMany(array $args): array
    {
        return $this->doInsertMany($args);
    }

    /**
     * @param array{where?: PostWhereInput, data: PostUpdateInput} $args
     * @return array{count: int}
     */
    public function updateMany(array $args): array
    {
        return $this->doUpdateMany($args);
    }

    /**
     * @param array{where?: PostWhereInput} $args
     * @return array{count: int}
     */
    public function deleteMany(array $args = []): array
    {
        return $this->doDeleteMany($args);
    }

    /**
     * @param array<string,mixed> $row
     * @return PostRow
     */
    private function narrowRow(array $row): array
    {
        // DB row shape comes from PDO + columnTypes(); trusted to match PostRow.
        /** @phpstan-ignore return.type */
        return $row;
    }

    /**
     * @param array<string,mixed>|null $row
     * @return PostRow|null
     */
    private function narrowOptionalRow(?array $row): ?array
    {
        // DB row shape comes from PDO + columnTypes(); trusted to match PostRow.
        /** @phpstan-ignore return.type */
        return $row;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<PostRow>
     */
    private function narrowRows(array $rows): array
    {
        // DB row shape comes from PDO + columnTypes(); trusted to match PostRow.
        /** @phpstan-ignore return.type */
        return $rows;
    }
}
