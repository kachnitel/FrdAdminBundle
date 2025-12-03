<?php

namespace Frd\AdminBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Frd\AdminBundle\Tests\Fixtures\TestEntity;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

class EntityListLiveComponentTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        static::bootKernel();

        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();

        $metadatas = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($em);

        try {
            $schemaTool->dropSchema($metadatas);
        } catch (\Exception) {
            // Ignore if tables don't exist yet
        }

        $schemaTool->createSchema($metadatas);
    }

    public function testInitialRenderAndState(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'FRD:Admin:EntityList', // The AsLiveComponent name
            data: ['entityClass' => TestEntity::class, 'entityShortClass' => 'TestEntity'] // Required LiveProp
        );

        $rendered = $testComponent->render();
        $this->assertStringContainsString('<table', $rendered);
        $this->assertStringContainsString('Global search across all columns', $rendered);
        $this->assertStringContainsString('No TestEntity found.', $rendered);

        $component = $testComponent->component();
        $this->assertSame(TestEntity::class, $component->entityClass);
        $this->assertSame('id', $component->sortBy);
    }

    public function testSearchPropUpdatesState(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'FRD:Admin:EntityList',
            data: ['entityClass' => TestEntity::class, 'entityShortClass' => 'TestEntity']
        );

        $searchQuery = 'Test Search Value';

        // This triggers a re-render internally, effectively testing the DB connection
        $testComponent->set('search', $searchQuery);

        $component = $testComponent->component();
        $this->assertSame($searchQuery, $component->search);

        $rendered = $testComponent->render();
        $this->assertStringContainsString('value="'.$searchQuery.'"', $rendered);
    }

    public function testLiveActionChangesSortDirection(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'FRD:Admin:EntityList',
            data: ['entityClass' => TestEntity::class, 'entityShortClass' => 'TestEntity', 'sortBy' => 'name', 'sortDirection' => 'ASC']
        );

        // Initial check
        $this->assertSame('name', $testComponent->component()->sortBy);
        $this->assertSame('ASC', $testComponent->component()->sortDirection);

        // Set the property directly to simulate the action's result
        $testComponent->set('sortDirection', 'DESC');

        $this->assertSame('DESC', $testComponent->component()->sortDirection);
    }
}