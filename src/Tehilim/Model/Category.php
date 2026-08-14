<?php

declare(strict_types=1);

namespace App\Tehilim\Model;

use Polidog\Tehilim\Client\BaseModelClient;
use Polidog\Tehilim\Client\Relation;

/**
 * @phpstan-import-type PostRowScalar from \App\Tehilim\Model\Post
 * @phpstan-import-type PostWhereUnique from \App\Tehilim\Model\Post
 * @phpstan-type CategoryRowScalar array{id: int, name: string, slug: string}
 * @phpstan-type CategoryRow array{id: int, name: string, slug: string, posts?: list<PostRowScalar>}
 * @phpstan-type CategoryInsertInput array{id?: int, name: string, slug: string, posts?: array{connect?: list<PostWhereUnique>}}
 * @phpstan-type CategoryUpdateInput array{id?: int, name?: string, slug?: string, posts?: array{connect?: list<PostWhereUnique>, disconnect?: list<PostWhereUnique>, set?: list<PostWhereUnique>}}
 * @phpstan-type CategoryWhereUnique array{id?: int, slug?: string}
 * @phpstan-type CategoryWhereInput array<string,mixed>
 * @phpstan-type CategoryOrderBy array<string,'asc'|'desc'>|list<array<string,'asc'|'desc'>>
 * @phpstan-type CategoryInclude array{posts?: bool|array{where?: array<string,mixed>, take?: int, skip?: int}}
 * @phpstan-type CategorySelect array{id?: bool, name?: bool, slug?: bool}|list<'id'|'name'|'slug'>
 */
final class Category extends BaseModelClient
{
    public const ?string PK = 'id';

    protected function table(): string
    {
        return 'Category';
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
            'posts' => new Relation('manyToMany', 'Post', ['id'], ['id'], '_CategoryToPost', 'A', 'B'),
        ];
    }


    /**
     * @param array{where: CategoryWhereUnique, include?: CategoryInclude, select?: CategorySelect} $args
     * @return CategoryRow|null
     */
    public function findUnique(array $args): ?array
    {
        return $this->narrowOptionalRow($this->doFindUnique($args));
    }

    /**
     * @param array{where?: CategoryWhereInput, orderBy?: CategoryOrderBy, take?: int, skip?: int, include?: CategoryInclude, select?: CategorySelect} $args
     * @return CategoryRow|null
     */
    public function findFirst(array $args = []): ?array
    {
        return $this->narrowOptionalRow($this->doFindFirst($args));
    }

    /**
     * @param array{where?: CategoryWhereInput, orderBy?: CategoryOrderBy, take?: int, skip?: int, include?: CategoryInclude, select?: CategorySelect} $args
     * @return list<CategoryRow>
     */
    public function findMany(array $args = []): array
    {
        return $this->narrowRows($this->doFindMany($args));
    }

    /**
     * @param array{data: CategoryInsertInput} $args
     * @return CategoryRow
     */
    public function insert(array $args): array
    {
        return $this->narrowRow($this->doInsert($args));
    }

    /**
     * @param array{where: CategoryWhereUnique, data: CategoryUpdateInput} $args
     * @return CategoryRow
     */
    public function update(array $args): array
    {
        return $this->narrowRow($this->doUpdate($args));
    }

    /**
     * @param array{where: CategoryWhereUnique} $args
     * @return CategoryRow
     */
    public function delete(array $args): array
    {
        return $this->narrowRow($this->doDelete($args));
    }

    /**
     * @param array{where?: CategoryWhereInput} $args
     */
    public function count(array $args = []): int
    {
        return $this->doCount($args);
    }

    /**
     * @param array{where: CategoryWhereUnique, update: CategoryUpdateInput, insert: CategoryInsertInput} $args
     * @return CategoryRow
     */
    public function upsert(array $args): array
    {
        return $this->narrowRow($this->doUpsert($args));
    }

    /**
     * @param array{data: list<CategoryInsertInput>, skipDuplicates?: bool} $args
     * @return array{count: int}
     */
    public function insertMany(array $args): array
    {
        return $this->doInsertMany($args);
    }

    /**
     * @param array{where?: CategoryWhereInput, data: CategoryUpdateInput} $args
     * @return array{count: int}
     */
    public function updateMany(array $args): array
    {
        return $this->doUpdateMany($args);
    }

    /**
     * @param array{where?: CategoryWhereInput} $args
     * @return array{count: int}
     */
    public function deleteMany(array $args = []): array
    {
        return $this->doDeleteMany($args);
    }

    /**
     * @param array<string,mixed> $row
     * @return CategoryRow
     */
    private function narrowRow(array $row): array
    {
        // DB row shape comes from PDO + columnTypes(); trusted to match CategoryRow.
        /** @phpstan-ignore return.type */
        return $row;
    }

    /**
     * @param array<string,mixed>|null $row
     * @return CategoryRow|null
     */
    private function narrowOptionalRow(?array $row): ?array
    {
        // DB row shape comes from PDO + columnTypes(); trusted to match CategoryRow.
        /** @phpstan-ignore return.type */
        return $row;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<CategoryRow>
     */
    private function narrowRows(array $rows): array
    {
        // DB row shape comes from PDO + columnTypes(); trusted to match CategoryRow.
        /** @phpstan-ignore return.type */
        return $rows;
    }
}
