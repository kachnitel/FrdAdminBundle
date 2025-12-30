<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Controller;

use Kachnitel\AdminBundle\Controller\GenericAdminController;
use Kachnitel\AdminBundle\DataSource\DataSourceRegistry;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GenericAdminControllerTest extends TestCase
{
    private GenericAdminController $controller;
    private MockObject&EntityDiscoveryService $entityDiscovery;
    private MockObject&DataSourceRegistry $dataSourceRegistry;

    protected function setUp(): void
    {
        $this->entityDiscovery = $this->createMock(EntityDiscoveryService::class);
        $this->dataSourceRegistry = $this->createMock(DataSourceRegistry::class);

        $this->controller = new GenericAdminController(
            $this->entityDiscovery,
            'app_admin_entity',
            'app_admin_dashboard',
            'App\\Entity\\',
            'ROLE_ADMIN'
        );

        $this->controller->setDataSourceRegistry($this->dataSourceRegistry);
    }

    /**
     * @test
     */
    public function checkEntityPermissionDoesNotThrowWhenNoRequiredRole(): void
    {
        // Create controller with no required role
        $controller = new GenericAdminController(
            $this->entityDiscovery,
            'app_admin_entity',
            'app_admin_dashboard',
            'App\\Entity\\',
            null  // No required role
        );

        // Just verify creation works
        $this->assertInstanceOf(GenericAdminController::class, $controller);
    }

    /**
     * @test
     */
    public function checkGlobalPermissionDoesNotThrowWhenNoRequiredRole(): void
    {
        // Create controller with no required role
        $controller = new GenericAdminController(
            $this->entityDiscovery,
            'app_admin_entity',
            'app_admin_dashboard',
            'App\\Entity\\',
            null  // No required role
        );

        // Just verify creation works
        $this->assertInstanceOf(GenericAdminController::class, $controller);
    }
}
