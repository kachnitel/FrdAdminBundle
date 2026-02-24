<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Attribute;

use Attribute;

/**
 * Configure row actions behaviour at the entity level.
 *
 * Usage:
 * #[AdminActionsConfig(disableDefaults: true)]
 * #[AdminActionsConfig(exclude: ['edit'])]
 * #[AdminActionsConfig(include: ['show', 'duplicate'])]
 */
#[Attribute(Attribute::TARGET_CLASS)]
class AdminActionsConfig
{
    /**
     * @param bool               $disableDefaults If true, suppress the default show/edit actions
     * @param array<string>|null $exclude         Action names to remove (applied after include)
     * @param array<string>|null $include         Whitelist — only these action names are shown
     */
    public function __construct(
        public readonly bool $disableDefaults = false,
        public readonly ?array $exclude = null,
        public readonly ?array $include = null,
    ) {}
}
