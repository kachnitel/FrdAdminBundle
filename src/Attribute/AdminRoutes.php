<?php

namespace Kachnitel\AdminBundle\Attribute;

use Attribute;

/**
 * Defines routes for entity CRUD operations.
 *
 * Usage:
 * #[AdminRoutes([
 *     'index' => 'app_product_index',
 *     'new' => 'app_product_new',
 *     'show' => 'app_product_show',
 *     'edit' => 'app_product_edit',
 *     'delete' => 'app_product_delete'
 * ])]
 */
#[Attribute(Attribute::TARGET_CLASS)]
class AdminRoutes
{
    /**
     * @param array<string, string> $routes
     */
    public function __construct(
        private array $routes = []
    ) {}

    public function get(string $key): ?string
    {
        return $this->routes[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->routes[$key]);
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->routes;
    }
}
