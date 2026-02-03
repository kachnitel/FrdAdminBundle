<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\Tests\Fixtures\AllTypesEntity;

/**
 * Tests that all Doctrine field types render without errors in the entity list preview.
 *
 * This catches issues like DateTimeImmutable "could not be converted to string"
 * when a type-specific template is missing and the fallback tries {{ value }}.
 */
class TypePreviewRenderTest extends ComponentTestCase
{
    public function testAllTypePreviewTemplatesRenderWithoutErrors(): void
    {
        $entity = new AllTypesEntity();
        $entity->setName('Type Test');

        $this->em->persist($entity);
        $this->em->flush();

        $testComponent = $this->createLiveComponent(
            name: 'K:Admin:EntityList',
            data: ['entityClass' => AllTypesEntity::class, 'entityShortClass' => 'AllTypesEntity'],
        );

        $rendered = (string) $testComponent->render();

        // String
        $this->assertStringContainsString('Type Test', $rendered);
        // Boolean
        $this->assertStringContainsString('Yes', $rendered);
        // Date
        $this->assertStringContainsString('2000-01-15', $rendered);
        // DateTime
        $this->assertStringContainsString('2024-06-01 12:30:00', $rendered);
        // DateTimeImmutable
        $this->assertStringContainsString('2024-06-15 14:00:00', $rendered);
        // DateImmutable (date-only format)
        $this->assertStringContainsString('2025-12-31', $rendered);
        // Time
        $this->assertStringContainsString('14:30:00', $rendered);
        // TimeImmutable
        $this->assertStringContainsString('09:15:00', $rendered);
        // DateTimeTz (includes timezone)
        $this->assertStringContainsString('2024-03-20 10:00:00', $rendered);
        // DateTimeTzImmutable (includes timezone)
        $this->assertStringContainsString('2024-12-01 18:00:00', $rendered);
        // DateInterval
        $this->assertStringContainsString('2h', $rendered);
        $this->assertStringContainsString('30m', $rendered);
        // Enum
        $this->assertStringContainsString('active', $rendered);
    }
}
