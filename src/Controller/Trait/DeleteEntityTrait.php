<?php

namespace Frd\AdminBundle\Controller\Trait;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides safe entity deletion with foreign key constraint handling and CSRF protection.
 */
trait DeleteEntityTrait
{
    protected function doDelete(
        Request $request,
        object $entity,
        EntityManagerInterface $em,
        Response $successResponse
    ): Response {
        $this->validateCsrfEntityRequest($request, $entity);
        $shortName = (new \ReflectionClass($entity))->getShortName();
        $entityId = $entity->getId();

        try {
            $em->remove($entity);
            $em->flush();
            $this->addFlash('success', $shortName . ' #' . $entityId . ' deleted.');
        } catch (ForeignKeyConstraintViolationException $th) {
            $this->addFlash('error', 'Cannot delete ' . $shortName . ' because it is in use.');

            if ($request->headers->get('referer')) {
                return $this->redirect($request->headers->get('referer'));
            }
        }

        return $successResponse;
    }

    /**
     * @throws \InvalidArgumentException if CSRF token is invalid
     */
    protected function validateCsrfEntityRequest(Request $request, object $entity): void
    {
        $csrfKey = $request->attributes->get('_route') . '-' . $entity->getId();
        if (!$this->isCsrfTokenValid($csrfKey, $request->request->get('_token'))) {
            throw new \InvalidArgumentException('Invalid CSRF token ' . $csrfKey);
        }
    }

    // Abstract methods that must be implemented by the controller
    abstract protected function addFlash(string $type, mixed $message): void;
    abstract protected function redirect(string $url, int $status = 302): Response;
    abstract protected function isCsrfTokenValid(string $id, ?string $token): bool;
}
