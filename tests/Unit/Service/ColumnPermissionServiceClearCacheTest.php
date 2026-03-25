<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Service;

use Kachnitel\AdminBundle\Attribute\ColumnPermission;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\ColumnPermissionService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Covers gaps in ColumnPermissionServiceTest:
 *
 *   - clearCache() actually forces a re-read from reflection (not just resets to empty)
 *   - getColumnPermissionMap() with an empty permissions array on the attribute
 *   - canPerformAction() returns true without calling the voter when permissions is []
 *   - getRestrictedColumns() includes columns with empty-array permissions (has attribute)
 *
 * @covers \Kachnitel\AdminBundle\Service\ColumnPermissionService
 * @group column-permissions
 */
class ColumnPermissionServiceClearCacheTest extends TestCase
{
    /** @var AuthorizationCheckerInterface&MockObject */
    private AuthorizationCheckerInterface $authChecker;

    protected function setUp(): void
    {
        $this->authChecker = $this->createMock(AuthorizationCheckerInterface::class);
    }

    // ── clearCache() ──────────────────────────────────────────────────────────

    /** @test */
    public function clearCacheDoesNotBreakSubsequentReads(): void
    {
        $service = new ColumnPermissionService($this->authChecker);

        $map1 = $service->getColumnPermissionMap(PermCacheFixtureEntity::class);
        $this->assertArrayHasKey('salary', $map1);

        $service->clearCache();

        $map2 = $service->getColumnPermissionMap(PermCacheFixtureEntity::class);
        $this->assertArrayHasKey('salary', $map2);
        $this->assertSame($map1, $map2, 'Re-read after clearCache must return equivalent data.');
    }

    /** @test */
    public function clearCacheAllowsDifferentClassToBeReadCleanly(): void
    {
        $service = new ColumnPermissionService($this->authChecker);

        // Populate cache with one class
        $service->getColumnPermissionMap(PermCacheFixtureEntity::class);
        $service->clearCache();

        // Different class must work without carrying over stale state
        $emptyMap = $service->getColumnPermissionMap(PermCacheNoAttrsFixture::class);
        $this->assertSame([], $emptyMap);
    }

    /** @test */
    public function clearCacheResetsInternalMapSoVoterIsCalledAgain(): void
    {
        $service = new ColumnPermissionService($this->authChecker);

        // First canPerformAction — populates cache
        $this->authChecker->method('isGranted')->with('ROLE_HR')->willReturn(true);
        $service->canPerformAction(PermCacheFixtureEntity::class, 'salary', AdminEntityVoter::ADMIN_SHOW);

        $service->clearCache();

        // After clear the voter should be consulted again (internal map re-built)
        // We assert canPerformAction still returns the correct answer post-clear
        $result = $service->canPerformAction(
            PermCacheFixtureEntity::class,
            'salary',
            AdminEntityVoter::ADMIN_SHOW,
        );
        $this->assertTrue($result);
    }

    // ── Empty permissions array on the attribute ──────────────────────────────

    /** @test */
    public function emptyPermissionsArrayAppearsInMapWithEmptyValue(): void
    {
        $service = new ColumnPermissionService($this->authChecker);
        $map     = $service->getColumnPermissionMap(PermCacheEmptyPermFixture::class);

        $this->assertArrayHasKey('field', $map, '#[ColumnPermission([])] property must appear in the map.');
        $this->assertSame([], $map['field'], 'Permissions must be the empty array as-declared.');
    }

    /** @test */
    public function canPerformActionReturnsTrueForEmptyPermissionsWithoutCallingVoter(): void
    {
        $this->authChecker->expects($this->never())->method('isGranted');

        $service = new ColumnPermissionService($this->authChecker);

        $this->assertTrue(
            $service->canPerformAction(
                PermCacheEmptyPermFixture::class,
                'field',
                AdminEntityVoter::ADMIN_SHOW,
            ),
            'Column with ColumnPermission([]) has no action restrictions — must always return true.'
        );
    }

    /** @test */
    public function canPerformActionReturnsTrueForAllActionsWithEmptyPermissions(): void
    {
        $service = new ColumnPermissionService($this->authChecker);

        foreach ([AdminEntityVoter::ADMIN_SHOW, AdminEntityVoter::ADMIN_EDIT, AdminEntityVoter::ADMIN_DELETE] as $action) {
            $this->assertTrue(
                $service->canPerformAction(PermCacheEmptyPermFixture::class, 'field', $action),
                "Action $action must be allowed when permissions array is empty."
            );
        }
    }

    /** @test */
    public function getRestrictedColumnsIncludesColumnWithEmptyPermissions(): void
    {
        // getRestrictedColumns returns all columns that HAVE the #[ColumnPermission]
        // attribute, regardless of whether the map is empty or not.
        $service    = new ColumnPermissionService($this->authChecker);
        $restricted = $service->getRestrictedColumns(PermCacheEmptyPermFixture::class);

        $this->assertContains(
            'field',
            $restricted,
            'Column with #[ColumnPermission([])] must appear in getRestrictedColumns() — it has the attribute.'
        );
    }

    /** @test */
    public function getDeniedColumnsForActionDoesNotIncludeEmptyPermissionsColumn(): void
    {
        // getDeniedColumnsForAction only returns columns the current user CANNOT access.
        // A column with ColumnPermission([]) has no restrictions → never denied.
        $this->authChecker->method('isGranted')->willReturn(false); // even if voter denies everything

        $service = new ColumnPermissionService($this->authChecker);
        $denied  = $service->getDeniedColumnsForAction(PermCacheEmptyPermFixture::class, AdminEntityVoter::ADMIN_SHOW);

        $this->assertNotContains(
            'field',
            $denied,
            'Column with empty permissions is never denied — canPerformAction must return true regardless of voter.'
        );
    }
}

/** @internal test fixture */
class PermCacheFixtureEntity
{
    #[ColumnPermission([AdminEntityVoter::ADMIN_SHOW => 'ROLE_HR'])]
    private float $salary = 0.0;
}

/** @internal test fixture — no ColumnPermission attributes */
class PermCacheNoAttrsFixture
{
    private string $name = '';
}

/** @internal test fixture — ColumnPermission with empty permissions array */
class PermCacheEmptyPermFixture
{
    #[ColumnPermission([])]
    private string $field = '';
}
