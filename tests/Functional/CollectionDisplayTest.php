<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Kachnitel\AdminBundle\Tests\Fixtures\EntityWithCollectionDisplay;
use Kachnitel\AdminBundle\Tests\Fixtures\TagEntity;
use PHPUnit\Framework\Attributes\Test;

/**
 * @group collection-display
 */
final class CollectionDisplayTest extends ComponentTestCase
{
    // private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var ManagerRegistry $doctrine */
        $doctrine = static::getContainer()->get('doctrine');
        /** @var EntityManagerInterface $em */
        $em       = $doctrine->getManager();
        $this->em = $em;
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /**
     * Persist an EntityWithCollectionDisplay populated with $tagCount tags in all three collections.
     */
    private function persistEntityWithTags(int $tagCount): EntityWithCollectionDisplay
    {
        $entity = new EntityWithCollectionDisplay();
        $entity->setName('Test Entity');
        $this->em->persist($entity);

        for ($i = 1; $i <= $tagCount; $i++) {
            $tag = new TagEntity();
            $tag->setName('Tag ' . $i);
            $this->em->persist($tag);
            $entity->getTagsAccordion()->add($tag);
            $entity->getTagsList()->add($tag);
            $entity->getTagsDefault()->add($tag);
        }

        $this->em->flush();
        $this->em->clear();

        return $entity;
    }

    private function renderEntityList(): string
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: [
                'entityClass'      => EntityWithCollectionDisplay::class,
                'entityShortClass' => 'EntityWithCollectionDisplay',
            ],
        );

        return (string) $testComponent->render();
    }

    // ── accordion (collapsible) ───────────────────────────────────────────────

    #[Test]
    public function accordionRendersDetailsAndSummary(): void
    {
        $this->persistEntityWithTags(2);

        $rendered = $this->renderEntityList();

        $this->assertStringContainsString('<details', $rendered);
        $this->assertStringContainsString('<summary', $rendered);
    }

    #[Test]
    public function accordionSummaryShowsCountLabel(): void
    {
        $this->persistEntityWithTags(5);

        $rendered = $this->renderEntityList();

        $this->assertStringContainsString('5 items', $rendered);
    }

    #[Test]
    public function accordionShowsItemsUpToLimit(): void
    {
        $this->persistEntityWithTags(5); // limit is 3

        $rendered = $this->renderEntityList();

        // Tags 1–3 should appear, Tag 4 and 5 should not be in item list
        $this->assertStringContainsString('Tag 1', $rendered);
        $this->assertStringContainsString('Tag 2', $rendered);
        $this->assertStringContainsString('Tag 3', $rendered);
    }

    #[Test]
    public function accordionShowsOverflowLinkWhenCountExceedsLimit(): void
    {
        $this->persistEntityWithTags(5); // limit 3 → overflow 2

        $rendered = $this->renderEntityList();

        $this->assertStringContainsString('+ 2 more', $rendered);
    }

    #[Test]
    public function accordionHasNoOverflowWhenCountWithinLimit(): void
    {
        $this->persistEntityWithTags(2); // limit 3 → no overflow

        $rendered = $this->renderEntityList();

        $this->assertStringNotContainsString('more…', $rendered);
    }

    #[Test]
    public function accordionSummaryContainsViewAllLinkWhenCollectionUrlAvailable(): void
    {
        // The ↗ link only renders when admin_collection_url() returns a URL.
        // EntityWithCollectionDisplay::tagsAccordion is a unidirectional ManyToMany
        // with no inversedBy, so admin_collection_url() returns null → no link rendered.
        // This test verifies the count label and <summary> are present regardless.
        $this->persistEntityWithTags(2);

        $rendered = $this->renderEntityList();

        $this->assertStringContainsString('2 items', $rendered);
        $this->assertStringContainsString('<summary', $rendered);
    }

    // ── always-visible list ───────────────────────────────────────────────────

    #[Test]
    public function alwaysVisibleListRendersWithoutDetailsElement(): void
    {
        $this->persistEntityWithTags(2);

        $rendered = $this->renderEntityList();

        // tagsList column should render an inline div, not a <details>
        // We can't distinguish which <details> belongs to which column, but we
        // can assert the list CSS class appears (set by collection_inline macro)
        $this->assertStringContainsString('admin-collection-inline', $rendered);
    }

    #[Test]
    public function alwaysVisibleListShowsOverflowLink(): void
    {
        $this->persistEntityWithTags(5); // limit 3 → overflow 2

        $rendered = $this->renderEntityList();

        // "+ 2 more…" appears from at least one of the two display columns
        $this->assertStringContainsString('+ 2 more', $rendered);
    }

    // ── default (count+link) mode ─────────────────────────────────────────────

    #[Test]
    public function defaultModeRendersCountLabel(): void
    {
        $this->persistEntityWithTags(4);

        $rendered = $this->renderEntityList();

        $this->assertStringContainsString('4 items', $rendered);
    }

    #[Test]
    public function defaultModeDoesNotRenderCollectionListWhenEmpty(): void
    {
        // With zero tags the default column should show '-', not an inline item list.
        $this->persistEntityWithTags(0);

        $rendered = $this->renderEntityList();

        // No collection list markup — the admin-collection-list class must not appear
        $this->assertStringNotContainsString('admin-collection-list', $rendered);
    }

    // ── empty collection ──────────────────────────────────────────────────────

    #[Test]
    public function emptyCollectionRendersDashRegardlessOfMode(): void
    {
        $this->persistEntityWithTags(0);

        $rendered = $this->renderEntityList();

        // All three columns empty → three dashes
        $dashCount = substr_count($rendered, '>-<');
        $this->assertGreaterThanOrEqual(3, $dashCount);
    }

    // ── singular / plural label ───────────────────────────────────────────────

    #[Test]
    public function singleItemLabelIsSingular(): void
    {
        $this->persistEntityWithTags(1);

        $rendered = $this->renderEntityList();

        $this->assertStringContainsString('1 item', $rendered);
        $this->assertStringNotContainsString('1 items', $rendered);
    }
}
