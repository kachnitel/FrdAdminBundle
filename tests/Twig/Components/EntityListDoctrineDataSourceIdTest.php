<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Twig\Components;

use Kachnitel\AdminBundle\Archive\ArchiveService;
use Kachnitel\AdminBundle\Config\EntityListConfig;
use Kachnitel\AdminBundle\DataSource\DataSourceRegistry;
use Kachnitel\AdminBundle\DataSource\DoctrineDataSource;
use Kachnitel\AdminBundle\Security\ObjectAuthorizationChecker;
use Kachnitel\AdminBundle\Service\CollectionScopeResolver;
use Kachnitel\AdminBundle\Service\EntityListBatchService;
use Kachnitel\AdminBundle\Service\EntityListColumnService;
use Kachnitel\AdminBundle\Service\EntityListPermissionService;
use Kachnitel\AdminBundle\Service\Preferences\AdminPreferencesStorageInterface;
use Kachnitel\AdminBundle\Twig\Components\EntityList;
use Kachnitel\DataSourceContracts\DataSourceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityList::class)]
#[UsesClass(EntityListConfig::class)]
#[Group('entity-list')]
#[Group('datasource-id')]
final class EntityListDoctrineDataSourceIdTest extends TestCase
{
    private function makeComponent(DataSourceInterface $dataSource): EntityList
    {
        $registry = $this->createStub(DataSourceRegistry::class);
        $registry->method('resolve')->willReturn($dataSource);

        $component = new EntityList(
            $this->createStub(EntityListPermissionService::class),
            new EntityListConfig(),
            $registry,
            $this->createStub(EntityListBatchService::class),
            $this->createStub(AdminPreferencesStorageInterface::class),
            $this->createStub(EntityListColumnService::class),
            $this->createStub(ArchiveService::class),
            $this->createStub(ObjectAuthorizationChecker::class),
            $this->createStub(CollectionScopeResolver::class),
        );
        $component->dataSourceId = 'Product';

        return $component;
    }

    private function doctrineSource(): DoctrineDataSource
    {
        $ds = $this->createStub(DoctrineDataSource::class);
        $ds->method('getEntityClass')->willReturn('App\\Entity\\Product');
        $ds->method('getShortName')->willReturn('Product');

        return $ds;
    }

    #[Test]
    public function adoptsEntityClassAndShortNameFromDoctrineDataSource(): void
    {
        $component = $this->makeComponent($this->doctrineSource());

        $component->resolveEntityClass();

        $this->assertSame('App\\Entity\\Product', $component->entityClass);
        $this->assertSame('Product', $component->entityShortClass);
    }

    #[Test]
    public function isDoctrineEntityIsTrueForDoctrineDataSourceId(): void
    {
        $this->assertTrue($this->makeComponent($this->doctrineSource())->isDoctrineEntity());
    }

    #[Test]
    public function customDataSourceKeepsEntityClassEmptyAndIsNotDoctrine(): void
    {
        $component = $this->makeComponent($this->createStub(DataSourceInterface::class));
        $component->dataSourceId = 'api-products';

        $component->resolveEntityClass();

        $this->assertSame('', $component->entityClass);
        $this->assertFalse($component->isDoctrineEntity());
    }

    #[Test]
    public function explicitShortClassIsNotOverwritten(): void
    {
        $component = $this->makeComponent($this->doctrineSource());
        $component->entityShortClass = 'Custom';

        $component->resolveEntityClass();

        $this->assertSame('Custom', $component->entityShortClass);
    }
}