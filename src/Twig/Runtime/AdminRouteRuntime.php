<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Runtime;

use Doctrine\Persistence\Proxy;
use Kachnitel\AdminBundle\Attribute\AdminRoutes;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\AttributeHelper;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Runtime for entity routing in templates.
 */
class AdminRouteRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private RouterInterface $router,
        private AttributeHelper $attributeHelper,
        private ?AuthorizationCheckerInterface $authChecker = null,
        private ?EntityDiscoveryService $entityDiscovery = null,
        private ?FormRegistryInterface $formRegistry = null,
        private string $formNamespace = 'App\\Form\\',
        private string $formSuffix = 'FormType',
        private string $entityNamespace = 'App\\Entity\\',
    ) {}

    /**
     * Generate a path for an entity's route.
     * @param array<string, mixed> $parameters
     */
    public function getPath(object|string $object, string $routeName, array $parameters = []): string
    {
        $route = $this->getRoute($object, $routeName);

        if (!$route) {
            throw new \Exception(sprintf(
                'Route "%s" not found for object "%s"',
                $routeName,
                is_object($object) ? $this->getRealClass($object) : $object
            ));
        }

        $parameters = $this->autoFillIdParameter($object, $route, $parameters);
        $parameters = $this->autoFillClassParameter($object, $route, $parameters);
        $parameters = $this->autoFillEntitySlugParameter($object, $route, $parameters);

        return $this->router->generate($route, $parameters);
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    private function autoFillIdParameter(object|string $object, string $route, array $parameters): array
    {
        if (
            empty($parameters['id'])
            && is_object($object)
            && $this->routeHasParameter($route, 'id')
        ) {
            if (method_exists($object, 'getId')) {
                $parameters['id'] = $object->getId();
            } elseif (property_exists($object, 'id') && isset($object->id)) {
                $parameters['id'] = $object->id;
            }
        }

        return $parameters;
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    private function autoFillClassParameter(object|string $object, string $route, array $parameters): array
    {
        if (empty($parameters['class']) && $this->routeHasParameter($route, 'class')) {
            $class = is_object($object) ? $this->getRealClass($object) : $object;
            $parameters['class'] = (new \ReflectionClass($class))->getShortName();
        }

        return $parameters;
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    private function autoFillEntitySlugParameter(object|string $object, string $route, array $parameters): array
    {
        if (empty($parameters['entitySlug']) && $this->routeHasParameter($route, 'entitySlug')) {
            $class = is_object($object) ? $this->getRealClass($object) : $object;
            $shortName = (new \ReflectionClass($class))->getShortName();
            $parameters['entitySlug'] = strtolower(preg_replace('/[A-Z]/', '-$0', lcfirst($shortName)));
        }

        return $parameters;
    }

    /**
     * Check if an entity has a specific route defined.
     * Returns true for generic admin routes even without AdminRoutes attribute.
     */
    public function hasRoute(object|string $object, string $name): bool
    {
        $routes = $this->getRoutesAttribute($object);

        if ($routes && $routes->has($name)) {
            return true;
        }

        return $this->getGenericAdminRoute($name) !== null;
    }

    /**
     * Get the route name for an entity action.
     * Falls back to generic admin routes if no AdminRoutes attribute is found.
     */
    public function getRoute(object|string $object, string $name): ?string
    {
        $routes = $this->getRoutesAttribute($object);

        if ($routes) {
            return $routes->get($name);
        }

        return $this->getGenericAdminRoute($name);
    }

    /**
     * Get generic admin controller route name for an action.
     */
    private function getGenericAdminRoute(string $action): ?string
    {
        return match ($action) {
            'index'     => 'app_admin_entity_index',
            'show'      => 'app_admin_entity_show',
            'edit'      => 'app_admin_entity_edit',
            'new'       => 'app_admin_entity_new',
            'archive'   => 'app_admin_entity_archive',
            'unarchive' => 'app_admin_entity_unarchive',
            'delete'    => 'app_admin_entity_delete',
            default     => null,
        };
    }

    private function getRoutesAttribute(object|string $object): ?object
    {
        $routes = $this->attributeHelper->getAttribute($object, AdminRoutes::class);

        if ($routes) {
            return $routes;
        }

        return null;
    }

    public function isRouteAccessible(string $route): bool
    {
        if ($this->authChecker === null) {
            return true;
        }

        return $this->router->getRouteCollection()->get($route) !== null;
    }

    public function canPerformAction(object $entity, string $action): bool
    {
        $entityClass = $this->getRealClass($entity);
        $shortName = (new \ReflectionClass($entityClass))->getShortName();

        return $this->isActionAccessible($shortName, $action);
    }

    /**
     * Check if a user can perform an action on an entity.
     * Checks permissions and form availability.
     *
     * @param string $entityShortName Entity short name (e.g., 'Product', 'User')
     * @param string $action Action name ('index', 'show', 'new', 'edit', 'archive', 'unarchive', 'delete')
     * @return bool True if action is accessible
     */
    public function isActionAccessible(string $entityShortName, string $action): bool
    {
        if (!$this->hasRoute($entityShortName, $action)) {
            return false;
        }

        $voterAttribute = match ($action) {
            'index'              => AdminEntityVoter::ADMIN_INDEX,
            'show'               => AdminEntityVoter::ADMIN_SHOW,
            'new'                => AdminEntityVoter::ADMIN_NEW,
            'edit'               => AdminEntityVoter::ADMIN_EDIT,
            'archive', 'unarchive' => AdminEntityVoter::ADMIN_ARCHIVE,
            'delete'             => AdminEntityVoter::ADMIN_DELETE,
            default              => null,
        };

        if ($this->authChecker !== null && $voterAttribute !== null) {
            if (!$this->authChecker->isGranted($voterAttribute, $entityShortName)) {
                return false;
            }
        }

        if (in_array($action, ['new', 'edit'], true)) {
            if (!$this->hasForm($entityShortName)) {
                return false;
            }
        }

        return true;
    }

    private function hasForm(string $entityShortName): bool
    {
        if ($this->formRegistry === null || $this->entityDiscovery === null) {
            return true;
        }

        try {
            $entityClass = $this->entityDiscovery->resolveEntityClass($entityShortName, $this->entityNamespace);
            if ($entityClass) {
                $adminAttr = $this->entityDiscovery->getAdminAttribute($entityClass);
                $formType = $adminAttr?->getFormType()
                    ?: $this->formNamespace . $entityShortName . $this->formSuffix;
                return $this->formRegistry->hasType($formType);
            }
        } catch (\Exception) {
            // Fall through to default behavior
        }

        $formType = $this->formNamespace . $entityShortName . $this->formSuffix;
        return $this->formRegistry->hasType($formType);
    }

    private function routeHasParameter(string $route, string $parameter): bool
    {
        $routeObj = $this->router->getRouteCollection()->get($route);

        if (!$routeObj) {
            return false;
        }

        if ($routeObj->hasRequirement($parameter)) {
            return true;
        }

        $path = $routeObj->getPath();
        return str_contains($path, '{' . $parameter . '}');
    }

    private function getRealClass(object $object): string
    {
        if ($object instanceof Proxy) {
            return get_parent_class($object);
        }

        return get_class($object);
    }
}
