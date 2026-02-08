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
    public function onUpdatedSetsValueAndColumn(): void
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

    /**
     * @test
     */
    public function enumWithOptionsRendersSelectDropdown(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:ColumnFilter',
            data: [
                'column' => 'category',
                'value' => '',
                'filterMetadata' => [
                    'type' => 'enum',
                    'options' => ['small', 'medium', 'large'],
                ],
            ],
        );

        $rendered = (string) $testComponent->render();
        $this->assertStringContainsString('<select', $rendered);
        $this->assertStringContainsString('small', $rendered);
        $this->assertStringContainsString('medium', $rendered);
        $this->assertStringContainsString('large', $rendered);
    }

    /**
     * @test
     */
    public function enumWithOptionsShowsAllOptionByDefault(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:ColumnFilter',
            data: [
                'column' => 'size',
                'value' => '',
                'filterMetadata' => [
                    'type' => 'enum',
                    'options' => ['S', 'M', 'L'],
                ],
            ],
        );

        $rendered = (string) $testComponent->render();
        $this->assertStringContainsString('>All</option>', $rendered);
    }

    /**
     * @test
     */
    public function enumWithOptionsHidesAllOptionWhenDisabled(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:ColumnFilter',
            data: [
                'column' => 'size',
                'value' => '',
                'filterMetadata' => [
                    'type' => 'enum',
                    'options' => ['S', 'M', 'L'],
                    'showAllOption' => false,
                ],
            ],
        );

        $rendered = (string) $testComponent->render();
        $this->assertStringNotContainsString('>All</option>', $rendered);
    }

    /**
     * @test
     */
    public function enumWithOptionsPreservesSelectedValue(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:ColumnFilter',
            data: [
                'column' => 'priority',
                'value' => 'high',
                'filterMetadata' => [
                    'type' => 'enum',
                    'options' => ['low', 'medium', 'high'],
                ],
            ],
        );

        $rendered = (string) $testComponent->render();
        $this->assertMatchesRegularExpression('/value="high"[^>]*selected/', $rendered);
    }

    /**
     * @test
     */
    public function enumWithMultipleTrueRendersCheckboxesInsteadOfSelect(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:ColumnFilter',
            data: [
                'column' => 'status',
                'value' => '',
                'filterMetadata' => [
                    'type' => 'enum',
                    'multiple' => true,
                    'options' => ['active', 'inactive'],
                ],
            ],
        );

        $rendered = (string) $testComponent->render();
        $this->assertStringContainsString('type="checkbox"', $rendered);
        $this->assertStringNotContainsString('<select', $rendered);
        $this->assertStringContainsString('active', $rendered);
        $this->assertStringContainsString('inactive', $rendered);
    }

    /**
     * @test
     */
    public function enumWithoutEnumClassOrOptionsFallsBackToText(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:ColumnFilter',
            data: [
                'column' => 'broken',
                'value' => '',
                'filterMetadata' => ['type' => 'enum'],
            ],
        );

        $rendered = (string) $testComponent->render();
        $this->assertStringContainsString('type="text"', $rendered);
    }
}
