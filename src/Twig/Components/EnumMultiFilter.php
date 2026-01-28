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

    #[LiveProp]
    public string $enumClass;

    #[LiveProp]
    public string $label = '';

    /**
     * @internal Individual selected values (internal tracking).
     * @var array<int|string>
     */
    #[LiveProp(writable: true, onUpdated: 'onSelectedValuesUpdated')]
    public array $selectedValues = [];

    #[PostHydrate]
    public function deserializeValue(): void
    {
        if ($this->value === '') {
            $this->selectedValues = [];
            return;
        }

        $decoded = json_decode($this->value, true);
        $this->selectedValues = is_array($decoded) ? $decoded : [$this->value];
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
