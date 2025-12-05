<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/**
 * Base test case for component tests with common database setup/teardown.
 */
abstract class ComponentTestCase extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected EntityManagerInterface $em;

    protected static function getKernelClass(): string
    {
        return ComponentTestKernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->em = $container->get('doctrine')->getManager();

        // Create database schema for all test entities
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->em);
        $schemaTool->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        // Drop database schema after each test
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->em);
        $schemaTool->dropSchema($metadata);

        parent::tearDown();

        restore_exception_handler();
    }
}
