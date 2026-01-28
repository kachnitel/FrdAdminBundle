<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components;

use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\PostHydrate;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\ComponentToolsTrait;

#[AsLiveComponent('K:Admin:ColumnFilter', template: '@KachnitelAdmin/components/ColumnFilter.html.twig')]
class ColumnFilter
{
    use DefaultActionTrait;
    use ComponentToolsTrait;

    #[LiveProp]
    public string $column;

    /**
     * Filter value - string for single select, JSON array string for multi-select.
     */
    #[LiveProp(writable: true, onUpdated: 'onUpdated')]
    public string $value = '';

    /**
     * For multi-select: individual selected values (internal tracking).
     * @var array<string>
     */
    #[LiveProp(writable: true, onUpdated: 'onSelectedValuesUpdated')]
    public array $selectedValues = [];

    /**
     * Full filter metadata (type, enumClass, showAllOption, multiple, placeholder, etc.)
     * @var array<string, mixed>
     */
    #[LiveProp]
    public array $filterMetadata = [];

    public function getType(): string
    {
        return $this->filterMetadata['type'] ?? 'text';
    }

    public function isMultiple(): bool
    {
        return $this->filterMetadata['multiple'] ?? false;
    }

    #[PostHydrate]
    public function deserializeValue(): void
    {
        if (!$this->isMultiple()) {
            return;
        }

        if ($this->value === '') {
            $this->selectedValues = [];
            return;
        }

        $decoded = json_decode($this->value, true);
        $this->selectedValues = is_array($decoded) ? $decoded : [$this->value];
    }

    public function onUpdated(): void
    {
        $this->emitUp('filter:updated', [
            'column' => $this->column,
            'value'  => $this->value,
        ]);
    }

    public function onSelectedValuesUpdated(): void
    {
        // Serialize selected values to JSON for multi-select
        $this->value = empty($this->selectedValues)
            ? ''
            : json_encode(array_values($this->selectedValues), JSON_THROW_ON_ERROR);

        $this->emitUp('filter:updated', [
            'column' => $this->column,
            'value'  => $this->value,
        ]);
    }

    #[LiveAction]
    public function clearMultiSelect(): void
    {
        $this->selectedValues = [];
        $this->value = '';

        $this->emitUp('filter:updated', [
            'column' => $this->column,
            'value'  => $this->value,
        ]);
    }
}
