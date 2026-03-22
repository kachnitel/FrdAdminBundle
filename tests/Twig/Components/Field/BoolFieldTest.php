<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional\Field;

use Kachnitel\AdminBundle\Tests\Fixtures\InlineEditEntity;
use Kachnitel\AdminBundle\Tests\Functional\ComponentTestCase;

/**
 * Functional tests for BoolField LiveComponent (now in entity-components-bundle).
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
            name: 'K:Entity:Field:Bool',
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
            name: 'K:Entity:Field:Bool',
            data: [
                'entity'   => $entity,
                'property' => 'active',
                'editMode' => true,
            ],
        );

        $this->assertFalse($component->component()->currentValue);
    }

    public function testMountCoercesNullToFalse(): void
    {
        $entity = $this->createEntity(null);

        $component = $this->createLiveComponent(
            name: 'K:Entity:Field:Bool',
            data: [
                'entity'   => $entity,
                'property' => 'active',
                'editMode' => true,
            ],
        );

        $this->assertFalse($component->component()->currentValue);
    }

    // ── save() ────────────────────────────────────────────────────────────────

    public function testSavePersistsTrue(): void
    {
        $entity = $this->createEntity(false);

        $component = $this->createLiveComponent(
            name: 'K:Entity:Field:Bool',
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
            name: 'K:Entity:Field:Bool',
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
            name: 'K:Entity:Field:Bool',
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
            name: 'K:Entity:Field:Bool',
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
            name: 'K:Entity:Field:Bool',
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
            name: 'K:Entity:Field:Bool',
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
            name: 'K:Entity:Field:Bool',
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
