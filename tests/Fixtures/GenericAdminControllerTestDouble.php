<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Controller\GenericAdminController;
use Kachnitel\AdminBundle\Security\ObjectAuthorizationChecker;
use Kachnitel\AdminBundle\Service\EntityDiscoveryService;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Overrides GenericAdminController's Symfony AbstractController touch-points
 * (render, generateUrl, addFlash, isCsrfTokenValid, redirect/redirectToRoute,
 * permission checks) so the controller's own logic — route dispatch, CSRF
 * key building, archive config resolution, breadcrumb assembly — runs for
 * real against plain PHPUnit stubs, without booting a kernel.
 *
 * createNotFoundException()'s real implementation just constructs a
 * NotFoundHttpException — container-free — so it's deliberately not
 * overridden. redirectToRoute() IS container-free too, but is overridden
 * anyway purely to capture the route/params for assertions.
 *
 * Declares its own constructor (mirroring GenericAdminController's exactly,
 * argument-for-argument) purely to default the constructor-injected
 * $objectAuthChecker to a PermissiveObjectAuthorizationChecker when the
 * caller doesn't care about object-level authorization — the common case
 * for every test suite other than GenericAdminControllerObjectAuthorizationTest.
 * Tests that DO need to exercise object-level authorization pass their own
 * mock/stub via the $objectAuthChecker constructor argument instead — see
 * GenericAdminControllerObjectAuthorizationTest. Because the checker is now
 * a plain constructor argument (no #[Required] setter involved), there is
 * no "forgot to wire it up after construction" failure mode to guard against.
 */
final class GenericAdminControllerTestDouble extends GenericAdminController
{
    /** @var array<string, bool> keyed by "attribute:subject" */
    public array $grantResults = [];
    public bool $denyAccess = false;

    /** @var array{view: string, parameters: array<string, mixed>}|null */
    public ?array $lastRender = null;

    /** @var list<array{string, mixed}> */
    public array $flashes = [];

    public ?string $redirectedTo = null;
    public ?string $redirectedRoute = null;
    /** @var array<string, mixed>|null */
    public ?array $redirectedRouteParams = null;

    public bool $csrfValid = true;
    /** @var list<array{string, ?string}> */
    public array $csrfChecks = [];

    public function __construct(
        EntityManagerInterface $em,
        EntityDiscoveryService $entityDiscovery,
        string $entityNamespace,
        string $formNamespace,
        string $formSuffix,
        FormRegistryInterface $formRegistry,
        string $routePrefix = 'app_admin_entity',
        string $dashboardRoute = 'app_admin_dashboard',
        ?string $requiredRole = 'ROLE_ADMIN',
        ?ObjectAuthorizationChecker $objectAuthChecker = null,
    ) {
        parent::__construct(
            em: $em,
            entityDiscovery: $entityDiscovery,
            entityNamespace: $entityNamespace,
            formNamespace: $formNamespace,
            formSuffix: $formSuffix,
            formRegistry: $formRegistry,
            objectAuthChecker: $objectAuthChecker ?? new PermissiveObjectAuthorizationChecker(),
            routePrefix: $routePrefix,
            dashboardRoute: $dashboardRoute,
            requiredRole: $requiredRole,
        );
    }

    protected function isGranted(mixed $attribute, mixed $subject = null): bool
    {
        $attributeValue = is_scalar($attribute) ? (string) $attribute : '';
        $key = $attributeValue . ':' . (is_string($subject) ? $subject : 'null');

        return $this->grantResults[$key] ?? true;
    }

    protected function denyAccessUnlessGranted(mixed $attribute, mixed $subject = null, string $message = 'Access Denied.'): void
    {
        if ($this->denyAccess) {
            throw new AccessDeniedException($message);
        }
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function render(string $view, array $parameters = [], ?Response $response = null): Response
    {
        $this->lastRender = ['view' => $view, 'parameters' => $parameters];

        return $response ?? new Response();
    }

    protected function addFlash(string $type, mixed $message): void
    {
        $this->flashes[] = [$type, $message];
    }

    protected function redirect(string $url, int $status = 302): RedirectResponse
    {
        $this->redirectedTo = $url;

        return new RedirectResponse($url, $status);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function redirectToRoute(string $route, array $parameters = [], int $status = 302): RedirectResponse
    {
        $this->redirectedRoute = $route;
        $this->redirectedRouteParams = $parameters;

        return new RedirectResponse('/' . $route, $status);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function generateUrl(string $route, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        return '/' . $route . ($parameters === [] ? '' : '?' . http_build_query($parameters));
    }

    protected function isCsrfTokenValid(string $id, ?string $token): bool
    {
        $this->csrfChecks[] = [$id, $token];

        return $this->csrfValid;
    }
}
