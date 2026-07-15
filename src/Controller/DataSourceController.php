<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Controller;

use Kachnitel\AdminBundle\DataSource\DataSourceRegistry;
use Kachnitel\AdminBundle\DataSource\DoctrineDataSource;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Dashboard and non-Doctrine data-source browsing routes.
 *
 * Split out of GenericAdminController to bring that class's coupling
 * (was 13) and class-level cyclomatic complexity (was 52) back under
 * PHPMD's thresholds — these three routes were the only consumers of
 * DataSourceRegistry/DoctrineDataSource in that class, and moving them
 * also dropped GenericAdminController's constructor from 10 params to 9.
 *
 * Deliberately extends Symfony's AbstractController rather than
 * AbstractAdminController: none of these routes touch the entity-CRUD
 * scaffolding (doIndex/doNew/doShow/doEdit/getRepository/getFormType/...),
 * they only need render(), isGranted()/denyAccessUnlessGranted(), and the
 * two services injected below.
 *
 * Route names (app_admin_dashboard, app_admin_datasource_index,
 * app_admin_datasource_show) are unchanged from their previous home on
 * GenericAdminController, so existing links, redirects, and route-based
 * functional tests are unaffected by the move.
 *
 * Test coverage note: as with GenericAdminController's own action methods
 * (index/new/show/edit/delete), the three routes here call render() and
 * isGranted()/denyAccessUnlessGranted(), which need a real container —
 * consistent with this bundle's existing convention, they're intended to
 * be covered by functional/route-level tests rather than unit tests.
 */
class DataSourceController extends AbstractController
{
    public function __construct(
        private readonly EntityDiscoveryService $entityDiscovery,
        private readonly DataSourceRegistry $dataSourceRegistry,
        private readonly string $entityNamespace,
        private readonly ?string $requiredRole = 'ROLE_ADMIN',
    ) {}

    /**
     * Dashboard: Lists all supported entities and data sources.
     */
    #[Route('/admin', name: 'app_admin_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $this->checkGlobalPermission();

        $supportedEntities = $this->entityDiscovery->getAdminEntityShortNames();

        usort($supportedEntities, function ($a, $b): int {
            $entityClassA = $this->entityDiscovery->resolveEntityClass($a, $this->entityNamespace);
            $entityClassB = $this->entityDiscovery->resolveEntityClass($b, $this->entityNamespace);
            $labelA = $entityClassA ? ($this->entityDiscovery->getAdminAttribute($entityClassA)?->getLabel() ?? $a) : $a;
            $labelB = $entityClassB ? ($this->entityDiscovery->getAdminAttribute($entityClassB)?->getLabel() ?? $b) : $b;
            return strcasecmp($labelA, $labelB);
        });

        $supportedEntities = $this->filterAccessibleEntities($supportedEntities);

        $entities = array_map(function (string $entityName): array {
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
            fn (string $name): bool => $this->isGranted(AdminEntityVoter::ADMIN_INDEX, $name)
        ));
    }

    private function checkGlobalPermission(): void
    {
        if ($this->requiredRole !== null) {
            $this->denyAccessUnlessGranted($this->requiredRole);
        }
    }
}
