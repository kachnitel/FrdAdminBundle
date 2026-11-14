<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Attribute;

use Attribute;
use Kachnitel\AdminBundle\Attribute\AdminActionsConfig;
use PHPUnit\Framework\Attributes as PHPUnit;
use PHPUnit\Framework\TestCase;

final class AdminActionsConfigTest extends TestCase
{
    #[PHPUnit\Test]
    public function itHasCorrectDefaults(): void
    {
        $config = new AdminActionsConfig();

        $this->assertFalse($config->disableDefaults);
        $this->assertNull($config->exclude);
        $this->assertNull($config->include);
    }

    #[PHPUnit\Test]
    public function disableDefaultsCanBeSet(): void
    {
        $config = new AdminActionsConfig(disableDefaults: true);

        $this->assertTrue($config->disableDefaults);
    }

    #[PHPUnit\Test]
    public function excludeCanBeSet(): void
    {
        $config = new AdminActionsConfig(exclude: ['delete', 'edit']);

        $this->assertSame(['delete', 'edit'], $config->exclude);
    }

    #[PHPUnit\Test]
    public function includeCanBeSet(): void
    {
        $config = new AdminActionsConfig(include: ['show', 'duplicate']);

        $this->assertSame(['show', 'duplicate'], $config->include);
    }

    #[PHPUnit\Test]
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

    #[PHPUnit\Test]
    public function itCanTargetClasses(): void
    {
        $reflection = new \ReflectionClass(AdminActionsConfig::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        $this->assertCount(1, $attributes);
        $attrInstance = $attributes[0]->newInstance();
        $this->assertSame(Attribute::TARGET_CLASS, $attrInstance->flags);
    }
}
