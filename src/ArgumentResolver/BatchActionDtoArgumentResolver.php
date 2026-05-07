<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\ArgumentResolver;

use Kachnitel\AdminBundle\ValueObject\BatchActionDto;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * Argument resolver for BatchActionDto.
 *
 * Automatically injects BatchActionDto into controller/component methods.
 * Converts POST data (from batch action forms) into a type-safe DTO.
 */
class BatchActionDtoArgumentResolver implements ValueResolverInterface
{
    /**
     * @return iterable<BatchActionDto>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (BatchActionDto::class !== $argument->getType()) {
            return [];
        }

        $entityIds = $request->request->all('batchActionEntityIds');
        $actionName = $request->request->getString('batchActionName', '');
        $entityClass = $request->request->getString('entityClass', '');
        $entityShortClass = $request->request->getString('entityShortClass', '');
        $allSelected = $request->request->getBoolean('allSelected', false);

        // Validate required fields
        if (!$actionName || !$entityClass) {
            return [];
        }

        yield new BatchActionDto(
            name: $actionName,
            entityIds: array_values($entityIds),  // Re-index to ensure numeric array
            entityClass: $entityClass,
            entityShortClass: $entityShortClass,
            allSelected: $allSelected,
        );
    }
}
