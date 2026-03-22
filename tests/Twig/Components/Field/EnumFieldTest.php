<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional\Field;

use Kachnitel\AdminBundle\Tests\Fixtures\InlineEditEnumEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\TestStatus;
use Kachnitel\AdminBundle\Tests\Functional\ComponentTestCase;
use Kachnitel\EntityComponentsBundle\Components\Field\EnumField;

/**
 * Functional tests for EnumField LiveComponent (now in entity-components-bundle).
 *
 * @group inline-edit
 * @group inline-edit-field
 */
class EnumFieldTest extends ComponentTestCase
{
    private function createEntity(): InlineEditEnumEntity
    {
        $entity = new InlineEditEnumEntity();
        $this->em->persist($entity);
        $this->em->flush();

        return $entity;
    }

    private function getComponent(): EnumField
    {
        return static::getContainer()->get(EnumField::class);
    }

    // ── mount() ───────────────────────────────────────────────────────────────

    public function testMountInitializesSelectedValueFromBackedEnum(): void
    {
        $entity = $this->createEntity();
        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($entity, 'status');

        $this->assertSame('active', $component->selectedValue);
    }

    // ── save() ────────────────────────────────────────────────────────────────

    public function testSavePersistsBackedEnum(): void
    {
        $entity = $this->createEntity();
        $id = $entity->getId();

        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($entity, 'status');

        $component->selectedValue = TestStatus::ARCHIVED->value;
        $component->save();

        $this->em->clear();
        $reloaded = $this->em->find(InlineEditEnumEntity::class, $id);
        $this->assertSame(TestStatus::ARCHIVED, $reloaded->status);
    }

    public function testSaveThrowsExceptionForNonEnumProperty(): void
    {
        $entity = $this->createEntity();
        $component = $this->getComponent();
        $component->editMode = true;

        $component->mount($entity, 'notAnEnum');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid enum type');

        $component->save();
    }

    // ── Configuration ─────────────────────────────────────────────────────────

    public function testGetFormFieldConfigDetectsRequiredState(): void
    {
        $entity = $this->createEntity();
        $component = $this->getComponent();

        $component->mount($entity, 'status');
        $this->assertTrue($component->getFormFieldConfig()['required']);
    }
}
