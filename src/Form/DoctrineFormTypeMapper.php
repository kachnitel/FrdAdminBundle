<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Form;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\OneToManyAssociationMapping;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\UX\LiveComponent\Form\Type\LiveCollectionType;

/**
 * Maps Doctrine field and association metadata to Symfony form field configurations.
 *
 * Returns a config array of the form:
 *   ['type' => FormTypeClass::class, 'options' => [...]]
 *
 * Returns null for field types that have no sensible Symfony form equivalent
 * (e.g. json, array, object) — callers should skip these silently.
 *
 * Association mapping:
 *   - Single-valued (ManyToOne, OneToOne)  → EntityType
 *   - ManyToMany                           → EntityType with multiple: true
 *   - OneToMany                            → LiveCollectionType with recursive DynamicEntityFormType
 *
 * For backed enum fields, an EnumType (choice type) is returned when the enum
 * class is discoverable via the Doctrine field mapping's enumType property.
 *
 * empty_data rules (mirrors the string/integer pattern):
 *   - nullable field    → null  (PropertyAccessor accepts null on ?Type properties)
 *   - non-nullable int  → 0     (prevents null TypeError before validation fires)
 *   - non-nullable num  → 0     (idem)
 *   - non-nullable date → ISO sentinel string that the view transformer can parse.
 *     The transformer (DateTimeToLocalizedStringTransformer with html5 format) uses
 *     DateTime::createFromFormat('Y-m-d\TH:i:s', ...) for datetime types,
 *     'Y-m-d' for date types, and 'H:i:s' for time types.
 *     Sentinel values: '1970-01-01T00:00:00', '1970-01-01', '00:00:00'.
 *
 * input option:
 *   Symfony's DateType/DateTimeType/TimeType 'input' controls the PHP object type
 *   returned by reverseTransform().  Without it, the default 'datetime' causes a
 *   TypeError when writing a \DateTime onto a \DateTimeImmutable property.
 *   We derive 'input' from the Doctrine type suffix ('_immutable' → 'datetime_immutable').
 */
class DoctrineFormTypeMapper
{
    /**
     * Get the Symfony form field config for a Doctrine scalar field.
     *
     * @param ClassMetadata<object> $metadata
     * @return array{type: class-string<FormTypeInterface<object>>, options: array<string, mixed>}|null
     *   Null when the field type has no supported form equivalent.
     */
    public function getFieldConfig(ClassMetadata $metadata, string $fieldName): ?array
    {
        if (!$metadata->hasField($fieldName)) {
            return null;
        }

        $mapping  = $metadata->getFieldMapping($fieldName);
        $nullable = $mapping->nullable ?? false;

        // Backed enum — use a ChoiceType built from the enum cases
        $enumType = $mapping->enumType ?? null;
        if ($enumType !== null && enum_exists($enumType)) {
            return $this->buildEnumConfig($enumType, $nullable);
        }

        return match ($mapping->type) {
            'string'                                => [
                'type'    => TextType::class,
                'options' => ['required' => !$nullable, 'empty_data' => $nullable ? null : ''],
            ],
            'text'                                  => [
                'type'    => TextareaType::class,
                'options' => ['required' => !$nullable, 'empty_data' => $nullable ? null : ''],
            ],
            'integer', 'smallint', 'bigint'         => [
                'type'    => IntegerType::class,
                'options' => ['required' => !$nullable, 'empty_data' => $nullable ? '' : 0],
            ],
            'decimal', 'float'                      => [
                'type'    => NumberType::class,
                'options' => ['required' => !$nullable, 'html5' => true, 'empty_data' => $nullable ? '' : 0],
            ],
            'boolean'                               => [
                'type'    => CheckboxType::class,
                'options' => ['required' => false],
            ],
            'date'                                  => [
                'type'    => DateType::class,
                'options' => ['required' => !$nullable, 'widget' => 'single_text', 'input' => 'datetime', 'empty_data' => $nullable ? '' : '1970-01-01'],
            ],
            'date_immutable'                        => [
                'type'    => DateType::class,
                'options' => ['required' => !$nullable, 'widget' => 'single_text', 'input' => 'datetime_immutable', 'empty_data' => $nullable ? '' : '1970-01-01'],
            ],
            'datetime', 'datetimetz'                => [
                'type'    => DateTimeType::class,
                'options' => ['required' => !$nullable, 'widget' => 'single_text', 'input' => 'datetime', 'empty_data' => $nullable ? '' : '1970-01-01T00:00:00'],
            ],
            'datetime_immutable', 'datetimetz_immutable' => [
                'type'    => DateTimeType::class,
                'options' => ['required' => !$nullable, 'widget' => 'single_text', 'input' => 'datetime_immutable', 'empty_data' => $nullable ? '' : '1970-01-01T00:00:00'],
            ],
            'time'                                  => [
                'type'    => TimeType::class,
                'options' => ['required' => !$nullable, 'widget' => 'single_text', 'input' => 'datetime', 'empty_data' => $nullable ? '' : '00:00:00'],
            ],
            'time_immutable'                        => [
                'type'    => TimeType::class,
                'options' => ['required' => !$nullable, 'widget' => 'single_text', 'input' => 'datetime_immutable', 'empty_data' => $nullable ? '' : '00:00:00'],
            ],
            // json, array, object, simple_array — no supported form equivalent
            default => null,
        };
    }

    /**
     * Get the Symfony form field config for a Doctrine association.
     *
     * Single-valued (ManyToOne, OneToOne):
     *   → EntityType (simple dropdown)
     *
     * ManyToMany:
     *   → EntityType with multiple: true (multi-select)
     *
     * OneToMany:
     *   → LiveCollectionType with DynamicEntityFormType as entry_type.
     *     entry_options includes is_root: false to prevent infinite recursion —
     *     the child form will skip its own collection associations.
     *
     * @param ClassMetadata<object> $metadata
     * @return array{type: class-string<FormTypeInterface<object>>, options: array<string, mixed>}|null
     *   Null when the association does not exist.
     */
    public function getAssociationConfig(ClassMetadata $metadata, string $associationName): ?array
    {
        if (!$metadata->hasAssociation($associationName)) {
            return null;
        }

        if ($metadata->isSingleValuedAssociation($associationName)) {
            return $this->buildSingleAssociationConfig($metadata, $associationName);
        }

        // Collection-valued: distinguish OneToMany from ManyToMany
        $mapping = $metadata->getAssociationMapping($associationName);

        if ($mapping instanceof OneToManyAssociationMapping) {
            return $this->buildOneToManyConfig($metadata, $associationName);
        }

        // Any other collection-valued association is treated as ManyToMany
        // (covers both owning and inverse sides)
        return $this->buildManyToManyConfig($metadata, $associationName);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * @param ClassMetadata<object> $metadata
     * @return array{type: class-string<FormTypeInterface<object>>, options: array<string, mixed>}
     */
    private function buildSingleAssociationConfig(ClassMetadata $metadata, string $associationName): array
    {
        /** @var class-string $targetClass */
        $targetClass = $metadata->getAssociationTargetClass($associationName);

        return [
            'type'    => EntityType::class,
            'options' => [
                'class'        => $targetClass,
                'required'     => false,
                'autocomplete' => true,
            ],
        ];
    }

    /**
     * ManyToMany → EntityType with multiple: true (multi-select).
     *
     * @param ClassMetadata<object> $metadata
     * @return array{type: class-string<FormTypeInterface<object>>, options: array<string, mixed>}
     */
    private function buildManyToManyConfig(ClassMetadata $metadata, string $associationName): array
    {
        /** @var class-string $targetClass */
        $targetClass = $metadata->getAssociationTargetClass($associationName);

        return [
            'type'    => EntityType::class,
            'options' => [
                'class'        => $targetClass,
                'multiple'     => true,
                'required'     => false,
                'autocomplete' => true,
            ],
        ];
    }

    /**
     * OneToMany → LiveCollectionType with recursive DynamicEntityFormType.
     *
     * is_root: false in entry_options prevents infinite recursion — child forms
     * will skip their own collection associations.
     *
     * @param ClassMetadata<object> $metadata
     * @return array{type: class-string<FormTypeInterface<object>>, options: array<string, mixed>}
     */
    private function buildOneToManyConfig(ClassMetadata $metadata, string $associationName): array
    {
        /** @var class-string $targetClass */
        $targetClass = $metadata->getAssociationTargetClass($associationName);

        return [
            'type'    => LiveCollectionType::class,
            'options' => [
                'entry_type'    => DynamicEntityFormType::class,
                'entry_options' => [
                    'entity_class' => $targetClass,
                    'data_class'   => $targetClass,
                    'is_root'      => false,
                ],
                'allow_add'    => true,
                'allow_delete' => true,
                'by_reference' => false,
            ],
        ];
    }

    /**
     * Build a ChoiceType config from a backed enum class.
     *
     * @param class-string<\BackedEnum> $enumClass
     * @return array{type: class-string<FormTypeInterface<object>>, options: array<string, mixed>}
     */
    private function buildEnumConfig(string $enumClass, bool $nullable): array
    {
        return [
            'type'    => EnumType::class,
            'options' => [
                'class'       => $enumClass,
                'required'    => !$nullable,
                'placeholder' => $nullable ? '' : false,
                'choice_label' => fn(\BackedEnum $case): string => method_exists($case, 'displayValue') ? $case->displayValue() : $case->name,
                'empty_data' => $nullable ? null : $enumClass::cases()[0]->value, // Default to first case if non-nullable
            ],
        ];
    }
}
