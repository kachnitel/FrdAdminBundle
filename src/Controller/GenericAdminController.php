<?php

declare(strict_types=1);

namespace Frd\AdminBundle\Controller;

use Frd\AdminBundle\Service\EntityDiscoveryService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Generic Admin Controller with auto-discovery via #[Admin] attribute.
 *
 * This controller provides a zero-code admin interface for entities.
 *
 * Mark entities with #[Admin] attribute for auto-discovery:
 *     #[Admin(label: 'Products', icon: 'inventory')]
 *     class Product {}
 *
 * URLs are automatically kebab-cased: /admin/work-station
 */
class GenericAdminController extends AbstractAdminController
{
    public function __construct(
        private readonly EntityDiscoveryService $entityDiscovery,
        private readonly string $routePrefix = 'app_admin_entity',
        private readonly string $dashboardRoute = 'app_admin_dashboard',
        private readonly string $entityNamespace = 'App\\Entity\\',
    ) {}

    /**
     * List of supported entities discovered via #[Admin] attribute.
     *
     * @return array<string> List of entity short names (e.g., 'Product', 'Region')
     */
    protected function getSupportedEntities(): array
    {
        return $this->entityDiscovery->getAdminEntityShortNames();
    }

    protected function getRoutePrefix(): string
    {
        return $this->routePrefix;
    }

    /**
     * Dashboard: Lists all supported entities.
     */
    #[Route('/admin', name: 'app_admin_dashboard', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function dashboard(): Response
    {
        // Convert entities to view data with label and icon from #[Admin] attribute
        $entities = array_map(function($entityName) {
            // Resolve full class name
            $entityClass = $this->entityDiscovery->resolveEntityClass($entityName, $this->entityNamespace);

            // Get Admin attribute if available
            $adminAttr = $entityClass ? $this->entityDiscovery->getAdminAttribute($entityClass) : null;

            return [
                'name' => $entityName,
                // Use label from attribute, or fallback to formatted entity name
                'label' => $adminAttr?->getLabel() ?? trim(preg_replace('/[A-Z]/', ' $0', $entityName)),
                // Use icon from attribute, or null
                'icon' => $adminAttr?->getIcon(),
                // PascalCase to kebab-case slug (e.g. WorkStation -> work-station)
                'slug'  => strtolower(preg_replace('/[A-Z]/', '-$0', lcfirst($entityName))),
            ];
        }, $this->getSupportedEntities());

        return $this->render('@FrdAdmin/admin/dashboard.html.twig', [
            'entities' => $entities
        ]);
    }

    #[Route('/admin/{entitySlug}', name: 'app_admin_entity_index', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(string $entitySlug): Response
    {
        $entityName = $this->resolveEntityName($entitySlug);

        return $this->render('@FrdAdmin/admin/index_live.html.twig', [
            'entityClass' => $this->entityNamespace . $entityName,
            'entityShortClass' => $entityName
        ]);
    }

    #[Route('/admin/{entitySlug}/new', name: 'app_admin_entity_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, string $entitySlug): Response
    {
        $entityName = $this->resolveEntityName($entitySlug);
        return $this->doNew($entityName, $request);
    }

    #[Route('/admin/{entitySlug}/{id}', name: 'app_admin_entity_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function show(string $entitySlug, int $id): Response
    {
        $entityName = $this->resolveEntityName($entitySlug);
        return $this->doShow($entityName, $id);
    }

    #[Route('/admin/{entitySlug}/{id}/edit', name: 'app_admin_entity_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, string $entitySlug, int $id): Response
    {
        $entityName = $this->resolveEntityName($entitySlug);
        return $this->doEdit($entityName, $id, $request);
    }

    #[Route('/admin/{entitySlug}/{id}', name: 'app_admin_entity_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, string $entitySlug, int $id): Response
    {
        $entityName = $this->resolveEntityName($entitySlug);
        return $this->doDeleteEntity($entityName, $id, $request);
    }

    /**
     * Converts a kebab-case slug (e.g. work-station) to PascalCase Entity name (e.g. WorkStation).
     * Validates against the supported entities list.
     */
    private function resolveEntityName(string $slug): string
    {
        // Convert 'work-station' to 'WorkStation'
        $entityName = implode('', array_map(
            fn($part) => ucfirst($part),
            explode('-', $slug)
        ));

        if (!in_array($entityName, $this->getSupportedEntities())) {
            throw new NotFoundHttpException(sprintf('Entity "%s" is not supported.', $entityName));
        }

        return $entityName;
    }
}
