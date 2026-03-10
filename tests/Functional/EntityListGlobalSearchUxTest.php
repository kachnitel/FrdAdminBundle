<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Kachnitel\AdminBundle\Twig\Components\EntityList;

/**
 * Functional tests for global search tooltip UX in EntityList.
 *
 * @group global-search
 */
class EntityListGlobalSearchUxTest extends ComponentTestCase
{
    /** @test */
    public function searchInputHasSimplePlaceholder(): void
    {
        $component = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => TestEntity::class,
                'entityShortClass' => 'TestEntity',
            ],
        );

        $rendered = (string) $component->render();

        $this->assertStringContainsString('placeholder="Search…"', $rendered);
        $this->assertStringNotContainsString('Global search across all columns', $rendered);
    }

    /** @test */
    public function searchHelpIconIsRenderedWithSearchableColumnLabel(): void
    {
        $component = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => TestEntity::class,
                'entityShortClass' => 'TestEntity',
            ],
        );

        $rendered = (string) $component->render();

        // Help icon element must be present
        $this->assertStringContainsString('admin-search-help', $rendered);

        // TestEntity has a 'name' string field — its label must appear in title
        $this->assertStringContainsString('Name', $rendered);
    }

    /** @test */
    public function renderedHelpIconHasNonEmptyTitle(): void
    {
        $component = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => TestEntity::class,
                'entityShortClass' => 'TestEntity',
            ],
        );

        $rendered = (string) $component->render();

        // The title attribute on admin-search-help must not be empty
        $this->assertMatchesRegularExpression(
            '/class="admin-search-help"[^>]+title="[^"]+"/s',
            $rendered,
        );
    }
}
