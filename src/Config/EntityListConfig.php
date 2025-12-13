<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Config;

/**
 * Configuration value object for EntityList component.
 */
final readonly class EntityListConfig
{
    /**
     * @param array<int> $allowedItemsPerPage
     */
    public function __construct(
        public string $formNamespace = 'App\\Form\\',
        public string $formSuffix = 'Type',
        public int $defaultItemsPerPage = 20,
        public array $allowedItemsPerPage = [10, 20, 50, 100]
    ) {}
}
