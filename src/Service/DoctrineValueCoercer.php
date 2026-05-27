<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Converts raw scalar form values (strings, booleans from checkboxes) into the
 * typed PHP values expected by Doctrine entity setters.
 *
 * Used exclusively by AutoEntityForm for the "new entity" code path, where field
 * values are collected as plain LiveProp scalars in the parent component rather
 * than being persisted individually by child Field components.
 *
 * The edit code path (formMode=true Field components) does not use this class —
 * each Field component already handles its own type coercion internally.
 *
 * ## Supported Doctrine types
 *
 * | Doctrine type                          | PHP type produced         |
 * |----------------------------------------|---------------------------|
 * | integer, smallint, bigint              | int                       |
 * | decimal, float                         | float                     |
 * | boolean                                | bool                      |
 * | date, date_immutable                   | DateTimeImmutable (date)  |
 * | datetime, datetimetz, datetime_immutable, datetimetz_immutable | DateTimeImmutable |
 * | time, time_immutable                   | DateTimeImmutable (time)  |
 * | Backed enum (via enumType mapping)     | BackedEnum instance       |
 * | string, text, and all others           | string (unchanged)        |
 *
 * ## Nullable fields
 *
 * When the raw value is an empty string and the Doctrine column is nullable,
 * null is returned regardless of type.
 *
 * ## Associations (ManyToOne / OneToOne)
 *
 * Single-valued associations expect the related entity's integer ID as the raw
 * value. The entity is loaded via EntityManager::getReference() (no extra SQL).
 */
class DoctrineValueCoercer
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Coerce a raw form value to the PHP type expected by the entity property.
     *
     * @param ClassMetadata<object> $metadata
     * @param string                $property  Property name on the entity
     * @param mixed                 $rawValue  Raw value from LiveProp / form input
     * @return mixed                Typed PHP value ready to pass to PropertyAccessor
     */
    public function coerce(ClassMetadata $metadata, string $property, mixed $rawValue): mixed
    {
        // Association: raw value is the related entity's integer ID.
        if ($metadata->hasAssociation($property)) {
            return $this->coerceAssociation($metadata, $property, $rawValue);
        }

        if (!$metadata->hasField($property)) {
            return $rawValue;
        }

        $mapping  = $metadata->getFieldMapping($property);
        $nullable = $mapping->nullable ?? false;

        // Empty string on a nullable column → null.
        if ($rawValue === '' || $rawValue === null) {
            return $nullable ? null : $rawValue;
        }

        // Backed enum — resolve before generic type mapping.
        $enumType = $mapping->enumType ?? null;
        /** @phpstan-ignore function.alreadyNarrowedType */
        if ($enumType !== null && is_a($enumType, \BackedEnum::class, true)) {
            /** @var class-string<\BackedEnum> $enumType */
            return $enumType::from($rawValue);
        }

        return match ($mapping->type) {
            'integer', 'smallint', 'bigint'                                        => (int) $rawValue,
            'decimal', 'float'                                                     => (float) $rawValue,
            'boolean'                                                              => $this->coerceBool($rawValue),
            'date', 'date_immutable'                                               => $this->coerceDate($rawValue),
            'datetime', 'datetime_immutable', 'datetimetz', 'datetimetz_immutable' => $this->coerceDatetime($rawValue),
            'time', 'time_immutable'                                               => $this->coerceTime($rawValue),
            default                                                                => (string) $rawValue,
        };
    }

    /**
     * Coerce all editable fields of a new entity from a formValues array.
     *
     * Skips any property not present in formValues (left at its default).
     *
     * @param ClassMetadata<object>     $metadata
     * @param array<string, mixed>      $formValues  Raw form values keyed by property name
     * @return array<string, mixed>     Typed values ready for PropertyAccessor::setValue()
     */
    public function coerceAll(ClassMetadata $metadata, array $formValues): array
    {
        $result = [];

        foreach ($formValues as $property => $rawValue) {
            $result[$property] = $this->coerce($metadata, $property, $rawValue);
        }

        return $result;
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function coerceAssociation(ClassMetadata $metadata, string $property, mixed $rawValue): mixed
    {
        if ($rawValue === '' || $rawValue === null) {
            return null;
        }

        /** @var class-string $targetClass */
        $targetClass = $metadata->getAssociationTargetClass($property);

        // getReference() returns a proxy — no DB hit unless fields are accessed.
        return $this->em->getReference($targetClass, (int) $rawValue);
    }

    private function coerceBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        // HTML checkboxes submit '1' / 'on' when checked, absent when unchecked.
        return in_array($value, ['1', 'true', 'on', true], true);
    }

    private function coerceDate(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        $dateTime = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $value);

        return $dateTime !== false ? $dateTime : null;
    }

    private function coerceDatetime(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        // Try HTML datetime-local format first ('Y-m-d\TH:i'), then full ISO.
        foreach (['Y-m-d\TH:i', 'Y-m-d H:i:s', \DateTimeInterface::ATOM] as $format) {
            $dateTime = \DateTimeImmutable::createFromFormat($format, (string) $value);
            if ($dateTime !== false) {
                return $dateTime;
            }
        }

        return null;
    }

    private function coerceTime(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        $dateTime = \DateTimeImmutable::createFromFormat('H:i', (string) $value)
            ?: \DateTimeImmutable::createFromFormat('H:i:s', (string) $value);

        return $dateTime !== false ? $dateTime : null;
    }
}
