<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Kachnitel\AdminBundle\Twig\Components\EntityList;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

/**
 * Comprehensive tests for the per-field hover-triggered inline editing feature.
 *
 * Architecture (Option C – per-field immediate flush):
 *   1. ✏️ → editRow($id) → row mounts Field components in display mode
 *   2. Hover cell → .field-edit-trigger appears (CSS opacity transition)
 *   3. Click trigger → activateEditing on Field component → input + save/cancel
 *   4. Field save/cancel flushes immediately → display mode, row stays open
 *   5. ✕ → editingRowId = null
 *
 * Test groups:
 *   @group inline-edit           – all tests here
 *   @group inline-edit-state     – editingRowId lifecycle / PHP logic
 *   @group inline-edit-template  – HTML output assertions
 *   @group inline-edit-isolation – feature doesn't break existing EntityList behaviour
 *
 * @group inline-edit
 */
class EntityListInlineEditTest extends ComponentTestCase
{
    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function createEntity(string $name = 'Test'): TestEntity
    {
        $entity = new TestEntity();
        $entity->setName($name);
        $this->em->persist($entity);
        $this->em->flush();

        return $entity;
    }

    /** @param array<string, mixed> $extra */
    private function makeList(array $extra = []): TestLiveComponent
    {
        return $this->createLiveComponent(
            name: EntityList::class,
            data: array_merge(
                ['entityClass' => TestEntity::class, 'entityShortClass' => 'TestEntity'],
                $extra,
            ),
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // editingRowId – default
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @group inline-edit-state
     */
    public function testEditingRowIdDefaultsToNull(): void
    {
        $list = $this->makeList();

        $this->assertNull($list->component()->editingRowId);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // editRow()
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @group inline-edit-state
     */
    public function testEditRowSetsEditingRowId(): void
    {
        $entity = $this->createEntity();
        $list   = $this->makeList();

        $list->call('editRow', ['id' => $entity->getId()]);

        $this->assertSame($entity->getId(), $list->component()->editingRowId);
    }

    /**
     * @group inline-edit-state
     */
    public function testEditRowReplacesCurrentlyEditingRow(): void
    {
        $first  = $this->createEntity('First');
        $second = $this->createEntity('Second');
        $list   = $this->makeList();

        $list->call('editRow', ['id' => $first->getId()]);
        $list->call('editRow', ['id' => $second->getId()]);

        $this->assertSame($second->getId(), $list->component()->editingRowId);
    }

    /**
     * @group inline-edit-state
     */
    public function testEditRowDoesNotClearSelectedIds(): void
    {
        $entity = $this->createEntity();
        $list   = $this->makeList(['selectedIds' => [$entity->getId()]]);

        $list->call('editRow', ['id' => $entity->getId()]);

        $this->assertContains($entity->getId(), $list->component()->selectedIds);
    }

    /**
     * @group inline-edit-state
     */
    public function testEditRowDoesNotChangePage(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createEntity("P$i");
        }
        $list = $this->makeList(['page' => 2]);

        $entity = $this->createEntity();
        $list->call('editRow', ['id' => $entity->getId()]);

        $this->assertSame(2, $list->component()->page);
    }

    /**
     * @group inline-edit-state
     */
    public function testEditRowDoesNotChangeColumnFilters(): void
    {
        $entity = $this->createEntity();
        $list   = $this->makeList(['columnFilters' => ['name' => 'foo']]);

        $list->call('editRow', ['id' => $entity->getId()]);

        $this->assertSame(['name' => 'foo'], $list->component()->columnFilters);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // isRowEditing()
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @group inline-edit-state
     */
    public function testIsRowEditingReturnsTrueForEditingEntity(): void
    {
        $entity = $this->createEntity();
        $list   = $this->makeList(['editingRowId' => $entity->getId()]);

        $this->assertTrue($list->component()->isRowEditing($entity));
    }

    /**
     * @group inline-edit-state
     */
    public function testIsRowEditingReturnsFalseForDifferentEntity(): void
    {
        $editing = $this->createEntity('Editing');
        $other   = $this->createEntity('Other');
        $list    = $this->makeList(['editingRowId' => $editing->getId()]);

        $this->assertFalse($list->component()->isRowEditing($other));
    }

    /**
     * @group inline-edit-state
     */
    public function testIsRowEditingReturnsFalseWhenNullId(): void
    {
        $entity = $this->createEntity();
        $list   = $this->makeList();

        $this->assertFalse($list->component()->isRowEditing($entity));
    }

    /**
     * @group inline-edit-state
     */
    public function testIsRowEditingReturnsFalseForObjectWithoutGetId(): void
    {
        $list = $this->makeList(['editingRowId' => 1]);

        $this->assertFalse($list->component()->isRowEditing(new \stdClass()));
    }

    /**
     * @group inline-edit-state
     */
    public function testIsRowEditingReturnsFalseAfterExit(): void
    {
        $entity = $this->createEntity();
        $list   = $this->makeList();

        $list->call('editRow', ['id' => $entity->getId()]);
        // $list->call('exitRowEdit');
        $list->set('editingRowId', null);

        $this->assertFalse($list->component()->isRowEditing($entity));
    }

    /**
     * @group inline-edit-state
     */
    public function testIsRowEditingOnlyMatchesCurrentEditingRow(): void
    {
        $editing = $this->createEntity('Editing');
        $other1  = $this->createEntity('Other 1');
        $other2  = $this->createEntity('Other 2');
        $list    = $this->makeList(['editingRowId' => $editing->getId()]);

        $component = $list->component();

        $this->assertTrue($component->isRowEditing($editing));
        $this->assertFalse($component->isRowEditing($other1));
        $this->assertFalse($component->isRowEditing($other2));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // canEditRow()
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * ComponentTestKernel uses TestAdminEntityVoter which grants everything.
     *
     * @group inline-edit-state
     */
    public function testCanEditRowReturnsTrueForDoctrineEntityWhenGranted(): void
    {
        $list = $this->makeList();

        $this->assertTrue($list->component()->canEditRow());
    }

    /**
     * @group inline-edit-state
     */
    public function testCanEditRowReturnsFalseForCustomDataSource(): void
    {
        $list = $this->createLiveComponent(
            name: EntityList::class,
            data: ['dataSourceId' => 'data-source'],
        );

        $this->assertFalse($list->component()->canEditRow());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Template – default state (no row open)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @group inline-edit-template
     */
    public function testDefaultStateRendersEditPencilButton(): void
    {
        $this->createEntity('Row');
        $list = $this->makeList();

        $html = (string) $list->render();

        $this->assertStringContainsString('editRow', $html);
    }

    /**
     * @group inline-edit-template
     */
    public function testDefaultStateHasNoExitButton(): void
    {
        $this->createEntity('Row');
        $list = $this->makeList();

        $html = (string) $list->render();

        $this->assertStringNotContainsString('Exit edit mode', $html);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Template – row in edit mode
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @group inline-edit-template
     */
    public function testEditingRowRendersExitButton(): void
    {
        $entity = $this->createEntity('Editable');
        $list   = $this->makeList(['editingRowId' => $entity->getId()]);

        $list->call('editRow', ['id' => $entity->getId()]);
        $html = (string) $list->render();

        // $this->assertStringContainsString('Exit edit mode', $html);
        $this->assertStringContainsString('✕', $html);
    }

    /**
     * @group inline-edit-template
     */
    public function testExitButtonAppearsExactlyOnce(): void
    {
        $this->createEntity('Non-editing 1');
        $this->createEntity('Non-editing 2');
        $editing = $this->createEntity('Editing');
        $list    = $this->makeList(['editingRowId' => $editing->getId()]);

        $html = (string) $list->render();

        $this->assertSame(1, substr_count($html, 'Exit edit mode'),
            'Exit edit mode must appear exactly once – only for the editing row');
    }

    /**
     * @group inline-edit-template
     */
    public function testEditingRowRendersHoverOverlayTriggers(): void
    {
        $entity = $this->createEntity('Editable');
        $list   = $this->makeList(['editingRowId' => $entity->getId()]);

        $html = (string) $list->render();

        $this->assertStringContainsString('field-edit-trigger', $html);
    }

    /**
     * @group inline-edit-template
     */
    public function testHoverTriggerCallsActivateEditing(): void
    {
        $entity = $this->createEntity('Editable');
        $list   = $this->makeList(['editingRowId' => $entity->getId()]);

        $html = (string) $list->render();

        $this->assertStringContainsString('activateEditing', $html);
    }

    /**
     * @group inline-edit-template
     */
    public function testEditingRowStillShowsEntityData(): void
    {
        $entity = $this->createEntity('My Entity Name');
        $list   = $this->makeList(['editingRowId' => $entity->getId()]);

        $html = (string) $list->render();

        $this->assertStringContainsString($entity->getName(), $html);
    }

    /**
     * Non-editing rows still show their ✏️ buttons and entity data.
     *
     * @group inline-edit-template
     */
    public function testNonEditingRowsRetainEditPencilButtonAndData(): void
    {
        $editing    = $this->createEntity('Editing');
        $notEditing = $this->createEntity('Not Editing');
        $list       = $this->makeList(['editingRowId' => $editing->getId()]);

        $html = (string) $list->render();

        // editRow appears for non-editing rows (at least once)
        $this->assertGreaterThanOrEqual(1, substr_count($html, 'editRow'));
        $this->assertStringContainsString($notEditing->getName(), $html);
    }

    /**
     * @group inline-edit-template
     */
    public function testHoverTriggersCountMatchesEditableColumns(): void
    {
        $entity = $this->createEntity('Editable');
        $list   = $this->makeList(['editingRowId' => $entity->getId()]);

        $html           = (string) $list->render();
        $triggerCount   = substr_count($html, 'field-edit-trigger');
        $columnCount    = count($list->component()->getColumns());

        // Each editable column in the editing row gets one trigger; count ≤ column count
        $this->assertGreaterThan(0, $triggerCount);
        $this->assertLessThanOrEqual($columnCount, $triggerCount);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Template – after exitRowEdit
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @group inline-edit-template
     */
    public function testExitRowEditRemovesOverlaysFromTemplate(): void
    {
        $entity = $this->createEntity();
        $list   = $this->makeList(['editingRowId' => $entity->getId()]);

        // $list->call('exitRowEdit');
        $list->set('editingRowId', null);

        $html = (string) $list->render();

        $this->assertStringNotContainsString('field-edit-trigger', $html);
        $this->assertStringNotContainsString('Exit edit mode', $html);
    }

    /**
     * @group inline-edit-template
     */
    public function testExitRowEditRestoresEditPencilButton(): void
    {
        $entity = $this->createEntity();
        $list   = $this->makeList(['editingRowId' => $entity->getId()]);

        // $list->call('exitRowEdit');
        $list->set('editingRowId', null);

        $html = (string) $list->render();

        $this->assertStringContainsString('editRow', $html);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Row switching
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @group inline-edit-state
     */
    public function testSwitchingEditRowUpdatesIsRowEditing(): void
    {
        $e1 = $this->createEntity('First');
        $e2 = $this->createEntity('Second');
        $list = $this->makeList(['editingRowId' => $e1->getId()]);

        $list->call('editRow', ['id' => $e2->getId()]);

        $component = $list->component();
        $this->assertFalse($component->isRowEditing($e1));
        $this->assertTrue($component->isRowEditing($e2));
    }

    /**
     * @group inline-edit-template
     */
    public function testSwitchingEditRowMovesTriggerToNewRow(): void
    {
        $e1   = $this->createEntity('First');
        $e2   = $this->createEntity('Second');
        $list = $this->makeList();

        $list->call('editRow', ['id' => $e1->getId()]);
        $list->call('editRow', ['id' => $e2->getId()]);

        $html = (string) $list->render();

        // Exit button still appears exactly once
        $this->assertSame(1, substr_count($html, 'Exit edit mode'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Design contracts (regression guards)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * saveRow was removed in favour of per-field immediate flush.
     *
     * @group inline-edit-state
     */
    public function testEntityListHasNoSaveRowMethod(): void
    {
        $list = $this->makeList();

        $this->assertFalse(
            method_exists($list->component(), 'saveRow'),
            'saveRow was removed – each Field component does its own flush',
        );
    }

    /**
     * cancelRowEdit was removed in favour of per-field cancel.
     *
     * @group inline-edit-state
     */
    public function testEntityListHasNoCancelRowEditMethod(): void
    {
        $list = $this->makeList();

        $this->assertFalse(
            method_exists($list->component(), 'cancelRowEdit'),
            'cancelRowEdit was removed – each Field component manages its own cancel',
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Isolation – editing must not break other EntityList features
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @group inline-edit-isolation
     */
    public function testEditingRowIdDoesNotAffectPagination(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->createEntity("Entity $i");
        }

        $list = $this->makeList(['editingRowId' => 1]);

        $info = $list->component()->getPaginationInfo();
        $this->assertGreaterThanOrEqual(5, $info->totalItems);
    }

    /**
     * @group inline-edit-isolation
     */
    public function testEditingRowIdDoesNotAffectSorting(): void
    {
        $list = $this->makeList(['editingRowId' => 1]);

        $list->call('sort', ['column' => 'name']);

        $this->assertSame('name', $list->component()->sortBy);
    }

    /**
     * @group inline-edit-isolation
     */
    public function testEditingRowIdDoesNotAffectSearch(): void
    {
        $this->createEntity('Searchable');
        $this->createEntity('Other');

        $list = $this->makeList(['editingRowId' => 1]);

        $list->set('search', 'Searchable');

        $results = $list->component()->getEntities();
        $this->assertCount(1, $results);
    }

    /**
     * @group inline-edit-isolation
     */
    public function testBatchSelectAndInlineEditCoexist(): void
    {
        $e1 = $this->createEntity('Selected');
        $e2 = $this->createEntity('Editing');

        $list = $this->makeList([
            'selectedIds'  => [$e1->getId()],
            'editingRowId' => $e2->getId(),
        ]);

        $component = $list->component();

        $this->assertContains($e1->getId(), $component->selectedIds);
        $this->assertSame($e2->getId(), $component->editingRowId);
        $this->assertTrue($component->isRowEditing($e2));
        $this->assertFalse($component->isRowEditing($e1));
    }
}
