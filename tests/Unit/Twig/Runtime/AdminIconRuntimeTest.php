<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Twig\Runtime;

use Kachnitel\AdminBundle\Twig\Runtime\AdminIconRuntime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('icons')]
final class AdminIconRuntimeTest extends TestCase
{
    #[Test]
    public function returnsEmptyStringWithoutIcon(): void
    {
        $this->assertSame('', (new AdminIconRuntime('mi'))->renderIcon(null));
        $this->assertSame('', (new AdminIconRuntime('mi'))->renderIcon(''));
    }

    #[Test]
    public function rendersBareEscapedTextWithoutAnyClass(): void
    {
        $this->assertSame('👀', (new AdminIconRuntime())->renderIcon('👀'));
        $this->assertSame('&lt;b&gt;', (new AdminIconRuntime())->renderIcon('<b>'));
    }

    #[Test]
    public function wrapsLigatureTextInSpanWithActionClass(): void
    {
        $this->assertSame(
            '<span class="material-symbols-outlined" aria-hidden="true">edit</span>',
            (new AdminIconRuntime())->renderIcon('edit', 'material-symbols-outlined'),
        );
    }

    #[Test]
    public function inheritsGlobalClassWhenActionClassIsNull(): void
    {
        $this->assertSame(
            '<span class="mi" aria-hidden="true">edit</span>',
            (new AdminIconRuntime('mi'))->renderIcon('edit'),
        );
    }

    #[Test]
    public function emptyActionClassOptsOutOfGlobalClass(): void
    {
        $this->assertSame('👀', (new AdminIconRuntime('mi'))->renderIcon('👀', ''));
    }

    #[Test]
    public function actionClassOverridesGlobalClass(): void
    {
        $this->assertStringContainsString(
            'class="bi"',
            (new AdminIconRuntime('mi'))->renderIcon('x', 'bi'),
        );
    }

    #[Test]
    public function escapesClassAttribute(): void
    {
        $this->assertStringContainsString(
            'class="a&quot;b"',
            (new AdminIconRuntime())->renderIcon('x', 'a"b'),
        );
    }

    #[Test]
    public function classOnlyIconRendersEmptySpanForExplicitClass(): void
    {
        $this->assertSame(
            '<span class="bi bi-pencil" aria-hidden="true"></span>',
            (new AdminIconRuntime())->renderIcon(null, 'bi bi-pencil'),
        );
    }

    #[Test]
    public function inheritedGlobalClassAloneDoesNotProduceEmptyIcon(): void
    {
        $this->assertSame('', (new AdminIconRuntime('mi'))->renderIcon(null));
    }

    #[Test]
    public function uxPrefixDelegatesToRenderer(): void
    {
        $renderer = new class {
            /** @var array{string, array<string, string>}|null */
            public ?array $call = null;

            /** @param array<string, string> $attributes */
            public function renderIcon(string $name, array $attributes = []): string
            {
                $this->call = [$name, $attributes];

                return '<svg/>';
            }
        };

        $html = (new AdminIconRuntime('mi', $renderer))->renderIcon('ux:material-symbols:edit');

        $this->assertSame('<svg/>', $html);
        $this->assertSame(['material-symbols:edit', ['aria-hidden' => 'true', 'class' => 'mi']], $renderer->call);
    }

    #[Test]
    public function uxPrefixWithoutRendererThrowsHelpfulError(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('symfony/ux-icons');

        (new AdminIconRuntime())->renderIcon('ux:material-symbols:edit');
    }
}
