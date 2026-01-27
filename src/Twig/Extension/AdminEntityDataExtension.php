<?php

namespace Kachnitel\AdminBundle\Twig\Extension;

use Kachnitel\AdminBundle\Twig\Runtime\AdminEntityDataRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension providing entity data access functions.
 */
class AdminEntityDataExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('admin_get_entity_data', [AdminEntityDataRuntime::class, 'getData']),
            new TwigFunction('admin_get_entity_columns', [AdminEntityDataRuntime::class, 'getColumns']),
            new TwigFunction('admin_is_collection', [AdminEntityDataRuntime::class, 'isCollection']),
            new TwigFunction('admin_is_association', [AdminEntityDataRuntime::class, 'isAssociation']),
            new TwigFunction('admin_get_association_type', [AdminEntityDataRuntime::class, 'getAssociationType']),
            new TwigFunction('admin_get_property_type', [AdminEntityDataRuntime::class, 'getPropertyType']),
            new TwigFunction('admin_column_templates', [AdminEntityDataRuntime::class, 'getColumnTemplates']),
        ];
    }
}
