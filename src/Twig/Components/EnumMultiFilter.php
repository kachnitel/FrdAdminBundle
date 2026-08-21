<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components;

use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\PostHydrate;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\ComponentToolsTrait;

#[AsLiveComponent('K:Admin:EnumMultiFilter', template: '@KachnitelAdmin/components/EnumMultiFilter.html.twig')]
class EnumMultiFilter
{
    use DefaultActionTrait;
    use ComponentToolsTrait;

    #[LiveProp]
    public string $column;

    /**
     * Serialized selected values as JSON array: ["value1", "value2"]
     * Empty string means no filter is set.
     */
    #[LiveProp(writable: true)]
    public string $value = '';

    /**
     * PHP BackedEnum class name. Either this or $options must be provided.
     */
    #[LiveProp]
    public string $enumClass = '';

    /**
     * String options array. Either this or $enumClass must be provided.
     * @var array<string>
     */
    #[LiveProp]
    public array $options = [];

    #[LiveProp]
    public string $label = '';

    /**
     * @internal Individual selected values (internal tracking).
     * @var array<int|string>
     */
    #[LiveProp(writable: true, onUpdated: 'onSelectedValuesUpdated')]
    public array $selectedValues = [];

    /**
     * Get all available options as value => label pairs.
     *
     * @return array<string, string>
     */
    public function getChoices(): array
    {
        if ($this->enumClass !== '') {
            if (!is_subclass_of($this->enumClass, \BackedEnum::class)) {
                return [];
            }

            return $this->getEnumChoices($this->enumClass);
        }

        return array_combine($this->options, $this->options) ?: [];
    }

    /**
     * @param class-string<\BackedEnum> $enumClass
     * @return array<string, string>
     */
    private function getEnumChoices(string $enumClass): array
    {
        $choices = [];
        foreach ($enumClass::cases() as $case) {
            $label = method_exists($case, 'displayValue')
                ? $case->displayValue()
                : $case->name;
            $choices[(string) $case->value] = is_scalar($label) ? (string) $label : '';
        }

        return $choices;
    }

    #[PostHydrate]
    public function deserializeValue(): void
    {
        if ($this->value === '') {
            $this->selectedValues = [];
            return;
        }

        $decoded = json_decode($this->value, true);
        if (!is_array($decoded)) {
            $this->selectedValues = [$this->value];
            return;
        }

        $this->selectedValues = [];
        foreach ($decoded as $value) {
            if (is_int($value) || is_string($value)) {
                $this->selectedValues[] = $value;
            }
        }
    }

    public function mount(string $value = ''): void
    {
        $this->value = $value;
        $this->deserializeValue();
    }

    public function onSelectedValuesUpdated(): void
    {
        $this->value = empty($this->selectedValues)
            ? ''
            : json_encode(array_values($this->selectedValues), JSON_THROW_ON_ERROR);

        $this->emitUp('filter:updated', [
            'column' => $this->column,
            'value'  => $this->value,
        ]);
    }

    #[LiveAction]
    public function clear(): void
    {
        $this->selectedValues = [];
        $this->value = '';

        $this->emitUp('filter:updated', [
            'column' => $this->column,
            'value'  => $this->value,
        ]);
    }
}
