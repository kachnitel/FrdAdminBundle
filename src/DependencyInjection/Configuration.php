<?php

namespace Frd\AdminBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Bundle configuration definition.
 */
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('frd_admin');
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
                    ->defaultValue('app_entity')
                    ->info('Route prefix for entity CRUD operations')
                ->end()
                ->scalarNode('required_role')
                    ->defaultValue('ROLE_ADMIN')
                    ->info('Required role for accessing admin')
                ->end()
                ->scalarNode('base_layout')
                    ->defaultNull()
                    ->info('Base layout template (defaults to none, templates extend app layout)')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
