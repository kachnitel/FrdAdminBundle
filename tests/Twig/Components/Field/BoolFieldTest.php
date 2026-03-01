<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional\Field;

use Kachnitel\AdminBundle\Tests\Fixtures\InlineEditEntity;
use Kachnitel\AdminBundle\Tests\Functional\ComponentTestCase;

/**
 * Functional tests for BoolField LiveComponent.
 *
 * BoolField stores currentValue as bool — not ?bool. Null values from the
 * entity are coerced to false. BoolField does not support nullable boolean
 * columns; use a non-nullable boolean column with a sensible default instead.
 *
 * Covers:
 *  - mount() sets currentValue to bool (null coerced to false — documented below)
 *  - save() writes the bool back and persists
 *  - cancelEdit() reverts currentValue from the refreshed entity state
 *  - True/false state transitions
 *
 * @group inline-edit
 * @group inline-edit-field
 */
class BoolFieldTest extends ComponentTestCase
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    private function createEntity(?bool $active = false): InlineEditEntity
    {
        $entity = new InlineEditEntity();
        $entity->setActive($active);
        $this->em->persist($entity);
        $this->em->flush();

        return $entity;
    }

    // ── mount() ───────────────────────────────────────────────────────────────

    public function testMountHydratesTrueValue(): void
    {
        $entity = $this->createEntity(true);

        $component = $this->createLiveComponent(
            name: 'K:Admin:Field:Bool',
            data: [
                'entity'   => $entity,
                'property' => 'active',
                'editMode' => true,
            ],
        );

        $this->assertTrue($component->component()->currentValue);
    }

    public function testMountHydratesFalseValue(): void
    {
        $entity = $this->createEntity(false);

        $component = $this->createLiveComponent(
            name: 'K:Admin:Field:Bool',
            data: [
                'entity'   => $entity,
                'property' => 'active',
                'editMode' => true,
            ],
        );

        $this->assertFalse($component->component()->currentValue);
    }

    /**
     * BoolField coerces null → false. Nullable boolean columns are not
     * supported by this field component; document this as a known limitation.
     */
    public function testMountCoercesNullToFalse(): void
    {
        $entity = $this->createEntity(null);

        $component = $this->createLiveComponent(
            name: 'K:Admin:Field:Bool',
            data: [
                'entity'   => $entity,
                'property' => 'active',
                'editMode' => true,
            ],
        );

        // BoolField does not differentiate null from false
        $this->assertFalse($component->component()->currentValue);
    }

    // ── save() ────────────────────────────────────────────────────────────────

    public function testSavePersistsTrue(): void
    {
        $entity = $this->createEntity(false);

        $component = $this->createLiveComponent(
            name: 'K:Admin:Field:Bool',
            data: [
                'entity'   => $entity,
                'property' => 'active',
                'editMode' => true,
            ],
        );

        $component->set('currentValue', true);
        $component->call('save');

        $this->em->clear();
        $reloaded = $this->em->find(InlineEditEntity::class, $entity->getId());
        $this->assertTrue($reloaded?->getActive());
    }

    public function testSavePersistsFalse(): void
    {
        $entity = $this->createEntity(true);

        $component = $this->createLiveComponent(
            name: 'K:Admin:Field:Bool',
            data: [
                'entity'   => $entity,
                'property' => 'active',
                'editMode' => true,
            ],
        );

        $component->set('currentValue', false);
        $component->call('save');

        $this->em->clear();
        $reloaded = $this->em->find(InlineEditEntity::class, $entity->getId());
        $this->assertFalse($reloaded?->getActive());
    }

    public function testSaveExitsEditMode(): void
    {
        $entity = $this->createEntity();

        $component = $this->createLiveComponent(
            name: 'K:Admin:Field:Bool',
            data: [
                'entity'   => $entity,
                'property' => 'active',
                'editMode' => true,
            ],
        );

        $component->set('currentValue', true);
        $component->call('save');

        $this->assertFalse($component->component()->editMode);
    }

    // ── cancelEdit() ─────────────────────────────────────────────────────────

    public function testCancelEditExitsEditMode(): void
    {
        $entity = $this->createEntity(true);

        $component = $this->createLiveComponent(
            name: 'K:Admin:Field:Bool',
            data: [
                'entity'   => $entity,
                'property' => 'active',
                'editMode' => true,
            ],
        );

        $component->call('cancelEdit');

        $this->assertFalse($component->component()->editMode);
    }

    public function testCancelEditRevertsToTrue(): void
    {
        $entity = $this->createEntity(true);

        $component = $this->createLiveComponent(
            name: 'K:Admin:Field:Bool',
            data: [
                'entity'   => $entity,
                'property' => 'active',
                'editMode' => true,
            ],
        );

        $component->set('currentValue', false);
        $component->call('cancelEdit');

        $this->assertTrue($component->component()->currentValue);
    }

    public function testCancelEditRevertsToFalse(): void
    {
        $entity = $this->createEntity(false);

        $component = $this->createLiveComponent(
            name: 'K:Admin:Field:Bool',
            data: [
                'entity'   => $entity,
                'property' => 'active',
                'editMode' => true,
            ],
        );

        $component->set('currentValue', true);
        $component->call('cancelEdit');

        $this->assertFalse($component->component()->currentValue);
    }

    public function testCancelEditDoesNotPersistUnsavedChange(): void
    {
        $entity = $this->createEntity(true);
        $id = $entity->getId();

        $component = $this->createLiveComponent(
            name: 'K:Admin:Field:Bool',
            data: [
                'entity'   => $entity,
                'property' => 'active',
                'editMode' => true,
            ],
        );

        $component->set('currentValue', false);
        $component->call('cancelEdit');

        $this->em->clear();
        $reloaded = $this->em->find(InlineEditEntity::class, $id);
        $this->assertTrue($reloaded?->getActive());
    }
}
