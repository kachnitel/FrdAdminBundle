<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Extension;

use Kachnitel\AdminBundle\Twig\Runtime\BatchActionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class BatchActionExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'admin_batch_actions',
                [BatchActionRuntime::class, 'getVisibleBatchActions'],
            ),
        ];
    }
}
