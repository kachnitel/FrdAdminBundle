<?php

namespace Frd\AdminBundle\Tests\Functional;

use Frd\AdminBundle\Tests\Fixtures\TestEntity;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\TwigComponent\ComponentRenderer;

/**
 * Functional test for LiveComponent rendering.
 *
 * This test actually renders the component to catch template errors
 * that unit tests miss.
 */
class LiveComponentRenderTest extends KernelTestCase
{
    private ComponentRenderer $renderer;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $this->renderer = static::getContainer()->get('ux.twig_component.component_renderer');
    }

    public function testAdminEntityListComponentRenders(): void
    {
        // Render the component with test entity class
        $rendered = $this->renderer->createAndRender('AdminEntityList', [
            'entityClass' => TestEntity::class,
        ]);

        // Component should render without errors
        $this->assertNotEmpty($rendered);

        // Should contain table structure
        $this->assertStringContainsString('<table', $rendered);
        $this->assertStringContainsString('</table>', $rendered);

        // Should contain filter row
        $this->assertStringContainsString('filters', $rendered);
    }

    public function testAdminEntityListWithEmptyResults(): void
    {
        $rendered = $this->renderer->createAndRender('AdminEntityList', [
            'entityClass' => TestEntity::class,
        ]);

        // Should show "no entities found" message when empty
        $this->assertStringContainsString('No', $rendered);
        $this->assertStringContainsString('TestEntity', $rendered);
    }

    public function testFilterMetadataIsGenerated(): void
    {
        $rendered = $this->renderer->createAndRender('AdminEntityList', [
            'entityClass' => TestEntity::class,
        ]);

        // Filters should be rendered based on metadata
        // At minimum we expect filter inputs for text fields
        $this->assertStringContainsString('data-model="columnFilters', $rendered);
    }
}
