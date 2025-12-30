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

    public function testValuePropertyChangeTriggersDeserialization(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:DateRangeFilter',
            data: ['column' => 'createdAt', 'compact' => true],
        );

        // Initially empty
        $component = $testComponent->component();
        $this->assertSame('', $component->from);
        $this->assertSame('', $component->to);

        // Update value prop (simulating URL parameter deserialization)
        $newJsonValue = json_encode(['from' => '2025-06-10', 'to' => '2025-12-20']);
        $testComponent->set('value', $newJsonValue);

        $component = $testComponent->component();
        // Should be deserialized by onUpdated callback
        $this->assertSame('2025-06-10', $component->from);
        $this->assertSame('2025-12-20', $component->to);
    }

    public function testValuePropertyChangeWithUrlEncodedJson(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:DateRangeFilter',
            data: ['column' => 'dateCreated', 'compact' => true],
        );

        // Simulate URL parameter: columnFilters%5BdateCreated%5D=%7B%22from%22%3A%222025-12-11%22%2C%22to%22%3A%222025-12-11%22%7D
        // Which decodes to: {"from":"2025-12-11","to":"2025-12-11"}
        $urlEncodedValue = json_encode(['from' => '2025-12-11', 'to' => '2025-12-11']);
        $testComponent->set('value', $urlEncodedValue);

        $component = $testComponent->component();
        $this->assertSame('2025-12-11', $component->from);
        $this->assertSame('2025-12-11', $component->to);

        // Button should display single date when both are the same
        $rendered = $testComponent->render()->toString();
        $this->assertStringContainsString('Dec 11', $rendered);
        $this->assertStringNotContainsString('onwards', $rendered);
        $this->assertStringNotContainsString('Until', $rendered);
    }

    public function testButtonDisplaysOnwardsWhenOnlyFromSet(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:DateRangeFilter',
            data: [
                'column' => 'createdAt',
                'value' => json_encode(['from' => '2024-06-01', 'to' => null]),
                'compact' => true,
            ],
        );

        $rendered = $testComponent->render()->toString();
        $this->assertStringContainsString('Jun 01', $rendered);
        $this->assertStringContainsString('onwards', $rendered);
    }

    public function testButtonDisplaysUntilWhenOnlyToSet(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:DateRangeFilter',
            data: [
                'column' => 'createdAt',
                'value' => json_encode(['from' => null, 'to' => '2024-12-31']),
                'compact' => true,
            ],
        );

        $rendered = $testComponent->render()->toString();
        $this->assertStringContainsString('Until', $rendered);
        $this->assertStringContainsString('Dec 31', $rendered);
    }

    public function testButtonDisplaysRangeWhenBothDatesSetAndDifferent(): void
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
        $this->assertStringContainsString('Jan 15', $rendered);
        $this->assertStringContainsString('Dec 31', $rendered);
        $this->assertStringContainsString('–', $rendered);
    }

    public function testValuePropChangeDoesNotTriggerFilterEmit(): void
    {
        // When value is set externally, it should only deserialize, not emit back up
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:DateRangeFilter',
            data: ['column' => 'createdAt', 'compact' => true],
        );

        $jsonValue = json_encode(['from' => '2024-01-15', 'to' => '2024-12-31']);
        $testComponent->set('value', $jsonValue);

        $component = $testComponent->component();
        // The component should be deserialized correctly
        $this->assertSame('2024-01-15', $component->from);
        $this->assertSame('2024-12-31', $component->to);
        // And the value should be preserved
        $this->assertSame($jsonValue, $component->value);
    }

    public function testEmptyStringValueDeserializesToEmptyFromTo(): void
    {
        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:DateRangeFilter',
            data: [
                'column' => 'createdAt',
                'value' => json_encode(['from' => '2024-01-15', 'to' => '2024-12-31']),
                'compact' => true,
            ],
        );

        // Clear the value
        $testComponent->set('value', '');

        $component = $testComponent->component();
        $this->assertSame('', $component->from);
        $this->assertSame('', $component->to);
        $this->assertSame('', $component->value);
    }
}
