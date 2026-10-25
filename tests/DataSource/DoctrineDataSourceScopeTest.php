<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\DataSource;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\DataSource\DoctrineColumnAttributeProvider;
use Kachnitel\AdminBundle\DataSource\DoctrineColumnTypeMapper;
use Kachnitel\AdminBundle\DataSource\DoctrineCustomColumnProvider;
use Kachnitel\AdminBundle\DataSource\DoctrineDataSource;
use Kachnitel\AdminBundle\DataSource\DoctrineFilterConverter;
use Kachnitel\AdminBundle\DataSource\DoctrineItemValueResolver;
use Kachnitel\AdminBundle\Service\EntityListQueryService;
use Kachnitel\AdminBundle\Service\FilterMetadataProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * DataSource instances are shared through DataSourceRegistry, so a scope set for one
 * query must be consumed by it and never leak into the next.
 */
#[CoversClass(DoctrineDataSource::class)]
#[UsesClass(Admin::class)]
#[Group('collection-dialog')]
final class DoctrineDataSourceScopeTest extends TestCase
{
    /** @var list<array{0: ?string, 1: array<array-key, mixed>}> */
    private array $seenScopes = [];

    #[Test]
    public function queryForwardsTheScopeToTheQueryService(): void
    {
        $dataSource = $this->makeDataSource();
        $dataSource->setScopeCondition('e.id IN (…)', ['scope_owner_id' => 5]);

        $this->runQuery($dataSource);

        $this->assertSame([['e.id IN (…)', ['scope_owner_id' => 5]]], $this->seenScopes);
    }

    #[Test]
    public function queryConsumesTheScopeSoItCannotLeakIntoTheNextQuery(): void
    {
        $dataSource = $this->makeDataSource();
        $dataSource->setScopeCondition('e.id IN (…)', ['scope_owner_id' => 5]);

        $this->runQuery($dataSource);
        $this->runQuery($dataSource);

        $this->assertSame(
            [['e.id IN (…)', ['scope_owner_id' => 5]], [null, []]],
            $this->seenScopes,
        );
    }

    #[Test]
    public function unscopedQueryPassesNoScope(): void
    {
        $dataSource = $this->makeDataSource();

        $this->runQuery($dataSource);

        $this->assertSame([[null, []]], $this->seenScopes);
    }

    #[Test]
    public function settingNullClearsAPreviouslySetScope(): void
    {
        $dataSource = $this->makeDataSource();
        $dataSource->setScopeCondition('e.id IN (…)', ['scope_owner_id' => 5]);
        $dataSource->setScopeCondition(null);

        $this->runQuery($dataSource);

        $this->assertSame([[null, []]], $this->seenScopes);
    }

    private function runQuery(DoctrineDataSource $dataSource): void
    {
        $dataSource->query('', [], 'id', 'DESC', 1, 20);
    }

    private function makeDataSource(): DoctrineDataSource
    {
        $queryService = $this->createStub(EntityListQueryService::class);
        $queryService->method('getEntities')->willReturnCallback(
            function (string $entityClass, ?string $repositoryMethod, string $search, array $columnFilters, array $filterMetadata, string $sortBy, string $sortDirection, int $page, int $itemsPerPage, ?string $archiveDqlCondition = null, ?string $scopeDqlCondition = null, array $scopeParameters = []): array {
                $this->seenScopes[] = [$scopeDqlCondition, $scopeParameters];

                return ['entities' => [], 'total' => 0, 'page' => 1];
            }
        );

        return new DoctrineDataSource(
            entityClass: \stdClass::class,
            adminAttribute: new Admin(),
            em: $this->createStub(EntityManagerInterface::class),
            queryService: $queryService,
            filterMetadataProvider: $this->createStub(FilterMetadataProvider::class),
            customColumnProvider: $this->createStub(DoctrineCustomColumnProvider::class),
            columnAttributeProvider: $this->createStub(DoctrineColumnAttributeProvider::class),
            columnTypeMapper: $this->createStub(DoctrineColumnTypeMapper::class),
            filterConverter: $this->createStub(DoctrineFilterConverter::class),
            itemValueResolver: $this->createStub(DoctrineItemValueResolver::class),
        );
    }
}
