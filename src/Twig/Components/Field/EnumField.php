<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components\Field;

use Kachnitel\AdminBundle\Twig\Components\Field\Traits\PropertyInfoTrait;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

/**
 * Editable field component for PHP backed enum types.
 *
 * Renders enum values as a dropdown select in edit mode. Supports both
 * unit enums and backed enums (string or int).
 *
 * @example
 * ```twig
 * <twig:Field:EnumField
 *     :entity="product"
 *     property="status"
 *     :editMode="editingProductId == product.id"
 * />
 * ```
 */
#[AsLiveComponent('K:Admin:Field:Enum', template: '@KachnitelAdmin/components/field/EnumField.html.twig')]
class EnumField extends AbstractEditableField
{
    use PropertyInfoTrait;

    /**
     * The currently selected enum value (as string for binding).
     */
    #[LiveProp(writable: true)]
    public ?string $selectedValue = null;

    /**
     * Initialize selected value from entity when entering edit mode.
     */
    public function mount(object $entity, string $property): void
    {
        parent::mount($entity, $property);

        if ($this->editMode) {
            $currentValue = $this->readValue();
            $this->selectedValue = $currentValue instanceof \BackedEnum
                ? (string) $currentValue->value
                : (is_string($currentValue)
                    ? $currentValue
                    : throw new \UnexpectedValueException('Unexpected current value for Enum. String or BackedEnum expected.'));
        }
    }

    /**
     * Get all possible enum cases for the dropdown.
     *
     * @return array<string, string> Map of value => label
     */
    #[ExposeInTemplate]
    public function getEnumCases(): array
    {
        $enumClass = $this->getEnumClass();
        if ($enumClass === null) {
            return [];
        }

        $cases = [];
        foreach ($enumClass::cases() as $case) {
            if ($case instanceof \BackedEnum) {
                $cases[(string) $case->value] = $this->formatEnumLabel($case);
            } else {
                $cases[$case->name] = $this->formatEnumLabel($case);
            }
        }

        return $cases;
    }

    #[LiveAction]
    public function cancelEdit(): void
    {
        parent::cancelEdit();
        $this->selectedValue = null;
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed>
     */
    public function getFormFieldConfig(): array
    {
        return [
            'type'     => 'choice',
            'choices'  => $this->getEnumCases(),
            'required' => !$this->isNullable(),
        ];
    }

    // ── Template method ────────────────────────────────────────────────────────

    /**
     * Resolve and write the selected enum value to the entity property.
     *
     * Backed enums: delegates to BackedEnum::from(), which throws \ValueError on invalid values.
     * Unit enums: iterates cases, throws \RuntimeException when no case name matches.
     *             A silent no-op would corrupt data if the client sends an unknown case name.
     *
     * Called only after canEdit() passes in the base save() method.
     *
     * @throws \RuntimeException when the property has no backing enum class,
     *                           or when no case name matches for a unit enum
     */
    protected function persistEdit(): void
    {
        $enumClass = $this->getEnumClass();

        if ($enumClass === null) {
            throw new \RuntimeException(
                sprintf('Invalid enum type for property "%s::$%s".', $this->entityClass, $this->property)
            );
        }

        if ($this->selectedValue === null) {
            $this->writeValue(null);
        } elseif (is_subclass_of($enumClass, \BackedEnum::class)) {
            // BackedEnum::from() already throws \ValueError on unknown values.
            $this->writeValue($enumClass::from($this->selectedValue));
        } else {
            // Unit enum: find by name, throw clearly if not found.
            $matched = false;
            foreach ($enumClass::cases() as $case) {
                if ($case->name === $this->selectedValue) {
                    $this->writeValue($case);
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                throw new \RuntimeException(
                    sprintf(
                        'Unknown case "%s" for unit enum %s. Valid cases: %s.',
                        $this->selectedValue,
                        $enumClass,
                        implode(', ', array_map(fn(\UnitEnum $case) => $case->name, $enumClass::cases())),
                    )
                );
            }
        }
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Get the enum class for this property.
     *
     * Uses ClassMetadata::getFieldMapping()->enumType rather than
     * PropertyInfoTrait::getPropertyType() because DoctrineExtractor::getType()
     * returns the DBAL column type string (e.g. 'string') via __toString(),
     * not the PHP enum FQCN. The enumType mapping key is the only reliable source.
     *
     * @return class-string<\UnitEnum>|null
     */
    private function getEnumClass(): ?string
    {
        $metadata = $this->entityManager->getClassMetadata($this->entityClass);
        if (!$metadata->hasField($this->property)) {
            return null;
        }
        /** @var string|null $enumType */
        $enumType = $metadata->getFieldMapping($this->property)->enumType ?? null;
        if ($enumType === null || !enum_exists($enumType)) {
            return null;
        }

        return $enumType;
    }

    /**
     * Format an enum case for display.
     */
    private function formatEnumLabel(\UnitEnum $enum): string
    {
        if (method_exists($enum, 'label')) {
            return (string) $enum->label();
        }

        if (method_exists($enum, 'getLabel')) {
            return (string) $enum->getLabel();
        }

        // Default: convert enum name to readable format
        // e.g., "PENDING_APPROVAL" -> "Pending Approval"
        return ucwords(strtolower(str_replace('_', ' ', $enum->name)));
    }
}
