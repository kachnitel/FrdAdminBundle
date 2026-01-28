<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Kachnitel\AdminBundle\Command\DebugFiltersCommand;
use Kachnitel\AdminBundle\Service\FilterMetadataProvider;
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
class DebugFiltersCommandTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

    /** @var FilterMetadataProvider&MockObject */
    private FilterMetadataProvider $filterMetadataProvider;

    /** @var ClassMetadataFactory&MockObject */
    private ClassMetadataFactory $metadataFactory;

    private DebugFiltersCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->filterMetadataProvider = $this->createMock(FilterMetadataProvider::class);
        $this->metadataFactory = $this->createMock(ClassMetadataFactory::class);

        $this->em->method('getMetadataFactory')->willReturn($this->metadataFactory);

        $this->command = new DebugFiltersCommand($this->em, $this->filterMetadataProvider);
        $this->commandTester = new CommandTester($this->command);
    }

    /**
     * @test
     */
    public function commandCanBeInstantiated(): void
    {
        $this->assertInstanceOf(DebugFiltersCommand::class, $this->command);
    }

    /**
     * @test
     */
    public function commandHasCorrectName(): void
    {
        $this->assertSame('admin:debug:filters', $this->command->getName());
    }

    /**
     * @test
     */
    public function commandHasDescription(): void
    {
        $this->assertNotEmpty($this->command->getDescription());
    }

    /**
     * @test
     */
    public function listEntitiesWithNoEntitiesFound(): void
    {
        $this->metadataFactory->method('getAllMetadata')->willReturn([]);

        $this->commandTester->execute([]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('No entities found', $output);
    }

    /**
     * @test
     */
    public function showErrorForNonExistentEntity(): void
    {
        $this->metadataFactory->method('getAllMetadata')->willReturn([]);

        $this->commandTester->execute(['entityClass' => 'NonExistentEntity']);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('not found', $output);
    }

    /**
     * @test
     */
    public function commandConfiguredWithEntityClassArgument(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasArgument('entityClass'));
        $this->assertFalse($definition->getArgument('entityClass')->isRequired());
    }

    /**
     * @test
     */
    public function commandConfiguredWithAllOption(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('all'));
        $this->assertSame('a', $definition->getOption('all')->getShortcut());
    }
}
