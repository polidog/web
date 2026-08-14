<?php

declare(strict_types=1);

namespace App\Tehilim\Model;

use Polidog\Tehilim\Client\BaseModelClient;
use Polidog\Tehilim\Client\Relation;

/**
 * @phpstan-import-type PostRowScalar from \App\Tehilim\Model\Post
 * @phpstan-import-type PostWhereUnique from \App\Tehilim\Model\Post
 * @phpstan-type UserRowScalar array{id: int, githubId: int, login: string, name: string|null, avatarUrl: string|null, role: string, createdAt: \DateTimeImmutable}
 * @phpstan-type UserRow array{id: int, githubId: int, login: string, name: string|null, avatarUrl: string|null, role: string, createdAt: \DateTimeImmutable, posts?: list<PostRowScalar>}
 * @phpstan-type UserInsertInput array{id?: int, githubId: int, login: string, name?: string|null, avatarUrl?: string|null, role?: string, createdAt?: \DateTimeImmutable}
 * @phpstan-type UserUpdateInput array{id?: int, githubId?: int, login?: string, name?: string|null, avatarUrl?: string|null, role?: string, createdAt?: \DateTimeImmutable}
 * @phpstan-type UserWhereUnique array{id?: int, githubId?: int}
 * @phpstan-type UserWhereInput array<string,mixed>
 * @phpstan-type UserOrderBy array<string,'asc'|'desc'>|list<array<string,'asc'|'desc'>>
 * @phpstan-type UserInclude array{posts?: bool|array{where?: array<string,mixed>, take?: int, skip?: int}}
 * @phpstan-type UserSelect array{id?: bool, githubId?: bool, login?: bool, name?: bool, avatarUrl?: bool, role?: bool, createdAt?: bool}|list<'id'|'githubId'|'login'|'name'|'avatarUrl'|'role'|'createdAt'>
 */
final class User extends BaseModelClient
{
    public const ?string PK = 'id';

    protected function table(): string
    {
        return 'User';
    }

    protected function primaryKey(): string
    {
        return 'id';
    }

    /** @return list<string> */
    protected function columns(): array
    {
        return ['id', 'githubId', 'login', 'name', 'avatarUrl', 'role', 'createdAt'];
    }

    /** @return array<string,string> */
    protected function columnTypes(): array
    {
        return ['id' => 'int', 'githubId' => 'int', 'login' => 'string', 'name' => 'string', 'avatarUrl' => 'string', 'role' => 'string', 'createdAt' => 'DateTime'];
    }

    /** @return array<string, Relation> */
    protected function relations(): array
    {
        return [
            'posts' => new Relation('hasMany', 'Post', ['id'], ['authorId']),
        ];
    }


    /**
     * @param array{where: UserWhereUnique, include?: UserInclude, select?: UserSelect} $args
     * @return UserRow|null
     */
    public function findUnique(array $args): ?array
    {
        return $this->narrowOptionalRow($this->doFindUnique($args));
    }

    /**
     * @param array{where?: UserWhereInput, orderBy?: UserOrderBy, take?: int, skip?: int, include?: UserInclude, select?: UserSelect} $args
     * @return UserRow|null
     */
    public function findFirst(array $args = []): ?array
    {
        return $this->narrowOptionalRow($this->doFindFirst($args));
    }

    /**
     * @param array{where?: UserWhereInput, orderBy?: UserOrderBy, take?: int, skip?: int, include?: UserInclude, select?: UserSelect} $args
     * @return list<UserRow>
     */
    public function findMany(array $args = []): array
    {
        return $this->narrowRows($this->doFindMany($args));
    }

    /**
     * @param array{data: UserInsertInput} $args
     * @return UserRow
     */
    public function insert(array $args): array
    {
        return $this->narrowRow($this->doInsert($args));
    }

    /**
     * @param array{where: UserWhereUnique, data: UserUpdateInput} $args
     * @return UserRow
     */
    public function update(array $args): array
    {
        return $this->narrowRow($this->doUpdate($args));
    }

    /**
     * @param array{where: UserWhereUnique} $args
     * @return UserRow
     */
    public function delete(array $args): array
    {
        return $this->narrowRow($this->doDelete($args));
    }

    /**
     * @param array{where?: UserWhereInput} $args
     */
    public function count(array $args = []): int
    {
        return $this->doCount($args);
    }

    /**
     * @param array{where: UserWhereUnique, update: UserUpdateInput, insert: UserInsertInput} $args
     * @return UserRow
     */
    public function upsert(array $args): array
    {
        return $this->narrowRow($this->doUpsert($args));
    }

    /**
     * @param array{data: list<UserInsertInput>, skipDuplicates?: bool} $args
     * @return array{count: int}
     */
    public function insertMany(array $args): array
    {
        return $this->doInsertMany($args);
    }

    /**
     * @param array{where?: UserWhereInput, data: UserUpdateInput} $args
     * @return array{count: int}
     */
    public function updateMany(array $args): array
    {
        return $this->doUpdateMany($args);
    }

    /**
     * @param array{where?: UserWhereInput} $args
     * @return array{count: int}
     */
    public function deleteMany(array $args = []): array
    {
        return $this->doDeleteMany($args);
    }

    /**
     * @param array<string,mixed> $row
     * @return UserRow
     */
    private function narrowRow(array $row): array
    {
        // DB row shape comes from PDO + columnTypes(); trusted to match UserRow.
        /** @phpstan-ignore return.type */
        return $row;
    }

    /**
     * @param array<string,mixed>|null $row
     * @return UserRow|null
     */
    private function narrowOptionalRow(?array $row): ?array
    {
        // DB row shape comes from PDO + columnTypes(); trusted to match UserRow.
        /** @phpstan-ignore return.type */
        return $row;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<UserRow>
     */
    private function narrowRows(array $rows): array
    {
        // DB row shape comes from PDO + columnTypes(); trusted to match UserRow.
        /** @phpstan-ignore return.type */
        return $rows;
    }
}
