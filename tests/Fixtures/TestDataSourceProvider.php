<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Kachnitel\AdminBundle\DataSource\DataSourceInterface;
use Kachnitel\AdminBundle\DataSource\DataSourceProviderInterface;

/**
 * Test provider that provides custom data sources for testing.
 */
class TestDataSourceProvider implements DataSourceProviderInterface
{
    private CustomTemplateDataSource $customTemplateDataSource;

    public function __construct()
    {
        $this->customTemplateDataSource = new CustomTemplateDataSource();
    }

    /**
     * @return iterable<DataSourceInterface>
     */
    public function getDataSources(): iterable
    {
        yield $this->customTemplateDataSource;
    }

    public function getCustomTemplateDataSource(): CustomTemplateDataSource
    {
        return $this->customTemplateDataSource;
    }
}
