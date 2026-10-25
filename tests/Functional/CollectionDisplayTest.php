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
        // The ↗ link only renders when admin_collection_url() returns a URL. Links now
        // carry a collection scope, so they no longer depend on an inverse field.
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
    public function collectionDialogRendersButtonAndLazyEmbeddedList(): void
    {
        $entity = new EntityWithCollectionDisplay();
        $entity->setName('Dialog owner');
        $this->em->persist($entity);

        $tag = new TagEntity();
        $tag->setName('Dialog item');
        $tag->setDialogOwner($entity);
        $this->em->persist($tag);
        $this->em->flush();
        $this->em->clear();

        $rendered = $this->renderEntityList();

        $this->assertStringContainsString('data-controller="admin-collection-dialog"', $rendered);
        $this->assertStringContainsString('<dialog', $rendered);
        $this->assertStringContainsString('loading="lazy"', $rendered);
        $this->assertStringContainsString('Dialog item', $rendered);
        // Owner class is part of the id so same-id/same-property owners can't collide.
        $this->assertMatchesRegularExpression('/id="collection-dialog-EntityWithCollectionDisplay-\d+-dialogItems"/', $rendered);
    }

    /**
     * UX cannot target one of two same-named nested lists, so a dialog inside an embedded
     * list would leak its filter/batch events into the embedded list around it. Dialog
     * cells therefore fall back to the plain count + link there.
     */
    #[Test]
    #[\PHPUnit\Framework\Attributes\Group('collection-scope')]
    public function collectionDialogFallsBackToPlainCellInsideAnEmbeddedList(): void
    {
        $entity = new EntityWithCollectionDisplay();
        $entity->setName('Dialog owner');
        $this->em->persist($entity);

        $tag = new TagEntity();
        $tag->setName('Dialog item');
        $tag->setDialogOwner($entity);
        $this->em->persist($tag);
        $this->em->flush();
        $this->em->clear();

        $rendered = (string) $this->createLiveComponent(
            name: 'K:Admin:EntityListEmbedded',
            data: [
                'entityClass'      => EntityWithCollectionDisplay::class,
                'entityShortClass' => 'EntityWithCollectionDisplay',
            ],
        )->render();

        $this->assertStringNotContainsString('data-controller="admin-collection-dialog"', $rendered);
        $this->assertStringNotContainsString('<dialog', $rendered);
        $this->assertStringContainsString('1 item', $rendered);
    }

    #[Test]
    public function collectionDialogUsesThemeDialogMacrosLikeInlineAdd(): void
    {
        $entity = new EntityWithCollectionDisplay();
        $entity->setName('Dialog owner');
        $this->em->persist($entity);

        $tag = new TagEntity();
        $tag->setName('Dialog item');
        $tag->setDialogOwner($entity);
        $this->em->persist($tag);
        $this->em->flush();
        $this->em->clear();

        $rendered = $this->renderEntityList();

        // Default (Bootstrap) theme: dialog_wide() centers via position-fixed + translate-middle
        $this->assertStringContainsString('position-fixed top-50 start-50 translate-middle', $rendered);
        $this->assertStringContainsString('modal-header', $rendered);
        $this->assertStringContainsString('modal-body', $rendered);
        $this->assertStringContainsString('btn-close', $rendered);
        // Shared hook for the app's ::backdrop rule (same class the inline-add dialog carries)
        $this->assertStringContainsString('admin-inline-dialog', $rendered);
        // No hand-rolled layout: it must come from the theme, not inline styles
        $this->assertStringNotContainsString('min(70rem,95vw)', $rendered);
    }

    // ── dialog combined with collectionDisplay ────────────────────────────────

    private function persistEntityWithDialogDisplayTags(int $tagCount): void
    {
        $entity = new EntityWithCollectionDisplay();
        $entity->setName('Dialog display owner');
        $this->em->persist($entity);

        for ($i = 1; $i <= $tagCount; $i++) {
            $tag = new TagEntity();
            $tag->setName('Dialog tag ' . $i);
            $this->em->persist($tag);
            $entity->getTagsDialogAccordion()->add($tag);
            $entity->getTagsDialogList()->add($tag);
        }

        $this->em->flush();
        $this->em->clear();
    }

    /**
     * @return list<string> ids of every <dialog> that has an ancestor with one of $ancestorTags
     */
    private function dialogIdsInside(string $rendered, string ...$ancestorTags): array
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8"?><body>' . $rendered . '</body>');
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $conditions = array_map(static fn (string $tag): string => 'ancestor::' . $tag, $ancestorTags);
        $ids = [];
        foreach ((new \DOMXPath($dom))->query('//dialog[' . implode(' or ', $conditions) . ']') ?: [] as $dialog) {
            \assert($dialog instanceof \DOMElement);
            $ids[] = $dialog->getAttribute('id');
        }

        return $ids;
    }

    /**
     * A click anywhere inside a <summary> toggles its <details>, and the dialog's content is
     * a DOM descendant of its parent even when it paints in the top layer — so the dialog
     * (and its button's controller wrapper) must sit outside <summary> and <details>.
     */
    #[Test]
    #[\PHPUnit\Framework\Attributes\Group('collection-scope')]
    public function collectionDialogInCollapsibleModeRendersOutsideSummaryAndDetails(): void
    {
        $this->persistEntityWithDialogDisplayTags(2);

        $rendered = $this->renderEntityList();

        $this->assertMatchesRegularExpression('/id="collection-dialog-EntityWithCollectionDisplay-\d+-tagsDialogAccordion"/', $rendered);
        $this->assertSame([], $this->dialogIdsInside($rendered, 'summary', 'details'));
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\Group('collection-scope')]
    public function collectionDialogWithCollectionDisplayKeepsTheInlineItemsAndLimit(): void
    {
        $this->persistEntityWithDialogDisplayTags(5); // limit 3 → "+ 2 more"

        $rendered = $this->renderEntityList();

        $this->assertStringContainsString('Dialog tag 1', $rendered);
        $this->assertStringContainsString('+ 2 more', $rendered);
        $this->assertMatchesRegularExpression('/id="collection-dialog-EntityWithCollectionDisplay-\d+-tagsDialogList"/', $rendered);
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\Group('collection-scope')]
    public function collectionDialogInAlwaysVisibleModeRendersOutsideDetails(): void
    {
        $this->persistEntityWithDialogDisplayTags(2);

        $rendered = $this->renderEntityList();

        $this->assertSame([], $this->dialogIdsInside($rendered, 'details'));
        $this->assertStringContainsString('admin-collection-inline', $rendered);
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
