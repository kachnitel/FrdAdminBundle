<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Twig\Runtime;

use Doctrine\Persistence\Proxy;
use Kachnitel\AdminBundle\Attribute\AdminRoutes;
use Kachnitel\AdminBundle\Service\AttributeHelper;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Runtime for entity routing in templates.
 *
 * Handles route name resolution and URL generation for admin entity actions.
 * Permission and form-availability checks are delegated to ActionAccessibilityChecker.
 */
class AdminRouteRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private RouterInterface $router,
        private AttributeHelper $attributeHelper,
        private ActionAccessibilityChecker $accessibilityChecker,
        private ?AuthorizationCheckerInterface $authChecker = null,
    ) {}

    /**
     * Generate a path for an entity's route.
     *
     * @param object|class-string  $object
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
     * Check if an entity has a specific route defined.
     * Returns true for generic admin routes even without AdminRoutes attribute.
     */
    public function hasRoute(object|string $object, string $name): bool
    {
        $routes = $this->getRoutesAttribute($object);

        if ($routes !== null && $routes->has($name)) {
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

        if ($routes !== null) {
            return $routes->get($name);
        }

        return $this->getGenericAdminRoute($name);
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
        $shortName = (new \ReflectionClass($this->getRealClass($entity)))->getShortName();

        return $this->isActionAccessible($shortName, $action);
    }

    /**
     * Check if a user can perform an action on an entity.
     *
     * @param string $entityShortName Entity short name (e.g., 'Product', 'User')
     * @param string $action          Action name ('index', 'show', 'new', 'edit', 'archive', 'unarchive', 'delete')
     */
    public function isActionAccessible(string $entityShortName, string $action): bool
    {
        return $this->accessibilityChecker->isActionAccessible(
            $entityShortName,
            $action,
            $this->hasRoute($entityShortName, $action),
        );
    }

    // ── Private helpers ────────────────────────────────────────────────────────

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

    private function getRoutesAttribute(object|string $object): ?AdminRoutes
    {
        /** @var AdminRoutes|null $routes */
        $routes = $this->attributeHelper->getAttribute($object, AdminRoutes::class);

        return $routes;
    }

    /**
     * @param object|class-string  $object
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
     * @param object|class-string  $object
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    private function autoFillClassParameter(object|string $object, string $route, array $parameters): array
    {
        if (empty($parameters['class']) && $this->routeHasParameter($route, 'class')) {
            $shortName = $this->getShortClassName($object);
            if ($shortName === false) {
                return $parameters;
            }
            $parameters['class'] = $shortName;
        }

        return $parameters;
    }

    /**
     * @param object|class-string  $object
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    private function autoFillEntitySlugParameter(object|string $object, string $route, array $parameters): array
    {
        if (empty($parameters['entitySlug']) && $this->routeHasParameter($route, 'entitySlug')) {
            $shortName = $this->getShortClassName($object);
            if ($shortName === false) {
                return $parameters;
            }
            $parameters['entitySlug'] = strtolower((string) preg_replace('/[A-Z]/', '-$0', lcfirst($shortName)));
        }

        return $parameters;
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

        return str_contains($routeObj->getPath(), '{' . $parameter . '}');
    }

    /**
     * @return class-string
     */
    private function getRealClass(object $object): string
    {
        if ($object instanceof Proxy) {
            $parent = get_parent_class($object);
            if ($parent !== false) {
                return $parent;
            }
        }

        return $object::class;
    }

    /**
     * @param object|class-string $object
     */
    private function getShortClassName(object|string $object): string|false
    {
        $class = is_object($object) ? $this->getRealClass($object) : $object;
        if (!class_exists($class)) {
            return false;
        }

        /** @var class-string $class */
        return (new \ReflectionClass($class))->getShortName();
    }
}
