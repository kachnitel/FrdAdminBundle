<?php

namespace Frd\AdminBundle\Twig\Runtime;

use Frd\AdminBundle\Attribute\AdminRoutes;
use Frd\AdminBundle\Service\AttributeHelper;
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
                is_object($object) ? get_class($object) : $object
            ));
        }

        // Auto-fill id parameter if object has getId() method
        if (
            empty($parameters['id'])
            && is_object($object)
            && method_exists($object, 'getId')
            && $this->routeHasParameter($route, 'id')
        ) {
            $parameters['id'] = $object->getId();
        }

        // Auto-fill class parameter if route needs it
        if (empty($parameters['class']) && $this->routeHasParameter($route, 'class')) {
            $class = is_object($object) ? get_class($object) : $object;
            $parameters['class'] = (new \ReflectionClass($class))->getShortName();
        }

        return $this->router->generate($route, $parameters);
    }

    /**
     * Check if an entity has a specific route defined.
     */
    public function hasRoute(object|string $object, string $name): bool
    {
        $routes = $this->attributeHelper->getAttribute($object, AdminRoutes::class);

        if (!$routes) {
            return false;
        }

        return $routes->has($name);
    }

    /**
     * Get the route name for an entity action.
     */
    public function getRoute(object|string $object, string $name): ?string
    {
        $routes = $this->attributeHelper->getAttribute($object, AdminRoutes::class);

        if (!$routes) {
            return null;
        }

        return $routes->get($name);
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
}
