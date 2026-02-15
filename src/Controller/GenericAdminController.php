<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Controller;

use Kachnitel\AdminBundle\DataSource\DataSourceRegistry;
use Kachnitel\AdminBundle\DataSource\DoctrineDataSource;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Service\Attribute\Required;

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
 *
 * Permissions are checked using the AdminEntityVoter which respects:
 * 1. Entity-specific permissions from #[Admin(permissions: [...])]
 * 2. Global required_role configuration (fallback)
 */
class GenericAdminController extends AbstractAdminController
{
    private DataSourceRegistry $dataSourceRegistry;

    public function __construct(
        private readonly EntityDiscoveryService $entityDiscovery,
        private readonly string $routePrefix = 'app_admin_entity',
        private readonly string $dashboardRoute = 'app_admin_dashboard',
        private readonly string $entityNamespace = 'App\\Entity\\',
        private readonly ?string $requiredRole = 'ROLE_ADMIN',
    ) {}

    #[Required]
    public function setDataSourceRegistry(DataSourceRegistry $registry): void
    {
        $this->dataSourceRegistry = $registry;
    }

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
     * Dashboard: Lists all supported entities and data sources.
     */
    #[Route('/admin', name: 'app_admin_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        // Dashboard uses global required_role (doesn't check entity-specific permissions)
        $this->checkGlobalPermission();

        $supportedEntities = $this->getSupportedEntities();

        // sort entities alphabetically by label (with fallback to name if no label)
        usort($supportedEntities, function($a, $b) {
            $labelA = $this->entityDiscovery->getAdminAttribute($this->entityNamespace . $a)?->getLabel() ?? $a;
            $labelB = $this->entityDiscovery->getAdminAttribute($this->entityNamespace . $b)?->getLabel() ?? $b;
            return strcasecmp($labelA, $labelB);
        });

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
                'type' => 'entity',
            ];
        }, $supportedEntities);

        // Collect non-Doctrine data sources (e.g., audit logs)
        $dataSources = [];
        foreach ($this->dataSourceRegistry->all() as $identifier => $dataSource) {
            // Skip Doctrine data sources (they're already in entities list)
            if ($dataSource instanceof DoctrineDataSource) {
                continue;
            }
            $dataSources[] = [
                'identifier' => $identifier,
                'label' => $dataSource->getLabel(),
                'icon' => $dataSource->getIcon(),
                'type' => 'datasource',
            ];
        }

        return $this->render('@KachnitelAdmin/admin/dashboard.html.twig', [
            'entities' => $entities,
            'dataSources' => $dataSources,
        ]);
    }

    #[Route('/admin/{entitySlug}', name: 'app_admin_entity_index', methods: ['GET'])]
    public function index(string $entitySlug): Response
    {
        $entityName = $this->resolveEntityName($entitySlug);
        $this->checkEntityPermission(AdminEntityVoter::ADMIN_INDEX, $entityName);

        return $this->render('@KachnitelAdmin/admin/index_live.html.twig', [
            'entityClass' => $this->entityNamespace . $entityName,
            'entityShortClass' => $entityName
        ]);
    }

    #[Route('/admin/{entitySlug}/new', name: 'app_admin_entity_new', methods: ['GET', 'POST'])]
    public function new(Request $request, string $entitySlug): Response
    {
        $entityName = $this->resolveEntityName($entitySlug);
        $this->checkEntityPermission(AdminEntityVoter::ADMIN_NEW, $entityName);

        return $this->doNew($entityName, $request);
    }

    #[Route('/admin/{entitySlug}/{id}', name: 'app_admin_entity_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(string $entitySlug, int $id): Response
    {
        $entityName = $this->resolveEntityName($entitySlug);
        $this->checkEntityPermission(AdminEntityVoter::ADMIN_SHOW, $entityName);

        return $this->doShow($entityName, $id);
    }

    #[Route('/admin/{entitySlug}/{id}/edit', name: 'app_admin_entity_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, string $entitySlug, int $id): Response
    {
        $entityName = $this->resolveEntityName($entitySlug);
        $this->checkEntityPermission(AdminEntityVoter::ADMIN_EDIT, $entityName);

        return $this->doEdit($entityName, $id, $request);
    }

    #[Route('/admin/{entitySlug}/{id}', name: 'app_admin_entity_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, string $entitySlug, int $id): Response
    {
        $entityName = $this->resolveEntityName($entitySlug);
        $this->checkEntityPermission(AdminEntityVoter::ADMIN_DELETE, $entityName);

        return $this->doDeleteEntity($entityName, $id, $request);
    }

    // ===== Data Source Routes (for non-Doctrine data sources like audit logs) =====

    /**
     * List data source entries.
     */
    #[Route('/admin/data/{dataSourceId}', name: 'app_admin_datasource_index', methods: ['GET'], priority: 10)]
    public function dataSourceIndex(string $dataSourceId): Response
    {
        $this->checkGlobalPermission();

        $dataSource = $this->dataSourceRegistry->get($dataSourceId);
        if (!$dataSource) {
            throw new NotFoundHttpException(sprintf('Data source "%s" not found.', $dataSourceId));
        }

        return $this->render('@KachnitelAdmin/admin/datasource_index.html.twig', [
            'dataSourceId' => $dataSourceId,
            'dataSource' => $dataSource,
        ]);
    }

    /**
     * Show a single data source entry.
     */
    #[Route('/admin/data/{dataSourceId}/{id}', name: 'app_admin_datasource_show', methods: ['GET'], priority: 10)]
    public function dataSourceShow(string $dataSourceId, string $id): Response
    {
        $this->checkGlobalPermission();

        $dataSource = $this->dataSourceRegistry->get($dataSourceId);
        if (!$dataSource) {
            throw new NotFoundHttpException(sprintf('Data source "%s" not found.', $dataSourceId));
        }

        if (!$dataSource->supportsAction('show')) {
            throw new NotFoundHttpException('This data source does not support showing individual entries.');
        }

        $item = $dataSource->find($id);
        if (!$item) {
            throw new NotFoundHttpException(sprintf('Entry "%s" not found in data source "%s".', $id, $dataSourceId));
        }

        return $this->render('@KachnitelAdmin/admin/datasource_show.html.twig', [
            'dataSourceId' => $dataSourceId,
            'dataSource' => $dataSource,
            'item' => $item,
        ]);
    }

    /**
     * Check entity-specific permissions if authentication is enabled.
     */
    private function checkEntityPermission(string $attribute, string $entityName): void
    {
        if ($this->requiredRole !== null) {
            $this->denyAccessUnlessGranted($attribute, $entityName);
        }
    }

    /**
     * Check global permissions if authentication is enabled.
     */
    private function checkGlobalPermission(): void
    {
        if ($this->requiredRole !== null) {
            $this->denyAccessUnlessGranted($this->requiredRole);
        }
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
