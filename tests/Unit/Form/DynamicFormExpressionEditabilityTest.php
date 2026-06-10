<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Form;

use Kachnitel\AdminBundle\Attribute\AdminColumn;
use Kachnitel\AdminBundle\Form\DynamicEntityFormType;
use Kachnitel\AdminBundle\Form\DoctrineFormTypeMapper;
use Kachnitel\AdminBundle\RowAction\RowActionExpressionLanguage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Tests expression evaluation in #[AdminColumn(editable: ...)] during form building.
 *
 * @covers \Kachnitel\AdminBundle\Form\DynamicEntityFormType
 * @covers \Kachnitel\AdminBundle\Form\DynamicFormEditabilityListener
 * @group dynamic-form
 * @group expressions
 */
#[CoversClass(DynamicEntityFormType::class)]
#[Group('dynamic-form')]
#[Group('expressions')]
class DynamicFormExpressionEditabilityTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

    /** @var ClassMetadata<object>&MockObject */
    private ClassMetadata $metadata;

    /** @var DoctrineFormTypeMapper&MockObject */
    private DoctrineFormTypeMapper $mapper;

    /** @var RowActionExpressionLanguage&MockObject */
    private RowActionExpressionLanguage $expressionLanguage;

    /** @var AuthorizationCheckerInterface&MockObject */
    private AuthorizationCheckerInterface $authorizationChecker;

    /** @var FormBuilderInterface<mixed>&MockObject */
    private FormBuilderInterface $builder;

    protected function setUp(): void
    {
        $this->em                  = $this->createMock(EntityManagerInterface::class);
        $this->metadata            = $this->createMock(ClassMetadata::class);
        $this->mapper              = $this->createMock(DoctrineFormTypeMapper::class);
        $this->expressionLanguage  = $this->createMock(RowActionExpressionLanguage::class);
        $this->authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $this->builder             = $this->createMock(FormBuilderInterface::class);

        $this->em->method('getClassMetadata')->willReturn($this->metadata);
        $this->metadata->method('getSingleIdentifierFieldName')->willReturn('id');
    }

    private function createFormType(): DynamicEntityFormType
    {
        return new DynamicEntityFormType(
            $this->em,
            $this->mapper,
            $this->expressionLanguage,
            $this->authorizationChecker,
        );
    }

    /**
     * When #[AdminColumn(editable: 'expression')] is used on a field and entity_instance
     * is passed during form building, the expression must be evaluated at build time.
     *
     * @test
     */
    public function expressionIsEvaluatedDuringFormBuildingWhenEntityInstanceProvided(): void
    {
        $entity = new ExpressionTestEntity();
        $entity->enabled = true;

        $this->metadata->method('getFieldNames')->willReturn(['statusField']);
        $this->metadata->method('getAssociationNames')->willReturn([]);

        $this->mapper->method('getFieldConfig')->willReturn([
            'type'    => 'Symfony\Component\Form\Extension\Core\Type\TextType',
            'options' => [],
        ]);

        // With expression evaluating to true, field should be added
        $this->expressionLanguage->method('evaluate')
            ->with('entity.enabled', $entity, $this->authorizationChecker)
            ->willReturn(true);

        $this->builder->expects($this->once())
            ->method('add')
            ->with('statusField', 'Symfony\Component\Form\Extension\Core\Type\TextType', $this->anything());

        $this->builder->expects($this->once())
            ->method('addEventListener');

        $formType = $this->createFormType();
        $formType->buildForm($this->builder, [
            'entity_class'      => ExpressionTestEntity::class,
            'entity_instance'   => $entity,
            'is_root'           => true,
        ]);
    }

    /**
     * When #[AdminColumn(editable: false)] is used (explicit false), the field must be
     * blocked (excluded) regardless of entity_instance.
     *
     * @test
     */
    public function explicitFalseBlocksFieldRegardlessOfEntity(): void
    {
        $entity = new ExpressionTestEntity();
        $entity->enabled = true;

        $this->metadata->method('getFieldNames')->willReturn(['blockedField']);
        $this->metadata->method('getAssociationNames')->willReturn([]);

        // blockedField has #[AdminColumn(editable: false)], so mapper should not be called
        $this->mapper->expects($this->never())->method('getFieldConfig');

        $this->builder->expects($this->never())->method('add');
        $this->builder->expects($this->once())->method('addEventListener');

        $formType = $this->createFormType();
        $formType->buildForm($this->builder, [
            'entity_class'      => ExpressionTestEntity::class,
            'entity_instance'   => $entity,
            'is_root'           => true,
        ]);
    }

    /**
     * When #[AdminColumn(editable: true)] is used (explicit true), the field must be
     * included regardless of entity_instance.
     *
     * @test
     */
    public function explicitTrueIncludesFieldRegardlessOfEntity(): void
    {
        $entity = new ExpressionTestEntity();
        $entity->enabled = false;

        $this->metadata->method('getFieldNames')->willReturn(['forcedField']);
        $this->metadata->method('getAssociationNames')->willReturn([]);

        $this->mapper->method('getFieldConfig')->willReturn([
            'type'    => 'Symfony\Component\Form\Extension\Core\Type\TextType',
            'options' => [],
        ]);

        // Explicit true means field is included even though entity state might suggest otherwise
        $this->builder->expects($this->once())
            ->method('add')
            ->with('forcedField', 'Symfony\Component\Form\Extension\Core\Type\TextType', $this->anything());

        $this->builder->expects($this->once())->method('addEventListener');

        $formType = $this->createFormType();
        $formType->buildForm($this->builder, [
            'entity_class'      => ExpressionTestEntity::class,
            'entity_instance'   => $entity,
            'is_root'           => true,
        ]);
    }

    /**
     * When no entity_instance is provided (null), expressions cannot be evaluated
     * at build time. The field should still be included at this stage; the
     * DynamicFormEditabilityListener will remove it later when data is bound.
     *
     * @test
     */
    public function expressionFieldIsIncludedWhenNoEntityInstanceProvided(): void
    {
        $this->metadata->method('getFieldNames')->willReturn(['statusField']);
        $this->metadata->method('getAssociationNames')->willReturn([]);

        $this->mapper->method('getFieldConfig')->willReturn([
            'type'    => 'Symfony\Component\Form\Extension\Core\Type\TextType',
            'options' => [],
        ]);

        // No entity instance → expression cannot be evaluated, so field is included
        $this->expressionLanguage->expects($this->never())->method('evaluate');

        $this->builder->expects($this->once())
            ->method('add')
            ->with('statusField', 'Symfony\Component\Form\Extension\Core\Type\TextType', $this->anything());

        $this->builder->expects($this->once())->method('addEventListener');

        $formType = $this->createFormType();
        $formType->buildForm($this->builder, [
            'entity_class'      => ExpressionTestEntity::class,
            'entity_instance'   => null, // no entity available
            'is_root'           => true,
        ]);
    }
}

// ── Test entity with various editability rules ──────────────────────────────

class ExpressionTestEntity
{
    #[AdminColumn(editable: 'entity.enabled')]
    public string $statusField = '';

    #[AdminColumn(editable: false)]
    public string $blockedField = '';

    #[AdminColumn(editable: true)]
    public string $forcedField = '';

    public bool $enabled = false;
}
