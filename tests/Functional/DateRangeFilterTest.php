<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\Twig\Components\DateRangeFilter;

class DateRangeFilterTest extends ComponentTestCase
{
    public function testInitialRenderWithEmptyValue(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:DateRangeFilter',
            data: ['column' => 'createdAt', 'compact' => true],
        );

        $component = $testComponent->component();
        $this->assertInstanceOf(DateRangeFilter::class, $component);
        $this->assertSame('createdAt', $component->column);
        $this->assertSame('', $component->from);
        $this->assertSame('', $component->to);
        $this->assertSame('', $component->value);

        $rendered = $testComponent->render()->toString();
        $this->assertStringContainsString('Select dates', $rendered);
        $this->assertStringContainsString('popovertarget=', $rendered);
    }

    public function testDeserializeJsonValueOnHydration(): void
    {
        $jsonValue = json_encode(['from' => '2024-01-15', 'to' => '2024-12-31']);

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:DateRangeFilter',
            data: [
                'column' => 'createdAt',
                'value' => $jsonValue,
                'compact' => true,
            ],
        );

        $component = $testComponent->component();
        $this->assertSame('2024-01-15', $component->from);
        $this->assertSame('2024-12-31', $component->to);
        $this->assertSame($jsonValue, $component->value);
    }

    public function testDeserializeJsonWithNullValues(): void
    {
        $jsonValue = json_encode(['from' => '2024-01-15', 'to' => null]);

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:DateRangeFilter',
            data: [
                'column' => 'createdAt',
                'value' => $jsonValue,
                'compact' => true,
            ],
        );

        $component = $testComponent->component();
        $this->assertSame('2024-01-15', $component->from);
        $this->assertSame('', $component->to);
    }

    public function testDeserializeInvalidJsonHandledGracefully(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:DateRangeFilter',
            data: [
                'column' => 'createdAt',
                'value' => 'invalid json {]',
                'compact' => true,
            ],
        );

        $component = $testComponent->component();
        // Invalid JSON should result in empty from/to
        $this->assertSame('', $component->from);
        $this->assertSame('', $component->to);
    }

    public function testUpdateFromFieldSerializesAndEmits(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:DateRangeFilter',
            data: ['column' => 'createdAt', 'compact' => true],
        );

        $testComponent->set('from', '2024-01-15');

        $component = $testComponent->component();
        $this->assertSame('2024-01-15', $component->from);

        // Value should be serialized to JSON
        $decoded = json_decode($component->value, true);
        $this->assertIsArray($decoded);
        $this->assertSame('2024-01-15', $decoded['from']);
        $this->assertNull($decoded['to']);
    }

    public function testUpdateToFieldSerializesAndEmits(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:DateRangeFilter',
            data: ['column' => 'createdAt', 'compact' => true],
        );

        $testComponent->set('to', '2024-12-31');

        $component = $testComponent->component();
        $this->assertSame('2024-12-31', $component->to);

        // Value should be serialized to JSON
        $decoded = json_decode($component->value, true);
        $this->assertIsArray($decoded);
        $this->assertNull($decoded['from']);
        $this->assertSame('2024-12-31', $decoded['to']);
    }

    public function testUpdateBothFieldsSerializesCorrectly(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:DateRangeFilter',
            data: ['column' => 'createdAt', 'compact' => true],
        );

        $testComponent->set('from', '2024-01-15');
        $testComponent->set('to', '2024-12-31');

        $component = $testComponent->component();
        $this->assertSame('2024-01-15', $component->from);
        $this->assertSame('2024-12-31', $component->to);

        $decoded = json_decode($component->value, true);
        $this->assertSame('2024-01-15', $decoded['from']);
        $this->assertSame('2024-12-31', $decoded['to']);
    }

    public function testClearingFromFieldKeepsTo(): void
    {
        $jsonValue = json_encode(['from' => '2024-01-15', 'to' => '2024-12-31']);

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:DateRangeFilter',
            data: [
                'column' => 'createdAt',
                'value' => $jsonValue,
                'compact' => true,
            ],
        );

        // Clear from field
        $testComponent->set('from', '');

        $component = $testComponent->component();
        $this->assertSame('', $component->from);
        $this->assertSame('2024-12-31', $component->to);

        $decoded = json_decode($component->value, true);
        $this->assertNull($decoded['from']);
        $this->assertSame('2024-12-31', $decoded['to']);
    }

    public function testExternalValueUpdateDeserializesOnNextRender(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:DateRangeFilter',
            data: [
                'column' => 'createdAt',
                'value' => json_encode(['from' => '2024-01-01', 'to' => '2024-01-31']),
                'compact' => true,
            ],
        );

        // Simulate external update to value prop
        $newJsonValue = json_encode(['from' => '2024-06-01', 'to' => '2024-06-30']);
        $testComponent->set('value', $newJsonValue);

        $component = $testComponent->component();
        $this->assertSame('2024-06-01', $component->from);
        $this->assertSame('2024-06-30', $component->to);
    }

    public function testCompactModeRendersButton(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:DateRangeFilter',
            data: ['column' => 'createdAt', 'compact' => true],
        );

        $rendered = $testComponent->render()->toString();
        $this->assertStringContainsString('<button', $rendered);
        $this->assertStringContainsString('popovertarget=', $rendered);
        $this->assertStringContainsString('📅', $rendered);
    }

    public function testNonCompactModeRendersInlineInputs(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:DateRangeFilter',
            data: ['column' => 'createdAt', 'compact' => false],
        );

        $rendered = $testComponent->render()->toString();
        $this->assertStringNotContainsString('<button', $rendered);
        $this->assertStringNotContainsString('popover=', $rendered);
        $this->assertStringContainsString('type="date"', $rendered);
    }

    public function testButtonDisplaysDateRangeWhenSet(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:DateRangeFilter',
            data: [
                'column' => 'createdAt',
                'value' => json_encode(['from' => '2024-01-15', 'to' => '2024-12-31']),
                'compact' => true,
            ],
        );

        $rendered = $testComponent->render()->toString();
        // Should show the date range in the button
        $this->assertStringContainsString('Jan 15', $rendered);
        $this->assertStringContainsString('Dec 31', $rendered);
    }

    public function testInputValuesReflectDeserializedData(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:DateRangeFilter',
            data: [
                'column' => 'createdAt',
                'value' => json_encode(['from' => '2024-03-20', 'to' => '2024-09-15']),
                'compact' => false,
            ],
        );

        $rendered = $testComponent->render()->toString();
        $this->assertStringContainsString('value="2024-03-20"', $rendered);
        $this->assertStringContainsString('value="2024-09-15"', $rendered);
    }
}
