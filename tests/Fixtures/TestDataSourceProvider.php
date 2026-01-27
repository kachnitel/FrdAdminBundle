<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\DataSource\DataSourceInterface;
use Kachnitel\AdminBundle\DataSource\DataSourceProviderInterface;

/**
 * Test provider that provides custom data sources for testing.
 */
class TestDataSourceProvider implements DataSourceProviderInterface
{
    private CustomTemplateDataSource $customTemplateDataSource;
    private TestEntityDataSource $testEntityDataSource;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->customTemplateDataSource = new CustomTemplateDataSource();
        $this->testEntityDataSource = new TestEntityDataSource();
        $this->testEntityDataSource->setEntityManager($entityManager);
    }

    /**
     * @return iterable<DataSourceInterface>
     */
    public function getDataSources(): iterable
    {
        yield $this->customTemplateDataSource;
        yield $this->testEntityDataSource;
    }

    public function getCustomTemplateDataSource(): CustomTemplateDataSource
    {
        return $this->customTemplateDataSource;
    }

    public function getTestEntityDataSource(): TestEntityDataSource
    {
        return $this->testEntityDataSource;
    }
}
