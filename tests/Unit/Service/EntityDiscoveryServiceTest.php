<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\RelatedEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\ConfiguredEntity;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EntityDiscoveryServiceTest extends TestCase
{
    private EntityDiscoveryService $service;
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $entityManager;
    /** @var ClassMetadataFactory&MockObject */
    private ClassMetadataFactory $metadataFactory;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->metadataFactory = $this->createMock(ClassMetadataFactory::class);

        $this->entityManager
            ->method('getMetadataFactory')
            ->willReturn($this->metadataFactory);

        $this->service = new EntityDiscoveryService($this->entityManager);
    }

    public function testGetAdminEntitiesFindsEntitiesWithAttribute(): void
    {
        // Mock metadata for TestEntity (has #[Admin]) and RelatedEntity (doesn't have it)
        $testEntityMetadata = $this->createMock(ClassMetadata::class);
        $testEntityMetadata->method('getName')->willReturn(TestEntity::class);

        $relatedEntityMetadata = $this->createMock(ClassMetadata::class);
        $relatedEntityMetadata->method('getName')->willReturn(RelatedEntity::class);

        $this->metadataFactory
            ->method('getAllMetadata')
            ->willReturn([$testEntityMetadata, $relatedEntityMetadata]);

        $result = $this->service->getAdminEntities();

        // TestEntity has #[Admin] attribute
        $this->assertArrayHasKey(TestEntity::class, $result);
        $this->assertInstanceOf(Admin::class, $result[TestEntity::class]);

        // RelatedEntity does NOT have #[Admin] attribute
        $this->assertArrayNotHasKey(RelatedEntity::class, $result);
    }

    public function testGetAdminAttributeReturnsAttributeForAdminEntity(): void
    {
        $testEntityMetadata = $this->createMock(ClassMetadata::class);
        $testEntityMetadata->method('getName')->willReturn(TestEntity::class);

        $this->metadataFactory
            ->method('getAllMetadata')
            ->willReturn([$testEntityMetadata]);

        $adminAttr = $this->service->getAdminAttribute(TestEntity::class);

        $this->assertInstanceOf(Admin::class, $adminAttr);
        $this->assertSame('Test Entities', $adminAttr->getLabel());
        $this->assertSame('science', $adminAttr->getIcon());
        $this->assertSame(['id', 'name', 'active'], $adminAttr->getColumns());
        $this->assertSame(15, $adminAttr->getItemsPerPage());
    }

    public function testGetAdminAttributeReturnsNullForNonAdminEntity(): void
    {
        $relatedEntityMetadata = $this->createMock(ClassMetadata::class);
        $relatedEntityMetadata->method('getName')->willReturn(RelatedEntity::class);

        $this->metadataFactory
            ->method('getAllMetadata')
            ->willReturn([$relatedEntityMetadata]);

        $adminAttr = $this->service->getAdminAttribute(RelatedEntity::class);

        $this->assertNull($adminAttr);
    }

    public function testIsAdminEntityReturnsTrueForEntityWithAttribute(): void
    {
        $testEntityMetadata = $this->createMock(ClassMetadata::class);
        $testEntityMetadata->method('getName')->willReturn(TestEntity::class);

        $this->metadataFactory
            ->method('getAllMetadata')
            ->willReturn([$testEntityMetadata]);

        $this->assertTrue($this->service->isAdminEntity(TestEntity::class));
        $this->assertFalse($this->service->isAdminEntity(RelatedEntity::class));
    }

    public function testGetAdminEntityClassesReturnsFullyQualifiedNames(): void
    {
        $testEntityMetadata = $this->createMock(ClassMetadata::class);
        $testEntityMetadata->method('getName')->willReturn(TestEntity::class);

        $this->metadataFactory
            ->method('getAllMetadata')
            ->willReturn([$testEntityMetadata]);

        $classes = $this->service->getAdminEntityClasses();

        $this->assertContains(TestEntity::class, $classes);
        $this->assertNotContains(RelatedEntity::class, $classes);
    }

    public function testGetAdminEntityShortNamesReturnsClassNamesWithoutNamespace(): void
    {
        $testEntityMetadata = $this->createMock(ClassMetadata::class);
        $testEntityMetadata->method('getName')->willReturn(TestEntity::class);

        $this->metadataFactory
            ->method('getAllMetadata')
            ->willReturn([$testEntityMetadata]);

        $shortNames = $this->service->getAdminEntityShortNames();

        $this->assertContains('TestEntity', $shortNames);
        $this->assertNotContains('RelatedEntity', $shortNames);
    }

    public function testResolveEntityClassFindsEntityByShortName(): void
    {
        $testEntityMetadata = $this->createMock(ClassMetadata::class);
        $testEntityMetadata->method('getName')->willReturn(TestEntity::class);

        $this->metadataFactory
            ->method('getAllMetadata')
            ->willReturn([$testEntityMetadata]);

        $resolvedClass = $this->service->resolveEntityClass('TestEntity');

        $this->assertSame(TestEntity::class, $resolvedClass);
    }

    public function testResolveEntityClassUsesDefaultNamespaceForNonAdminEntities(): void
    {
        // Empty metadata - no admin entities
        $this->metadataFactory
            ->method('getAllMetadata')
            ->willReturn([]);

        // TestEntity exists in the namespace
        $resolvedClass = $this->service->resolveEntityClass(
            'TestEntity',
            'Kachnitel\\AdminBundle\\Tests\\Fixtures\\'
        );

        $this->assertSame(TestEntity::class, $resolvedClass);
    }

    public function testResolveEntityClassReturnsNullForNonExistentEntity(): void
    {
        $this->metadataFactory
            ->method('getAllMetadata')
            ->willReturn([]);

        $resolvedClass = $this->service->resolveEntityClass('NonExistentEntity');

        $this->assertNull($resolvedClass);
    }

    public function testClearCacheForcesReload(): void
    {
        $testEntityMetadata = $this->createMock(ClassMetadata::class);
        $testEntityMetadata->method('getName')->willReturn(TestEntity::class);

        $this->metadataFactory
            ->expects($this->exactly(2))
            ->method('getAllMetadata')
            ->willReturn([$testEntityMetadata]);

        // First call - loads and caches
        $this->service->getAdminEntities();

        // Second call - uses cache (getAllMetadata not called again)
        $this->service->getAdminEntities();

        // Clear cache
        $this->service->clearCache();

        // Third call - reloads from metadata factory
        $this->service->getAdminEntities();
    }

    public function testPermissionsAreReadCorrectly(): void
    {
        $testEntityMetadata = $this->createMock(ClassMetadata::class);
        $testEntityMetadata->method('getName')->willReturn(TestEntity::class);

        $this->metadataFactory
            ->method('getAllMetadata')
            ->willReturn([$testEntityMetadata]);

        $adminAttr = $this->service->getAdminAttribute(TestEntity::class);

        $this->assertNotNull($adminAttr);
        $this->assertSame('ROLE_TEST_VIEW', $adminAttr->getPermissionForAction('index'));
        $this->assertSame('ROLE_TEST_EDIT', $adminAttr->getPermissionForAction('edit'));
        $this->assertNull($adminAttr->getPermissionForAction('delete'));
    }

    public function testComprehensiveAttributeConfiguration(): void
    {
        $configuredEntityMetadata = $this->createMock(ClassMetadata::class);
        $configuredEntityMetadata->method('getName')->willReturn(ConfiguredEntity::class);

        $this->metadataFactory
            ->method('getAllMetadata')
            ->willReturn([$configuredEntityMetadata]);

        $adminAttr = $this->service->getAdminAttribute(ConfiguredEntity::class);

        $this->assertNotNull($adminAttr);

        // Basic properties
        $this->assertSame('Configured Items', $adminAttr->getLabel());
        $this->assertSame('settings', $adminAttr->getIcon());
        $this->assertSame('App\Form\ConfiguredEntityType', $adminAttr->getFormType());

        // Feature toggles
        $this->assertFalse($adminAttr->isEnableFilters());
        $this->assertFalse($adminAttr->isEnableBatchActions());

        // Column configuration
        $this->assertSame(['id', 'name', 'email', 'status', 'createdAt'], $adminAttr->getColumns());
        $this->assertSame(['password', 'secret'], $adminAttr->getExcludeColumns());
        $this->assertSame(['name', 'email'], $adminAttr->getFilterableColumns());

        // Pagination
        $this->assertSame(50, $adminAttr->getItemsPerPage());

        // Sorting
        $this->assertSame('createdAt', $adminAttr->getSortBy());
        $this->assertSame('DESC', $adminAttr->getSortDirection());

        // Permissions (all actions)
        $this->assertSame('ROLE_USER', $adminAttr->getPermissionForAction('index'));
        $this->assertSame('ROLE_USER', $adminAttr->getPermissionForAction('show'));
        $this->assertSame('ROLE_EDITOR', $adminAttr->getPermissionForAction('new'));
        $this->assertSame('ROLE_EDITOR', $adminAttr->getPermissionForAction('edit'));
        $this->assertSame('ROLE_ADMIN', $adminAttr->getPermissionForAction('delete'));
    }

    public function testDefaultValues(): void
    {
        $testEntityMetadata = $this->createMock(ClassMetadata::class);
        $testEntityMetadata->method('getName')->willReturn(TestEntity::class);

        $this->metadataFactory
            ->method('getAllMetadata')
            ->willReturn([$testEntityMetadata]);

        $adminAttr = $this->service->getAdminAttribute(TestEntity::class);

        $this->assertNotNull($adminAttr);

        // Defaults for properties not set in TestEntity
        $this->assertTrue($adminAttr->isEnableFilters()); // Default is true
        $this->assertTrue($adminAttr->isEnableBatchActions()); // Default is true
        $this->assertNull($adminAttr->getExcludeColumns()); // Not set
        $this->assertNull($adminAttr->getFilterableColumns()); // Not set
        $this->assertNull($adminAttr->getSortBy()); // Not set
        $this->assertNull($adminAttr->getSortDirection()); // Not set
        $this->assertNull($adminAttr->getFormType()); // Not set
    }

    public function testMultipleAdminEntitiesAreDiscovered(): void
    {
        $testEntityMetadata = $this->createMock(ClassMetadata::class);
        $testEntityMetadata->method('getName')->willReturn(TestEntity::class);

        $configuredEntityMetadata = $this->createMock(ClassMetadata::class);
        $configuredEntityMetadata->method('getName')->willReturn(ConfiguredEntity::class);

        $relatedEntityMetadata = $this->createMock(ClassMetadata::class);
        $relatedEntityMetadata->method('getName')->willReturn(RelatedEntity::class);

        $this->metadataFactory
            ->method('getAllMetadata')
            ->willReturn([$testEntityMetadata, $configuredEntityMetadata, $relatedEntityMetadata]);

        $result = $this->service->getAdminEntities();

        // TestEntity and ConfiguredEntity have #[Admin], RelatedEntity does not
        $this->assertCount(2, $result);
        $this->assertArrayHasKey(TestEntity::class, $result);
        $this->assertArrayHasKey(ConfiguredEntity::class, $result);
        $this->assertArrayNotHasKey(RelatedEntity::class, $result);
    }
}
