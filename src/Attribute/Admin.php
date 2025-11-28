<?php

namespace Frd\AdminBundle\Attribute;

use Attribute;

/**
 * Marks an entity as manageable by the admin bundle.
 *
 * Usage:
 * #[Admin(label: 'Products', icon: 'inventory')]
 * class Product { }
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Admin
{
    public function __construct(
        private ?string $label = null,
        private ?string $icon = null,
        private ?string $formType = null,
        private bool $enableDatatables = true,
        private bool $enableFilters = true,
        private bool $enableBatchActions = true,
    ) {}

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getFormType(): ?string
    {
        return $this->formType;
    }

    public function isEnableDatatables(): bool
    {
        return $this->enableDatatables;
    }

    public function isEnableFilters(): bool
    {
        return $this->enableFilters;
    }

    public function isEnableBatchActions(): bool
    {
        return $this->enableBatchActions;
    }
}
