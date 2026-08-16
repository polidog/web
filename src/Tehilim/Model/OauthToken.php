<?php

declare(strict_types=1);

namespace App\Tehilim\Model;

use Polidog\Tehilim\Client\BaseModelClient;

/**
 * @phpstan-type OauthTokenRowScalar array{id: int, accessTokenHash: string, refreshTokenHash: string|null, clientId: string, scope: string, resource: string, accessExpiresAt: \DateTimeImmutable, refreshExpiresAt: \DateTimeImmutable|null, revokedAt: \DateTimeImmutable|null, createdAt: \DateTimeImmutable}
 * @phpstan-type OauthTokenRow array{id: int, accessTokenHash: string, refreshTokenHash: string|null, clientId: string, scope: string, resource: string, accessExpiresAt: \DateTimeImmutable, refreshExpiresAt: \DateTimeImmutable|null, revokedAt: \DateTimeImmutable|null, createdAt: \DateTimeImmutable}
 * @phpstan-type OauthTokenInsertInput array{id?: int, accessTokenHash: string, refreshTokenHash?: string|null, clientId: string, scope: string, resource: string, accessExpiresAt: \DateTimeImmutable, refreshExpiresAt?: \DateTimeImmutable|null, revokedAt?: \DateTimeImmutable|null, createdAt?: \DateTimeImmutable}
 * @phpstan-type OauthTokenUpdateInput array{id?: int, accessTokenHash?: string, refreshTokenHash?: string|null, clientId?: string, scope?: string, resource?: string, accessExpiresAt?: \DateTimeImmutable, refreshExpiresAt?: \DateTimeImmutable|null, revokedAt?: \DateTimeImmutable|null, createdAt?: \DateTimeImmutable}
 * @phpstan-type OauthTokenWhereUnique array{id?: int, accessTokenHash?: string, refreshTokenHash?: string|null}
 * @phpstan-type OauthTokenWhereInput array<string,mixed>
 * @phpstan-type OauthTokenOrderBy array<string,'asc'|'desc'>|list<array<string,'asc'|'desc'>>
 * @phpstan-type OauthTokenInclude array{}
 * @phpstan-type OauthTokenSelect array{id?: bool, accessTokenHash?: bool, refreshTokenHash?: bool, clientId?: bool, scope?: bool, resource?: bool, accessExpiresAt?: bool, refreshExpiresAt?: bool, revokedAt?: bool, createdAt?: bool}|list<'id'|'accessTokenHash'|'refreshTokenHash'|'clientId'|'scope'|'resource'|'accessExpiresAt'|'refreshExpiresAt'|'revokedAt'|'createdAt'>
 */
final class OauthToken extends BaseModelClient
{
    public const ?string PK = 'id';

    protected function table(): string
    {
        return 'OauthToken';
    }

    protected function primaryKey(): string
    {
        return 'id';
    }

    /** @return list<string> */
    protected function columns(): array
    {
        return ['id', 'accessTokenHash', 'refreshTokenHash', 'clientId', 'scope', 'resource', 'accessExpiresAt', 'refreshExpiresAt', 'revokedAt', 'createdAt'];
    }

    /** @return array<string,string> */
    protected function columnTypes(): array
    {
        return ['id' => 'int', 'accessTokenHash' => 'string', 'refreshTokenHash' => 'string', 'clientId' => 'string', 'scope' => 'string', 'resource' => 'string', 'accessExpiresAt' => 'DateTime', 'refreshExpiresAt' => 'DateTime', 'revokedAt' => 'DateTime', 'createdAt' => 'DateTime'];
    }


    /**
     * @param array{where: OauthTokenWhereUnique, include?: OauthTokenInclude, select?: OauthTokenSelect} $args
     * @return OauthTokenRow|null
     */
    public function findUnique(array $args): ?array
    {
        return $this->narrowOptionalRow($this->doFindUnique($args));
    }

    /**
     * @param array{where?: OauthTokenWhereInput, orderBy?: OauthTokenOrderBy, take?: int, skip?: int, include?: OauthTokenInclude, select?: OauthTokenSelect} $args
     * @return OauthTokenRow|null
     */
    public function findFirst(array $args = []): ?array
    {
        return $this->narrowOptionalRow($this->doFindFirst($args));
    }

    /**
     * @param array{where?: OauthTokenWhereInput, orderBy?: OauthTokenOrderBy, take?: int, skip?: int, include?: OauthTokenInclude, select?: OauthTokenSelect} $args
     * @return list<OauthTokenRow>
     */
    public function findMany(array $args = []): array
    {
        return $this->narrowRows($this->doFindMany($args));
    }

    /**
     * @param array{data: OauthTokenInsertInput} $args
     * @return OauthTokenRow
     */
    public function insert(array $args): array
    {
        return $this->narrowRow($this->doInsert($args));
    }

    /**
     * @param array{where: OauthTokenWhereUnique, data: OauthTokenUpdateInput} $args
     * @return OauthTokenRow
     */
    public function update(array $args): array
    {
        return $this->narrowRow($this->doUpdate($args));
    }

    /**
     * @param array{where: OauthTokenWhereUnique} $args
     * @return OauthTokenRow
     */
    public function delete(array $args): array
    {
        return $this->narrowRow($this->doDelete($args));
    }

    /**
     * @param array{where?: OauthTokenWhereInput} $args
     */
    public function count(array $args = []): int
    {
        return $this->doCount($args);
    }

    /**
     * @param array{where: OauthTokenWhereUnique, update: OauthTokenUpdateInput, insert: OauthTokenInsertInput} $args
     * @return OauthTokenRow
     */
    public function upsert(array $args): array
    {
        return $this->narrowRow($this->doUpsert($args));
    }

    /**
     * @param array{data: list<OauthTokenInsertInput>, skipDuplicates?: bool} $args
     * @return array{count: int}
     */
    public function insertMany(array $args): array
    {
        return $this->doInsertMany($args);
    }

    /**
     * @param array{where?: OauthTokenWhereInput, data: OauthTokenUpdateInput} $args
     * @return array{count: int}
     */
    public function updateMany(array $args): array
    {
        return $this->doUpdateMany($args);
    }

    /**
     * @param array{where?: OauthTokenWhereInput} $args
     * @return array{count: int}
     */
    public function deleteMany(array $args = []): array
    {
        return $this->doDeleteMany($args);
    }

    /**
     * @param array<string,mixed> $row
     * @return OauthTokenRow
     */
    private function narrowRow(array $row): array
    {
        // DB row shape comes from PDO + columnTypes(); trusted to match OauthTokenRow.
        /** @phpstan-ignore return.type */
        return $row;
    }

    /**
     * @param array<string,mixed>|null $row
     * @return OauthTokenRow|null
     */
    private function narrowOptionalRow(?array $row): ?array
    {
        // DB row shape comes from PDO + columnTypes(); trusted to match OauthTokenRow.
        /** @phpstan-ignore return.type */
        return $row;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<OauthTokenRow>
     */
    private function narrowRows(array $rows): array
    {
        // DB row shape comes from PDO + columnTypes(); trusted to match OauthTokenRow.
        /** @phpstan-ignore return.type */
        return $rows;
    }
}
