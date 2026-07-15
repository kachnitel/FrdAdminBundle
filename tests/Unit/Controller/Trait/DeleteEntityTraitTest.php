<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Controller\Trait;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Controller\Trait\DeleteEntityTrait;
use Kachnitel\AdminBundle\Tests\Fixtures\DeletableEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\DeleteEntityTraitHost;
use Kachnitel\AdminBundle\Tests\Fixtures\TestForeignKeyConstraintViolationException;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversTrait(DeleteEntityTrait::class)]
#[Group('controller')]
#[Group('delete')]
final class DeleteEntityTraitTest extends TestCase
{
    private DeleteEntityTraitHost $host;
    private Stub&EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->host = new DeleteEntityTraitHost();
        $this->em = $this->createStub(EntityManagerInterface::class);
    }

    private function requestFor(string $route, ?string $token, ?string $referer = null): Request
    {
        $request = Request::create('/admin/widget/delete', 'POST');
        $request->attributes->set('_route', $route);
        if ($token !== null) {
            $request->request->set('_token', $token);
        }
        if ($referer !== null) {
            $request->headers->set('referer', $referer);
        }

        return $request;
    }

    // ── validateCsrfEntityRequest ───────────────────────────────────────────

    #[Test]
    public function validateCsrfEntityRequestPassesWithValidToken(): void
    {
        $this->host->csrfValid = true;

        $this->host->validateCsrfEntityRequest(
            $this->requestFor('widget_delete', 'valid-token'),
            new DeletableEntity(7),
        );

        $this->addToAssertionCount(1); // No exception thrown = success.
    }

    #[Test]
    public function validateCsrfEntityRequestThrowsOnInvalidToken(): void
    {
        $this->host->csrfValid = false;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid CSRF token widget_delete-3');

        $this->host->validateCsrfEntityRequest(
            $this->requestFor('widget_delete', 'bad-token'),
            new DeletableEntity(3),
        );
    }

    #[Test]
    public function validateCsrfEntityRequestBuildsKeyFromRouteAndEntityId(): void
    {
        $this->host->csrfValid = false;

        $this->expectExceptionMessage('Invalid CSRF token order_delete-99');

        $this->host->validateCsrfEntityRequest(
            $this->requestFor('order_delete', 'bad-token'),
            new DeletableEntity(99),
        );
    }

    #[Test]
    public function validateCsrfEntityRequestHandlesEntityWithoutGetId(): void
    {
        $this->host->csrfValid = false;
        $entityWithoutId = new class {
            // Deliberately no getId() method.
        };

        $this->expectExceptionMessage('Invalid CSRF token widget_delete-');

        $this->host->validateCsrfEntityRequest(
            $this->requestFor('widget_delete', 'bad-token'),
            $entityWithoutId,
        );
    }

    #[Test]
    public function validateCsrfEntityRequestTreatsNonStringTokenAsMissing(): void
    {
        $this->host->csrfValid = false;
        $request = $this->requestFor('widget_delete', null);
        // Bypass requestFor()'s (?string $token) signature to submit a non-string
        // _token — covers is_string($token)'s false branch. Must be a scalar: an
        // array here would make InputBag::get() throw its own BadRequestException
        // before the trait's ternary ever runs, which isn't what this test is for.
        $request->request->set('_token', 12345);

        $this->expectExceptionMessage('Invalid CSRF token widget_delete-7');

        $this->host->validateCsrfEntityRequest($request, new DeletableEntity(7));
    }

    // ── doDelete ─────────────────────────────────────────────────────────

    #[Test]
    public function doDeleteRemovesFlushesAndReturnsSuccessResponseOnSuccess(): void
    {
        $entity = new DeletableEntity(42);
        $successResponse = new Response('deleted', 200);

        $result = $this->host->doDelete(
            $this->requestFor('widget_delete', 'valid-token'),
            $entity,
            $this->em,
            $successResponse,
        );

        $this->assertSame($successResponse, $result);
        $this->assertSame([['success', 'DeletableEntity #42 deleted.']], $this->host->flashes);
        $this->assertNull($this->host->redirectedTo);
    }

    #[Test]
    public function doDeleteThrowsBeforeTouchingEntityManagerWhenCsrfInvalid(): void
    {
        $this->host->csrfValid = false;
        // Tripwire: if remove() is ever called, this throws instead of the
        // expected InvalidArgumentException, proving the CSRF check runs first.
        $this->em->method('remove')->willThrowException(new \LogicException('should not be called'));

        $this->expectException(\InvalidArgumentException::class);

        $this->host->doDelete(
            $this->requestFor('widget_delete', 'bad-token'),
            new DeletableEntity(1),
            $this->em,
            new Response(),
        );
    }

    #[Test]
    public function doDeleteAddsErrorFlashAndRedirectsToRefererOnForeignKeyViolation(): void
    {
        $this->em->method('remove');
        $this->em->method('flush')->willThrowException(new TestForeignKeyConstraintViolationException());

        $result = $this->host->doDelete(
            $this->requestFor('widget_delete', 'valid-token', referer: '/admin/widget'),
            new DeletableEntity(5),
            $this->em,
            new Response('unused', 200),
        );

        $this->assertSame([['error', 'Cannot delete DeletableEntity because it is in use.']], $this->host->flashes);
        $this->assertSame('/admin/widget', $this->host->redirectedTo);
        $this->assertInstanceOf(RedirectResponse::class, $result);
    }

    #[Test]
    public function doDeleteReturnsSuccessResponseOnForeignKeyViolationWithoutReferer(): void
    {
        $this->em->method('remove');
        $this->em->method('flush')->willThrowException(new TestForeignKeyConstraintViolationException());

        $successResponse = new Response('fallback', 200);

        $result = $this->host->doDelete(
            $this->requestFor('widget_delete', 'valid-token'), // no referer
            new DeletableEntity(6),
            $this->em,
            $successResponse,
        );

        // No referer to redirect to — falls through to the success response.
        $this->assertSame($successResponse, $result);
        $this->assertCount(1, $this->host->flashes);
        $this->assertSame('error', $this->host->flashes[0][0]);
    }
}
