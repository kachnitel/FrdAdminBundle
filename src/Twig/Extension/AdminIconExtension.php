<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Extension;

use Kachnitel\AdminBundle\Twig\Runtime\AdminIconRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AdminIconExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'admin_icon',
                [AdminIconRuntime::class, 'renderIcon'],
                ['is_safe' => ['html']],
            ),
        ];
    }
}
