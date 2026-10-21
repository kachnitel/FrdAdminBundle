<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\Service\CollectionScopeResolver;
use Kachnitel\AdminBundle\Service\EntityListQueryService;
use Kachnitel\AdminBundle\Tests\Fixtures\TagEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('functional')]
#[Group('collection-dialog')]
#[Group('collection-scope')]
final class CollectionScopeQueryTest extends ComponentTestCase
{
    /** @return iterable<string, array{string}> */
    public static function collectionProperties(): iterable
    {
        yield 'one-to-many' => ['tags'];
        yield 'unidirectional many-to-many' => ['items'];
        yield 'inverse-side many-to-many' => ['sharedItems'];
    }

    #[DataProvider('collectionProperties')]
    public function testScopeQueryReturnsOnlyItemsAssociatedWithTheOwner(string $property): void
    {
        $owner = new TestEntity();
        $owner->setName('Scoped owner');
        $this->em->persist($owner);
        $this->em->flush();

        $decoy = new TestEntity();
        $decoy->setName('Decoy owner ' . $owner->getId());
        $this->em->persist($decoy);

        $ownedItem = new TagEntity();
        $ownedItem->setName('Owned item');
        $decoyItem = new TagEntity();
        $decoyItem->setName('Decoy item');
        $this->em->persist($ownedItem);
        $this->em->persist($decoyItem);

        $this->associate($property, $owner, $ownedItem);
        $this->associate($property, $decoy, $decoyItem);
        $this->em->flush();

        /** @var CollectionScopeResolver $scopeResolver */
        $scopeResolver = static::getContainer()->get(CollectionScopeResolver::class);
        [$dql, $parameters] = $scopeResolver->resolve([
            'ownerClass' => TestEntity::class,
            'ownerId' => (string) $owner->getId(),
            'property' => $property,
        ]);

        /** @var EntityListQueryService $queryService */
        $queryService = static::getContainer()->get(EntityListQueryService::class);
        $result = $queryService->getEntities(
            entityClass: TagEntity::class,
            repositoryMethod: null,
            search: '',
            columnFilters: [],
            filterMetadata: [],
            sortBy: 'id',
            sortDirection: 'ASC',
            page: 1,
            itemsPerPage: 50,
            scopeDqlCondition: $dql,
            scopeParameters: $parameters,
        );

        $this->assertCount(1, $result['entities']);
        $this->assertSame($ownedItem->getId(), $result['entities'][0]->getId());
    }

    private function associate(string $property, TestEntity $owner, TagEntity $item): void
    {
        match ($property) {
            'tags' => $owner->addTag($item),
            'items' => $owner->getItems()->add($item),
            'sharedItems' => $item->getSharedOwners()->add($owner),
        };
    }
}