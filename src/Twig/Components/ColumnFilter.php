<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components;

use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\ComponentToolsTrait;

#[AsLiveComponent('K:Admin:ColumnFilter', template: '@KachnitelAdmin/components/ColumnFilter.html.twig')]
class ColumnFilter
{
    use DefaultActionTrait;
    use ComponentToolsTrait;

    #[LiveProp]
    public string $column;

    #[LiveProp(writable: true, onUpdated: 'onUpdated')]
    public string $value = '';

    /**
     * Full filter metadata (type, enumClass, showAllOption, placeholder, etc.)
     * @var array<string, mixed>
     */
    #[LiveProp]
    public array $filterMetadata = [];

    public function getType(): string
    {
        return $this->filterMetadata['type'] ?? 'text';
    }

    public function onUpdated(): void
    {
        $this->emitUp('filter:updated', [
            'column' => $this->column,
            'value'  => $this->value,
        ]);
    }
}
