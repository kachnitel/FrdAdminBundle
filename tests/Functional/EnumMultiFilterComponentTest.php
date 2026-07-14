<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\Twig\Components\EnumMultiFilter;
use PHPUnit\Framework\Attributes\Test;

final class EnumMultiFilterComponentTest extends ComponentTestCase
{
    #[Test]
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

        /** @var EnumMultiFilter $component */
        $component = $testComponent->component();
        $this->assertSame(['pending', 'approved'], $component->selectedValues);
    }

    #[Test]
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

        /** @var EnumMultiFilter $component */
        $component = $testComponent->component();
        $this->assertSame([], $component->selectedValues);
    }

    #[Test]
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

        /** @var EnumMultiFilter $component */
        $component = $testComponent->component();
        $this->assertSame(['pending'], $component->selectedValues);
    }

    #[Test]
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

        /** @var EnumMultiFilter $component */
        $component = $testComponent->component();
        $this->assertSame('["pending","approved"]', $component->value);
    }

    #[Test]
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

        /** @var EnumMultiFilter $component */
        $component = $testComponent->component();
        $this->assertSame('', $component->value);
    }

    #[Test]
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

        /** @var EnumMultiFilter $component */
        $component = $testComponent->component();
        $this->assertSame('', $component->value);
        $this->assertSame([], $component->selectedValues);
    }

    #[Test]
    public function getChoicesReturnsEnumCasesWhenEnumClass(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EnumMultiFilter',
            data: [
                'column' => 'status',
                'value' => '',
                'enumClass' => \Kachnitel\AdminBundle\Tests\Fixtures\TestStatus::class,
            ],
        );

        /** @var EnumMultiFilter $component */
        $component = $testComponent->component();
        $choices = $component->getChoices();
        $this->assertSame(['active' => 'ACTIVE', 'inactive' => 'INACTIVE', 'archived' => 'ARCHIVED'], $choices);
    }

    #[Test]
    public function getChoicesReturnsOptionsWhenNoEnumClass(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EnumMultiFilter',
            data: [
                'column' => 'category',
                'value' => '',
                'options' => ['electronics', 'clothing', 'food'],
            ],
        );

        /** @var EnumMultiFilter $component */
        $component = $testComponent->component();
        $this->assertSame(
            ['electronics' => 'electronics', 'clothing' => 'clothing', 'food' => 'food'],
            $component->getChoices()
        );
    }

    #[Test]
    public function rendersWithStringOptions(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EnumMultiFilter',
            data: [
                'column' => 'category',
                'value' => '',
                'options' => ['electronics', 'clothing', 'food'],
            ],
        );

        $rendered = (string) $testComponent->render();
        $this->assertStringContainsString('electronics', $rendered);
        $this->assertStringContainsString('clothing', $rendered);
        $this->assertStringContainsString('food', $rendered);
    }

    #[Test]
    public function selectionWorksWithStringOptions(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EnumMultiFilter',
            data: [
                'column' => 'category',
                'value' => '',
                'options' => ['electronics', 'clothing', 'food'],
            ],
        );

        $testComponent->set('selectedValues', ['electronics', 'food']);

        /** @var EnumMultiFilter $component */
        $component = $testComponent->component();
        $this->assertSame('["electronics","food"]', $component->value);
    }

    #[Test]
    public function clearWorksWithStringOptions(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EnumMultiFilter',
            data: [
                'column' => 'category',
                'value' => '["electronics","food"]',
                'options' => ['electronics', 'clothing', 'food'],
            ],
        );

        $testComponent->call('clear');

        /** @var EnumMultiFilter $component */
        $component = $testComponent->component();
        $this->assertSame('', $component->value);
        $this->assertSame([], $component->selectedValues);
    }

    #[Test]
    public function rendersWithEnumClassOptions(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EnumMultiFilter',
            data: [
                'column' => 'status',
                'value' => '',
                'enumClass' => \Kachnitel\AdminBundle\Tests\Fixtures\TestStatus::class,
            ],
        );

        $rendered = (string) $testComponent->render();
        $this->assertStringContainsString('type="checkbox"', $rendered);
        $this->assertStringContainsString('ACTIVE', $rendered);
        $this->assertStringContainsString('INACTIVE', $rendered);
    }

    #[Test]
    public function rendersSelectedCheckboxesAsChecked(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EnumMultiFilter',
            data: [
                'column' => 'status',
                'value' => '["active"]',
                'enumClass' => \Kachnitel\AdminBundle\Tests\Fixtures\TestStatus::class,
            ],
        );

        $rendered = (string) $testComponent->render();
        // The "active" checkbox should be checked
        $this->assertMatchesRegularExpression('/value="active"[^>]*checked/', $rendered);
        // The "inactive" checkbox should NOT be checked
        $this->assertDoesNotMatchRegularExpression('/value="inactive"[^>]*checked/', $rendered);
    }
}
