<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Attribute;

use Attribute;

/**
 * Defines a virtual, template-driven column that is not backed by a Doctrine field.
 *
 * Repeatable — add one per custom column on the entity class.
 *
 * The column template receives:
 *   - `entity`   — the entity object (use this to compute anything you need)
 *   - `value`    — always null (no Doctrine field backing)
 *   - `property` — the column name string
 *   - `cell`     — true (rendering in a table cell)
 *
 * Ordering:
 *   - When `columns:` is set in #[Admin], include the custom column name in that list
 *     to control its position.
 *   - When `columns:` is NOT set, custom columns are appended after auto-detected
 *     Doctrine columns.
 *
 * Example:
 * ```php
 * #[Admin(label: 'Users', columns: ['id', 'email', 'fullName', 'createdAt'])]
 * #[AdminCustomColumn(
 *     name: 'fullName',
 *     label: 'Full Name',
 *     template: 'admin/columns/user_full_name.html.twig',
 * )]
 * class User { }
 * ```
 *
 * @see \Kachnitel\AdminBundle\DataSource\DoctrineCustomColumnProvider
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class AdminCustomColumn
{
    /**
     * @param string      $name     Unique column identifier — must match name used in Admin::columns
     * @param string      $template Twig template path for rendering the cell
     * @param string|null $label    Column header label (humanised from name when null)
     * @param bool        $sortable Whether the column header renders a sort link (default false — no DB field)
     */
    public function __construct(
        public readonly string $name,
        public readonly string $template,
        public readonly ?string $label = null,
        public readonly bool $sortable = false,
    ) {}
}
