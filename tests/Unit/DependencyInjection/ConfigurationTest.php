<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\DependencyInjection;

use Kachnitel\AdminBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    private Processor $processor;
    private Configuration $configuration;

    protected function setUp(): void
    {
        $this->processor = new Processor();
        $this->configuration = new Configuration();
    }

    public function testRequiredRoleFalseNormalizesToNull(): void
    {
        $config = $this->processor->processConfiguration(
            $this->configuration,
            [
                'kachnitel_admin' => [
                    'required_role' => false,
                ],
            ]
        );

        $this->assertNull($config['required_role'], 'Boolean false should be normalized to null');
    }

    public function testRequiredRoleStringFalseNormalizesToNull(): void
    {
        $config = $this->processor->processConfiguration(
            $this->configuration,
            [
                'kachnitel_admin' => [
                    'required_role' => 'false',
                ],
            ]
        );

        $this->assertNull($config['required_role'], 'String "false" should be normalized to null');
    }

    public function testRequiredRoleDefaultValue(): void
    {
        $config = $this->processor->processConfiguration(
            $this->configuration,
            [
                'kachnitel_admin' => [],
            ]
        );

        $this->assertSame('ROLE_ADMIN', $config['required_role'], 'Default should be ROLE_ADMIN');
    }

    public function testRequiredRoleCustomValue(): void
    {
        $config = $this->processor->processConfiguration(
            $this->configuration,
            [
                'kachnitel_admin' => [
                    'required_role' => 'ROLE_CUSTOM',
                ],
            ]
        );

        $this->assertSame('ROLE_CUSTOM', $config['required_role'], 'Custom role should be preserved');
    }

    public function testRequiredRolePublicAccessValue(): void
    {
        $config = $this->processor->processConfiguration(
            $this->configuration,
            [
                'kachnitel_admin' => [
                    'required_role' => 'PUBLIC_ACCESS',
                ],
            ]
        );

        $this->assertSame('PUBLIC_ACCESS', $config['required_role'], 'PUBLIC_ACCESS should be preserved');
    }
}
