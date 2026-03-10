<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\DataSource\ColumnGroup;
use Kachnitel\AdminBundle\DataSource\DoctrineDataSourceFactory;
use Kachnitel\AdminBundle\Tests\Fixtures\EntityWithConfiguredGroups;
use Kachnitel\AdminBundle\Tests\Fixtures\EntityWithGroupedColumns;
use Kachnitel\AdminBundle\Twig\Components\EntityList;

/**
 * Functional tests for composite column display configuration.
 *
 * EntityWithConfiguredGroups fixture:
 *   - 'name_block'    → HEADER_COLLAPSIBLE, SUB_LABELS_ICON
 *   - 'address_block' → HEADER_TEXT,        SUB_LABELS_HIDDEN
 *
 * EntityWithGroupedColumns fixture (no AdminColumnGroup) → HEADER_TEXT default.
 *
 * @group composite-columns
 */
class EntityListColumnGroupConfigTest extends ComponentTestCase
{
    // ──────────────────────────────────────────────────────────────────────────
    // Data source: config propagation
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function dataSourceAppliesAdminColumnGroupConfig(): void
    {
        /** @var DoctrineDataSourceFactory $factory */
        $factory = static::getContainer()->get(DoctrineDataSourceFactory::class);
        $ds = $factory->create(EntityWithConfiguredGroups::class);

        $this->assertNotNull($ds);
        $slots = $ds->getColumnGroups();

        $groups = array_values(array_filter($slots, fn ($s) => $s instanceof ColumnGroup));
        $this->assertCount(2, $groups);

        /** @var ColumnGroup $nameGroup */
        $nameGroup = current(array_filter($groups, fn (ColumnGroup $g) => $g->id === 'name_block'));
        $this->assertSame(ColumnGroup::SUB_LABELS_ICON, $nameGroup->subLabels);
        $this->assertSame(ColumnGroup::HEADER_COLLAPSIBLE, $nameGroup->header);

        /** @var ColumnGroup $addrGroup */
        $addrGroup = current(array_filter($groups, fn (ColumnGroup $g) => $g->id === 'address_block'));
        $this->assertSame(ColumnGroup::SUB_LABELS_HIDDEN, $addrGroup->subLabels);
        $this->assertSame(ColumnGroup::HEADER_TEXT, $addrGroup->header);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // header: 'text' mode
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function headerTextModeRendersGroupLabelWithNoSortOrFilterRows(): void
    {
        // address_block uses HEADER_TEXT — plain label, no sort/filter rows for its columns.
        // name_block on the same page uses HEADER_COLLAPSIBLE, which is correct and expected.
        $testComponent = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => EntityWithConfiguredGroups::class,
                'entityShortClass' => 'EntityWithConfiguredGroups',
            ],
        );

        $html = (string) $testComponent->render();

        // address_block label appears as plain text
        $this->assertStringContainsString('Address block', $html);
        // HEADER_TEXT: no sort buttons for address group sub-columns in the header
        $this->assertStringNotContainsString('data-live-column-param="city"', $html);
        $this->assertStringNotContainsString('data-live-column-param="country"', $html);
        // HEADER_TEXT: no <details> for address_block (the collapsible is only for name_block)
        // We can verify by checking the full page has exactly one <details> element
        $this->assertSame(1, substr_count($html, '<details'));
    }

    /** @test */
    public function defaultGroupWithNoConfigUsesTextHeader(): void
    {
        // EntityWithGroupedColumns has no AdminColumnGroup — must default to HEADER_TEXT.
        // Requires DoctrineDataSource fallback to be ColumnGroup::HEADER_TEXT, not HEADER_FULL.
        $testComponent = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => EntityWithGroupedColumns::class,
                'entityShortClass' => 'EntityWithGroupedColumns',
            ],
        );

        $html = (string) $testComponent->render();

        // Group label present as plain text
        $this->assertStringContainsString('Name block', $html);
        // HEADER_TEXT: no <details> toggle
        $this->assertStringNotContainsString('<details', $html);
        // HEADER_TEXT: no sort buttons for group sub-columns in the header
        $this->assertStringNotContainsString('data-live-column-param="firstName"', $html);
        $this->assertStringNotContainsString('data-live-column-param="lastName"', $html);
        // HEADER_TEXT: no admin-composite-group-label div (that is only for 'full' and 'collapsible')
        $this->assertStringNotContainsString('admin-composite-group-label', $html);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // header: 'collapsible' mode
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function headerCollapsibleModeRendersDetailsElement(): void
    {
        $entity = new EntityWithConfiguredGroups();
        $entity->setFirstName('Jane')->setLastName('Doe')
               ->setCity('Vancouver')->setCountry('Canada')
               ->setEmail('jane@example.com');
        $this->em->persist($entity);
        $this->em->flush();

        $testComponent = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => EntityWithConfiguredGroups::class,
                'entityShortClass' => 'EntityWithConfiguredGroups',
            ],
        );

        $html = (string) $testComponent->render();

        // name_block uses HEADER_COLLAPSIBLE
        $this->assertStringContainsString('admin-composite-header-collapsible', $html);
        $this->assertStringContainsString('<details', $html);
        // The <summary> carries the group label (not a bare <div>)
        $this->assertStringContainsString('<summary class="admin-composite-group-label">', $html);
        $this->assertStringContainsString('Name block', $html);
        // Collapsed by default — no `open` attribute
        $this->assertStringNotContainsString('<details open', $html);
    }

    /** @test */
    public function headerCollapsibleModeContainsSubColumnFiltersInDom(): void
    {
        $testComponent = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => EntityWithConfiguredGroups::class,
                'entityShortClass' => 'EntityWithConfiguredGroups',
            ],
        );

        $html = (string) $testComponent->render();

        // Sub-column sort and filter inputs are inside the <details> (in DOM, hidden by default)
        $this->assertStringContainsString('data-live-column-param="firstName"', $html);
        $this->assertStringContainsString('data-live-column-param="lastName"', $html);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // header: 'full' mode — template branch distinguishable from collapsible
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function headerCollapsibleUsesDetailsSummaryNotPlainDiv(): void
    {
        // HEADER_FULL renders: <div class="admin-composite-group-label">
        // HEADER_COLLAPSIBLE renders: <summary class="admin-composite-group-label">
        // This confirms the two branches produce distinct markup.
        $testComponent = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => EntityWithConfiguredGroups::class,
                'entityShortClass' => 'EntityWithConfiguredGroups',
            ],
        );

        $html = (string) $testComponent->render();

        // Collapsible group label is a <summary>, not a bare <div>
        $this->assertStringContainsString('<summary class="admin-composite-group-label">', $html);
        $this->assertStringNotContainsString('<div class="admin-composite-group-label">', $html);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // subLabels modes
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function subLabelsIconModeRendersIconWithColumnLabelTitle(): void
    {
        $entity = new EntityWithConfiguredGroups();
        $entity->setFirstName('Jane')->setLastName('Doe')
               ->setCity('Vancouver')->setCountry('Canada')
               ->setEmail('jane@example.com');
        $this->em->persist($entity);
        $this->em->flush();

        $testComponent = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => EntityWithConfiguredGroups::class,
                'entityShortClass' => 'EntityWithConfiguredGroups',
            ],
        );

        $html = (string) $testComponent->render();

        $this->assertStringContainsString('admin-composite-cell-label--icon', $html);
        $this->assertStringContainsString('title="First name"', $html);
        $this->assertStringContainsString('title="Last name"', $html);
    }

    /** @test */
    public function subLabelsHiddenModeRendersNoLabelSpan(): void
    {
        $entity = new EntityWithConfiguredGroups();
        $entity->setFirstName('Alice')->setLastName('Smith')
               ->setCity('Toronto')->setCountry('Canada')
               ->setEmail('alice@example.com');
        $this->em->persist($entity);
        $this->em->flush();

        $testComponent = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => EntityWithConfiguredGroups::class,
                'entityShortClass' => 'EntityWithConfiguredGroups',
            ],
        );

        $html = (string) $testComponent->render();

        $this->assertStringContainsString('Toronto', $html);
        $this->assertStringContainsString('Canada', $html);
        $this->assertStringNotContainsString('>City<', $html);
        $this->assertStringNotContainsString('>Country<', $html);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // rowspan fix
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function compositeThAlwaysHasRowspan2(): void
    {
        $testComponent = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => EntityWithConfiguredGroups::class,
                'entityShortClass' => 'EntityWithConfiguredGroups',
            ],
        );

        $html = (string) $testComponent->render();

        $this->assertStringContainsString('class="admin-composite-th" rowspan="2"', $html);
    }
}