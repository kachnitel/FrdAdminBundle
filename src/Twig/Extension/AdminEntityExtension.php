<?php

namespace Kachnitel\AdminBundle\Twig\Extension;

use Kachnitel\AdminBundle\Twig\Runtime\AdminEntityDataRuntime;
use Kachnitel\AdminBundle\Twig\Runtime\AdminEntityInfoRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension providing entity data access functions.
 */
class AdminEntityExtension extends AbstractExtension
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
            new TwigFunction('admin_column_templates', [AdminEntityInfoRuntime::class, 'getColumnTemplates']),
            // Convenience: combines admin_is_collection + admin_get_property_type + admin_column_templates
            new TwigFunction('admin_entity_column_templates', [AdminEntityInfoRuntime::class, 'getEntityColumnTemplates']),
            new TwigFunction('admin_field_component_name', [AdminEntityInfoRuntime::class, 'getFieldComponentName']),
            new TwigFunction('admin_column_attribute', [AdminEntityInfoRuntime::class, 'getColumnAttribute']),
            new TwigFunction('admin_entity_label', [AdminEntityDataRuntime::class, 'getEntityLabel']),
        ];
    }
}