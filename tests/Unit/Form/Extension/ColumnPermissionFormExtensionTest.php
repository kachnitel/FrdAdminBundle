<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Form\Extension;

use Kachnitel\AdminBundle\Form\Extension\ColumnPermissionFormExtension;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\ColumnPermissionService;
use Kachnitel\AdminBundle\Tests\Fixtures\ColumnPermissionFormEntity;
use Kachnitel\DynamicFormBundle\Form\DynamicEntityFormType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Forms;

#[Group('column-permission-form')]
final class ColumnPermissionFormExtensionTest extends TestCase
{
    /** @var ColumnPermissionService&Stub */
    private ColumnPermissionService $permissions;

    protected function setUp(): void
    {
        $this->permissions = $this->createStub(ColumnPermissionService::class);
        $this->permissions->method('getColumnPermissionMap')->willReturn([
            'salary' => [],
            'ssn' => [],
        ]);
    }

    public function testExtendsDynamicEntityFormType(): void
    {
        self::assertSame([DynamicEntityFormType::class], [...ColumnPermissionFormExtension::getExtendedTypes()]);
    }

    public function testFieldsWithoutPermissionAreUntouched(): void
    {
        $this->grant([]);
        $builder = $this->builder();

        $this->extension()->buildForm($builder, ['data_class' => ColumnPermissionFormEntity::class]);

        self::assertTrue($builder->has('name'));
        self::assertFalse($builder->get('name')->getDisabled());
    }

    public function testShowDeniedRemovesField(): void
    {
        $this->grant([]);
        $builder = $this->builder();

        $this->extension()->buildForm($builder, ['data_class' => ColumnPermissionFormEntity::class]);

        self::assertFalse($builder->has('salary'));
        self::assertFalse($builder->has('ssn'));
    }

    public function testEntityClassFallbackIsUsedWhenDataClassIsMissing(): void
    {
        $this->grant([]);
        $builder = $this->builder();

        $this->extension()->buildForm($builder, ['entity_class' => ColumnPermissionFormEntity::class]);

        self::assertFalse($builder->has('salary'));
        self::assertFalse($builder->has('ssn'));
    }

    public function testShowGrantedEditDeniedDisablesField(): void
    {
        $this->grant([AdminEntityVoter::ADMIN_SHOW]);
        $builder = $this->builder();

        $this->extension()->buildForm($builder, ['data_class' => ColumnPermissionFormEntity::class]);

        self::assertTrue($builder->has('salary'));
        self::assertTrue($builder->get('salary')->getDisabled());
    }

    public function testShowAndEditGrantedLeavesFieldEditable(): void
    {
        $this->grant([AdminEntityVoter::ADMIN_SHOW, AdminEntityVoter::ADMIN_EDIT]);
        $builder = $this->builder();

        $this->extension()->buildForm($builder, ['data_class' => ColumnPermissionFormEntity::class]);

        self::assertFalse($builder->get('salary')->getDisabled());
    }

    public function testMissingEntityClassMetadataThrows(): void
    {
        $builder = $this->builder();

        $this->expectException(\InvalidArgumentException::class);
        $this->extension()->buildForm($builder, []);
    }

    /** @param list<string> $actions granted actions for every protected column */
    private function grant(array $actions): void
    {
        $this->permissions->method('canPerformAction')->willReturnCallback(
            static fn (string $class, string $property, string $action): bool => \in_array($action, $actions, true),
        );
    }

    private function extension(): ColumnPermissionFormExtension
    {
        return new ColumnPermissionFormExtension($this->permissions);
    }

    /**
     * @return FormBuilderInterface<mixed>
     */
    private function builder(): FormBuilderInterface
    {
        $builder = Forms::createFormFactoryBuilder()->getFormFactory()
            ->createBuilder(FormType::class, null, ['data_class' => ColumnPermissionFormEntity::class]);
        foreach (['name', 'salary', 'ssn'] as $field) {
            $builder->add($field, TextType::class);
        }

        return $builder;
    }
}
