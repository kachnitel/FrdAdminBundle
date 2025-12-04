<?php

declare(strict_types=1);

namespace Frd\AdminBundle\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Provides admin bundle configuration as Twig globals.
 */
class AdminConfigExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly ?string $baseLayout
    ) {}

    public function getGlobals(): array
    {
        return [
            'frd_admin_base_layout' => $this->baseLayout,
        ];
    }
}
