<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Extension;

use Kachnitel\AdminBundle\Twig\Runtime\RowActionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class RowActionExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'admin_row_actions',
                [RowActionRuntime::class, 'getRowActions'],
            ),
            new TwigFunction(
                'admin_visible_row_actions',
                [RowActionRuntime::class, 'getVisibleRowActions'],
            ),
        ];
    }
}
