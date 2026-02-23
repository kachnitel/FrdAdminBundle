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
                : ($currentValue !== null ? $currentValue->name : null);
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

    /**
     * Get the enum class for this property.
     *
     * @return class-string<\UnitEnum>|null
     */
    private function getEnumClass(): ?string
    {
        $propertyType = $this->getPropertyType();
        if ($propertyType === null || !enum_exists($propertyType)) {
            return null;
        }

        return $propertyType;
    }

    /**
     * Format an enum case for display.
     */
    private function formatEnumLabel(\UnitEnum $enum): string
    {
        // Check if enum has a label() or getLabel() method
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

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed>
     */
    public function getFormFieldConfig(): array
    {
        $required = !$this->isNullable();

        return [
            'type' => 'choice',
            'choices' => $this->getEnumCases(),
            'required' => $required,
        ];
    }

    /**
     * {@inheritdoc}
     */
    #[LiveAction]
    public function save(): void
    {
        $enumClass = $this->getEnumClass();

        if ($enumClass === null) {
            throw new \RuntimeException('Invalid enum type for property');
        }

        // Convert selected value back to enum instance
        if ($this->selectedValue === null) {
            $this->writeValue(null);
        } else {
            // Try backed enum first
            if (is_subclass_of($enumClass, \BackedEnum::class)) {
                $this->writeValue($enumClass::from($this->selectedValue));
            } else {
                // Unit enum
                foreach ($enumClass::cases() as $case) {
                    if ($case->name === $this->selectedValue) {
                        $this->writeValue($case);
                        break;
                    }
                }
            }
        }

        parent::save();
    }
}
