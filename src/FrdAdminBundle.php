<?php

namespace Frd\AdminBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class FrdAdminBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $rootNode */
        $rootNode = $definition->rootNode();
        $rootNode->children()
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
                    ->info('Required role for accessing admin')
                ->end()
                ->scalarNode('base_layout')
                    ->defaultNull()
                    ->info('Base layout template (defaults to none, templates extend app layout)')
                ->end()
                ->arrayNode('entities')
                    ->info('List of entities to manage via the generic admin controller')
                    ->defaultValue([])
                    ->scalarPrototype()->end()
                    ->example(['Region', 'FulfillmentMethod', 'WorkStation'])
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
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Store configuration as parameters
        $builder->setParameter('frd_admin.entity_namespace', $config['entity_namespace']);
        $builder->setParameter('frd_admin.form_namespace', $config['form_namespace']);
        $builder->setParameter('frd_admin.form_suffix', $config['form_suffix']);
        $builder->setParameter('frd_admin.route_prefix', $config['route_prefix']);
        $builder->setParameter('frd_admin.dashboard_route', $config['dashboard_route']);
        $builder->setParameter('frd_admin.required_role', $config['required_role']);
        $builder->setParameter('frd_admin.entities', $config['entities'] ?? []);
        $builder->setParameter('frd_admin.enable_generic_controller', $config['enable_generic_controller']);
        $builder->setParameter('frd_admin.pagination.default_items_per_page', $config['pagination']['default_items_per_page']);
        $builder->setParameter('frd_admin.pagination.allowed_items_per_page', $config['pagination']['allowed_items_per_page']);

        // Load services
        $container->import('../config/services.yaml');
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Register Twig namespace
        $container->extension('twig', [
            'paths' => [
                $this->getPath() . '/templates' => 'FrdAdmin',
            ],
        ]);

        // Register LiveComponent namespace
        $container->extension('twig_component', [
            'defaults' => [
                'Frd\\AdminBundle\\Twig\\Components\\' => 'components/',
            ],
        ]);
    }
}
