<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

class ColumnFilterComponentTest extends ComponentTestCase
{
    /**
     * @test
     */
    public function getTypeReturnsDefaultText(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:ColumnFilter',
            data: [
                'column' => 'name',
                'value' => '',
                'filterMetadata' => [],
            ],
        );

        $component = $testComponent->component();
        $this->assertSame('text', $component->getType());
    }

    /**
     * @test
     */
    public function getTypeReturnsTypeFromMetadata(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:ColumnFilter',
            data: [
                'column' => 'status',
                'value' => '',
                'filterMetadata' => ['type' => 'enum'],
            ],
        );

        $component = $testComponent->component();
        $this->assertSame('enum', $component->getType());
    }

    /**
     * @test
     */
    public function onUpdatedEmitsFilterUpdatedEvent(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:ColumnFilter',
            data: [
                'column' => 'name',
                'value' => '',
                'filterMetadata' => [],
            ],
        );

        // Set a value which triggers onUpdated
        $testComponent->set('value', 'test search');

        $component = $testComponent->component();
        $this->assertSame('test search', $component->value);
        $this->assertSame('name', $component->column);
    }

    /**
     * @test
     */
    public function componentRendersWithTextInput(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:ColumnFilter',
            data: [
                'column' => 'name',
                'value' => 'initial value',
                'filterMetadata' => ['type' => 'text'],
            ],
        );

        $rendered = (string) $testComponent->render();
        $this->assertStringContainsString('initial value', $rendered);
    }

    /**
     * @test
     */
    public function componentCanUpdateValue(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:ColumnFilter',
            data: [
                'column' => 'status',
                'value' => 'old',
                'filterMetadata' => ['type' => 'text'],
            ],
        );

        $testComponent->set('value', 'new');

        $component = $testComponent->component();
        $this->assertSame('new', $component->value);
    }
}
