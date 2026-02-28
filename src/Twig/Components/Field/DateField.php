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

        $currentValue = $this->readValue();

        if ($currentValue === null) {
            $this->dateValue = null;
            return;
        }

        if (!$currentValue instanceof DateTimeInterface) {
            $this->dateValue = null;
            return;
        }

        $type = $this->getDateType();
        $this->dateValue = match($type) {
            'date' => $currentValue->format('Y-m-d'),
            'time' => $currentValue->format('H:i'),
            default => $currentValue->format('Y-m-d\TH:i'),
        };
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
     * {@inheritdoc}
     */
    #[LiveAction]
    public function save(): void
    {
        // Convert string back to DateTime/DateTimeImmutable
        if ($this->dateValue === null || $this->dateValue === '') {
            $this->writeValue(null);
        } else {
            $dateTime = $this->parseDateTime($this->dateValue);
            $this->writeValue($dateTime);
        }

        parent::save();
    }

    /**
     * Parse date string to DateTime or DateTimeImmutable based on Doctrine column type.
     */
    private function parseDateTime(string $dateString): DateTimeInterface
    {
        $type = $this->getDateType();

        // Determine format based on type
        $format = match($type) {
            'date' => 'Y-m-d',
            'time' => 'H:i',
            default => 'Y-m-d\TH:i',
        };

        // For time-only, add today's date so createFromFormat has a full timestamp
        if ($type === 'time') {
            $dateString = date('Y-m-d') . 'T' . $dateString;
            $format = 'Y-m-d\TH:i';
        }

        $useImmutable = $this->shouldUseImmutable();

        try {
            if ($useImmutable) {
                $dateTime = DateTimeImmutable::createFromFormat($format, $dateString);
            } else {
                $dateTime = \DateTime::createFromFormat($format, $dateString);
            }

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
     * The PropertyInfo/TypeInfo API returns PHP class names instead and cannot
     * be used here reliably across Symfony versions.
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
     * Uses ClassMetadata::getTypeOfField() for the same reason as shouldUseImmutable():
     * DoctrineExtractor::getType().__toString() yields PHP class names, not Doctrine
     * type strings, so the switch cases ('date', 'time', …) would never match.
     *
     * @return string One of: 'date', 'datetime', 'time'
     */
    private function getDateType(): string
    {
        $doctrineType = $this->entityManager
            ->getClassMetadata($this->entityClass)
            ->getTypeOfField($this->property);

        return match($doctrineType) {
            'date', 'date_immutable'  => 'date',
            'time', 'time_immutable'  => 'time',
            default                   => 'datetime',
        };
    }
}
