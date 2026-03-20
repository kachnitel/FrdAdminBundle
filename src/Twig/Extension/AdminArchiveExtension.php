<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Extension;

use Kachnitel\AdminBundle\Twig\Runtime\AdminArchiveRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension providing archive-related functions.
 */
class AdminArchiveExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('admin_is_archived', [AdminArchiveRuntime::class, 'isArchived']),
        ];
    }
}
