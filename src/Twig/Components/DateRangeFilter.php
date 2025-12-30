<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components;

use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\PostHydrate;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\ComponentToolsTrait;

#[AsLiveComponent('K:Admin:DateRangeFilter', template: '@KachnitelAdmin/components/DateRangeFilter.html.twig')]
class DateRangeFilter
{
    use DefaultActionTrait;
    use ComponentToolsTrait;

    #[LiveProp]
    public string $column;

    /**
     * Serialized date range as JSON: {"from": "2024-01-01", "to": "2024-12-31"}
     * Empty string means no filter is set.
     */
    #[LiveProp(writable: true)]
    public string $value = '';

    #[LiveProp]
    public bool $compact = true;

    /**
     * @internal Extracted from deserialized value.
     * Writable and triggers onUpdated when changed.
     */
    #[LiveProp(writable: true, onUpdated: 'onUpdated')]
    public string $from = '';

    /**
     * @internal Extracted from deserialized value.
     * Writable and triggers onUpdated when changed.
     */
    #[LiveProp(writable: true, onUpdated: 'onUpdated')]
    public string $to = '';

    #[PostHydrate]
    public function deserializeValue(): void
    {
        if (!$this->value) {
            $this->from = '';
            $this->to = '';
            return;
        }

        $decoded = json_decode($this->value, true);
        if (is_array($decoded)) {
            $this->from = $decoded['from'] ?? '';
            $this->to = $decoded['to'] ?? '';
        }
    }

    #[LiveAction]
    public function clear(): void
    {
        $this->value = '';

        $this->deserializeValue();
    }

    public function mount(string $value = ''): void
    {
        $this->value = $value;

        $this->deserializeValue();
    }

    public function onUpdated(string $propertyName): void
    {
        if ($propertyName === 'value') {
            $this->deserializeValue();
            return;
        }

        // from or to changed
        $this->value = json_encode([
            'from' => $this->from ?: null,
            'to'   => $this->to ?: null,
        ]);

        $this->emitUp('filter:updated', [
            'column' => $this->column,
            'value'  => $this->value,
        ]);
    }
}
