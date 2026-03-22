<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional\Field;

use Kachnitel\AdminBundle\Tests\Fixtures\InlineEditEntity;
use Kachnitel\AdminBundle\Tests\Functional\ComponentTestCase;

/**
 * Functional tests for IntField LiveComponent (now in entity-components-bundle).
 *
 * @group inline-edit
 * @group inline-edit-field
 */
class IntFieldTest extends ComponentTestCase
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    private function createEntity(int $count = 42): InlineEditEntity
    {
        $entity = new InlineEditEntity();
        $entity->setCount($count);
        $this->em->persist($entity);
        $this->em->flush();

        return $entity;
    }

    // ── mount() ───────────────────────────────────────────────────────────────

    public function testMountPopulatesCurrentValueInEditMode(): void
    {
        $entity = $this->createEntity(99);

        $component = $this->createLiveComponent(
            name: 'K:Entity:Field:Int',
            data: [
                'entity'   => $entity,
                'property' => 'count',
                'editMode' => true,
            ],
        );

        $this->assertSame(99, $component->component()->currentValue);
    }

    public function testMountWithZeroValue(): void
    {
        $entity = $this->createEntity(0);

        $component = $this->createLiveComponent(
            name: 'K:Entity:Field:Int',
            data: [
                'entity'   => $entity,
                'property' => 'count',
                'editMode' => true,
            ],
        );

        $this->assertSame(0, $component->component()->currentValue);
    }

    public function testMountWithNegativeValue(): void
    {
        $entity = $this->createEntity(-5);

        $component = $this->createLiveComponent(
            name: 'K:Entity:Field:Int',
            data: [
                'entity'   => $entity,
                'property' => 'count',
                'editMode' => true,
            ],
        );

        $this->assertSame(-5, $component->component()->currentValue);
    }

    // ── save() ────────────────────────────────────────────────────────────────

    public function testSaveWritesIntToEntity(): void
    {
        $entity = $this->createEntity(10);

        $component = $this->createLiveComponent(
            name: 'K:Entity:Field:Int',
            data: [
                'entity'   => $entity,
                'property' => 'count',
                'editMode' => true,
            ],
        );

        $component->set('currentValue', '777');
        $component->call('save');

        $this->em->clear();
        $reloaded = $this->em->find(InlineEditEntity::class, $entity->getId());
        $this->assertSame(777, $reloaded?->getCount());
    }

    public function testSaveZeroValue(): void
    {
        $entity = $this->createEntity(50);

        $component = $this->createLiveComponent(
            name: 'K:Entity:Field:Int',
            data: [
                'entity'   => $entity,
                'property' => 'count',
                'editMode' => true,
            ],
        );

        $component->set('currentValue', '0');
        $component->call('save');

        $this->em->clear();
        $reloaded = $this->em->find(InlineEditEntity::class, $entity->getId());
        $this->assertSame(0, $reloaded?->getCount());
    }

    public function testSaveNegativeValue(): void
    {
        $entity = $this->createEntity(100);

        $component = $this->createLiveComponent(
            name: 'K:Entity:Field:Int',
            data: [
                'entity'   => $entity,
                'property' => 'count',
                'editMode' => true,
            ],
        );

        $component->set('currentValue', '-3');
        $component->call('save');

        $this->em->clear();
        $reloaded = $this->em->find(InlineEditEntity::class, $entity->getId());
        $this->assertSame(-3, $reloaded?->getCount());
    }

    public function testSaveExitsEditMode(): void
    {
        $entity = $this->createEntity();

        $component = $this->createLiveComponent(
            name: 'K:Entity:Field:Int',
            data: [
                'entity'   => $entity,
                'property' => 'count',
                'editMode' => true,
            ],
        );

        $component->set('currentValue', '1');
        $component->call('save');

        $this->assertFalse($component->component()->editMode);
    }

    // ── cancelEdit() ─────────────────────────────────────────────────────────

    public function testCancelEditExitsEditMode(): void
    {
        $entity = $this->createEntity();

        $component = $this->createLiveComponent(
            name: 'K:Entity:Field:Int',
            data: [
                'entity'   => $entity,
                'property' => 'count',
                'editMode' => true,
            ],
        );

        $component->call('cancelEdit');

        $this->assertFalse($component->component()->editMode);
    }

    public function testCancelEditRevertsCurrentValueToPersistedValue(): void
    {
        $entity = $this->createEntity(42);

        $component = $this->createLiveComponent(
            name: 'K:Entity:Field:Int',
            data: [
                'entity'   => $entity,
                'property' => 'count',
                'editMode' => true,
            ],
        );

        $component->set('currentValue', 9999);
        $component->call('cancelEdit');

        $this->assertSame(42, $component->component()->currentValue);
    }

    public function testCancelEditDoesNotPersistUnsavedInput(): void
    {
        $entity = $this->createEntity(42);
        $id = $entity->getId();

        $component = $this->createLiveComponent(
            name: 'K:Entity:Field:Int',
            data: [
                'entity'   => $entity,
                'property' => 'count',
                'editMode' => true,
            ],
        );

        $component->set('currentValue', 9999);
        $component->call('cancelEdit');

        $this->em->clear();
        $reloaded = $this->em->find(InlineEditEntity::class, $id);
        $this->assertSame(42, $reloaded?->getCount());
    }

    public function testCancelEditRevertsToZeroWhenPersistedValueIsZero(): void
    {
        $entity = $this->createEntity(0);

        $component = $this->createLiveComponent(
            name: 'K:Entity:Field:Int',
            data: [
                'entity'   => $entity,
                'property' => 'count',
                'editMode' => true,
            ],
        );

        $component->set('currentValue', 99);
        $component->call('cancelEdit');

        $this->assertSame(0, $component->component()->currentValue);
    }
}
