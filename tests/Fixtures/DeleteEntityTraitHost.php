<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Kachnitel\AdminBundle\Controller\Trait\DeleteEntityTrait;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Minimal host for exercising DeleteEntityTrait in isolation.
 *
 * In production, addFlash()/redirect()/isCsrfTokenValid() are inherited for
 * free from Symfony's AbstractController — AbstractAdminController never
 * implements them itself. Standing up a full controller (container, request
 * stack, CSRF token manager) just to unit test the trait's own branching
 * would mean testing Symfony, not this code, so this host implements the
 * three abstract methods directly and records what they were called with.
 */
final class DeleteEntityTraitHost
{
    use DeleteEntityTrait {
        doDelete as public;
        validateCsrfEntityRequest as public;
    }

    /** @var list<array{string, mixed}> */
    public array $flashes = [];

    public ?string $redirectedTo = null;
    public int $redirectStatus = 302;

    public bool $csrfValid = true;

    protected function addFlash(string $type, mixed $message): void
    {
        $this->flashes[] = [$type, $message];
    }

    protected function redirect(string $url, int $status = 302): RedirectResponse
    {
        $this->redirectedTo = $url;
        $this->redirectStatus = $status;

        return new RedirectResponse($url, $status);
    }

    protected function isCsrfTokenValid(string $id, ?string $token): bool
    {
        return $this->csrfValid;
    }
}
