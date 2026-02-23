<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components\Field;

use DateTime;
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
 * - Date only (type: 'date')
 * - DateTime with time (type: 'datetime')
 * - Time only (type: 'time')
 * - Null values
 *
 * Detects type based on property type (e.g., 'date', 'datetime', 'time') and formats accordingly.
 *
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
     * Parse date string to DateTime or DateTimeImmutable based on property type.
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

        // For time-only, add today's date
        if ($type === 'time') {
            $dateString = date('Y-m-d') . 'T' . $dateString;
            $format = 'Y-m-d\TH:i';
        }

        // Determine if we should create DateTime or DateTimeImmutable
        $useImmutable = $this->shouldUseImmutable();

        try {
            if ($useImmutable) {
                $dateTime = DateTimeImmutable::createFromFormat($format, $dateString);
            } else {
                $dateTime = DateTime::createFromFormat($format, $dateString);
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
     * Determine if property uses DateTimeImmutable or DateTime.
     */
    private function shouldUseImmutable(): bool
    {
        $propertyType = $this->getPropertyType();

        // consider date, date_immutable, time, time_immutable, datetime, datetime_immutable, datetimetz, datetimetz_immutable
        return str_ends_with($propertyType ?? '', '_immutable');
    }

    /**
     * Get the date type from options.
     *
     * @return string One of: 'date', 'datetime', 'time'
     */
    private function getDateType(): string
    {
        switch ($this->getPropertyType()) {
            case 'date':
            case 'date_immutable':
                return 'date';
            case 'time':
            case 'time_immutable':
                return 'time';
            // case 'datetime':
            // case 'datetime_immutable':
            // case 'datetimetz':
            // case 'datetimetz_immutable':
            //     return 'datetime';
            default:
                // Default to datetime if type is not recognized or datetime variants
                return 'datetime';
        }
    }
}
