<?php

declare(strict_types=1);

namespace App\Tehilim\Model;

use Polidog\Tehilim\Client\BaseModelClient;

/**
 * @phpstan-type OauthClientRowScalar array{id: int, clientId: string, clientName: string, redirectUris: string, tokenEndpointAuthMethod: string, clientSecretHash: string|null, createdAt: \DateTimeImmutable}
 * @phpstan-type OauthClientRow array{id: int, clientId: string, clientName: string, redirectUris: string, tokenEndpointAuthMethod: string, clientSecretHash: string|null, createdAt: \DateTimeImmutable}
 * @phpstan-type OauthClientInsertInput array{id?: int, clientId: string, clientName: string, redirectUris: string, tokenEndpointAuthMethod?: string, clientSecretHash?: string|null, createdAt?: \DateTimeImmutable}
 * @phpstan-type OauthClientUpdateInput array{id?: int, clientId?: string, clientName?: string, redirectUris?: string, tokenEndpointAuthMethod?: string, clientSecretHash?: string|null, createdAt?: \DateTimeImmutable}
 * @phpstan-type OauthClientWhereUnique array{id?: int, clientId?: string}
 * @phpstan-type OauthClientWhereInput array<string,mixed>
 * @phpstan-type OauthClientOrderBy array<string,'asc'|'desc'>|list<array<string,'asc'|'desc'>>
 * @phpstan-type OauthClientInclude array{}
 * @phpstan-type OauthClientSelect array{id?: bool, clientId?: bool, clientName?: bool, redirectUris?: bool, tokenEndpointAuthMethod?: bool, clientSecretHash?: bool, createdAt?: bool}|list<'id'|'clientId'|'clientName'|'redirectUris'|'tokenEndpointAuthMethod'|'clientSecretHash'|'createdAt'>
 */
final class OauthClient extends BaseModelClient
{
    public const ?string PK = 'id';

    protected function table(): string
    {
        return 'OauthClient';
    }

    protected function primaryKey(): string
    {
        return 'id';
    }

    /** @return list<string> */
    protected function columns(): array
    {
        return ['id', 'clientId', 'clientName', 'redirectUris', 'tokenEndpointAuthMethod', 'clientSecretHash', 'createdAt'];
    }

    /** @return array<string,string> */
    protected function columnTypes(): array
    {
        return ['id' => 'int', 'clientId' => 'string', 'clientName' => 'string', 'redirectUris' => 'string', 'tokenEndpointAuthMethod' => 'string', 'clientSecretHash' => 'string', 'createdAt' => 'DateTime'];
    }


    /**
     * @param array{where: OauthClientWhereUnique, include?: OauthClientInclude, select?: OauthClientSelect} $args
     * @return OauthClientRow|null
     */
    public function findUnique(array $args): ?array
    {
        return $this->narrowOptionalRow($this->doFindUnique($args));
    }

    /**
     * @param array{where?: OauthClientWhereInput, orderBy?: OauthClientOrderBy, take?: int, skip?: int, include?: OauthClientInclude, select?: OauthClientSelect} $args
     * @return OauthClientRow|null
     */
    public function findFirst(array $args = []): ?array
    {
        return $this->narrowOptionalRow($this->doFindFirst($args));
    }

    /**
     * @param array{where?: OauthClientWhereInput, orderBy?: OauthClientOrderBy, take?: int, skip?: int, include?: OauthClientInclude, select?: OauthClientSelect} $args
     * @return list<OauthClientRow>
     */
    public function findMany(array $args = []): array
    {
        return $this->narrowRows($this->doFindMany($args));
    }

    /**
     * @param array{data: OauthClientInsertInput} $args
     * @return OauthClientRow
     */
    public function insert(array $args): array
    {
        return $this->narrowRow($this->doInsert($args));
    }

    /**
     * @param array{where: OauthClientWhereUnique, data: OauthClientUpdateInput} $args
     * @return OauthClientRow
     */
    public function update(array $args): array
    {
        return $this->narrowRow($this->doUpdate($args));
    }

    /**
     * @param array{where: OauthClientWhereUnique} $args
     * @return OauthClientRow
     */
    public function delete(array $args): array
    {
        return $this->narrowRow($this->doDelete($args));
    }

    /**
     * @param array{where?: OauthClientWhereInput} $args
     */
    public function count(array $args = []): int
    {
        return $this->doCount($args);
    }

    /**
     * @param array{where: OauthClientWhereUnique, update: OauthClientUpdateInput, insert: OauthClientInsertInput} $args
     * @return OauthClientRow
     */
    public function upsert(array $args): array
    {
        return $this->narrowRow($this->doUpsert($args));
    }

    /**
     * @param array{data: list<OauthClientInsertInput>, skipDuplicates?: bool} $args
     * @return array{count: int}
     */
    public function insertMany(array $args): array
    {
        return $this->doInsertMany($args);
    }

    /**
     * @param array{where?: OauthClientWhereInput, data: OauthClientUpdateInput} $args
     * @return array{count: int}
     */
    public function updateMany(array $args): array
    {
        return $this->doUpdateMany($args);
    }

    /**
     * @param array{where?: OauthClientWhereInput} $args
     * @return array{count: int}
     */
    public function deleteMany(array $args = []): array
    {
        return $this->doDeleteMany($args);
    }

    /**
     * @param array<string,mixed> $row
     * @return OauthClientRow
     */
    private function narrowRow(array $row): array
    {
        // DB row shape comes from PDO + columnTypes(); trusted to match OauthClientRow.
        /** @phpstan-ignore return.type */
        return $row;
    }

    /**
     * @param array<string,mixed>|null $row
     * @return OauthClientRow|null
     */
    private function narrowOptionalRow(?array $row): ?array
    {
        // DB row shape comes from PDO + columnTypes(); trusted to match OauthClientRow.
        /** @phpstan-ignore return.type */
        return $row;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<OauthClientRow>
     */
    private function narrowRows(array $rows): array
    {
        // DB row shape comes from PDO + columnTypes(); trusted to match OauthClientRow.
        /** @phpstan-ignore return.type */
        return $rows;
    }
}
