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
use Kachnitel\AdminBundle\Tests\Fixtures\TagEntity;
use Kachnitel\AdminBundle\Twig\Components\EntityList;
use Kachnitel\AdminBundle\Twig\Components\EntityListEmbedded;
use Kachnitel\DataSourceContracts\DataSourceInterface;
use Kachnitel\DataSourceContracts\PaginatedResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;

/**
 * Covers the EntityList / EntityListEmbedded inheritance and the `scope` LiveProp's
 * wiring into the data source.
 */
#[CoversClass(EntityList::class)]
#[CoversClass(EntityListEmbedded::class)]
#[UsesClass(EntityListConfig::class)]
#[Group('collection-dialog')]
#[Group('entity-list')]
final class EntityListEmbeddedTest extends TestCase
{
    private const URL_PROPS = ['search', 'sortBy', 'sortDirection', 'columnFilters', 'page', 'itemsPerPage', 'showArchived'];

    private const SCOPE = ['ownerClass' => 'App\\Entity\\Order', 'ownerId' => '5', 'property' => 'items'];

    /** @return \Iterator<string, array{string}> */
    public static function urlProps(): \Iterator
    {
        foreach (self::URL_PROPS as $prop) {
            yield $prop => [$prop];
        }
    }

    // ── URL binding is the only difference ────────────────────────────────────

    #[Test]
    #[DataProvider('urlProps')]
    public function listIsUrlBoundButEmbeddedIsNot(string $prop): void
    {
        $this->assertNotFalse(
            $this->liveProp(EntityList::class, $prop)->url(),
            sprintf('EntityList::$%s must stay URL-bound.', $prop),
        );
        $this->assertFalse(
            $this->liveProp(EntityListEmbedded::class, $prop)->url(),
            sprintf('EntityListEmbedded::$%s must not be URL-bound.', $prop),
        );
    }

    #[Test]
    #[DataProvider('urlProps')]
    public function urlPropsAreWritableOnBoth(string $prop): void
    {
        $this->assertTrue($this->liveProp(EntityList::class, $prop)->isIdentityWritable());
        $this->assertTrue($this->liveProp(EntityListEmbedded::class, $prop)->isIdentityWritable());
    }

    #[Test]
    public function bothRenderTheSameTemplate(): void
    {
        $this->assertSame(
            $this->template(EntityList::class),
            $this->template(EntityListEmbedded::class),
        );
    }

    #[Test]
    public function embeddedListExtendsEntityList(): void
    {
        $parent = (new \ReflectionClass(EntityListEmbedded::class))->getParentClass();

        $this->assertNotFalse($parent);
        $this->assertSame(EntityList::class, $parent->getName());
    }

    #[Test]
    public function scopeIsANonWritableLiveProp(): void
    {
        foreach ([EntityList::class, EntityListEmbedded::class] as $class) {
            $this->assertFalse(
                $this->liveProp($class, 'scope')->isIdentityWritable(),
                'scope must not be client-writable.',
            );
        }
    }

    #[Test]
    public function embeddedInitialisesItemsPerPageFromConfig(): void
    {
        $component = $this->makeComponent(EntityListEmbedded::class);

        $this->assertSame((new EntityListConfig())->defaultItemsPerPage, $component->itemsPerPage);
    }

    // ── Scope wiring ───────────────────────────────────────────────────────────

    #[Test]
    public function getEntitiesPassesResolvedScopeToDoctrineDataSource(): void
    {
        $dataSource = $this->createMock(DoctrineDataSource::class);
        $dataSource->expects($this->once())
            ->method('setScopeCondition')
            ->with('e.id IN (…)', ['scope_owner_id' => 5]);
        $dataSource->method('query')->willReturn($this->emptyResult());

        $resolver = $this->createStub(CollectionScopeResolver::class);
        $resolver->method('resolve')->willReturn(['e.id IN (…)', ['scope_owner_id' => 5]]);

        $component = $this->makeComponent(EntityListEmbedded::class, $dataSource, $resolver);
        $component->scope = self::SCOPE;

        $component->getEntities();
    }

    #[Test]
    public function getEntitiesClearsScopeWhenUnscoped(): void
    {
        $dataSource = $this->createMock(DoctrineDataSource::class);
        // Always set (null) so a stale scope on a shared instance cannot leak into this query.
        $dataSource->expects($this->once())->method('setScopeCondition')->with(null, []);
        $dataSource->method('query')->willReturn($this->emptyResult());

        $component = $this->makeComponent(EntityListEmbedded::class, $dataSource);

        $component->getEntities();
    }

    #[Test]
    public function scopeIsResolvedOncePerComponentInstance(): void
    {
        $dataSource = $this->createStub(DoctrineDataSource::class);
        $dataSource->method('query')->willReturn($this->emptyResult());

        $resolver = $this->createMock(CollectionScopeResolver::class);
        $resolver->expects($this->once())->method('resolve')->willReturn(['x', []]);

        $component = $this->makeComponent(EntityListEmbedded::class, $dataSource, $resolver);
        $component->scope = self::SCOPE;

        $component->checkPermissions();
        $component->getEntities();
    }

    #[Test]
    public function scopeOnNonDoctrineDataSourceFailsClosed(): void
    {
        $resolver = $this->createStub(CollectionScopeResolver::class);
        $resolver->method('resolve')->willReturn(['x', []]);

        $component = $this->makeComponent(
            EntityListEmbedded::class,
            $this->createStub(DataSourceInterface::class),
            $resolver,
        );
        $component->scope = self::SCOPE;

        $this->expectException(\LogicException::class);

        $component->getEntities();
    }

    #[Test]
    public function checkPermissionsPropagatesScopeDenial(): void
    {
        $resolver = $this->createStub(CollectionScopeResolver::class);
        $resolver->method('resolve')->willThrowException(new AccessDeniedException());

        $component = $this->makeComponent(EntityListEmbedded::class, $this->createStub(DoctrineDataSource::class), $resolver);
        $component->scope = self::SCOPE;

        $this->expectException(AccessDeniedException::class);

        $component->checkPermissions();
    }

    #[Test]
    #[Group('collection-scope')]
    public function scopeIsBoundToTheListsOwnEntityClass(): void
    {
        $dataSource = $this->createStub(DoctrineDataSource::class);
        $dataSource->method('getEntityClass')->willReturn(TagEntity::class);

        $resolver = $this->createMock(CollectionScopeResolver::class);
        $resolver->expects($this->once())
            ->method('resolve')
            ->with(self::SCOPE, TagEntity::class)
            ->willReturn(['x', []]);

        $component = $this->makeComponent(EntityListEmbedded::class, $dataSource, $resolver);
        $component->scope = self::SCOPE;

        $component->checkPermissions();
    }

    #[Test]
    public function emptyScopeNeverTouchesTheResolver(): void
    {
        $resolver = $this->createMock(CollectionScopeResolver::class);
        $resolver->expects($this->never())->method('resolve');

        $component = $this->makeComponent(EntityListEmbedded::class, null, $resolver);

        $component->checkPermissions();
    }

    // ── Event scoping ──────────────────────────────────────────────────────────

    #[Test]
    #[Group('collection-scope')]
    public function listComponentNameIsTheRegisteredNameOfTheConcreteClass(): void
    {
        $this->assertSame('K:Admin:EntityList', $this->makeComponent(EntityList::class)->getListComponentName());
        $this->assertSame('K:Admin:EntityListEmbedded', $this->makeComponent(EntityListEmbedded::class)->getListComponentName());
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * @param class-string<EntityList|EntityListEmbedded> $class
     */
    private function makeComponent(
        string $class,
        ?DataSourceInterface $dataSource = null,
        ?CollectionScopeResolver $resolver = null,
    ): EntityList|EntityListEmbedded {
        $resolver ??= $this->createStub(CollectionScopeResolver::class);
        $permissions = $this->createStub(EntityListPermissionService::class);
        $permissions->method('canViewList')->willReturn(true);

        $registry = $this->createStub(DataSourceRegistry::class);
        if ($dataSource !== null) {
            $registry->method('resolve')->willReturn($dataSource);
        }

        $component = new $class(
            $permissions,
            new EntityListConfig(),
            $registry,
            $this->createStub(EntityListBatchService::class),
            $this->createStub(AdminPreferencesStorageInterface::class),
            $this->createStub(EntityListColumnService::class),
            $this->createStub(ArchiveService::class),
            $this->createStub(ObjectAuthorizationChecker::class),
            $resolver,
        );
        $component->entityClass      = 'App\\Entity\\Product';
        $component->entityShortClass = 'Product';

        return $component;
    }

    private function emptyResult(): PaginatedResult
    {
        return new PaginatedResult(items: [], totalItems: 0, currentPage: 1, itemsPerPage: 20);
    }

    /**
     * @param class-string $class
     */
    private function liveProp(string $class, string $property): LiveProp
    {
        $attributes = (new \ReflectionProperty($class, $property))->getAttributes(LiveProp::class);
        $this->assertNotEmpty($attributes, sprintf('%s::$%s has no #[LiveProp].', $class, $property));

        return $attributes[0]->newInstance();
    }

    /**
     * @param class-string $class
     */
    private function template(string $class): ?string
    {
        $attributes = (new \ReflectionClass($class))->getAttributes(AsLiveComponent::class);
        $templateProperty = new \ReflectionProperty(AsTwigComponent::class, 'template');
        $template = $templateProperty->getValue($attributes[0]->newInstance());

        if (!is_string($template) && $template !== null) {
            throw new \UnexpectedValueException('The component template attribute must be a string or null.');
        }

        return $template;
    }
}
