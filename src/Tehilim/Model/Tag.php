<?php

declare(strict_types=1);

namespace App\Tehilim\Model;

use Polidog\Tehilim\Client\BaseModelClient;
use Polidog\Tehilim\Client\Relation;

/**
 * @phpstan-import-type PostRowScalar from \App\Tehilim\Model\Post
 * @phpstan-import-type PostWhereUnique from \App\Tehilim\Model\Post
 * @phpstan-type TagRowScalar array{id: int, name: string, slug: string}
 * @phpstan-type TagRow array{id: int, name: string, slug: string, posts?: list<PostRowScalar>}
 * @phpstan-type TagInsertInput array{id?: int, name: string, slug: string, posts?: array{connect?: list<PostWhereUnique>}}
 * @phpstan-type TagUpdateInput array{id?: int, name?: string, slug?: string, posts?: array{connect?: list<PostWhereUnique>, disconnect?: list<PostWhereUnique>, set?: list<PostWhereUnique>}}
 * @phpstan-type TagWhereUnique array{id?: int, slug?: string}
 * @phpstan-type TagWhereInput array<string,mixed>
 * @phpstan-type TagOrderBy array<string,'asc'|'desc'>|list<array<string,'asc'|'desc'>>
 * @phpstan-type TagInclude array{posts?: bool|array{where?: array<string,mixed>, take?: int, skip?: int}}
 * @phpstan-type TagSelect array{id?: bool, name?: bool, slug?: bool}|list<'id'|'name'|'slug'>
 */
final class Tag extends BaseModelClient
{
    public const ?string PK = 'id';

    protected function table(): string
    {
        return 'Tag';
    }

    protected function primaryKey(): string
    {
        return 'id';
    }

    /** @return list<string> */
    protected function columns(): array
    {
        return ['id', 'name', 'slug'];
    }

    /** @return array<string,string> */
    protected function columnTypes(): array
    {
        return ['id' => 'int', 'name' => 'string', 'slug' => 'string'];
    }

    /** @return array<string, Relation> */
    protected function relations(): array
    {
        return [
            'posts' => new Relation('manyToMany', 'Post', ['id'], ['id'], '_PostToTag', 'B', 'A'),
        ];
    }


    /**
     * @param array{where: TagWhereUnique, include?: TagInclude, select?: TagSelect} $args
     * @return TagRow|null
     */
    public function findUnique(array $args): ?array
    {
        return $this->narrowOptionalRow($this->doFindUnique($args));
    }

    /**
     * @param array{where?: TagWhereInput, orderBy?: TagOrderBy, take?: int, skip?: int, include?: TagInclude, select?: TagSelect} $args
     * @return TagRow|null
     */
    public function findFirst(array $args = []): ?array
    {
        return $this->narrowOptionalRow($this->doFindFirst($args));
    }

    /**
     * @param array{where?: TagWhereInput, orderBy?: TagOrderBy, take?: int, skip?: int, include?: TagInclude, select?: TagSelect} $args
     * @return list<TagRow>
     */
    public function findMany(array $args = []): array
    {
        return $this->narrowRows($this->doFindMany($args));
    }

    /**
     * @param array{data: TagInsertInput} $args
     * @return TagRow
     */
    public function insert(array $args): array
    {
        return $this->narrowRow($this->doInsert($args));
    }

    /**
     * @param array{where: TagWhereUnique, data: TagUpdateInput} $args
     * @return TagRow
     */
    public function update(array $args): array
    {
        return $this->narrowRow($this->doUpdate($args));
    }

    /**
     * @param array{where: TagWhereUnique} $args
     * @return TagRow
     */
    public function delete(array $args): array
    {
        return $this->narrowRow($this->doDelete($args));
    }

    /**
     * @param array{where?: TagWhereInput} $args
     */
    public function count(array $args = []): int
    {
        return $this->doCount($args);
    }

    /**
     * @param array{where: TagWhereUnique, update: TagUpdateInput, insert: TagInsertInput} $args
     * @return TagRow
     */
    public function upsert(array $args): array
    {
        return $this->narrowRow($this->doUpsert($args));
    }

    /**
     * @param array{data: list<TagInsertInput>, skipDuplicates?: bool} $args
     * @return array{count: int}
     */
    public function insertMany(array $args): array
    {
        return $this->doInsertMany($args);
    }

    /**
     * @param array{where?: TagWhereInput, data: TagUpdateInput} $args
     * @return array{count: int}
     */
    public function updateMany(array $args): array
    {
        return $this->doUpdateMany($args);
    }

    /**
     * @param array{where?: TagWhereInput} $args
     * @return array{count: int}
     */
    public function deleteMany(array $args = []): array
    {
        return $this->doDeleteMany($args);
    }

    /**
     * @param array<string,mixed> $row
     * @return TagRow
     */
    private function narrowRow(array $row): array
    {
        // DB row shape comes from PDO + columnTypes(); trusted to match TagRow.
        /** @phpstan-ignore return.type */
        return $row;
    }

    /**
     * @param array<string,mixed>|null $row
     * @return TagRow|null
     */
    private function narrowOptionalRow(?array $row): ?array
    {
        // DB row shape comes from PDO + columnTypes(); trusted to match TagRow.
        /** @phpstan-ignore return.type */
        return $row;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<TagRow>
     */
    private function narrowRows(array $rows): array
    {
        // DB row shape comes from PDO + columnTypes(); trusted to match TagRow.
        /** @phpstan-ignore return.type */
        return $rows;
    }
}
