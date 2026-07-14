<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Config;

use Kachnitel\AdminBundle\Config\EntityListConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EntityListConfigTest extends TestCase
{
    #[Test]
    public function defaultValuesAreSetCorrectly(): void
    {
        $config = new EntityListConfig();

        $this->assertSame('App\\Form\\', $config->formNamespace);
        $this->assertSame('Type', $config->formSuffix);
        $this->assertSame(20, $config->defaultItemsPerPage);
        $this->assertSame([10, 20, 50, 100], $config->allowedItemsPerPage);
    }

    #[Test]
    public function formNamespaceCanBeCustomized(): void
    {
        $config = new EntityListConfig(formNamespace: 'Custom\\Form\\Namespace\\');

        $this->assertSame('Custom\\Form\\Namespace\\', $config->formNamespace);
    }

    #[Test]
    public function formSuffixCanBeCustomized(): void
    {
        $config = new EntityListConfig(formSuffix: 'FormType');

        $this->assertSame('FormType', $config->formSuffix);
    }

    #[Test]
    public function defaultItemsPerPageCanBeCustomized(): void
    {
        $config = new EntityListConfig(defaultItemsPerPage: 50);

        $this->assertSame(50, $config->defaultItemsPerPage);
    }

    #[Test]
    public function allowedItemsPerPageCanBeCustomized(): void
    {
        $config = new EntityListConfig(allowedItemsPerPage: [5, 10, 25, 50]);

        $this->assertSame([5, 10, 25, 50], $config->allowedItemsPerPage);
    }
}
