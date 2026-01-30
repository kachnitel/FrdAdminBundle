<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Attribute;

use Attribute;

/**
 * Configure row actions behavior at the entity level.
 *
 * Usage:
 * #[AdminActionsConfig(
 *     disableDefaults: false,
 *     exclude: ['delete'],
 *     include: ['show', 'edit', 'duplicate']
 * )]
 * class Product { }
 */
#[Attribute(Attribute::TARGET_CLASS)]
class AdminActionsConfig
{
    /**
     * @param bool $disableDefaults If true, don't include default show/edit actions
     * @param array<string>|null $exclude Actions to exclude (e.g., ['delete'])
     * @param array<string>|null $include If set, only these actions are shown (whitelist)
     */
    public function __construct(
        public readonly bool $disableDefaults = false,
        public readonly ?array $exclude = null,
        public readonly ?array $include = null,
    ) {}
}
