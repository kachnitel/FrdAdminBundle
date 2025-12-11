<?php

namespace Kachnitel\AdminBundle\Twig\Extension;

use Kachnitel\AdminBundle\Twig\Runtime\AdminRouteRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Twig extension for entity routing functions.
 */
class AdminRouteExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('admin_get_route', [AdminRouteRuntime::class, 'getRoute']),
            new TwigFilter('admin_has_route', [AdminRouteRuntime::class, 'hasRoute']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('admin_object_path', [AdminRouteRuntime::class, 'getPath']),
            new TwigFunction('admin_route_accessible', [AdminRouteRuntime::class, 'isRouteAccessible']),
            new TwigFunction('admin_action_accessible', [AdminRouteRuntime::class, 'isActionAccessible']),
            new TwigFunction('admin_can_perform_action', [AdminRouteRuntime::class, 'canPerformAction']),
        ];
    }
}
