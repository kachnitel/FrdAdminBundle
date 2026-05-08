<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\ValueObject;

/**
 * Data transfer object carrying batch action context.
 *
 * Passed to Symfony route handlers that process batch actions.
 * Route handlers receive the selected entity IDs and can retrieve the
 * full entity objects from the EntityManager.
 *
 * Example route handler:
 *
 *   #[Route('/admin/product/bulk-publish', name: 'app_product_bulk_publish', methods: ['POST'])]
 *   public function bulkPublish(Request $request, EntityManagerInterface $em): Response
 *   {
 *       $ids = $request->request->all('ids');
 *       // ... process $ids
 *   }
 */
final readonly class BatchActionDto
{
    /**
     * @param string            $name            Batch action name
     * @param array<int|string> $entityIds       Selected entity IDs
     * @param string            $entityClass     Fully-qualified entity class name
     * @param string            $entityShortClass Short entity class name (e.g. 'Product')
     */
    public function __construct(
        public readonly string $name,
        public readonly array $entityIds,
        public readonly string $entityClass,
        public readonly string $entityShortClass,
    ) {}

    /**
     * Get the number of selected entities.
     */
    public function getCount(): int
    {
        return count($this->entityIds);
    }
}
