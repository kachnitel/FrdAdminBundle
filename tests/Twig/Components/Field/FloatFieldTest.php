<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional\Field;

use Kachnitel\AdminBundle\Tests\Fixtures\InlineEditEntity;
use Kachnitel\AdminBundle\Tests\Functional\ComponentTestCase;

/**
 * Functional tests for FloatField LiveComponent.
 *
 * Covers:
 *  - mount() initialises currentValue as string from the float
 *  - save() coerces currentValue to float and persists
 *  - cancelEdit() reverts currentValue — specifically guards against the
 *    (float) null = 0.0 false-positive that could mask a missing override
 *  - Zero, negative, and decimal precision edge cases
 *
 * @group inline-edit
 * @group inline-edit-field
 */
class FloatFieldTest extends ComponentTestCase
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    private function createEntity(float $score = 3.14): InlineEditEntity
    {
        $entity = new InlineEditEntity();
        $entity->setScore($score);
        $this->em->persist($entity);
        $this->em->flush();

        return $entity;
    }

    // ── mount() ───────────────────────────────────────────────────────────────

    public function testMountPopulatesCurrentValueInEditMode(): void
    {
        $entity = $this->createEntity(9.99);

        $component = $this->createLiveComponent(
            name: 'K:Admin:Field:Float',
            data: [
                'entity'   => $entity,
                'property' => 'score',
                'editMode' => true,
            ],
        );

        // currentValue is a string suitable for <input type="number">
        $this->assertStringContainsString('9.99', (string) $component->component()->currentValue);
    }

    public function testMountWithZeroFloat(): void
    {
        $entity = $this->createEntity(0.0);

        $component = $this->createLiveComponent(
            name: 'K:Admin:Field:Float',
            data: [
                'entity'   => $entity,
                'property' => 'score',
                'editMode' => true,
            ],
        );

        $this->assertSame(0.0, $component->component()->currentValue);
    }

    // ── save() ────────────────────────────────────────────────────────────────

    public function testSavePersistsFloatValue(): void
    {
        $entity = $this->createEntity(1.0);

        $component = $this->createLiveComponent(
            name: 'K:Admin:Field:Float',
            data: [
                'entity'   => $entity,
                'property' => 'score',
                'editMode' => true,
            ],
        );

        $component->set('currentValue', '7.5');
        $component->call('save');

        $this->em->clear();
        $reloaded = $this->em->find(InlineEditEntity::class, $entity->getId());
        $this->assertEqualsWithDelta(7.5, $reloaded?->getScore(), 0.0001);
    }

    public function testSaveNegativeFloat(): void
    {
        $entity = $this->createEntity(10.0);

        $component = $this->createLiveComponent(
            name: 'K:Admin:Field:Float',
            data: [
                'entity'   => $entity,
                'property' => 'score',
                'editMode' => true,
            ],
        );

        $component->set('currentValue', '-2.5');
        $component->call('save');

        $this->em->clear();
        $reloaded = $this->em->find(InlineEditEntity::class, $entity->getId());
        $this->assertEqualsWithDelta(-2.5, $reloaded?->getScore(), 0.0001);
    }

    public function testSaveExitsEditMode(): void
    {
        $entity = $this->createEntity();

        $component = $this->createLiveComponent(
            name: 'K:Admin:Field:Float',
            data: [
                'entity'   => $entity,
                'property' => 'score',
                'editMode' => true,
            ],
        );

        $component->set('currentValue', '1.0');
        $component->call('save');

        $this->assertFalse($component->component()->editMode);
    }

    // ── cancelEdit() ─────────────────────────────────────────────────────────

    public function testCancelEditExitsEditMode(): void
    {
        $entity = $this->createEntity();

        $component = $this->createLiveComponent(
            name: 'K:Admin:Field:Float',
            data: [
                'entity'   => $entity,
                'property' => 'score',
                'editMode' => true,
            ],
        );

        $component->call('cancelEdit');

        $this->assertFalse($component->component()->editMode);
    }

    public function testCancelEditRevertsCurrentValueToPersistedFloat(): void
    {
        $entity = $this->createEntity(3.14);

        $component = $this->createLiveComponent(
            name: 'K:Admin:Field:Float',
            data: [
                'entity'   => $entity,
                'property' => 'score',
                'editMode' => true,
            ],
        );

        $component->set('currentValue', 999.99);
        $component->call('cancelEdit');

        $this->assertEqualsWithDelta(3.14, $component->component()->currentValue, 0.0001);
    }

    public function testCancelEditDoesNotPersistUnsavedInput(): void
    {
        $entity = $this->createEntity(3.14);
        $id = $entity->getId();

        $component = $this->createLiveComponent(
            name: 'K:Admin:Field:Float',
            data: [
                'entity'   => $entity,
                'property' => 'score',
                'editMode' => true,
            ],
        );

        $component->set('currentValue', 999.99);
        $component->call('cancelEdit');

        $this->em->clear();
        $reloaded = $this->em->find(InlineEditEntity::class, $id);
        $this->assertEqualsWithDelta(3.14, $reloaded?->getScore(), 0.0001);
    }

    /**
     * Guards against the (float) null = 0.0 false positive.
     *
     * If cancelEdit() re-reads the value incorrectly (e.g. reads null and casts
     * it to 0.0), a persisted value of 5.0 would appear to revert to 0.0.
     */
    public function testCancelEditDoesNotFalselyCoerceNullToZero(): void
    {
        $entity = $this->createEntity(5.0);

        $component = $this->createLiveComponent(
            name: 'K:Admin:Field:Float',
            data: [
                'entity'   => $entity,
                'property' => 'score',
                'editMode' => true,
            ],
        );

        $component->set('currentValue', 88.8);
        $component->call('cancelEdit');

        // Must be 5.0, not 0.0 (which would happen if null were cast to float)
        $this->assertEqualsWithDelta(5.0, $component->component()->currentValue, 0.0001);
    }
}
