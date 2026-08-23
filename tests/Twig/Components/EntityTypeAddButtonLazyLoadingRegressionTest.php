<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Twig\Components;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Regression guard for the loading: "lazy" workaround documented in
 * InlineEntityForm's class docblock and in EntityTypeAddButton.html.twig.
 *
 * Mounting K:Admin:EntityType:InlineForm eagerly inside a closed native
 * <dialog> permanently breaks the first inline-add dialog rendered on a
 * page — see the linked issue for the full investigation. loading: "lazy"
 * is the only thing currently preventing that regression, so this test
 * fails loudly if a future edit drops it from the component() call.
 *
 * Deliberately a static check against the Twig source rather than a
 * rendered-output or browser assertion: Symfony UX LiveComponent's
 * lazy-loading placeholder markup is an implementation detail this test
 * shouldn't couple to, and the actual eager-mount-inside-<dialog> failure
 * can only be observed with a real <dialog> implementation — jsdom does
 * not implement HTMLDialogElement.showModal()/close(), see
 * assets/test/controllers/admin-inline-add_controller.test.js — so a
 * Vitest or PHPUnit rendering test would not catch a regression here
 * either. A browser-based (Panther) test is the real coverage for the
 * underlying bug, but is currently blocked project-wide; this test is the
 * cheap tripwire in the meantime.
 *
 * @see https://github.com/kachnitel/FrdAdminBundle/issues/12
 * @see \Kachnitel\AdminBundle\Twig\Components\InlineEntityForm
 */
#[Group('inline-add')]
final class EntityTypeAddButtonLazyLoadingRegressionTest extends TestCase
{
    private const TEMPLATE_PATH = __DIR__ . '/../../../templates/components/EntityTypeAddButton.html.twig';

    #[Test]
    public function templateFileExists(): void
    {
        $this->assertFileExists(
            self::TEMPLATE_PATH,
            'EntityTypeAddButton.html.twig moved — update TEMPLATE_PATH in this regression test.',
        );
    }

    #[Test]
    public function inlineFormComponentIsMountedWithLoadingLazy(): void
    {
        $source = file_get_contents(self::TEMPLATE_PATH);

        if ($source === false) {
            $this->fail('Could not read ' . self::TEMPLATE_PATH);
        }

        $callStart = strpos($source, "component('K:Admin:EntityType:InlineForm'");
        $this->assertNotFalse(
            $callStart,
            'Could not find the K:Admin:EntityType:InlineForm component() call in EntityTypeAddButton.html.twig.',
        );

        $callEnd = strpos($source, '})', $callStart);
        $this->assertNotFalse($callEnd, 'Could not find the end of the component() call.');

        $callSource = substr($source, $callStart, $callEnd - $callStart);

        $this->assertStringContainsString(
            'loading: "lazy"',
            $callSource,
            'loading: "lazy" was removed from the K:Admin:EntityType:InlineForm component() call. '
                . 'This is REQUIRED, not a performance tweak — removing it permanently breaks the first '
                . 'inline-add dialog rendered on a page (eager-mount inside a closed native <dialog> fails '
                . "silently). See InlineEntityForm's class docblock and "
                . 'https://github.com/kachnitel/FrdAdminBundle/issues/12 before removing this.',
        );
    }
}
