<?php

namespace Kachnitel\AdminBundle\Controller;

use Kachnitel\AdminBundle\Controller\Trait\DeleteEntityTrait;
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

    public function __construct(protected EntityManagerInterface $em) {}

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
     *
     * Form handling (submission, validation, persistence) is delegated to the
     * AdminEntityForm LiveComponent. This method only prepares the template variables.
     */
    protected function doNew(string $class): Response
    {
        $this->validateSupportedEntity($class);

        return $this->render($this->getNewTemplate($class), [
            'entityClass'       => $this->getEntityNamespace() . $class,
            'formTypeClass'     => $this->getFormType($class),
            'formComponentName' => $this->getFormComponentName($class),
            'breadcrumbs'       => $this->getBreadcrumbs($class),
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
     *
     * Form handling (submission, validation, persistence) is delegated to the
     * AdminEntityForm LiveComponent. This method only prepares the template variables.
     */
    protected function doEdit(string $class, int $id): Response
    {
        $this->validateSupportedEntity($class);

        $entity = $this->getRepository($class)->find($id);
        if (!$entity) {
            throw $this->createNotFoundException('No ' . $class . ' found for id ' . $id);
        }

        return $this->render($this->getEditTemplate($class), [
            'entity'            => $entity,
            'entityClass'       => $this->getEntityNamespace() . $class,
            'formTypeClass'     => $this->getFormType($class),
            'formComponentName' => $this->getFormComponentName($class),
            'breadcrumbs'       => $this->getBreadcrumbs($class, $entity),
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

        // Convert class name to entitySlug format (PascalCase -> kebab-case)
        $entitySlug = strtolower((string) preg_replace('/[A-Z]/', '-$0', lcfirst($class)));

        return $this->doDelete(
            $request,
            $entity,
            $this->em,
            $this->redirectToRoute($this->getRoutePrefix() . '_index', [
                'entitySlug' => $entitySlug
            ], Response::HTTP_SEE_OTHER)
        );
    }

    /**
     * Get repository for entity class.
     * @return \Doctrine\ORM\EntityRepository<object>
     */
    protected function getRepository(string $class): \Doctrine\ORM\EntityRepository
    {
        $className = $this->getEntityNamespace() . $class;
        if (!class_exists($className)) {
            throw new \InvalidArgumentException(sprintf('Class "%s" does not exist.', $className));
        }
        /** @var \Doctrine\ORM\EntityRepository<object> $repository */
        $repository = $this->em->getRepository($className);
        return $repository;
    }

    /**
     * Get form type for entity class.
     */
    protected function getFormType(string $class): string
    {
        return $this->getFormNamespace() . $class . $this->getFormSuffix();
    }

    /**
     * Get the LiveComponent name for the form.
     * Override to use a custom form component for all entities in this controller.
     * @SuppressWarnings(PHPMD.UnusedFormalParameter) Class is used in inherited controllers
     */
    protected function getFormComponentName(string $class): string
    {
        return 'K:Admin:EntityForm';
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
     * @return array<int, array{url: string, label: string}>
     */
    protected function getBreadcrumbs(string $class, ?object $entity = null): array
    {
        // Convert class name to entitySlug format (PascalCase -> kebab-case)
        $entitySlug = strtolower((string) preg_replace('/[A-Z]/', '-$0', lcfirst($class)));

        $breadcrumbs = [[
            'url' => $this->generateUrl($this->getRoutePrefix() . '_index', ['entitySlug' => $entitySlug]),
            'label' => $class
        ]];

        if ($entity !== null) {
            $entityLabel = $this->getEntityLabel($entity);
            $entityId = method_exists($entity, 'getId') ? $entity->getId() : null;
            $breadcrumbs[] = [
                'url' => $this->generateUrl($this->getRoutePrefix() . '_show', [
                    'id' => $entityId,
                    'entitySlug' => $entitySlug
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
            return (string) $entity->getName();
        }
        if (method_exists($entity, 'getLabel') && $entity->getLabel()) {
            return (string) $entity->getLabel();
        }
        if (method_exists($entity, 'getValue') && $entity->getValue()) {
            return (string) $entity->getValue();
        }
        if (method_exists($entity, 'getId')) {
            return '#' . $entity->getId();
        }
        return '#unknown';
    }

    // Template resolution methods (can be overridden for custom template paths)
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter("class"))
     */
    protected function getIndexTemplate(string $class): string
    {
        return '@KachnitelAdmin/admin/index.html.twig';
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter("class"))
     */
    protected function getShowTemplate(string $class): string
    {
        return '@KachnitelAdmin/admin/show.html.twig';
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter("class"))
     */
    protected function getEditTemplate(string $class): string
    {
        return '@KachnitelAdmin/admin/edit.html.twig';
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter("class"))
     */
    protected function getNewTemplate(string $class): string
    {
        return '@KachnitelAdmin/admin/new.html.twig';
    }

    // Configuration methods (must be implemented by application)
    /**
     * @return array<string>
     */
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
