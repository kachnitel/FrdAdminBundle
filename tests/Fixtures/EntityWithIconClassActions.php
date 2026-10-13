<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Kachnitel\AdminBundle\Attribute\AdminAction;

#[AdminAction(name: 'approve', label: 'Approve', icon: 'check', route: 'app_approve', iconClass: 'material-symbols-outlined')]
#[AdminAction(name: 'plain', label: 'Plain', icon: '✅', route: 'app_plain')]
#[AdminAction(name: 'bulk', label: 'Bulk', icon: 'delete', route: 'app_bulk', actionType: AdminAction::ACTION_TYPE_BATCH, iconClass: 'bi bi-trash')]
class EntityWithIconClassActions {}
