<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional\Field;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Kachnitel\AdminBundle\Tests\Fixtures\InlineEditDateEntity;
use Kachnitel\AdminBundle\Tests\Functional\ComponentTestCase;
use Kachnitel\AdminBundle\Twig\Components\Field\DateField;

/**
 * @covers \Kachnitel\AdminBundle\Twig\Components\Field\DateField
 *
 * @group field
 * @group inline-edit
 *
 * Why ComponentTestCase instead of PHPUnit TestCase:
 *
 * DateField uses PropertyInfoTrait which declares $doctrineExtractor as a typed
 * non-nullable property initialized only by a #[Required] method. Constructing the
 * component manually bypasses the Symfony DI lifecycle, leaving $doctrineExtractor
 * uninitialized and causing typed property access errors.
 *
 * Getting the component from the real container ensures:
 *   - Constructor injection (EntityManagerInterface, PropertyAccessorInterface, …)
 *   - #[Required] method injection (initPropertyInfoExtractors)
 *   - DoctrineExtractor backed by the real in-memory SQLite schema
 *
 * InlineEditDateEntity provides one nullable property per Doctrine date column type
 * variant with setters, enabling every branch of getDateType() and shouldUseImmutable().
 */
class DateFieldTest extends ComponentTestCase
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    private function createEntity(): InlineEditDateEntity
    {
        $entity = new InlineEditDateEntity();
        // Set initial non-null values so mount() can read them in editMode
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

    /**
     * Get DateField from the container — ensures full DI lifecycle including
     * all #[Required] setters are called before the component is used.
     */
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
        $component->mount($entity, 'birthDate'); // Doctrine 'date'

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
        $component->mount($entity, 'meetingTime'); // Doctrine 'time'

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

        // mount() exits early when editMode is false
        $this->assertNull($component->dateValue);
    }

    public function testMountSetsDateValueNullWhenFieldIsNull(): void
    {
        $entity = new InlineEditDateEntity(); // all fields null
        $this->em->persist($entity);
        $this->em->flush();

        $component = $this->getComponent();
        $component->editMode = true;
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

    public function testSaveCreatesPlainDateTimeForMutableColumn(): void
    {
        // createdAt is Doctrine 'datetime' — mutable
        $entity = $this->createEntity();

        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($entity, 'createdAt');

        $component->dateValue = '2025-06-01T09:00';
        $component->save();

        $result = $entity->getCreatedAt();
        $this->assertInstanceOf(DateTime::class, $result);
        $this->assertNotInstanceOf(DateTimeImmutable::class, $result); // @phpstan-ignore method.alreadyNarrowedType
    }

    public function testSaveCreatesDateTimeImmutableForImmutableColumn(): void
    {
        // updatedAt is Doctrine 'datetime_immutable'
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
        $component->mount($entity, 'birthDate'); // Doctrine 'date'

        $component->dateValue = '2025-03-15';
        $component->save();

        $result = $entity->getBirthDate();
        $this->assertInstanceOf(DateTimeInterface::class, $result);
        $this->assertSame('2025-03-15', $result->format('Y-m-d'));
    }

    public function testSaveHandlesDateImmutableString(): void
    {
        $entity = $this->createEntity();

        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($entity, 'expiresOn'); // Doctrine 'date_immutable'

        $component->dateValue = '2026-01-01';
        $component->save();

        $result = $entity->getExpiresOn();
        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertSame('2026-01-01', $result->format('Y-m-d'));
    }

    public function testSaveHandlesTimeOnlyString(): void
    {
        $entity = $this->createEntity();

        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($entity, 'meetingTime'); // Doctrine 'time'

        $component->dateValue = '14:30';
        $component->save();

        $result = $entity->getMeetingTime();
        $this->assertInstanceOf(DateTimeInterface::class, $result);
        $this->assertSame('14:30', $result->format('H:i'));
    }

    public function testSaveHandlesTimeImmutableString(): void
    {
        $entity = $this->createEntity();

        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($entity, 'loggedAt'); // Doctrine 'time_immutable'

        $component->dateValue = '09:00';
        $component->save();

        $result = $entity->getLoggedAt();
        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertSame('09:00', $result->format('H:i'));
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

    public function testSaveHandlesEmptyStringAsNull(): void
    {
        $entity = $this->createEntity();

        $component = $this->getComponent();
        $component->editMode = true;
        $component->mount($entity, 'createdAt');

        $component->dateValue = '';
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

        // Clear identity map and reload from DB
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

    // ── getFormFieldConfig() ──────────────────────────────────────────────────

    public function testGetFormFieldConfigReturnsDatetimeLocalForDatetimeColumn(): void
    {
        $entity = $this->createEntity();
        $component = $this->getComponent();
        $component->mount($entity, 'createdAt'); // Doctrine 'datetime'

        $this->assertSame('datetime-local', $component->getFormFieldConfig()['type']);
    }

    public function testGetFormFieldConfigReturnsDatetimeLocalForDatetimeImmutableColumn(): void
    {
        $entity = $this->createEntity();
        $component = $this->getComponent();
        $component->mount($entity, 'updatedAt'); // Doctrine 'datetime_immutable'

        $this->assertSame('datetime-local', $component->getFormFieldConfig()['type']);
    }

    public function testGetFormFieldConfigReturnsDateForDateColumn(): void
    {
        $entity = $this->createEntity();
        $component = $this->getComponent();
        $component->mount($entity, 'birthDate'); // Doctrine 'date'

        $this->assertSame('date', $component->getFormFieldConfig()['type']);
    }

    public function testGetFormFieldConfigReturnsDateForDateImmutableColumn(): void
    {
        $entity = $this->createEntity();
        $component = $this->getComponent();
        $component->mount($entity, 'expiresOn'); // Doctrine 'date_immutable'

        $this->assertSame('date', $component->getFormFieldConfig()['type']);
    }

    public function testGetFormFieldConfigReturnsTimeForTimeColumn(): void
    {
        $entity = $this->createEntity();
        $component = $this->getComponent();
        $component->mount($entity, 'meetingTime'); // Doctrine 'time'

        $this->assertSame('time', $component->getFormFieldConfig()['type']);
    }

    public function testGetFormFieldConfigReturnsTimeForTimeImmutableColumn(): void
    {
        $entity = $this->createEntity();
        $component = $this->getComponent();
        $component->mount($entity, 'loggedAt'); // Doctrine 'time_immutable'

        $this->assertSame('time', $component->getFormFieldConfig()['type']);
    }
}
