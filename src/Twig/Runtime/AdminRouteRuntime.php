<?php

namespace Kachnitel\AdminBundle\Twig\Runtime;

use Doctrine\ORM\EntityManagerInterface;
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
        private ?EntityManagerInterface $em = null,
        private string $formNamespace = 'App\\Form\\',
        private string $formSuffix = 'FormType'
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

        // Auto-fill route parameters
        $parameters = $this->autoFillIdParameter($object, $route, $parameters);
        $parameters = $this->autoFillClassParameter($object, $route, $parameters);
        $parameters = $this->autoFillEntitySlugParameter($object, $route, $parameters);

        return $this->router->generate($route, $parameters);
    }

    /**
     * Auto-fill id parameter if object has getId() method.
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
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
     * Auto-fill entitySlug parameter if route needs it (for GenericAdminController).
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
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
     * Check if the current user can perform an action on a specific entity object.
     *
     * @param object $entity The entity object
     * @param string $action Action name ('show', 'edit', 'delete')
     * @return bool True if action is accessible
     */
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
     * @param string $action Action name ('index', 'show', 'new', 'edit', 'delete')
     * @return bool True if action is accessible
     */
    public function isActionAccessible(string $entityShortName, string $action): bool
    {
        // Check if route exists
        if (!$this->hasRoute($entityShortName, $action)) {
            return false;
        }

        // Map action to voter attribute
        $voterAttribute = match($action) {
            'index' => AdminEntityVoter::ADMIN_INDEX,
            'show' => AdminEntityVoter::ADMIN_SHOW,
            'new' => AdminEntityVoter::ADMIN_NEW,
            'edit' => AdminEntityVoter::ADMIN_EDIT,
            'delete' => AdminEntityVoter::ADMIN_DELETE,
            default => null,
        };

        // Check permission via voter (if auth checker is available)
        if ($this->authChecker !== null && $voterAttribute !== null) {
            if (!$this->authChecker->isGranted($voterAttribute, $entityShortName)) {
                return false;
            }
        }

        // For new/edit actions, check if form exists
        if (in_array($action, ['new', 'edit'], true)) {
            if (!$this->hasForm($entityShortName)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get a URL to the admin page for a single related entity.
     *
     * Tries the "show" route first (direct link to the entity's detail page).
     * Falls back to the "index" route with an id filter if no show route is available.
     *
     * Returns null if the entity's class has no #[Admin] attribute.
     */
    public function getEntityAdminUrl(object $relatedEntity, bool $checkAccess = true): ?string
    {
        if ($this->entityDiscovery === null) {
            return null;
        }

        $entityClass = $this->getRealClass($relatedEntity);

        if (!$this->entityDiscovery->isAdminEntity($entityClass)) {
            return null;
        }

        $shortName = (new \ReflectionClass($entityClass))->getShortName();
        $entitySlug = strtolower((string) preg_replace('/[A-Z]/', '-$0', lcfirst($shortName)));
        $entityId = method_exists($relatedEntity, 'getId') ? $relatedEntity->getId() : null;

        // Try "show" route first
        if (!$checkAccess || $this->isActionAccessible($shortName, 'show')) {
            $showRoute = $this->getRoute($entityClass, 'show');
            if ($showRoute !== null && $entityId !== null) {

                return $this->router->generate($showRoute, [
                    'entitySlug' => $entitySlug,
                    'id' => $entityId,
                ]);
            }
        }

        if ($checkAccess && !$this->isActionAccessible($shortName, 'index')) {
            return null;
        }

        // Fallback to "index" route, filtered by id if available
        $indexRoute = $this->getRoute($entityClass, 'index');
        if ($indexRoute !== null) {
            $parameters = ['entitySlug' => $entitySlug];
            if ($entityId !== null) {
                $parameters['columnFilters'] = ['id' => (string) $entityId];
            }
            return $this->router->generate($indexRoute, $parameters);
        }

        return null;
    }

    /**
     * Get a URL to the target entity's admin list, pre-filtered to show items
     * related to the given entity's collection property.
     *
     * For OneToMany associations, the URL includes a columnFilter on the inverse
     * ManyToOne field so the list shows only items belonging to this entity.
     * For ManyToMany, the URL links to the target admin without a pre-applied filter.
     *
     * Returns null if the target entity has no #[Admin] attribute.
     */
    public function getCollectionAdminUrl(object $entity, string $property, bool $checkAccess = true): ?string
    {
        if ($this->em === null || $this->entityDiscovery === null) {
            return null;
        }

        $entityClass = $this->getRealClass($entity);
        $metadata = $this->em->getClassMetadata($entityClass);

        if (!$metadata->isCollectionValuedAssociation($property)) {
            return null;
        }

        $targetClass = $metadata->getAssociationTargetClass($property);

        if (!$this->entityDiscovery->isAdminEntity($targetClass)) {
            return null;
        }

        $route = $this->getRoute($targetClass, 'index');
        if ($route === null) {
            return null;
        }

        $targetShortName = (new \ReflectionClass($targetClass))->getShortName();

        if ($checkAccess && !$this->isActionAccessible($targetShortName, 'index')) {
            return null;
        }

        $targetSlug = strtolower((string) preg_replace('/[A-Z]/', '-$0', lcfirst($targetShortName)));

        $mapping = $metadata->getAssociationMapping($property);
        $mappedBy = $mapping->mappedBy ?? null;

        $parameters = ['entitySlug' => $targetSlug];

        if ($mappedBy !== null && method_exists($entity, 'getId') && $entity->getId() !== null) {
            $parameters['columnFilters'] = [$mappedBy => (string) $entity->getId()];
        }

        return $this->router->generate($route, $parameters);
    }

    /**
     * Check if an entity has a form defined.
     */
    private function hasForm(string $entityShortName): bool
    {
        if ($this->formRegistry === null || $this->entityDiscovery === null) {
            // If dependencies not available, assume form exists
            return true;
        }

        // Try to get form type from Admin attribute first
        try {
            $entityClass = $this->entityDiscovery->resolveEntityClass($entityShortName, 'App\\Entity\\');
            if ($entityClass) {
                $adminAttr = $this->entityDiscovery->getAdminAttribute($entityClass);
                $formType = $adminAttr?->getFormType()
                    ?: $this->formNamespace . $entityShortName . $this->formSuffix;
                return $this->formRegistry->hasType($formType);
            }
        } catch (\Exception) {
            // Fall through to default behavior
        }

        // Default: check standard form type
        $formType = $this->formNamespace . $entityShortName . $this->formSuffix;
        return $this->formRegistry->hasType($formType);
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
