<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional\Field;

use Kachnitel\AdminBundle\Tests\Fixtures\InlineEditEntity;
use Kachnitel\AdminBundle\Tests\Functional\ComponentTestCase;
use Kachnitel\EntityComponentsBundle\Components\Field\StringField;

/**
 * Functional tests for StringField LiveComponent.
 *
 * @group inline-edit
 * @group inline-edit-field
 */
class StringFieldTest extends ComponentTestCase
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    private function createEntity(string $title = 'Original'): InlineEditEntity
    {
        $entity = new InlineEditEntity();
        $entity->setTitle($title);
        $this->em->persist($entity);
        $this->em->flush();

        return $entity;
    }

    // ── mount() ───────────────────────────────────────────────────────────────

    public function testMountPopulatesCurrentValueInEditMode(): void
    {
        $entity = $this->createEntity('Hello World');

        $component = static::getContainer()->get(StringField::class);
        $component->editMode = true;
        $component->mount($entity, 'title');

        $this->assertSame('Hello World', $component->currentValue);
    }

    public function testMountDoesNotSetCurrentValueWhenNotInEditMode(): void
    {
        $entity = $this->createEntity('Should Not Appear');

        $component = $this->createLiveComponent(
            name: 'K:Entity:Field:String',
            data: [
                'entity'   => $entity,
                'property' => 'title',
                'editMode' => false,
            ],
        );

        $this->assertFalse($component->component()->editMode);
    }

    public function testMountWithEmptyStringEntity(): void
    {
        $entity = $this->createEntity('');

        $component = static::getContainer()->get(StringField::class);
        $component->editMode = true;
        $component->mount($entity, 'title');

        $this->assertSame('', $component->currentValue);
    }

    // ── save() ────────────────────────────────────────────────────────────────

    public function testSaveWritesNewValueToEntity(): void
    {
        $entity = $this->createEntity('Before');

        $component = $this->createLiveComponent(
            name: 'K:Entity:Field:String',
            data: [
                'entity'   => $entity,
                'property' => 'title',
                'editMode' => true,
            ],
        );

        $component->set('currentValue', 'After');
        $component->call('save');

        $this->em->clear();
        $reloaded = $this->em->find(InlineEditEntity::class, $entity->getId());
        $this->assertSame('After', $reloaded?->getTitle());
    }

    public function testSaveExitsEditMode(): void
    {
        $entity = $this->createEntity();

        $component = $this->createLiveComponent(
            name: 'K:Entity:Field:String',
            data: [
                'entity'   => $entity,
                'property' => 'title',
                'editMode' => true,
            ],
        );

        $component->set('currentValue', 'New Value');
        $component->call('save');

        $this->assertFalse($component->component()->editMode);
    }

    public function testSaveWithEmptyStringClearsField(): void
    {
        $entity = $this->createEntity('Was Set');

        $component = $this->createLiveComponent(
            name: 'K:Entity:Field:String',
            data: [
                'entity'   => $entity,
                'property' => 'title',
                'editMode' => true,
            ],
        );

        $component->set('currentValue', '');
        $component->call('save');

        $this->em->clear();
        $reloaded = $this->em->find(InlineEditEntity::class, $entity->getId());
        $this->assertSame('', $reloaded?->getTitle());
    }

    // ── cancelEdit() ─────────────────────────────────────────────────────────

    public function testCancelEditExitsEditMode(): void
    {
        $entity = $this->createEntity();

        $component = $this->createLiveComponent(
            name: 'K:Entity:Field:String',
            data: [
                'entity'   => $entity,
                'property' => 'title',
                'editMode' => true,
            ],
        );

        $component->call('cancelEdit');

        $this->assertFalse($component->component()->editMode);
    }

    public function testCancelEditRevertsCurrentValueToPersistedValue(): void
    {
        $entity = $this->createEntity('Persisted');

        $component = $this->createLiveComponent(
            name: 'K:Entity:Field:String',
            data: [
                'entity'   => $entity,
                'property' => 'title',
                'editMode' => true,
            ],
        );

        $component->set('currentValue', 'Unsaved Draft');
        $component->call('cancelEdit');

        $this->assertSame('Persisted', $component->component()->currentValue);
    }

    public function testCancelEditDoesNotPersistUnsavedInput(): void
    {
        $entity = $this->createEntity('Original');
        $id = $entity->getId();

        $component = $this->createLiveComponent(
            name: 'K:Entity:Field:String',
            data: [
                'entity'   => $entity,
                'property' => 'title',
                'editMode' => true,
            ],
        );

        $component->set('currentValue', 'Should Not Be Saved');
        $component->call('cancelEdit');

        $this->em->clear();
        $reloaded = $this->em->find(InlineEditEntity::class, $id);
        $this->assertSame('Original', $reloaded?->getTitle());
    }
}
