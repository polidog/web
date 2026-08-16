<?php

declare(strict_types=1);

namespace App\Tehilim\Model;

use Polidog\Tehilim\Client\BaseModelClient;

/**
 * @phpstan-type OauthAuthCodeRowScalar array{id: int, codeHash: string, clientId: string, redirectUri: string, codeChallenge: string, scope: string, resource: string, expiresAt: \DateTimeImmutable, usedAt: \DateTimeImmutable|null, createdAt: \DateTimeImmutable}
 * @phpstan-type OauthAuthCodeRow array{id: int, codeHash: string, clientId: string, redirectUri: string, codeChallenge: string, scope: string, resource: string, expiresAt: \DateTimeImmutable, usedAt: \DateTimeImmutable|null, createdAt: \DateTimeImmutable}
 * @phpstan-type OauthAuthCodeInsertInput array{id?: int, codeHash: string, clientId: string, redirectUri: string, codeChallenge: string, scope: string, resource: string, expiresAt: \DateTimeImmutable, usedAt?: \DateTimeImmutable|null, createdAt?: \DateTimeImmutable}
 * @phpstan-type OauthAuthCodeUpdateInput array{id?: int, codeHash?: string, clientId?: string, redirectUri?: string, codeChallenge?: string, scope?: string, resource?: string, expiresAt?: \DateTimeImmutable, usedAt?: \DateTimeImmutable|null, createdAt?: \DateTimeImmutable}
 * @phpstan-type OauthAuthCodeWhereUnique array{id?: int, codeHash?: string}
 * @phpstan-type OauthAuthCodeWhereInput array<string,mixed>
 * @phpstan-type OauthAuthCodeOrderBy array<string,'asc'|'desc'>|list<array<string,'asc'|'desc'>>
 * @phpstan-type OauthAuthCodeInclude array{}
 * @phpstan-type OauthAuthCodeSelect array{id?: bool, codeHash?: bool, clientId?: bool, redirectUri?: bool, codeChallenge?: bool, scope?: bool, resource?: bool, expiresAt?: bool, usedAt?: bool, createdAt?: bool}|list<'id'|'codeHash'|'clientId'|'redirectUri'|'codeChallenge'|'scope'|'resource'|'expiresAt'|'usedAt'|'createdAt'>
 */
final class OauthAuthCode extends BaseModelClient
{
    public const ?string PK = 'id';

    protected function table(): string
    {
        return 'OauthAuthCode';
    }

    protected function primaryKey(): string
    {
        return 'id';
    }

    /** @return list<string> */
    protected function columns(): array
    {
        return ['id', 'codeHash', 'clientId', 'redirectUri', 'codeChallenge', 'scope', 'resource', 'expiresAt', 'usedAt', 'createdAt'];
    }

    /** @return array<string,string> */
    protected function columnTypes(): array
    {
        return ['id' => 'int', 'codeHash' => 'string', 'clientId' => 'string', 'redirectUri' => 'string', 'codeChallenge' => 'string', 'scope' => 'string', 'resource' => 'string', 'expiresAt' => 'DateTime', 'usedAt' => 'DateTime', 'createdAt' => 'DateTime'];
    }


    /**
     * @param array{where: OauthAuthCodeWhereUnique, include?: OauthAuthCodeInclude, select?: OauthAuthCodeSelect} $args
     * @return OauthAuthCodeRow|null
     */
    public function findUnique(array $args): ?array
    {
        return $this->narrowOptionalRow($this->doFindUnique($args));
    }

    /**
     * @param array{where?: OauthAuthCodeWhereInput, orderBy?: OauthAuthCodeOrderBy, take?: int, skip?: int, include?: OauthAuthCodeInclude, select?: OauthAuthCodeSelect} $args
     * @return OauthAuthCodeRow|null
     */
    public function findFirst(array $args = []): ?array
    {
        return $this->narrowOptionalRow($this->doFindFirst($args));
    }

    /**
     * @param array{where?: OauthAuthCodeWhereInput, orderBy?: OauthAuthCodeOrderBy, take?: int, skip?: int, include?: OauthAuthCodeInclude, select?: OauthAuthCodeSelect} $args
     * @return list<OauthAuthCodeRow>
     */
    public function findMany(array $args = []): array
    {
        return $this->narrowRows($this->doFindMany($args));
    }

    /**
     * @param array{data: OauthAuthCodeInsertInput} $args
     * @return OauthAuthCodeRow
     */
    public function insert(array $args): array
    {
        return $this->narrowRow($this->doInsert($args));
    }

    /**
     * @param array{where: OauthAuthCodeWhereUnique, data: OauthAuthCodeUpdateInput} $args
     * @return OauthAuthCodeRow
     */
    public function update(array $args): array
    {
        return $this->narrowRow($this->doUpdate($args));
    }

    /**
     * @param array{where: OauthAuthCodeWhereUnique} $args
     * @return OauthAuthCodeRow
     */
    public function delete(array $args): array
    {
        return $this->narrowRow($this->doDelete($args));
    }

    /**
     * @param array{where?: OauthAuthCodeWhereInput} $args
     */
    public function count(array $args = []): int
    {
        return $this->doCount($args);
    }

    /**
     * @param array{where: OauthAuthCodeWhereUnique, update: OauthAuthCodeUpdateInput, insert: OauthAuthCodeInsertInput} $args
     * @return OauthAuthCodeRow
     */
    public function upsert(array $args): array
    {
        return $this->narrowRow($this->doUpsert($args));
    }

    /**
     * @param array{data: list<OauthAuthCodeInsertInput>, skipDuplicates?: bool} $args
     * @return array{count: int}
     */
    public function insertMany(array $args): array
    {
        return $this->doInsertMany($args);
    }

    /**
     * @param array{where?: OauthAuthCodeWhereInput, data: OauthAuthCodeUpdateInput} $args
     * @return array{count: int}
     */
    public function updateMany(array $args): array
    {
        return $this->doUpdateMany($args);
    }

    /**
     * @param array{where?: OauthAuthCodeWhereInput} $args
     * @return array{count: int}
     */
    public function deleteMany(array $args = []): array
    {
        return $this->doDeleteMany($args);
    }

    /**
     * @param array<string,mixed> $row
     * @return OauthAuthCodeRow
     */
    private function narrowRow(array $row): array
    {
        // DB row shape comes from PDO + columnTypes(); trusted to match OauthAuthCodeRow.
        /** @phpstan-ignore return.type */
        return $row;
    }

    /**
     * @param array<string,mixed>|null $row
     * @return OauthAuthCodeRow|null
     */
    private function narrowOptionalRow(?array $row): ?array
    {
        // DB row shape comes from PDO + columnTypes(); trusted to match OauthAuthCodeRow.
        /** @phpstan-ignore return.type */
        return $row;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<OauthAuthCodeRow>
     */
    private function narrowRows(array $rows): array
    {
        // DB row shape comes from PDO + columnTypes(); trusted to match OauthAuthCodeRow.
        /** @phpstan-ignore return.type */
        return $rows;
    }
}
