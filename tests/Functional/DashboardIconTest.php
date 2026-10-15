<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The dashboard renders #[Admin(icon: ...)] through the shared icon partial.
 * EntityWithRowActions declares icon '📋'.
 */
#[Group('icons')]
final class DashboardIconTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return DashboardPermissionTestKernel::class;
    }

    public function testDashboardRendersEntityIconBeforeLabel(): void
    {
        $client = static::createClient([], ['PHP_AUTH_USER' => 'admin', 'PHP_AUTH_PW' => 'admin']);
        $client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
        $this->assertMatchesRegularExpression(
            '/📋\s+Approvable Items/u',
            (string) $client->getResponse()->getContent()
        );
    }
}
