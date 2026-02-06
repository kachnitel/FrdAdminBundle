<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\Tests\Fixtures\PermissionTestEntity;
use Kachnitel\AdminBundle\Twig\Components\EntityList;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Functional tests for column permission enforcement in the UI.
 *
 * Tests that columns with #[ColumnPermission] attributes are properly filtered
 * when the user does NOT have the required role. This ensures protected data
 * is hidden by default.
 *
 * Note: Tests for users WITH required roles are handled at the unit level
 * (ColumnPermissionServiceTest) because the LiveComponent test helper doesn't
 * preserve security tokens across its internal HTTP requests.
 *
 * @see \Kachnitel\AdminBundle\Tests\Unit\Service\ColumnPermissionServiceTest
 * @see \Kachnitel\AdminBundle\Tests\Unit\Service\EntityListColumnServiceTest
 */
class ColumnPermissionUITest extends ComponentTestCase
{
    private Session $session;

    protected function setUp(): void
    {
        parent::setUp();

        // Create and push a request with session to the request stack
        $this->session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($this->session);

        $requestStack = self::getContainer()->get('request_stack');
        $requestStack->push($request);
    }

    public function testPermissionDeniedColumnsNotInGetColumns(): void
    {
        // By default, no user is authenticated, so permission-protected columns should be hidden
        $testComponent = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => PermissionTestEntity::class,
                'entityShortClass' => 'PermissionTestEntity',
            ],
        );

        $columns = $testComponent->component()->getColumns();

        // Public columns should be present
        $this->assertContains('id', $columns);
        $this->assertContains('name', $columns);
        $this->assertContains('publicField', $columns);

        // Permission-protected columns should NOT be present (no ROLE_HR or ROLE_MANAGER)
        $this->assertNotContains('salary', $columns, 'salary requires ROLE_HR');
        $this->assertNotContains('internalNotes', $columns, 'internalNotes requires ROLE_MANAGER');
    }

    public function testPermissionDeniedColumnsNotRenderedInTable(): void
    {
        // Create test entity with data
        $entity = new PermissionTestEntity();
        $entity->setName('John Doe');
        $entity->setSalary('50000.00');
        $entity->setInternalNotes('Confidential notes');
        $entity->setPublicField('Public info');
        $this->em->persist($entity);
        $this->em->flush();

        $testComponent = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => PermissionTestEntity::class,
                'entityShortClass' => 'PermissionTestEntity',
            ],
        );

        $rendered = (string) $testComponent->render();

        // Public column data should be rendered
        $this->assertStringContainsString('John Doe', $rendered);
        $this->assertStringContainsString('Public info', $rendered);

        // Permission-protected column data should NOT be rendered
        $this->assertStringNotContainsString('50000', $rendered);
        $this->assertStringNotContainsString('Confidential notes', $rendered);
    }

    public function testPermissionDeniedFiltersNotInFilterMetadata(): void
    {
        $testComponent = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => PermissionTestEntity::class,
                'entityShortClass' => 'PermissionTestEntity',
            ],
        );

        $filters = $testComponent->component()->getFilterMetadata();

        // Filters for public columns should exist
        $this->assertArrayHasKey('name', $filters);
        $this->assertArrayHasKey('publicField', $filters);

        // Filters for permission-protected columns should NOT exist
        $this->assertArrayNotHasKey('salary', $filters, 'salary filter should be hidden without ROLE_HR');
        $this->assertArrayNotHasKey('internalNotes', $filters, 'internalNotes filter should be hidden without ROLE_MANAGER');
    }

    public function testOnlyPublicColumnsInVisibleColumns(): void
    {
        $testComponent = $this->createLiveComponent(
            name: EntityList::class,
            data: [
                'entityClass' => PermissionTestEntity::class,
                'entityShortClass' => 'PermissionTestEntity',
            ],
        );

        $visibleColumns = $testComponent->component()->getVisibleColumns();

        // Should only contain public columns
        $this->assertSame(['id', 'name', 'publicField'], $visibleColumns);
    }
}
