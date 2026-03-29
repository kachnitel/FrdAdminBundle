<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Archive\ArchiveEntityService;
use Kachnitel\AdminBundle\Archive\ArchiveService;
use Kachnitel\AdminBundle\DataSource\DataSourceRegistry;
use Kachnitel\AdminBundle\DataSource\DoctrineDataSource;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

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
 *
 * @SuppressWarnings(TooManyPublicMethods)
 */
class GenericAdminController extends AbstractAdminController
{
    public function __construct(
        EntityManagerInterface $em,
        private readonly EntityDiscoveryService $entityDiscovery,
        private readonly string $entityNamespace,
        private readonly string $formNamespace,
        private readonly string $formSuffix,
        private readonly DataSourceRegistry $dataSourceRegistry,
        private readonly string $routePrefix = 'app_admin_entity',
        private readonly string $dashboardRoute = 'app_admin_dashboard',
        private readonly ?string $requiredRole = 'ROLE_ADMIN',
    ) {
        parent::__construct($em);
    }

    /**
     * @return array<string>
     */
    protected function getSupportedEntities(): array
    {
        return $this->entityDiscovery->getAdminEntityShortNames();
    }

    protected function getRoutePrefix(): string
    {
        return $this->routePrefix;
    }

    protected function getEntityNamespace(): string
    {
        return $this->entityNamespace;
    }

    protected function getFormNamespace(): string
    {
        return $this->formNamespace;
    }

    protected function getFormSuffix(): string
    {
        return $this->formSuffix;
    }

    /**
     * Dashboard: Lists all supported entities and data sources.
     */
    #[Route('/admin', name: 'app_admin_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $this->checkGlobalPermission();

        $supportedEntities = $this->getSupportedEntities();

        usort($supportedEntities, function ($a, $b) {
            $entityClassA = $this->entityDiscovery->resolveEntityClass($a, $this->entityNamespace);
            $entityClassB = $this->entityDiscovery->resolveEntityClass($b, $this->entityNamespace);
            $labelA = $entityClassA ? ($this->entityDiscovery->getAdminAttribute($entityClassA)?->getLabel() ?? $a) : $a;
            $labelB = $entityClassB ? ($this->entityDiscovery->getAdminAttribute($entityClassB)?->getLabel() ?? $b) : $b;
            return strcasecmp($labelA, $labelB);
        });

        $supportedEntities = $this->filterAccessibleEntities($supportedEntities);

        $entities = array_map(function ($entityName) {
            $entityClass = $this->entityDiscovery->resolveEntityClass($entityName, $this->entityNamespace);
            $adminAttr = $entityClass ? $this->entityDiscovery->getAdminAttribute($entityClass) : null;

            return [
                'name'  => $entityName,
                'label' => $adminAttr?->getLabel() ?? trim((string) preg_replace('/[A-Z]/', ' $0', $entityName)),
                'icon'  => $adminAttr?->getIcon(),
                'slug'  => strtolower((string) preg_replace('/[A-Z]/', '-$0', lcfirst($entityName))),
                'type'  => 'entity',
            ];
        }, $supportedEntities);

        $dataSources = [];
        foreach ($this->dataSourceRegistry->all() as $identifier => $dataSource) {
            if ($dataSource instanceof DoctrineDataSource) {
                continue;
            }
            $dataSources[] = [
                'identifier' => $identifier,
                'label'      => $dataSource->getLabel(),
                'icon'       => $dataSource->getIcon(),
                'type'       => 'datasource',
            ];
        }

        return $this->render('@KachnitelAdmin/admin/dashboard.html.twig', [
            'entities'    => $entities,
            'dataSources' => $dataSources,
        ]);
    }

    #[Route('/admin/{entitySlug}', name: 'app_admin_entity_index', methods: ['GET'])]
    public function index(string $entitySlug): Response
    {
        $entityName = $this->resolveEntityName($entitySlug);
        $this->checkEntityPermission(AdminEntityVoter::ADMIN_INDEX, $entityName);

        return $this->render('@KachnitelAdmin/admin/index_live.html.twig', [
            'entityClass'      => $this->entityNamespace . $entityName,
            'entityShortClass' => $entityName,
        ]);
    }

    #[Route('/admin/{entitySlug}/new', name: 'app_admin_entity_new', methods: ['GET', 'POST'])]
    public function new(string $entitySlug): Response
    {
        $entityName = $this->resolveEntityName($entitySlug);
        $this->checkEntityPermission(AdminEntityVoter::ADMIN_NEW, $entityName);

        return $this->doNew($entityName);
    }

    #[Route('/admin/{entitySlug}/{id}', name: 'app_admin_entity_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(string $entitySlug, int $id): Response
    {
        $entityName = $this->resolveEntityName($entitySlug);
        $this->checkEntityPermission(AdminEntityVoter::ADMIN_SHOW, $entityName);

        return $this->doShow($entityName, $id);
    }

    #[Route('/admin/{entitySlug}/{id}/edit', name: 'app_admin_entity_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(string $entitySlug, int $id): Response
    {
        $entityName = $this->resolveEntityName($entitySlug);
        $this->checkEntityPermission(AdminEntityVoter::ADMIN_EDIT, $entityName);

        return $this->doEdit($entityName, $id);
    }

    // ── Archive / Unarchive ────────────────────────────────────────────────────

    /**
     * Archive an entity (set the configured archive field).
     */
    #[Route('/admin/{entitySlug}/{id}/archive', name: 'app_admin_entity_archive', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function archive(
        Request $request,
        string $entitySlug,
        int $id,
        ArchiveService $archiveService,
        ArchiveEntityService $archiveEntityService,
    ): Response {
        $entityName = $this->resolveEntityName($entitySlug);
        $this->checkEntityPermission(AdminEntityVoter::ADMIN_ARCHIVE, $entityName);

        $entity = $this->getRepository($entityName)->find($id);
        if (!$entity) {
            throw $this->createNotFoundException('No ' . $entityName . ' found for id ' . $id);
        }

        $this->validateArchiveCsrf($request, 'archive', $id);

        $entityClassName = $this->entityNamespace . $entityName;
        if (!class_exists($entityClassName)) {
            throw new NotFoundHttpException('Entity class not found: ' . $entityClassName);
        }

        /** @var class-string $entityClass */
        $entityClass = $entityClassName;
        $config = $archiveService->resolveConfig($entityClass);

        if ($config === null) {
            throw new NotFoundHttpException('Archive is not configured for ' . $entityName);
        }

        $archiveEntityService->archive($entity, $config);

        $this->addFlash('success', $entityName . ' #' . $id . ' archived.');

        return $this->redirectToRefererOrIndex($request, $entitySlug);
    }

    /**
     * Unarchive an entity (clear the configured archive field).
     */
    #[Route('/admin/{entitySlug}/{id}/unarchive', name: 'app_admin_entity_unarchive', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function unarchive(
        Request $request,
        string $entitySlug,
        int $id,
        ArchiveService $archiveService,
        ArchiveEntityService $archiveEntityService,
    ): Response {
        $entityName = $this->resolveEntityName($entitySlug);
        $this->checkEntityPermission(AdminEntityVoter::ADMIN_ARCHIVE, $entityName);

        $entity = $this->getRepository($entityName)->find($id);
        if (!$entity) {
            throw $this->createNotFoundException('No ' . $entityName . ' found for id ' . $id);
        }

        $this->validateArchiveCsrf($request, 'unarchive', $id);

        $entityClassName = $this->entityNamespace . $entityName;
        if (!class_exists($entityClassName)) {
            throw new NotFoundHttpException('Entity class not found: ' . $entityClassName);
        }

        /** @var class-string $entityClass */
        $entityClass = $entityClassName;
        $config = $archiveService->resolveConfig($entityClass);

        if ($config === null) {
            throw new NotFoundHttpException('Archive is not configured for ' . $entityName);
        }

        $archiveEntityService->unarchive($entity, $config);

        $this->addFlash('success', $entityName . ' #' . $id . ' unarchived.');

        return $this->redirectToRefererOrIndex($request, $entitySlug);
    }

    // ── Delete ─────────────────────────────────────────────────────────────────

    #[Route('/admin/{entitySlug}/{id}', name: 'app_admin_entity_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, string $entitySlug, int $id): Response
    {
        $entityName = $this->resolveEntityName($entitySlug);
        $this->checkEntityPermission(AdminEntityVoter::ADMIN_DELETE, $entityName);

        return $this->doDeleteEntity($entityName, $id, $request);
    }

    // ── Data Source Routes ─────────────────────────────────────────────────────

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
            'dataSource'   => $dataSource,
        ]);
    }

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
            'dataSource'   => $dataSource,
            'item'         => $item,
        ]);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * @param array<string> $entityNames
     * @return array<string>
     */
    private function filterAccessibleEntities(array $entityNames): array
    {
        if ($this->requiredRole === null) {
            return $entityNames;
        }

        return array_values(array_filter(
            $entityNames,
            fn (string $name) => $this->isGranted(AdminEntityVoter::ADMIN_INDEX, $name)
        ));
    }

    private function checkEntityPermission(string $attribute, string $entityName): void
    {
        if ($this->requiredRole !== null) {
            $this->denyAccessUnlessGranted($attribute, $entityName);
        }
    }

    private function checkGlobalPermission(): void
    {
        if ($this->requiredRole !== null) {
            $this->denyAccessUnlessGranted($this->requiredRole);
        }
    }

    private function resolveEntityName(string $slug): string
    {
        $entityName = implode('', array_map(
            fn ($part) => ucfirst($part),
            explode('-', $slug)
        ));

        if (!in_array($entityName, $this->getSupportedEntities())) {
            throw new NotFoundHttpException(sprintf('Entity "%s" is not supported.', $entityName));
        }

        return $entityName;
    }

    protected function getFormComponentName(string $class): string
    {
        $entityClass = $this->entityDiscovery->resolveEntityClass($class, $this->entityNamespace);
        $adminAttr = $entityClass ? $this->entityDiscovery->getAdminAttribute($entityClass) : null;
        return $adminAttr?->getFormComponent() ?? 'K:Admin:EntityForm';
    }

    /**
     * Validate the CSRF token for archive/unarchive actions.
     * The token key matches what _RowActionButton.html.twig generates:
     * "{{ csrf_token(action.name ~ '_' ~ entityId) }}"
     */
    private function validateArchiveCsrf(Request $request, string $actionName, int $entityId): void
    {
        $csrfKey = $actionName . '_' . $entityId;
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid($csrfKey, is_string($token) ? $token : null)) {
            throw new \InvalidArgumentException('Invalid CSRF token for ' . $csrfKey);
        }
    }

    /**
     * Redirect to the HTTP referer if available and safe, otherwise to the entity index.
     */
    private function redirectToRefererOrIndex(Request $request, string $entitySlug): Response
    {
        $referer = $request->headers->get('referer');
        if ($referer) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_admin_entity_index', ['entitySlug' => $entitySlug]);
    }
}
