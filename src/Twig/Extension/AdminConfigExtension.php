<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Provides admin bundle configuration as Twig globals.
 */
class AdminConfigExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly ?string $baseLayout,
        private readonly string $theme
    ) {}

    public function getGlobals(): array
    {
        return [
            'kachnitel_admin_base_layout' => $this->baseLayout,
            'kachnitel_admin_theme' => $this->theme,
        ];
    }
}
