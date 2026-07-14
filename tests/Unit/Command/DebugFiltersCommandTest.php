<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Kachnitel\AdminBundle\Command\DebugFiltersCommand;
use Kachnitel\AdminBundle\Service\FilterMetadataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Unit tests for DebugFiltersCommand.
 *
 * Note: Tests that require actual entity classes are in the functional tests
 * because the command uses ReflectionClass which requires real classes.
 *
 * @see \Kachnitel\AdminBundle\Tests\Functional\DebugFiltersCommandTest
 */
#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class DebugFiltersCommandTest extends TestCase
{
    /** @var ClassMetadataFactory&MockObject */
    private ClassMetadataFactory $metadataFactory;

    private DebugFiltersCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $filterMetadataProvider = $this->createStub(FilterMetadataProvider::class);
        $this->metadataFactory = $this->createMock(ClassMetadataFactory::class);

        $em->method('getMetadataFactory')->willReturn($this->metadataFactory);

        $this->command = new DebugFiltersCommand($em, $filterMetadataProvider);
        $this->commandTester = new CommandTester($this->command);
    }

    #[Test]
    public function commandHasCorrectName(): void
    {
        $this->assertSame('admin:debug:filters', $this->command->getName());
    }

    #[Test]
    public function commandHasDescription(): void
    {
        $this->assertNotEmpty($this->command->getDescription());
    }

    #[Test]
    public function listEntitiesWithNoEntitiesFound(): void
    {
        $this->metadataFactory->method('getAllMetadata')->willReturn([]);

        $this->commandTester->execute([]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('No entities found', $output);
    }

    #[Test]
    public function showErrorForNonExistentEntity(): void
    {
        $this->metadataFactory->method('getAllMetadata')->willReturn([]);

        $this->commandTester->execute(['entityClass' => 'NonExistentEntity']);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('not found', $output);
    }

    #[Test]
    public function commandConfiguredWithEntityClassArgument(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasArgument('entityClass'));
        $this->assertFalse($definition->getArgument('entityClass')->isRequired());
    }

    #[Test]
    public function commandConfiguredWithAllOption(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('all'));
        $this->assertSame('a', $definition->getOption('all')->getShortcut());
    }
}
