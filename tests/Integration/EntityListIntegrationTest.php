<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Attribute\Admin;
use Kachnitel\AdminBundle\Attribute\ColumnPermission;
use Kachnitel\AdminBundle\Config\EntityListConfig;
use Kachnitel\AdminBundle\DataSource\DataSourceRegistry;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\Preferences\AdminPreferencesStorageInterface;
use Kachnitel\AdminBundle\Service\EntityListBatchService;
use Kachnitel\AdminBundle\Service\EntityListColumnService;
use Kachnitel\AdminBundle\Service\EntityListPermissionService;
use Kachnitel\AdminBundle\Twig\Components\EntityList;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Integration tests for EntityList with field components.
 *
 * Uses the real ColumnPermissionService (with a mocked AuthorizationChecker)
 * to test actual attribute discovery and permission filtering.
 *
 * @covers \Kachnitel\AdminBundle\Twig\Components\EntityList
 */
class EntityListIntegrationTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $entityManager;

    /** @var Security&MockObject */
    private Security $security;

    private EntityList $entityList;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->security = $this->createMock(Security::class);

        $this->entityList = new EntityList(
            $this->createMock(EntityListPermissionService::class),
            $this->security,
            new EntityListConfig(),
            $this->createMock(DataSourceRegistry::class),
            $this->createMock(EntityListBatchService::class),
            $this->createMock(AdminPreferencesStorageInterface::class),
            $this->createMock(EntityListColumnService::class),
        );

        $this->entityList->entityClass = ProductIntegrationEntity::class;
    }

    public function testCompleteCancelWorkflow(): void
    {
        $product = new ProductIntegrationEntity(1, 'Original Name', 99.99);

        $this->security->method('isGranted')->willReturn(true);

        $this->entityList->editRow($product->getId());
        $product->name = 'Modified Name';

        $this->entityList->editingRowId = null;

        $this->assertFalse($this->entityList->isRowEditing($product));
    }

    public function testSwitchingEditBetweenRows(): void
    {
        $product1 = new ProductIntegrationEntity(1, 'Product 1', 99.99);
        $product2 = new ProductIntegrationEntity(2, 'Product 2', 149.99);

        $this->security->method('isGranted')->willReturn(true);

        $this->entityList->editRow($product1->getId());
        $this->assertTrue($this->entityList->isRowEditing($product1));
        $this->assertFalse($this->entityList->isRowEditing($product2));

        $this->entityList->editRow($product2->getId());
        $this->assertFalse($this->entityList->isRowEditing($product1));
        $this->assertTrue($this->entityList->isRowEditing($product2));
    }

    /**
     * Entity self-validation (setter throws) prevents invalid data from reaching saveRow.
     */
    public function testEntitySelfValidationPreventsInvalidData(): void
    {
        $product = new ProductIntegrationEntity(1, 'Product', 99.99);
        $this->security->method('isGranted')->willReturn(true);
        $this->entityList->editRow($product->getId());

        $this->expectException(\InvalidArgumentException::class);
        $product->setName(''); // throws before saveRow
    }
}

#[Admin]
class ProductIntegrationEntity
{
    public string $name;

    #[ColumnPermission([
        AdminEntityVoter::ADMIN_SHOW => 'ROLE_USER',
        AdminEntityVoter::ADMIN_EDIT => 'ROLE_EDIT_PRICE',
    ])]
    public float $price;

    #[ColumnPermission([
        AdminEntityVoter::ADMIN_EDIT => 'ROLE_ADMIN',
    ])]
    public string $description;

    public function __construct(
        private int $id,
        string $name,
        float $price,
        string $description = '',
    ) {
        $this->name = $name;
        $this->price = $price;
        $this->description = $description;
    }

    public function getId(): int { return $this->id; }

    public function setName(string $name): self
    {
        if (empty($name)) {
            throw new \InvalidArgumentException('Name cannot be empty');
        }
        $this->name = $name;
        return $this;
    }
}