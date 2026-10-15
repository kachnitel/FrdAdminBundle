<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Service;

use Kachnitel\AdminBundle\Attribute\AdminAction;
use Kachnitel\AdminBundle\Service\AdminActionMapper;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('icons')]
final class AdminActionMapperTest extends TestCase
{
    private AdminActionMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new AdminActionMapper();
    }

    #[Test]
    public function toRowActionCopiesEveryRowField(): void
    {
        $attribute = new AdminAction(
            name: 'approve',
            label: 'Approve',
            icon: '✅',
            route: 'app_approve',
            routeParams: ['foo' => 'bar'],
            url: '/approve',
            permission: 'ROLE_ADMIN',
            voterAttribute: 'ADMIN_EDIT',
            condition: 'entity.isDraft()',
            cssClass: 'btn btn-x',
            confirmMessage: 'Sure?',
            openInNewTab: true,
            priority: 30,
            method: 'POST',
            template: 'foo.html.twig',
            liveComponent: 'App:Live',
            contexts: ['index'],
        );

        $row = $this->mapper->toRowAction($attribute);

        $this->assertSame('approve', $row->name);
        $this->assertSame('Approve', $row->label);
        $this->assertSame('✅', $row->icon);
        $this->assertSame('app_approve', $row->route);
        $this->assertSame(['foo' => 'bar'], $row->routeParams);
        $this->assertSame('/approve', $row->url);
        $this->assertSame('ROLE_ADMIN', $row->permission);
        $this->assertSame('ADMIN_EDIT', $row->voterAttribute);
        $this->assertSame('entity.isDraft()', $row->condition);
        $this->assertSame('btn btn-x', $row->cssClass);
        $this->assertSame('Sure?', $row->confirmMessage);
        $this->assertTrue($row->openInNewTab);
        $this->assertSame(30, $row->priority);
        $this->assertSame('POST', $row->method);
        $this->assertSame('foo.html.twig', $row->template);
        $this->assertSame('App:Live', $row->liveComponent);
        $this->assertSame(['index'], $row->contexts);
    }

    #[Test]
    public function toBatchActionCopiesEveryBatchField(): void
    {
        $attribute = new AdminAction(
            name: 'bulk-publish',
            label: 'Publish',
            icon: '📦',
            route: 'app_publish',
            url: '/publish',
            permission: 'ROLE_ADMIN',
            voterAttribute: 'ADMIN_EDIT',
            cssClass: 'btn btn-y',
            confirmMessage: 'Publish %count%?',
            priority: 20,
            liveComponent: 'App:Batch',
            actionType: AdminAction::ACTION_TYPE_BATCH,
        );

        $batch = $this->mapper->toBatchAction($attribute);

        $this->assertSame('bulk-publish', $batch->name);
        $this->assertSame('Publish', $batch->label);
        $this->assertSame('📦', $batch->icon);
        $this->assertSame('app_publish', $batch->route);
        $this->assertSame('/publish', $batch->url);
        $this->assertSame('App:Batch', $batch->liveComponent);
        $this->assertSame('ROLE_ADMIN', $batch->permission);
        $this->assertSame('ADMIN_EDIT', $batch->voterAttribute);
        $this->assertSame('btn btn-y', $batch->cssClass);
        $this->assertSame('Publish %count%?', $batch->confirmMessage);
        $this->assertSame(20, $batch->priority);
    }

    #[Test]
    public function iconIsPassedThroughVerbatimAndNullIsPreserved(): void
    {
        $withIcon = new AdminAction(name: 'a', label: 'A', icon: '🗑️');
        $without = new AdminAction(name: 'b', label: 'B');

        $this->assertSame('🗑️', $this->mapper->toRowAction($withIcon)->icon);
        $this->assertSame('🗑️', $this->mapper->toBatchAction($withIcon)->icon);
        $this->assertNull($this->mapper->toRowAction($without)->icon);
        $this->assertNull($this->mapper->toBatchAction($without)->icon);
    }
}
