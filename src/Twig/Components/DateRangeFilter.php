<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Components;

use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\ComponentToolsTrait;

#[AsLiveComponent('K:Admin:DateRangeFilter', template: '@KachnitelAdmin/components/DateRangeFilter.html.twig')]
class DateRangeFilter
{
    use DefaultActionTrait;
    use ComponentToolsTrait;

    #[LiveProp]
    public string $column;

    #[LiveProp(writable: true, onUpdated: 'onUpdated')]
    public string $from = '';

    #[LiveProp(writable: true, onUpdated: 'onUpdated')]
    public string $to = '';

    #[LiveProp]
    public bool $compact = true;

    public function onUpdated(): void
    {
        $this->emitUp('filter:updated', [
            'column' => $this->column,
            'value'  => [
                'from' => $this->from ?: null,
                'to'   => $this->to ?: null,
            ],
        ]);
    }
}
