<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Form;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\OneToManyAssociationMapping;
use Kachnitel\AdminBundle\Form\Exception\NullabilityMismatchException;
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
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\UX\LiveComponent\Form\Type\LiveCollectionType;

/**
 * Maps Doctrine field and association metadata to Symfony form field configurations.
 *
 * Returns a config array of the form:
 *   ['type' => FormTypeClass::class, 'options' => [...], 'requiresValueGuard' => bool]
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
 * ## empty_data is always ''
 *
 * Every non-boolean scalar type — string, text, int, decimal/float,
 * date(_immutable), datetime(tz)(_immutable), time(_immutable), enum — uses
 * `empty_data: ''`, never `null`, regardless of nullability. This was
 * unified after two separate confirmed bugs traced back to the same wrong
 * assumption ("every core transformer treats a literal null the same as an
 * empty string"):
 *
 *   - DateTimeToHtml5LocalDateTimeTransformer::reverseTransform() (used by
 *     DateType/DateTimeType/TimeType's single_text/html5 widget) and
 *     ChoiceToValueTransformer::reverseTransform() (used by EnumType) both
 *     open with a guard that rejects anything that isn't a string outright
 *     — `if (!\is_string($value)) { throw new TransformationFailedException(...); }`
 *     for the date transformer. Only `''` ever reaches their documented
 *     "return null for empty input" branch; a literal `null` fails the
 *     is_string() check first. Passing `null` directly threw
 *     TransformationFailedException with Symfony's own generic per-type
 *     message ("Please enter a valid date and time."), on fields with no
 *     blank-value problem at all — including genuinely nullable ones,
 *     which never should have shown any error.
 *   - NumberToLocalizedStringTransformer (int/decimal/float) explicitly
 *     treats `null` and `''` identically
 *     (`if (null === $value || '' === $value) { return null; }`), so this
 *     exact mismatch never surfaced there. That asymmetry across core
 *     transformers is real but not something worth relying on field-type
 *     by field-type — '' is the one value confirmed safe across all of
 *     them (verified by reading symfony/form 7.2's actual
 *     DateTimeToHtml5LocalDateTimeTransformer.php,
 *     NumberToLocalizedStringTransformer.php, and
 *     ChoiceToValueTransformer.php directly).
 *
 * RequiredValueTransformer (attached below whenever `requiresValueGuard` is
 * true) is unaffected by this change in what it does: it still only ever
 * has to reject one thing — the `null` that every one of these transformers
 * correctly and safely resolves '' to on their own.
 *
 * ## Nullability handling
 *
 * Doctrine's DB-level `nullable` flag and the PHP property's own type
 * nullability (read via ClassMetadata::getReflectionClass(), the same
 * mechanism Doctrine itself uses internally to validate typed-property
 * mappings) are cross-checked before any field config is built:
 *
 *   - Doctrine nullable: true  + PHP disallows null → NullabilityMismatchException.
 *     A NULL row would crash on hydration; the form layer cannot paper over
 *     that, so this fails loudly and early instead, naming the exact field.
 *   - Doctrine nullable: true  + PHP allows null     → no guard, no
 *     constraint. A genuinely optional field; Symfony's own transformers
 *     handle a blank submission cleanly on their own.
 *   - Doctrine nullable: false (required), regardless of PHP nullability →
 *     int/decimal/float/date/datetime/time/enum get RequiredValueTransformer,
 *     a model transformer attached by DynamicEntityFormType whenever this
 *     config's `requiresValueGuard` is true. It rejects the null that a
 *     blank submission resolves to during reverseTransform(), which routes
 *     it through Symfony's ordinary "not synchronized" handling — the same
 *     mechanism that already turns a malformed NumberType submission into a
 *     validation error instead of a crash. `invalid_message` is set so the
 *     resulting error reads "X is required." rather than the type's generic
 *     default. string/text are never guarded: their empty_data ('') is
 *     already a type-safe value with no crash risk, so a plain NotBlank
 *     constraint catches a blank submission through the ordinary validation
 *     pass instead — NotBlank cannot fire on a guarded type, because
 *     FormValidator skips a field's `constraints` option once it's
 *     unsynchronized.
 *
 * ## Duplicate validation messages on string/text
 *
 * A string/text property may already carry its own Symfony Validator
 * constraint — `#[Assert\NotBlank]` directly on a Doctrine entity property
 * is normal, recommended practice, independent of this bundle. If the
 * property already has any validator constraint attribute, this mapper
 * does not add its own NotBlank on top of it. Doing so unconditionally
 * produced two separate, differently-worded error messages for the same
 * blank field ("This value should not be blank." from the entity's own
 * constraint, "Name is required." from this mapper) — confusing, and not
 * something a developer who already wrote #[Assert\NotBlank] asked for.
 * If the property carries no validator constraint at all, this mapper's
 * own NotBlank remains the sensible default, since plenty of entities
 * (including this bundle's own test fixtures) rely on the admin bundle to
 * provide that behaviour automatically rather than declaring it themselves.
 *
 * This check only applies to string/text. Guarded types (int/decimal/
 * date/time/enum) never reach their own constraints once
 * RequiredValueTransformer marks them unsynchronized on a blank submission
 * — FormValidator skips a field's `constraints` option in that case — so
 * there is no equivalent duplication risk there, and no check is needed.
 *
 * input option:
 *   Symfony's DateType/DateTimeType/TimeType 'input' controls the PHP object type
 *   returned by reverseTransform().  Without it, the default 'datetime' causes a
 *   TypeError when writing a \DateTime onto a \DateTimeImmutable property.
 *   We derive 'input' from the Doctrine type suffix ('_immutable' → 'datetime_immutable').
 *
 * data-admin-entity-class attr:
 *   Added to all EntityType-backed association configs (single-valued and ManyToMany).
 *   Consumed by the admin_compact form theme to render the EntityTypeAddButton inline-add
 *   widget next to autocomplete fields. OneToMany (LiveCollectionType) is excluded because
 *   it already has its own add/remove UI.
 */
class DoctrineFormTypeMapper
{
    /**
     * Get the Symfony form field config for a Doctrine scalar field.
     *
     * @param ClassMetadata<object> $metadata
     * @return array{type: class-string<FormTypeInterface<object>>, options: array<string, mixed>, requiresValueGuard?: bool}|null
     *   Null when the field type has no supported form equivalent.
     * @throws NullabilityMismatchException see class docblock.
     */
    public function getFieldConfig(ClassMetadata $metadata, string $fieldName): ?array
    {
        if (!$metadata->hasField($fieldName)) {
            return null;
        }

        $mapping  = $metadata->getFieldMapping($fieldName);
        $nullable = $mapping->nullable ?? false;

        $this->assertNullabilityAgrees($metadata, $fieldName, $nullable);

        // Backed enum — use a ChoiceType built from the enum cases
        $enumType = $mapping->enumType ?? null;
        if ($enumType !== null && enum_exists($enumType)) {
            return $this->buildEnumConfig($fieldName, $enumType, $nullable);
        }

        $hasOwnConstraint = $this->hasExistingValidatorConstraint($metadata, $fieldName);

        return match ($mapping->type) {
            'string' => [
                'type'    => TextType::class,
                'options' => $this->scalarOptions($fieldName, $nullable, hasOwnConstraint: $hasOwnConstraint),
            ],
            'text' => [
                'type'    => TextareaType::class,
                'options' => $this->scalarOptions($fieldName, $nullable, hasOwnConstraint: $hasOwnConstraint),
            ],
            'integer', 'smallint', 'bigint' => [
                'type'               => IntegerType::class,
                'options'            => $this->scalarOptions($fieldName, $nullable, guarded: true),
                'requiresValueGuard' => !$nullable,
            ],
            'decimal', 'float' => [
                'type'               => NumberType::class,
                'options'            => $this->scalarOptions($fieldName, $nullable, ['html5' => true], guarded: true),
                'requiresValueGuard' => !$nullable,
            ],
            'boolean' => [
                'type'    => CheckboxType::class,
                'options' => ['required' => false],
            ],
            'date' => [
                'type'               => DateType::class,
                'options'            => $this->scalarOptions($fieldName, $nullable, ['widget' => 'single_text', 'input' => 'datetime'], guarded: true),
                'requiresValueGuard' => !$nullable,
            ],
            'date_immutable' => [
                'type'               => DateType::class,
                'options'            => $this->scalarOptions($fieldName, $nullable, ['widget' => 'single_text', 'input' => 'datetime_immutable'], guarded: true),
                'requiresValueGuard' => !$nullable,
            ],
            'datetime', 'datetimetz' => [
                'type'               => DateTimeType::class,
                'options'            => $this->scalarOptions($fieldName, $nullable, ['widget' => 'single_text', 'input' => 'datetime'], guarded: true),
                'requiresValueGuard' => !$nullable,
            ],
            'datetime_immutable', 'datetimetz_immutable' => [
                'type'               => DateTimeType::class,
                'options'            => $this->scalarOptions($fieldName, $nullable, ['widget' => 'single_text', 'input' => 'datetime_immutable'], guarded: true),
                'requiresValueGuard' => !$nullable,
            ],
            'time' => [
                'type'               => TimeType::class,
                'options'            => $this->scalarOptions($fieldName, $nullable, ['widget' => 'single_text', 'input' => 'datetime'], guarded: true),
                'requiresValueGuard' => !$nullable,
            ],
            'time_immutable' => [
                'type'               => TimeType::class,
                'options'            => $this->scalarOptions($fieldName, $nullable, ['widget' => 'single_text', 'input' => 'datetime_immutable'], guarded: true),
                'requiresValueGuard' => !$nullable,
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
     * EntityType configs include `attr: ['data-admin-entity-class' => $targetClass]`
     * so the admin_compact form theme can render the EntityTypeAddButton inline-add
     * widget. OneToMany (LiveCollectionType) is intentionally excluded since it
     * already provides its own add/remove row controls.
     *
     * Not subject to the nullability cross-check above: a blank association
     * submission means "no selection", which has no static-sentinel-fabrication
     * risk the way a scalar field does.
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
     * Options shared by every non-boolean scalar field type.
     *
     * empty_data is always '' — see class docblock ("empty_data is always
     * ''") for why a literal null is unsafe here. When `$guarded` is true
     * (int/decimal/date/time/enum), a blank required field is reported via
     * `invalid_message` once RequiredValueTransformer rejects the null that
     * '' resolves to — NotBlank would never fire there, since FormValidator
     * skips constraints on an unsynchronized field. When `$guarded` is
     * false (string/text), the field stays synchronized on a blank
     * submission ('' is a legitimate string), so a real NotBlank constraint
     * is what catches it — unless `$hasOwnConstraint` is true, meaning the
     * entity property already declares its own validator constraint and
     * this mapper should not add a second, differently-worded one on top.
     *
     * @param array<string, mixed> $extra Extra options to merge into the returned array.
     * @return array<string, mixed> Form field options.
     */
    private function scalarOptions(string $fieldName, bool $nullable, array $extra = [], bool $guarded = false, bool $hasOwnConstraint = false): array
    {
        $options = array_merge([
            'required'   => !$nullable,
            'empty_data' => '',
        ], $extra);

        if ($nullable) {
            return $options;
        }

        if ($guarded) {
            $options['invalid_message'] = sprintf('%s is required.', ucfirst($fieldName));
        } elseif (!$hasOwnConstraint) {
            $options['constraints'] = [new NotBlank(message: sprintf('%s is required.', ucfirst($fieldName)))];
        }

        return $options;
    }

    /**
     * True when the property already carries any Symfony Validator
     * constraint attribute of its own (most commonly #[Assert\NotBlank]).
     * Used only for string/text — see class docblock ("Duplicate validation
     * messages on string/text").
     *
     * @param ClassMetadata<object> $metadata
     */
    private function hasExistingValidatorConstraint(ClassMetadata $metadata, string $fieldName): bool
    {
        $reflectionClass = $metadata->getReflectionClass();
        /** @phpstan-ignore identical.alwaysFalse */
        if ($reflectionClass === null || !$reflectionClass->hasProperty($fieldName)) {
            return false;
        }

        foreach ($reflectionClass->getProperty($fieldName)->getAttributes() as $attribute) {
            if (is_a($attribute->getName(), Constraint::class, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param ClassMetadata<object> $metadata
     * @throws NullabilityMismatchException when Doctrine permits null but the
     *   PHP property type cannot represent it.
     */
    private function assertNullabilityAgrees(ClassMetadata $metadata, string $fieldName, bool $dbNullable): void
    {
        if (!$dbNullable) {
            return; // DB requires a value; a PHP-nullable property is fine (and expected) here.
        }

        if ($this->phpAllowsNull($metadata, $fieldName)) {
            return;
        }

        throw NullabilityMismatchException::forField($metadata->getName(), $fieldName);
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function phpAllowsNull(ClassMetadata $metadata, string $fieldName): bool
    {
        $reflectionClass = $metadata->getReflectionClass();
        if ($reflectionClass === null) { // @phpstan-ignore identical.alwaysFalse
            // NOTE: Static-reflection edge case (see Doctrine's own
            // GetReflectionClassImplementation) — can't determine PHP-level
            // nullability; don't block form generation over it.
            return true;
        }

        try {
            $type = $reflectionClass->getProperty($fieldName)->getType();
        } catch (\ReflectionException) {
            return true;
        }

        // An untyped property imposes no PHP-level constraint at all.
        return $type === null || $type->allowsNull();
    }

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
                // Consumed by the admin_compact form theme to render the EntityTypeAddButton
                // inline-add widget next to the autocomplete field.
                'attr'         => ['data-admin-entity-class' => $targetClass],
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
                // Consumed by the admin_compact form theme to render the EntityTypeAddButton
                // inline-add widget next to the autocomplete field.
                'attr'         => ['data-admin-entity-class' => $targetClass],
            ],
        ];
    }

    /**
     * OneToMany → LiveCollectionType with recursive DynamicEntityFormType.
     *
     * is_root: false in entry_options prevents infinite recursion — child forms
     * will skip their own collection associations.
     *
     * No `data-admin-entity-class` attr is added here because LiveCollectionType
     * already provides add/remove row controls; the inline-add dialog is not applicable.
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
     * @return array{type: class-string<FormTypeInterface<object>>, options: array<string, mixed>, requiresValueGuard: bool}
     */
    private function buildEnumConfig(string $fieldName, string $enumClass, bool $nullable): array
    {
        $options = [
            'class'        => $enumClass,
            'required'     => !$nullable,
            'placeholder'  => $nullable ? '' : false,
            'choice_label' => fn (\BackedEnum $case): string => method_exists($case, 'displayValue') ? $case->displayValue() : $case->name,
            'empty_data'   => '',
        ];

        if (!$nullable) {
            $options['invalid_message'] = sprintf('%s is required.', ucfirst($fieldName));
        }

        return [
            'type'               => EnumType::class,
            'options'            => $options,
            'requiresValueGuard' => !$nullable,
        ];
    }
}
