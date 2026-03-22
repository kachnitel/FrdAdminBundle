<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional\Field;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Kachnitel\AdminBundle\Tests\Fixtures\InlineEditDateEntity;
use Kachnitel\AdminBundle\Tests\Functional\ComponentTestCase;
use Kachnitel\EntityComponentsBundle\Components\Field\DateField;

/**
 * Functional tests for DateField LiveComponent (now in entity-components-bundle).
 *
 * @group inline-edit
 * @group inline-edit-field
 */
class DateFieldTest extends ComponentTestCase
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    private function createEntity(): InlineEditDateEntity
    {
        $entity = new InlineEditDateEntity();
        $entity->setCreatedAt(new DateTime('2024-06-01 12:30:00'));
        $entity->setUpdatedAt(new DateTimeImmutable('2024-06-15 14:00:00'));
        $entity->setBirthDate(new DateTime('2000-01-15'));
        $entity->setExpiresOn(new DateTimeImmutable('2025-12-31'));
        $entity->setMeetingTime(new DateTime('1970-01-01 14:30:00'));
        $entity->setLoggedAt(new DateTimeImmutable('1970-01-01 09:15:00'));
        $this->em->persist($entity);
        $this->em->flush();

        return $entity;
    }

    private function getComponent(): DateField
    {
        /** @var DateField $component */
        $component = static::getContainer()->get(DateField::class);

        return $component;
    }

    // ── mount(): dateValue initialization ─────────────────────────────────────

    public function testMountConvertsDatetimeToDatetimeLocalString(): void
    {
        $entity = $this->createEntity();

        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($entity, 'createdAt');

        $this->assertSame(
            $entity->getCreatedAt()?->format('Y-m-d\TH:i'),
            $component->dateValue,
        );
    }

    public function testMountConvertsDatetimeImmutableToDatetimeLocalString(): void
    {
        $entity = $this->createEntity();

        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($entity, 'updatedAt');

        $this->assertSame(
            $entity->getUpdatedAt()?->format('Y-m-d\TH:i'),
            $component->dateValue,
        );
    }

    public function testMountConvertsDateToDateString(): void
    {
        $entity = $this->createEntity();

        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($entity, 'birthDate');

        $this->assertSame(
            $entity->getBirthDate()?->format('Y-m-d'),
            $component->dateValue,
        );
    }

    public function testMountConvertsTimeToTimeString(): void
    {
        $entity = $this->createEntity();

        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($entity, 'meetingTime');

        $this->assertSame(
            $entity->getMeetingTime()?->format('H:i'),
            $component->dateValue,
        );
    }

    public function testMountSetsDateValueNullWhenNotInEditMode(): void
    {
        $entity = $this->createEntity();

        $component = $this->getComponent();
        $component->editMode = false;
        $component->mount($entity, 'createdAt');

        $this->assertNull($component->dateValue);
    }

    // ── save(): string → entity value ────────────────────────────────────────

    public function testSaveConvertsDatetimeStringToDateTime(): void
    {
        $entity = $this->createEntity();

        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($entity, 'createdAt');

        $component->dateValue = '2025-03-15T14:30';
        $component->save();

        $result = $entity->getCreatedAt();
        $this->assertInstanceOf(DateTimeInterface::class, $result);
        $this->assertSame('2025-03-15', $result->format('Y-m-d'));
        $this->assertSame('14:30', $result->format('H:i'));
    }

    public function testSaveCreatesDateTimeImmutableForImmutableColumn(): void
    {
        $entity = $this->createEntity();

        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($entity, 'updatedAt');

        $component->dateValue = '2025-06-01T09:00';
        $component->save();

        $this->assertInstanceOf(DateTimeImmutable::class, $entity->getUpdatedAt());
    }

    public function testSaveHandlesDateOnlyString(): void
    {
        $entity = $this->createEntity();

        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($entity, 'birthDate');

        $component->dateValue = '2025-03-15';
        $component->save();

        $result = $entity->getBirthDate();
        $this->assertInstanceOf(DateTimeInterface::class, $result);
        $this->assertSame('2025-03-15', $result->format('Y-m-d'));
    }

    public function testSaveHandlesTimeOnlyString(): void
    {
        $entity = $this->createEntity();

        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($entity, 'meetingTime');

        $component->dateValue = '14:30';
        $component->save();

        $result = $entity->getMeetingTime();
        $this->assertInstanceOf(DateTimeInterface::class, $result);
        $this->assertSame('14:30', $result->format('H:i'));
    }

    public function testSaveHandlesNullDateValue(): void
    {
        $entity = $this->createEntity();

        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($entity, 'createdAt');

        $component->dateValue = null;
        $component->save();

        $this->assertNull($entity->getCreatedAt());
    }

    public function testSaveExitsEditMode(): void
    {
        $entity = $this->createEntity();

        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($entity, 'createdAt');

        $component->dateValue = '2025-01-01T12:00';
        $component->save();

        $this->assertFalse($component->editMode);
    }

    public function testSaveFlushesToDatabase(): void
    {
        $entity = $this->createEntity();
        $id = $entity->getId();

        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($entity, 'createdAt');

        $component->dateValue = '2025-07-04T08:00';
        $component->save();

        $this->em->clear();
        $reloaded = $this->em->find(InlineEditDateEntity::class, $id);
        $this->assertNotNull($reloaded);
        $this->assertSame('2025-07-04', $reloaded->getCreatedAt()?->format('Y-m-d'));
    }

    public function testInvalidDateStringThrowsRuntimeException(): void
    {
        $entity = $this->createEntity();

        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($entity, 'createdAt');

        $component->dateValue = 'not-a-date';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid date format');

        $component->save();
    }

    // ── cancelEdit() ─────────────────────────────────────────────────────────

    public function testCancelEditExitsEditMode(): void
    {
        $entity = $this->createEntity();

        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($entity, 'createdAt');

        $component->cancelEdit();

        $this->assertFalse($component->editMode);
    }

    public function testCancelEditResetsDateValueToPersistedDatetime(): void
    {
        $entity = $this->createEntity();

        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($entity, 'createdAt');

        $component->dateValue = '2099-12-31T23:59';
        $component->cancelEdit();

        $this->assertSame(
            $entity->getCreatedAt()?->format('Y-m-d\TH:i'),
            $component->dateValue,
        );
    }

    public function testCancelEditDoesNotPersistUntypedInput(): void
    {
        $entity = $this->createEntity();
        $id = $entity->getId();
        $originalDate = $entity->getCreatedAt()?->format('Y-m-d');

        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($entity, 'createdAt');

        $component->dateValue = '2099-12-31T23:59';
        $component->cancelEdit();

        $this->em->clear();
        $reloaded = $this->em->find(InlineEditDateEntity::class, $id);
        $this->assertSame($originalDate, $reloaded?->getCreatedAt()?->format('Y-m-d'));
    }
}
