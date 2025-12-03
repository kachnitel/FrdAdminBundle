<?php

namespace Frd\AdminBundle\Controller;

use Frd\AdminBundle\Controller\Trait\DeleteEntityTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Abstract base controller for entity CRUD operations.
 *
 * Applications should extend this controller and implement:
 * - getSupportedEntities(): array
 * - getEntityNamespace(): string (optional, defaults to 'App\Entity\')
 * - getFormNamespace(): string (optional, defaults to 'App\Form\')
 * - getFormSuffix(): string (optional, defaults to 'FormType')
 */
abstract class AbstractAdminController extends AbstractController
{
    use DeleteEntityTrait;

    public function __construct(
        protected EntityManagerInterface $em
    ) {}

    /**
     * List all entities of the given class.
     */
    protected function doIndex(string $class): Response
    {
        $this->validateSupportedEntity($class);
        $repository = $this->getRepository($class);

        return $this->render($this->getIndexTemplate($class), [
            'entities' => $repository->findAll(),
            'entityClass' => $this->getEntityNamespace() . $class,
            'entityShortClass' => $class
        ]);
    }

    /**
     * Create a new entity.
     */
    protected function doNew(string $class, Request $request): Response
    {
        $this->validateSupportedEntity($class);
        $className = $this->getEntityNamespace() . $class;
        $entity = new $className();

        $formType = $this->getFormType($class);
        $form = $this->createForm($formType, $entity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($entity);
            $this->em->flush();

            return $this->redirectToRoute($this->getRoutePrefix() . '_index', [
                'class' => $class
            ], Response::HTTP_SEE_OTHER);
        }

        return $this->render($this->getNewTemplate($class), [
            'form' => $form,
            'breadcrumbs' => $this->getBreadcrumbs($class)
        ]);
    }

    /**
     * Show a single entity.
     */
    protected function doShow(string $class, int $id): Response
    {
        $this->validateSupportedEntity($class);
        $repository = $this->getRepository($class);
        $entity = $repository->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('No ' . $class . ' found for id ' . $id);
        }

        return $this->render($this->getShowTemplate($class), [
            'entity' => $entity,
            'entityClass' => $this->getEntityNamespace() . $class,
            'breadcrumbs' => $this->getBreadcrumbs($class, $entity)
        ]);
    }

    /**
     * Edit an existing entity.
     */
    protected function doEdit(string $class, int $id, Request $request): Response
    {
        $this->validateSupportedEntity($class);
        $repository = $this->getRepository($class);
        $entity = $repository->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('No ' . $class . ' found for id ' . $id);
        }

        $formType = $this->getFormType($class);
        $form = $this->createForm($formType, $entity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            return $this->redirectToRoute($this->getRoutePrefix() . '_index', [
                'class' => $class
            ], Response::HTTP_SEE_OTHER);
        }

        return $this->render($this->getEditTemplate($class), [
            'entity' => $entity,
            'form' => $form,
            'breadcrumbs' => $this->getBreadcrumbs($class, $entity)
        ]);
    }

    /**
     * Delete an entity.
     */
    protected function doDeleteEntity(string $class, int $id, Request $request): Response
    {
        $this->validateSupportedEntity($class);
        $repository = $this->getRepository($class);
        $entity = $repository->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('No ' . $class . ' found for id ' . $id);
        }

        return $this->doDelete(
            $request,
            $entity,
            $this->em,
            $this->redirectToRoute($this->getRoutePrefix() . '_index', [
                'class' => $class
            ], Response::HTTP_SEE_OTHER)
        );
    }

    /**
     * Get repository for entity class.
     */
    protected function getRepository(string $class): object
    {
        $className = $this->getEntityNamespace() . $class;
        return $this->em->getRepository($className);
    }

    /**
     * Get form type for entity class.
     */
    protected function getFormType(string $class): string
    {
        return $this->getFormNamespace() . $class . $this->getFormSuffix();
    }

    /**
     * Validate that the entity class is supported.
     */
    protected function validateSupportedEntity(string $class): void
    {
        if (!in_array($class, $this->getSupportedEntities())) {
            throw $this->createNotFoundException('Entity class ' . $class . ' is not supported');
        }
    }

    /**
     * Get breadcrumbs for navigation.
     */
    protected function getBreadcrumbs(string $class, ?object $entity = null): array
    {
        $breadcrumbs = [[
            'url' => $this->generateUrl($this->getRoutePrefix() . '_index', ['class' => $class]),
            'label' => $class
        ]];

        if ($entity) {
            $entityLabel = $this->getEntityLabel($entity);
            $breadcrumbs[] = [
                'url' => $this->generateUrl($this->getRoutePrefix() . '_show', [
                    'id' => $entity->getId(),
                    'class' => $class
                ]),
                'label' => $entityLabel
            ];
        }

        return $breadcrumbs;
    }

    /**
     * Get label for entity (tries name, label, value, or falls back to ID).
     */
    protected function getEntityLabel(object $entity): string
    {
        if (method_exists($entity, 'getName') && $entity->getName()) {
            return $entity->getName();
        }
        if (method_exists($entity, 'getLabel') && $entity->getLabel()) {
            return $entity->getLabel();
        }
        if (method_exists($entity, 'getValue') && $entity->getValue()) {
            return $entity->getValue();
        }
        return '#' . $entity->getId();
    }

    // Template resolution methods (can be overridden for custom template paths)
    protected function getIndexTemplate(string $class): string
    {
        return '@FrdAdmin/admin/index.html.twig';
    }

    protected function getShowTemplate(string $class): string
    {
        return '@FrdAdmin/admin/show.html.twig';
    }

    protected function getEditTemplate(string $class): string
    {
        return '@FrdAdmin/admin/edit.html.twig';
    }

    protected function getNewTemplate(string $class): string
    {
        return '@FrdAdmin/admin/new.html.twig';
    }

    // Configuration methods (must be implemented by application)
    abstract protected function getSupportedEntities(): array;

    // Configuration methods (can be overridden)
    protected function getEntityNamespace(): string
    {
        return 'App\\Entity\\';
    }

    protected function getFormNamespace(): string
    {
        return 'App\\Form\\';
    }

    protected function getFormSuffix(): string
    {
        return 'FormType';
    }

    protected function getRoutePrefix(): string
    {
        return 'app_entity';
    }
}
