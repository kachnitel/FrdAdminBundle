<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Tests\Fixtures\ConfiguredEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * Tests that permission enforcement respects both global required_role and
 * entity-specific permissions from #[Admin] attribute.
 */
class PermissionEnforcementTest extends KernelTestCase
{
    private AccessDecisionManagerInterface $accessDecisionManager;
    private AdminEntityVoter $voter;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->accessDecisionManager = $container->get('security.access.decision_manager');
        $this->voter = $container->get(AdminEntityVoter::class);
    }

    /**
     * Test that ConfiguredEntity has specific permissions configured.
     *
     * ConfiguredEntity has:
     *   permissions: [
     *     'index' => 'ROLE_USER',
     *     'show' => 'ROLE_USER',
     *     'new' => 'ROLE_EDITOR',
     *     'edit' => 'ROLE_EDITOR',
     *     'delete' => 'ROLE_ADMIN',
     *   ]
     */
    public function testEntitySpecificPermissions(): void
    {
        // ROLE_USER can index and show
        $userToken = $this->createToken(['ROLE_USER']);
        $this->assertTrue(
            $this->accessDecisionManager->decide($userToken, [AdminEntityVoter::ADMIN_INDEX], 'ConfiguredEntity'),
            'ROLE_USER should be able to index ConfiguredEntity'
        );
        $this->assertTrue(
            $this->accessDecisionManager->decide($userToken, [AdminEntityVoter::ADMIN_SHOW], 'ConfiguredEntity'),
            'ROLE_USER should be able to show ConfiguredEntity'
        );

        // ROLE_USER cannot create, edit, or delete
        $this->assertFalse(
            $this->accessDecisionManager->decide($userToken, [AdminEntityVoter::ADMIN_NEW], 'ConfiguredEntity'),
            'ROLE_USER should NOT be able to create ConfiguredEntity'
        );
        $this->assertFalse(
            $this->accessDecisionManager->decide($userToken, [AdminEntityVoter::ADMIN_EDIT], 'ConfiguredEntity'),
            'ROLE_USER should NOT be able to edit ConfiguredEntity'
        );
        $this->assertFalse(
            $this->accessDecisionManager->decide($userToken, [AdminEntityVoter::ADMIN_DELETE], 'ConfiguredEntity'),
            'ROLE_USER should NOT be able to delete ConfiguredEntity'
        );

        // ROLE_EDITOR can create and edit
        $editorToken = $this->createToken(['ROLE_EDITOR']);
        $this->assertTrue(
            $this->accessDecisionManager->decide($editorToken, [AdminEntityVoter::ADMIN_NEW], 'ConfiguredEntity'),
            'ROLE_EDITOR should be able to create ConfiguredEntity'
        );
        $this->assertTrue(
            $this->accessDecisionManager->decide($editorToken, [AdminEntityVoter::ADMIN_EDIT], 'ConfiguredEntity'),
            'ROLE_EDITOR should be able to edit ConfiguredEntity'
        );

        // ROLE_EDITOR cannot delete
        $this->assertFalse(
            $this->accessDecisionManager->decide($editorToken, [AdminEntityVoter::ADMIN_DELETE], 'ConfiguredEntity'),
            'ROLE_EDITOR should NOT be able to delete ConfiguredEntity'
        );

        // ROLE_ADMIN can delete
        $adminToken = $this->createToken(['ROLE_ADMIN']);
        $this->assertTrue(
            $this->accessDecisionManager->decide($adminToken, [AdminEntityVoter::ADMIN_DELETE], 'ConfiguredEntity'),
            'ROLE_ADMIN should be able to delete ConfiguredEntity'
        );
    }

    /**
     * Test that TestEntity uses specific permissions for some actions
     * and falls back to global required_role for others.
     *
     * TestEntity has:
     *   permissions: ['index' => 'ROLE_TEST_VIEW', 'edit' => 'ROLE_TEST_EDIT']
     */
    public function testMixedPermissions(): void
    {
        // User with ROLE_TEST_VIEW can index
        $viewToken = $this->createToken(['ROLE_TEST_VIEW']);
        $this->assertTrue(
            $this->accessDecisionManager->decide($viewToken, [AdminEntityVoter::ADMIN_INDEX], 'TestEntity'),
            'ROLE_TEST_VIEW should be able to index TestEntity'
        );

        // User with ROLE_TEST_VIEW cannot edit (needs ROLE_TEST_EDIT)
        $this->assertFalse(
            $this->accessDecisionManager->decide($viewToken, [AdminEntityVoter::ADMIN_EDIT], 'TestEntity'),
            'ROLE_TEST_VIEW should NOT be able to edit TestEntity'
        );

        // For actions without specific permissions, falls back to global ROLE_ADMIN
        // (new, show, delete not configured, so they require ROLE_ADMIN)
        $this->assertFalse(
            $this->accessDecisionManager->decide($viewToken, [AdminEntityVoter::ADMIN_NEW], 'TestEntity'),
            'ROLE_TEST_VIEW should NOT be able to create TestEntity (requires ROLE_ADMIN fallback)'
        );

        // User with ROLE_ADMIN can perform actions without specific permissions
        $adminToken = $this->createToken(['ROLE_ADMIN']);
        $this->assertTrue(
            $this->accessDecisionManager->decide($adminToken, [AdminEntityVoter::ADMIN_NEW], 'TestEntity'),
            'ROLE_ADMIN should be able to create TestEntity'
        );
        $this->assertTrue(
            $this->accessDecisionManager->decide($adminToken, [AdminEntityVoter::ADMIN_SHOW], 'TestEntity'),
            'ROLE_ADMIN should be able to show TestEntity'
        );
        $this->assertTrue(
            $this->accessDecisionManager->decide($adminToken, [AdminEntityVoter::ADMIN_DELETE], 'TestEntity'),
            'ROLE_ADMIN should be able to delete TestEntity'
        );
    }

    /**
     * Test that unauthenticated users are denied access.
     */
    public function testUnauthenticatedUserDenied(): void
    {
        $anonymousToken = $this->createToken([]);

        $this->assertFalse(
            $this->accessDecisionManager->decide($anonymousToken, [AdminEntityVoter::ADMIN_INDEX], 'TestEntity'),
            'Anonymous user should be denied access to TestEntity index'
        );

        $this->assertFalse(
            $this->accessDecisionManager->decide($anonymousToken, [AdminEntityVoter::ADMIN_NEW], 'ConfiguredEntity'),
            'Anonymous user should be denied access to ConfiguredEntity creation'
        );
    }

    /**
     * Test that users without proper roles are denied access.
     */
    public function testUnauthorizedUserDenied(): void
    {
        // User with only ROLE_USER tries to access TestEntity which requires ROLE_ADMIN or specific roles
        $userToken = $this->createToken(['ROLE_USER']);

        $this->assertFalse(
            $this->accessDecisionManager->decide($userToken, [AdminEntityVoter::ADMIN_INDEX], 'TestEntity'),
            'ROLE_USER should be denied access to TestEntity index (requires ROLE_TEST_VIEW)'
        );

        $this->assertFalse(
            $this->accessDecisionManager->decide($userToken, [AdminEntityVoter::ADMIN_NEW], 'TestEntity'),
            'ROLE_USER should be denied access to TestEntity creation (requires ROLE_ADMIN)'
        );
    }

    /**
     * Test that the voter only supports our specific attributes.
     */
    public function testVoterSupportsOnlySpecificAttributes(): void
    {
        $token = $this->createToken(['ROLE_ADMIN']);

        // Should support our attributes
        $supports = $this->invokePrivateMethod($this->voter, 'supports', [AdminEntityVoter::ADMIN_INDEX, 'TestEntity']);
        $this->assertTrue($supports, 'Voter should support ADMIN_INDEX attribute');

        $supports = $this->invokePrivateMethod($this->voter, 'supports', [AdminEntityVoter::ADMIN_DELETE, 'ConfiguredEntity']);
        $this->assertTrue($supports, 'Voter should support ADMIN_DELETE attribute');

        // Should not support random attributes
        $supports = $this->invokePrivateMethod($this->voter, 'supports', ['RANDOM_ATTRIBUTE', 'TestEntity']);
        $this->assertFalse($supports, 'Voter should not support random attributes');

        // Should not support non-string subjects
        $supports = $this->invokePrivateMethod($this->voter, 'supports', [AdminEntityVoter::ADMIN_INDEX, 123]);
        $this->assertFalse($supports, 'Voter should not support non-string subjects');
    }

    /**
     * Helper to create an authentication token with specific roles.
     *
     * @param array<string> $roles
     */
    private function createToken(array $roles): UsernamePasswordToken
    {
        $user = new InMemoryUser('test_user', 'password', $roles);
        return new UsernamePasswordToken($user, 'main', $roles);
    }

    /**
     * Helper to invoke private methods for testing.
     */
    private function invokePrivateMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
