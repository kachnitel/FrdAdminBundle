<?php

namespace Kachnitel\AdminBundle\Twig\Runtime;

use Doctrine\Persistence\Proxy;
use Kachnitel\AdminBundle\Attribute\AdminRoutes;
use Kachnitel\AdminBundle\Service\AttributeHelper;
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
        private ?AuthorizationCheckerInterface $authChecker = null
    ) {}

    /**
     * Generate a path for an entity's route.
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

        // Auto-fill route parameters
        $parameters = $this->autoFillIdParameter($object, $route, $parameters);
        $parameters = $this->autoFillClassParameter($object, $route, $parameters);
        $parameters = $this->autoFillEntitySlugParameter($object, $route, $parameters);

        return $this->router->generate($route, $parameters);
    }

    /**
     * Auto-fill id parameter if object has getId() method.
     */
    private function autoFillIdParameter(object|string $object, string $route, array $parameters): array
    {
        if (
            empty($parameters['id'])
            && is_object($object)
            && method_exists($object, 'getId')
            && $this->routeHasParameter($route, 'id')
        ) {
            $parameters['id'] = $object->getId();
        }

        return $parameters;
    }

    /**
     * Auto-fill class parameter if route needs it.
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
     * Auto-fill entitySlug parameter if route needs it (for GenericAdminController).
     */
    private function autoFillEntitySlugParameter(object|string $object, string $route, array $parameters): array
    {
        if (empty($parameters['entitySlug']) && $this->routeHasParameter($route, 'entitySlug')) {
            $class = is_object($object) ? $this->getRealClass($object) : $object;
            $shortName = (new \ReflectionClass($class))->getShortName();
            // Convert PascalCase to kebab-case (e.g., WorkStation -> work-station)
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

        // Check if generic admin route exists for this action
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

        // Fallback to generic admin controller routes
        return $this->getGenericAdminRoute($name);
    }

    /**
     * Get generic admin controller route name for an action.
     */
    private function getGenericAdminRoute(string $action): ?string
    {
        return match($action) {
            'index' => 'app_admin_entity_index',
            'show' => 'app_admin_entity_show',
            'edit' => 'app_admin_entity_edit',
            'new' => 'app_admin_entity_new',
            'delete' => 'app_admin_entity_delete',
            default => null,
        };
    }

    /**
     * Get routes attribute - checks both AdminRoutes and App\Attributes\Entity\Routes.
     */
    private function getRoutesAttribute(object|string $object): ?object
    {
        // First try AdminRoutes
        $routes = $this->attributeHelper->getAttribute($object, AdminRoutes::class);

        if ($routes) {
            return $routes;
        }

        // Fallback to app's Routes attribute if it exists
        // if (class_exists('App\Attributes\Entity\Routes')) {
        //     return $this->attributeHelper->getAttribute($object, 'App\Attributes\Entity\Routes');
        // }

        return null;
    }

    /**
     * Check if the current user has access to a route.
     */
    public function isRouteAccessible(string $route): bool
    {
        // If no auth checker, assume accessible
        if ($this->authChecker === null) {
            return true;
        }

        // For now, just check if route exists
        // Applications can extend this with custom security logic
        return $this->router->getRouteCollection()->get($route) !== null;
    }

    /**
     * Check if route has a specific parameter.
     */
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

    /**
     * Get the real class name of an object, handling Doctrine proxies.
     */
    private function getRealClass(object $object): string
    {
        // If it's a Doctrine proxy, get the parent class (the real entity class)
        if ($object instanceof Proxy) {
            return get_parent_class($object);
        }

        return get_class($object);
    }
}
