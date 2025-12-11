<?php

namespace Kachnitel\AdminBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Bundle configuration definition.
 */
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('kachnitel_admin');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('entity_namespace')
                    ->defaultValue('App\\Entity\\')
                    ->info('Base namespace for entities')
                ->end()
                ->scalarNode('form_namespace')
                    ->defaultValue('App\\Form\\')
                    ->info('Base namespace for form types')
                ->end()
                ->scalarNode('form_suffix')
                    ->defaultValue('FormType')
                    ->info('Suffix for form type classes')
                ->end()
                ->scalarNode('route_prefix')
                    ->defaultValue('app_admin_entity')
                    ->info('Route prefix for generic entity CRUD operations')
                ->end()
                ->scalarNode('dashboard_route')
                    ->defaultValue('app_admin_dashboard')
                    ->info('Dashboard route name')
                ->end()
                ->scalarNode('required_role')
                    ->defaultValue('ROLE_ADMIN')
                    ->info('Required role for accessing admin. Set to null to disable authentication.')
                    ->beforeNormalization()
                        ->ifTrue(fn($v) => $v === false || $v === 'false')
                        ->then(fn() => null)
                    ->end()
                ->end()
                ->scalarNode('base_layout')
                    ->defaultNull()
                    ->info('Base layout template (defaults to none, templates extend app layout)')
                ->end()
                ->booleanNode('enable_generic_controller')
                    ->defaultTrue()
                    ->info('Enable the generic admin controller with dynamic routes')
                ->end()
                ->arrayNode('pagination')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('default_items_per_page')
                            ->defaultValue(20)
                            ->min(1)
                            ->max(100)
                            ->info('Default number of items per page')
                        ->end()
                        ->arrayNode('allowed_items_per_page')
                            ->info('Allowed values for items per page dropdown')
                            ->defaultValue([10, 20, 50, 100])
                            ->integerPrototype()->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
