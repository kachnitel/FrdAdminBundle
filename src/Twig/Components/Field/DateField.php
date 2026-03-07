<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components\Field;

use DateTimeImmutable;
use DateTimeInterface;
use Kachnitel\AdminBundle\Twig\Components\Field\Traits\PropertyInfoTrait;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;

/**
 * Editable field component for date and datetime types.
 *
 * Supports:
 * - DateTime and DateTimeImmutable
 * - Date only (Doctrine type: 'date' / 'date_immutable')
 * - DateTime with time (Doctrine type: 'datetime' / 'datetime_immutable' / 'datetimetz' / 'datetimetz_immutable')
 * - Time only (Doctrine type: 'time' / 'time_immutable')
 * - Null values
 *
 * Detects date/time variant and mutability from the Doctrine column metadata
 * (ClassMetadata::getTypeOfField), which returns the Doctrine type name string
 * ('date', 'datetime_immutable', 'time', …) — not the PHP class name.
 *
 * ## cancelEdit() contract
 *
 * DateField uses a plain `#[LiveProp(writable: true)] public ?string $dateValue` (no hydrateWith)
 * so it must re-derive the display string from the refreshed entity explicitly.
 * cancelEdit() calls parent::cancelEdit() first (which calls EntityManager::refresh()),
 * then re-formats the current entity value back into $dateValue.
 *
 * ## persistEdit() contract
 *
 * Parses $dateValue string into a DateTimeInterface and writes it via writeValue().
 * Null / empty string → writes null (nullable date column).
 * Called only after canEdit() passes in the base save() method.
 */
#[AsLiveComponent('K:Admin:Field:Date', template: '@KachnitelAdmin/components/field/DateField.html.twig')]
class DateField extends AbstractEditableField
{
    use PropertyInfoTrait;

    /**
     * The date value as string for HTML input binding.
     *
     * Format depends on type:
     * - date: 'Y-m-d' (e.g., '2025-03-15')
     * - datetime: 'Y-m-d\TH:i' (e.g., '2025-03-15T14:30')
     * - time: 'H:i' (e.g., '14:30')
     */
    #[LiveProp(writable: true)]
    public ?string $dateValue = null;

    /**
     * Initialize dateValue from entity when entering edit mode.
     */
    public function mount(object $entity, string $property): void
    {
        parent::mount($entity, $property);

        if (!$this->editMode) {
            return;
        }

        $this->dateValue = $this->formatValueAsString($this->readValue());
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed>
     */
    public function getFormFieldConfig(): array
    {
        $type = $this->getDateType();

        return [
            'type' => $type === 'datetime' ? 'datetime-local' : $type,
            'required' => $this->isRequired(),
        ];
    }

    /**
     * Discard changes and restore $dateValue from the freshly-refreshed entity.
     *
     * parent::cancelEdit() calls EntityManager::refresh(), discarding any
     * pending ORM changes and making readValue() return the persisted state.
     * We then re-format the value into the string format that the <input> expects.
     */
    #[LiveAction]
    public function cancelEdit(): void
    {
        parent::cancelEdit();

        $this->dateValue = $this->formatValueAsString($this->readValue());
    }

    // ── Template method ────────────────────────────────────────────────────────

    /**
     * Parse $dateValue and write it to the entity property.
     * Called only after canEdit() passes in the base save() method.
     */
    protected function persistEdit(): void
    {
        if ($this->dateValue === null || $this->dateValue === '') {
            $this->writeValue(null);
        } else {
            $this->writeValue($this->parseDateTime($this->dateValue));
        }
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Format a DateTimeInterface (or null) as the string expected by the HTML input.
     *
     * Returns null when the value is null or not a DateTimeInterface instance
     * (e.g. when the property has never been set).
     */
    private function formatValueAsString(mixed $value): ?string
    {
        if (!$value instanceof DateTimeInterface) {
            return null;
        }

        return match ($this->getDateType()) {
            'date'  => $value->format('Y-m-d'),
            'time'  => $value->format('H:i'),
            default => $value->format('Y-m-d\TH:i'),
        };
    }

    /**
     * Parse date string to DateTime or DateTimeImmutable based on Doctrine column type.
     */
    private function parseDateTime(string $dateString): DateTimeInterface
    {
        $type = $this->getDateType();

        $format = match ($type) {
            'date'  => 'Y-m-d',
            'time'  => 'H:i',
            default => 'Y-m-d\TH:i',
        };

        // For time-only, add today's date so createFromFormat has a full timestamp
        if ($type === 'time') {
            $dateString = date('Y-m-d') . 'T' . $dateString;
            $format     = 'Y-m-d\TH:i';
        }

        $useImmutable = $this->shouldUseImmutable();

        try {
            $dateTime = $useImmutable
                ? DateTimeImmutable::createFromFormat($format, $dateString)
                : \DateTime::createFromFormat($format, $dateString);

            if ($dateTime === false) {
                throw new \RuntimeException('Invalid date format: ' . $dateString);
            }

            return $dateTime;
        } catch (\Exception $e) {
            throw new \RuntimeException('Invalid date format: ' . $dateString, 0, $e);
        }
    }

    /**
     * Determine if the Doctrine column maps to an immutable PHP type.
     *
     * Uses ClassMetadata::getTypeOfField() which returns the Doctrine type string
     * ('datetime_immutable', 'date_immutable', 'time_immutable', etc.).
     */
    private function shouldUseImmutable(): bool
    {
        $doctrineType = $this->entityManager
            ->getClassMetadata($this->entityClass)
            ->getTypeOfField($this->property);

        return str_ends_with($doctrineType ?? '', '_immutable');
    }

    /**
     * Get the date variant from the Doctrine column type.
     *
     * @return string One of: 'date', 'datetime', 'time'
     */
    private function getDateType(): string
    {
        $doctrineType = $this->entityManager
            ->getClassMetadata($this->entityClass)
            ->getTypeOfField($this->property);

        return match ($doctrineType) {
            'date', 'date_immutable'  => 'date',
            'time', 'time_immutable'  => 'time',
            default                   => 'datetime',
        };
    }
}
