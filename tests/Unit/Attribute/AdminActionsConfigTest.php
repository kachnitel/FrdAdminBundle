<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Attribute;

use Attribute;
use Kachnitel\AdminBundle\Attribute\AdminActionsConfig;
use PHPUnit\Framework\TestCase;

class AdminActionsConfigTest extends TestCase
{
    /**
     * @test
     */
    public function itHasCorrectDefaults(): void
    {
        $config = new AdminActionsConfig();

        $this->assertFalse($config->disableDefaults);
        $this->assertNull($config->exclude);
        $this->assertNull($config->include);
    }

    /**
     * @test
     */
    public function disableDefaultsCanBeSet(): void
    {
        $config = new AdminActionsConfig(disableDefaults: true);

        $this->assertTrue($config->disableDefaults);
    }

    /**
     * @test
     */
    public function excludeCanBeSet(): void
    {
        $config = new AdminActionsConfig(exclude: ['delete', 'edit']);

        $this->assertSame(['delete', 'edit'], $config->exclude);
    }

    /**
     * @test
     */
    public function includeCanBeSet(): void
    {
        $config = new AdminActionsConfig(include: ['show', 'duplicate']);

        $this->assertSame(['show', 'duplicate'], $config->include);
    }

    /**
     * @test
     */
    public function allParametersCanBeSetTogether(): void
    {
        $config = new AdminActionsConfig(
            disableDefaults: true,
            exclude: ['delete'],
            include: ['show', 'archive'],
        );

        $this->assertTrue($config->disableDefaults);
        $this->assertSame(['delete'], $config->exclude);
        $this->assertSame(['show', 'archive'], $config->include);
    }

    /**
     * @test
     */
    public function itCanTargetClasses(): void
    {
        $reflection = new \ReflectionClass(AdminActionsConfig::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        $this->assertCount(1, $attributes);
        $attrInstance = $attributes[0]->newInstance();
        $this->assertSame(Attribute::TARGET_CLASS, $attrInstance->flags);
    }
}
