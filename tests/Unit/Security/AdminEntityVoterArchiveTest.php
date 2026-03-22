<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Security;

use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use PHPUnit\Framework\TestCase;

/**
 * @group archive
 * @covers \Kachnitel\AdminBundle\Security\AdminEntityVoter
 */
class AdminEntityVoterArchiveTest extends TestCase
{
    public function testAllSixAttributesAreDistinct(): void
    {
        $attributes = [
            AdminEntityVoter::ADMIN_INDEX,
            AdminEntityVoter::ADMIN_SHOW,
            AdminEntityVoter::ADMIN_NEW,
            AdminEntityVoter::ADMIN_EDIT,
            AdminEntityVoter::ADMIN_ARCHIVE,
            AdminEntityVoter::ADMIN_DELETE,
        ];

        $this->assertCount(count($attributes), array_unique($attributes));
    }

    public function testArchivePermissionCanBeConfiguredPerEntity(): void
    {
        $admin = new Admin(permissions: [
            'archive' => 'ROLE_EDITOR',
            'delete'  => 'ROLE_ADMIN',
        ]);

        $this->assertSame('ROLE_EDITOR', $admin->getPermissionForAction('archive'));
        $this->assertSame('ROLE_ADMIN', $admin->getPermissionForAction('delete'));
        $this->assertNull($admin->getPermissionForAction('index'));
    }

    public function testArchivePermissionFallsBackToNullWhenNotConfigured(): void
    {
        $admin = new Admin(permissions: [
            'delete' => 'ROLE_ADMIN',
        ]);

        $this->assertNull($admin->getPermissionForAction('archive'));
    }
}
