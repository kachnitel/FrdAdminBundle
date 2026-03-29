<?php

namespace Kachnitel\AdminBundle\Controller\Trait;

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
        $entityId = method_exists($entity, 'getId') ? $entity->getId() : null;

        try {
            $em->remove($entity);
            $em->flush();
            $this->addFlash('success', $shortName . ' #' . $entityId . ' deleted.');
        } catch (ForeignKeyConstraintViolationException $th) {
            $this->addFlash('error', 'Cannot delete ' . $shortName . ' because it is in use.');

            if ($request->headers->get('referer')) {
                return $this->redirect((string) $request->headers->get('referer'));
            }
        }

        return $successResponse;
    }

    /**
     * @throws \InvalidArgumentException if CSRF token is invalid
     */
    protected function validateCsrfEntityRequest(Request $request, object $entity): void
    {
        $entityId = method_exists($entity, 'getId') ? $entity->getId() : null;
        $csrfKey = $request->attributes->get('_route') . '-' . $entityId;
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid($csrfKey, is_string($token) ? $token : null)) {
            throw new \InvalidArgumentException('Invalid CSRF token ' . $csrfKey);
        }
    }

    // Abstract methods that must be implemented by the controller
    abstract protected function addFlash(string $type, mixed $message): void;
    abstract protected function redirect(string $url, int $status = 302): Response;
    abstract protected function isCsrfTokenValid(string $id, ?string $token): bool;
}
