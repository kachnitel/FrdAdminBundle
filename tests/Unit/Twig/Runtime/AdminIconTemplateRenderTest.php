<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Twig\Runtime;

use Kachnitel\AdminBundle\Twig\Extension\AdminIconExtension;
use Kachnitel\AdminBundle\Twig\Runtime\AdminIconRuntime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

/**
 * Guards the escaping contract: the button templates store admin_icon() in a variable
 * ({% set %} drops the function's is_safe flag), so they must print it with |raw.
 */
#[Group('icons')]
final class AdminIconTemplateRenderTest extends TestCase
{
    #[Test]
    public function iconStoredInVariableRendersAsMarkupNotText(): void
    {
        $twig = new Environment(new ArrayLoader([
            't' => '{% set i = admin_icon(icon, cls) %}{% if i %}{{ i|raw }}{% endif %}',
        ]), ['autoescape' => 'html']);
        $twig->addExtension(new AdminIconExtension());
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            AdminIconRuntime::class => static fn (): AdminIconRuntime => new AdminIconRuntime(),
        ]));

        $this->assertSame(
            '<span class="material-symbols-outlined" aria-hidden="true">person_remove</span>',
            $twig->render('t', ['icon' => 'person_remove', 'cls' => 'material-symbols-outlined']),
        );
        $this->assertSame('&lt;b&gt;', $twig->render('t', ['icon' => '<b>', 'cls' => '']));
    }

    #[Test]
    public function buttonTemplatesNeverPrintIconHtmlWithoutRaw(): void
    {
        foreach (['_RowActionButton', '_BatchActionButton'] as $name) {
            $source = (string) file_get_contents(
                __DIR__ . '/../../../../templates/components/EntityList/' . $name . '.html.twig',
            );
            $this->assertStringNotContainsString('{{ iconHtml }}', $source, $name);
        }
    }
}
