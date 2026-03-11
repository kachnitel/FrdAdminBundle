<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests that the dashboard hides entity cards the current user cannot ADMIN_INDEX.
 *
 * Uses DashboardPermissionTestKernel which adds http_basic to the main firewall,
 * enabling HTTP Basic authentication for test requests.
 *
 * Entities registered in TestKernel fixtures:
 *   - TestEntity               index: ROLE_TEST_VIEW  (not ROLE_ADMIN)
 *   - ConfiguredEntity         index: ROLE_USER        (not ROLE_ADMIN)
 *   - EntityWithRowActions     no specific index perm → fallback ROLE_ADMIN
 *
 * @group dashboard-permissions
 */
class DashboardPermissionTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return DashboardPermissionTestKernel::class;
    }

    /**
     * ROLE_ADMIN user: only sees entities that fall back to the global ROLE_ADMIN permission.
     * Entities with entity-specific index roles (ROLE_TEST_VIEW, ROLE_USER) must be hidden.
     */
    public function testDashboardHidesEntitiesForWhichUserLacksIndexPermission(): void
    {
        $client = static::createClient([], [
            'PHP_AUTH_USER' => 'admin',
            'PHP_AUTH_PW'   => 'admin',
        ]);

        $client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();

        // TestEntity has index: ROLE_TEST_VIEW — ROLE_ADMIN alone does not satisfy this
        $this->assertStringNotContainsString('Test Entities', $content);

        // ConfiguredEntity has index: ROLE_USER — ROLE_ADMIN alone does not satisfy this (no hierarchy)
        $this->assertStringNotContainsString('Configured Items', $content);

        // EntityWithRowActions has no specific index permission → falls back to ROLE_ADMIN → visible
        $this->assertStringContainsString('Approvable Items', $content);
    }

    /**
     * An admin user can reach the dashboard and sees entities accessible to ROLE_ADMIN.
     */
    public function testDashboardShowsEntitiesUserCanAccess(): void
    {
        $client = static::createClient([], [
            'PHP_AUTH_USER' => 'admin',
            'PHP_AUTH_PW'   => 'admin',
        ]);

        $client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();

        // EntityWithRowActions has no specific index permission → ROLE_ADMIN fallback → visible
        $this->assertStringContainsString('Approvable Items', $content);
    }
}
