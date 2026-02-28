<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Extension;

use Kachnitel\AdminBundle\Twig\Runtime\RowActionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Registers row action Twig functions.
 */
class RowActionExtension extends AbstractExtension
{
    /**
     * @return array<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('admin_row_actions', [RowActionRuntime::class, 'getRowActions']),
            new TwigFunction('admin_visible_row_actions', [RowActionRuntime::class, 'getVisibleRowActions']),
            new TwigFunction('admin_is_action_visible', [RowActionRuntime::class, 'isActionVisible']),
        ];
    }
}
