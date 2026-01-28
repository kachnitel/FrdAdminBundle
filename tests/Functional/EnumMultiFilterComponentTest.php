<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

class EnumMultiFilterComponentTest extends ComponentTestCase
{
    /**
     * @test
     */
    public function deserializeValueParsesJsonArray(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EnumMultiFilter',
            data: [
                'column' => 'status',
                'value' => '["pending","approved"]',
                'enumClass' => \Kachnitel\AdminBundle\Tests\Fixtures\TestStatus::class,
            ],
        );

        $component = $testComponent->component();
        $this->assertSame(['pending', 'approved'], $component->selectedValues);
    }

    /**
     * @test
     */
    public function deserializeValueHandlesEmptyValue(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EnumMultiFilter',
            data: [
                'column' => 'status',
                'value' => '',
                'enumClass' => \Kachnitel\AdminBundle\Tests\Fixtures\TestStatus::class,
            ],
        );

        $component = $testComponent->component();
        $this->assertSame([], $component->selectedValues);
    }

    /**
     * @test
     */
    public function deserializeValueFallsBackToSingleValueForNonJson(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EnumMultiFilter',
            data: [
                'column' => 'status',
                'value' => 'pending',
                'enumClass' => \Kachnitel\AdminBundle\Tests\Fixtures\TestStatus::class,
            ],
        );

        $component = $testComponent->component();
        $this->assertSame(['pending'], $component->selectedValues);
    }

    /**
     * @test
     */
    public function onSelectedValuesUpdatedSerializesToJson(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EnumMultiFilter',
            data: [
                'column' => 'status',
                'value' => '',
                'enumClass' => \Kachnitel\AdminBundle\Tests\Fixtures\TestStatus::class,
            ],
        );

        $testComponent->set('selectedValues', ['pending', 'approved']);

        $component = $testComponent->component();
        $this->assertSame('["pending","approved"]', $component->value);
    }

    /**
     * @test
     */
    public function onSelectedValuesUpdatedSetsEmptyStringWhenNoSelection(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EnumMultiFilter',
            data: [
                'column' => 'status',
                'value' => '["pending"]',
                'enumClass' => \Kachnitel\AdminBundle\Tests\Fixtures\TestStatus::class,
            ],
        );

        $testComponent->set('selectedValues', []);

        $component = $testComponent->component();
        $this->assertSame('', $component->value);
    }

    /**
     * @test
     */
    public function clearResetsState(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EnumMultiFilter',
            data: [
                'column' => 'status',
                'value' => '["pending","approved"]',
                'enumClass' => \Kachnitel\AdminBundle\Tests\Fixtures\TestStatus::class,
            ],
        );

        $testComponent->call('clear');

        $component = $testComponent->component();
        $this->assertSame('', $component->value);
        $this->assertSame([], $component->selectedValues);
    }
}
