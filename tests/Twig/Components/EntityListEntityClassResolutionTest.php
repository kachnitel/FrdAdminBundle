<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Twig\Components;

use Kachnitel\AdminBundle\Archive\ArchiveService;
use Kachnitel\AdminBundle\Config\EntityListConfig;
use Kachnitel\AdminBundle\DataSource\DataSourceRegistry;
use Kachnitel\AdminBundle\Security\ObjectAuthorizationChecker;
use Kachnitel\AdminBundle\Service\EntityListBatchService;
use Kachnitel\AdminBundle\Service\EntityListColumnService;
use Kachnitel\AdminBundle\Service\EntityListPermissionService;
use Kachnitel\AdminBundle\Service\Preferences\AdminPreferencesStorageInterface;
use Kachnitel\AdminBundle\Twig\Components\EntityList;
use PHPUnit\Framework\Attributes as PHPUnit;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for EntityList::resolveEntityClass(), the #[PostHydrate] hook
 * that derives the fully-qualified entity class from entityShortClass and
 * the configured entity namespace when the caller hasn't passed entityClass
 * explicitly.
 */
#[PHPUnit\CoversClass(EntityList::class)]
#[PHPUnit\UsesClass(EntityListConfig::class)]
#[PHPUnit\Group('entity-class-resolution')]
final class EntityListEntityClassResolutionTest extends TestCase
{
    private function makeComponent(string $entityNamespace = 'App\\Entity\\'): EntityList
    {
        return new EntityList(
            $this->createStub(EntityListPermissionService::class),
            new EntityListConfig(entityNamespace: $entityNamespace),
            $this->createStub(DataSourceRegistry::class),
            $this->createStub(EntityListBatchService::class),
            $this->createStub(AdminPreferencesStorageInterface::class),
            $this->createStub(EntityListColumnService::class),
            $this->createStub(ArchiveService::class),
            $this->createStub(ObjectAuthorizationChecker::class),
        );
    }

    #[PHPUnit\Test]
    public function derivesEntityClassFromShortClassAndConfiguredNamespace(): void
    {
        $component = $this->makeComponent('App\\Entity\\');
        $component->entityShortClass = 'Product';

        $component->resolveEntityClass();

        $this->assertSame('App\\Entity\\Product', $component->entityClass);
    }

    #[PHPUnit\Test]
    public function respectsACustomEntityNamespace(): void
    {
        $component = $this->makeComponent('Acme\\Domain\\Catalog\\');
        $component->entityShortClass = 'Product';

        $component->resolveEntityClass();

        $this->assertSame('Acme\\Domain\\Catalog\\Product', $component->entityClass);
    }

    #[PHPUnit\Test]
    public function doesNotOverrideAnExplicitlyProvidedEntityClass(): void
    {
        $component = $this->makeComponent('App\\Entity\\');
        $component->entityShortClass = 'Product';
        $component->entityClass = 'App\\Legacy\\Product';

        $component->resolveEntityClass();

        $this->assertSame('App\\Legacy\\Product', $component->entityClass);
    }

    #[PHPUnit\Test]
    public function leavesEntityClassEmptyInDataSourceOnlyMode(): void
    {
        // No entityShortClass set — e.g. <twig:K:Admin:EntityList dataSourceId="..." />
        $component = $this->makeComponent('App\\Entity\\');

        $component->resolveEntityClass();

        $this->assertSame('', $component->entityClass);
    }

    #[PHPUnit\Test]
    public function isIdempotentAcrossRepeatedHydrationCycles(): void
    {
        $component = $this->makeComponent('App\\Entity\\');
        $component->entityShortClass = 'Product';

        $component->resolveEntityClass();
        $component->resolveEntityClass();

        $this->assertSame('App\\Entity\\Product', $component->entityClass);
    }

    #[PHPUnit\Test]
    public function resolvesEntityClassWhenDoctrineModeIsChecked(): void
    {
        $component = $this->makeComponent('App\\Entity\\');
        $component->entityShortClass = 'Product';

        $this->assertTrue($component->isDoctrineEntity());
        $this->assertSame('App\\Entity\\Product', $component->entityClass);
    }
}
