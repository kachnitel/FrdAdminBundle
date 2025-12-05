<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\Tests\Fixtures\ConfiguredEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Kachnitel\AdminBundle\Twig\Components\EntityList;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/**
 * Tests that the columns configuration from #[Admin] attribute is respected.
 */
class EntityListColumnsTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testColumnsFromAdminAttributeAreUsed(): void
    {
        // TestEntity has columns: ['id', 'name', 'active'] in its #[Admin] attribute
        $testComponent = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => TestEntity::class,
                'entityShortClass' => 'TestEntity',
            ]
        );

        $columns = $testComponent->component()->getColumns();

        // Should only include the columns specified in the #[Admin] attribute
        $this->assertSame(['id', 'name', 'active'], $columns);

        // Should NOT include other fields like 'description', 'quantity', 'price', etc.
        $this->assertNotContains('description', $columns);
        $this->assertNotContains('quantity', $columns);
        $this->assertNotContains('price', $columns);
        $this->assertNotContains('createdAt', $columns);
    }

    public function testAllColumnsUsedWhenNotConfigured(): void
    {
        // ConfiguredEntity has columns: ['id', 'name', 'email', 'status', 'createdAt']
        $testComponent = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => ConfiguredEntity::class,
                'entityShortClass' => 'ConfiguredEntity',
            ]
        );

        $columns = $testComponent->component()->getColumns();

        // Should include the configured columns
        $this->assertSame(['id', 'name', 'email', 'status', 'createdAt'], $columns);

        // Should NOT include excluded columns
        $this->assertNotContains('password', $columns);
        $this->assertNotContains('secret', $columns);
    }

    public function testExcludeColumnsAreRespected(): void
    {
        // ConfiguredEntity has excludeColumns: ['password', 'secret']
        $testComponent = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => ConfiguredEntity::class,
                'entityShortClass' => 'ConfiguredEntity',
            ]
        );

        $columns = $testComponent->component()->getColumns();

        // Excluded columns should not be present
        $this->assertNotContains('password', $columns);
        $this->assertNotContains('secret', $columns);

        // Other columns should be present
        $this->assertContains('id', $columns);
        $this->assertContains('name', $columns);
        $this->assertContains('email', $columns);
    }

    public function testColumnsInSpecifiedOrder(): void
    {
        // ConfiguredEntity specifies order: ['id', 'name', 'email', 'status', 'createdAt']
        $testComponent = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => ConfiguredEntity::class,
                'entityShortClass' => 'ConfiguredEntity',
            ]
        );

        $columns = $testComponent->component()->getColumns();

        // Columns should be in the exact order specified
        $expectedOrder = ['id', 'name', 'email', 'status', 'createdAt'];
        $this->assertSame($expectedOrder, array_values($columns));
    }

    public function testRenderedTableHasCorrectColumns(): void
    {
        // Test that the rendered HTML includes only the configured columns
        $testComponent = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => TestEntity::class,
                'entityShortClass' => 'TestEntity',
            ]
        );

        $rendered = (string) $testComponent->render();

        // Should have headers for configured columns
        $this->assertStringContainsString('Id', $rendered); // humanized 'id'
        $this->assertStringContainsString('Name', $rendered);
        $this->assertStringContainsString('Active', $rendered);

        // Should NOT have headers for non-configured columns
        $this->assertStringNotContainsString('Description', $rendered);
        $this->assertStringNotContainsString('Quantity', $rendered);
        $this->assertStringNotContainsString('Price', $rendered);
    }

    public function testTableDataRespectsColumnConfiguration(): void
    {
        // Create test entities with data
        $em = self::getContainer()->get('doctrine')->getManager();

        $entity1 = new TestEntity();
        $entity1->setName('Test Product 1');
        $entity1->setQuantity(100);
        $entity1->setPrice('29.99');

        $entity2 = new TestEntity();
        $entity2->setName('Test Product 2');
        $entity2->setQuantity(50);
        $entity2->setPrice('49.99');

        $em->persist($entity1);
        $em->persist($entity2);
        $em->flush();

        // TestEntity has columns: ['id', 'name', 'active'] - should NOT show quantity/price
        $testComponent = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => TestEntity::class,
                'entityShortClass' => 'TestEntity',
            ]
        );

        $rendered = (string) $testComponent->render();

        // Should render configured column data
        $this->assertStringContainsString('Test Product 1', $rendered);
        $this->assertStringContainsString('Test Product 2', $rendered);

        // Should NOT render data from excluded columns in table body
        // The actual numeric values should not appear as table cell content
        $this->assertStringNotContainsString('>100<', $rendered); // quantity value
        $this->assertStringNotContainsString('>50<', $rendered); // quantity value
        $this->assertStringNotContainsString('>29.99<', $rendered); // price value
        $this->assertStringNotContainsString('>49.99<', $rendered); // price value

        // Clean up
        $em->remove($entity1);
        $em->remove($entity2);
        $em->flush();
    }
}
