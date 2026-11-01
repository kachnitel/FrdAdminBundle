<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Form\Extension;

use Kachnitel\AdminBundle\Form\Extension\ColumnPermissionFormExtension;
use Kachnitel\AdminBundle\Service\ColumnPermissionService;
use Kachnitel\AdminBundle\Tests\Fixtures\ColumnPermissionFormEntity;
use Kachnitel\DynamicFormBundle\Form\DynamicEntityFormType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Forms;

/**
 * Enforcement lives in AdminColumnEditabilityResolver; the extension must never
 * mutate the generated form (see ColumnPermissionCreateWarningTest for diagnostics).
 */
#[Group('column-permission-form')]
final class ColumnPermissionFormExtensionTest extends TestCase
{
    public function testExtendsDynamicEntityFormType(): void
    {
        self::assertSame([DynamicEntityFormType::class], [...ColumnPermissionFormExtension::getExtendedTypes()]);
    }

    public function testNeverRemovesOrDisablesFields(): void
    {
        $permissions = $this->createStub(ColumnPermissionService::class);
        $permissions->method('getColumnPermissionMap')->willReturn(['salary' => [], 'ssn' => []]);
        $permissions->method('canPerformAction')->willReturn(false);
        $builder = $this->builder();

        (new ColumnPermissionFormExtension($permissions, true, new NullLogger()))
            ->buildForm($builder, ['entity_class' => ColumnPermissionFormEntity::class]);

        foreach (['name', 'salary', 'ssn'] as $field) {
            self::assertTrue($builder->has($field));
            self::assertFalse($builder->get($field)->getDisabled());
        }
    }

    public function testMissingEntityClassIsIgnored(): void
    {
        $extension = new ColumnPermissionFormExtension(
            $this->createStub(ColumnPermissionService::class),
            true,
            new NullLogger(),
        );

        $extension->buildForm($this->builder(), []);

        $this->addToAssertionCount(1);
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
