<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\Service\Preferences\PreferenceKeys;
use Kachnitel\AdminBundle\Tests\Fixtures\EntityWithColumnVisibility;
use Kachnitel\AdminBundle\Twig\Components\EntityList;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Functional tests for column visibility feature.
 *
 * Tests the end-to-end flow of column visibility toggling and persistence.
 */
class ColumnVisibilityTest extends ComponentTestCase
{
    private Session $session;

    protected function setUp(): void
    {
        parent::setUp();

        // Create and push a request with session to the request stack
        $this->session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($this->session);

        $requestStack = self::getContainer()->get('request_stack');
        $requestStack->push($request);
    }

    public function testAllColumnsVisibleByDefault(): void
    {
        $testComponent = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => EntityWithColumnVisibility::class,
                'entityShortClass' => 'EntityWithColumnVisibility',
            ],
        );

        $columns = $testComponent->component()->getColumns();
        $visibleColumns = $testComponent->component()->getVisibleColumns();

        // All configured columns should be visible by default
        $this->assertSame(['id', 'name', 'description', 'status'], $columns);
        $this->assertSame($columns, $visibleColumns);
    }

    public function testHiddenColumnsNotReturnedByGetVisibleColumns(): void
    {
        // Pre-set hidden columns directly in session (using the storage key prefix)
        $preferenceKey = PreferenceKeys::COLUMN_VISIBILITY . '.EntityWithColumnVisibility';
        $this->session->set('kachnitel_admin.pref.' . $preferenceKey, ['description', 'status']);

        $testComponent = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => EntityWithColumnVisibility::class,
                'entityShortClass' => 'EntityWithColumnVisibility',
            ],
        );

        $visibleColumns = $testComponent->component()->getVisibleColumns();

        // Only non-hidden columns should be visible
        $this->assertSame(['id', 'name'], $visibleColumns);
        $this->assertNotContains('description', $visibleColumns);
        $this->assertNotContains('status', $visibleColumns);
    }

    public function testHiddenColumnsNotRenderedInTable(): void
    {
        // Create and persist a test entity for rendering
        $entity = new EntityWithColumnVisibility();
        $entity->setName('Test Entity');
        $entity->setDescription('This should be hidden');
        $entity->setStatus('active');
        $this->em->persist($entity);
        $this->em->flush();

        // Pass hiddenColumns directly as component data
        $testComponent = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => EntityWithColumnVisibility::class,
                'entityShortClass' => 'EntityWithColumnVisibility',
                'hiddenColumns' => ['description'],
            ],
        );

        $rendered = (string) $testComponent->render();

        // Visible columns should be in the rendered output
        $this->assertStringContainsString('Name', $rendered);
        $this->assertStringContainsString('Status', $rendered);
        $this->assertStringContainsString('Test Entity', $rendered);

        // Hidden column data should NOT be rendered in table body
        // The text content "This should be hidden" should not appear
        $this->assertStringNotContainsString('This should be hidden', $rendered);

        // Verify that description column checkbox in visibility toggle is NOT checked
        $this->assertMatchesRegularExpression(
            '/<input[^>]*value="description"[^>]*data-action="live#action"[^>]*>/',
            $rendered,
            'Description toggle should exist but not be checked'
        );
        $this->assertStringNotContainsString(
            'value="description"' . "\n" . '                        checked',
            $rendered,
            'Description checkbox should not be checked'
        );
    }

    public function testColumnVisibilityToggleRendersWhenEnabled(): void
    {
        $testComponent = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => EntityWithColumnVisibility::class,
                'entityShortClass' => 'EntityWithColumnVisibility',
            ],
        );

        // Component should support column visibility
        $this->assertTrue($testComponent->component()->supportsColumnVisibility());

        $rendered = (string) $testComponent->render();

        // Column visibility toggle UI should be present
        $this->assertStringContainsString('toggleColumnVisibility', $rendered);
    }

    public function testToggleColumnVisibilityHidesColumn(): void
    {
        $testComponent = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => EntityWithColumnVisibility::class,
                'entityShortClass' => 'EntityWithColumnVisibility',
            ],
        );

        // Initially all columns visible
        $this->assertSame(['id', 'name', 'description', 'status'], $testComponent->component()->getVisibleColumns());

        // Directly call the toggle method on the component instance
        $testComponent->component()->toggleColumnVisibility('description');

        // description should now be hidden
        $this->assertSame(['id', 'name', 'status'], $testComponent->component()->getVisibleColumns());

        // Verify hiddenColumns was updated
        $this->assertContains('description', $testComponent->component()->hiddenColumns);
    }

    public function testToggleColumnVisibilityShowsHiddenColumn(): void
    {
        // Create component
        $testComponent = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => EntityWithColumnVisibility::class,
                'entityShortClass' => 'EntityWithColumnVisibility',
            ],
        );

        // Get the component instance once and reuse it
        $component = $testComponent->component();

        // Manually set hiddenColumns on the component instance
        $component->hiddenColumns = ['description'];

        // Verify description is hidden
        $this->assertNotContains('description', $component->getVisibleColumns());

        // Directly call the toggle method to show it
        $component->toggleColumnVisibility('description');

        // description should now be visible
        $this->assertContains('description', $component->getVisibleColumns());

        // Verify hiddenColumns was updated
        $this->assertEmpty($component->hiddenColumns);
    }

    public function testColumnVisibilityPreferencesPersistAcrossComponentInstances(): void
    {
        // Set preferences in session (using the storage key prefix)
        $preferenceKey = PreferenceKeys::COLUMN_VISIBILITY . '.EntityWithColumnVisibility';
        $this->session->set('kachnitel_admin.pref.' . $preferenceKey, ['status']);

        // Create first component instance
        $component1 = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => EntityWithColumnVisibility::class,
                'entityShortClass' => 'EntityWithColumnVisibility',
            ],
        );

        $this->assertSame(['id', 'name', 'description'], $component1->component()->getVisibleColumns());

        // Create second component instance (simulating page refresh)
        $component2 = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => EntityWithColumnVisibility::class,
                'entityShortClass' => 'EntityWithColumnVisibility',
            ],
        );

        // Second instance should also have status hidden
        $this->assertSame(['id', 'name', 'description'], $component2->component()->getVisibleColumns());
    }
}
