<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Form\Extension;

use Kachnitel\AdminBundle\Form\Extension\ColumnPermissionFormExtension;
use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Kachnitel\AdminBundle\Service\ColumnPermissionService;
use Kachnitel\AdminBundle\Tests\Fixtures\ColumnPermissionFormEntity;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\Event\PostSubmitEvent;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\ConstraintViolation;

/**
 * Dev-only diagnostics: a #[ColumnPermission] that removes/disables a column which then
 * fails validation on CREATE means users lacking the role can never create the entity.
 * The extension must say so (debug only) instead of leaving a silently unsaveable form.
 *
 * Validation is simulated by a POST_SUBMIT listener (priority 0, where Symfony's
 * ValidationListener runs); the extension's own listener runs after it.
 */
#[Group('column-permission-form')]
final class ColumnPermissionCreateWarningTest extends TestCase
{
    /** @var ColumnPermissionService&Stub */
    private ColumnPermissionService $permissions;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->permissions = $this->createStub(ColumnPermissionService::class);
        $this->permissions->method('getColumnPermissionMap')->willReturn(['salary' => [], 'ssn' => []]);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    #[Test]
    public function warnsWhenDisabledFieldFailsValidationOnCreate(): void
    {
        $this->grant([AdminEntityVoter::ADMIN_SHOW]); // visible, not editable -> disabled
        $this->logger->expects($this->once())->method('warning')
            ->with($this->stringContains('salary'), $this->arrayHasKey('property'));

        $form = $this->buildForm(debug: true, data: null, fieldErrors: ['salary']);
        $form->submit(['name' => 'x']);
    }

    #[Test]
    public function warnsWhenRemovedFieldFailsValidationOnCreate(): void
    {
        $this->grant([]); // show denied -> removed
        $this->logger->expects($this->once())->method('warning')
            ->with($this->stringContains('ssn'), $this->arrayHasKey('property'));

        $form = $this->buildForm(debug: true, data: null, rootViolationPaths: ['data.ssn']);
        $form->submit(['name' => 'x']);
    }

    #[Test]
    public function staysSilentOutsideDebug(): void
    {
        $this->grant([AdminEntityVoter::ADMIN_SHOW]);
        $this->logger->expects($this->never())->method('warning');

        $form = $this->buildForm(debug: false, data: null, fieldErrors: ['salary']);
        $form->submit(['name' => 'x']);
    }

    #[Test]
    public function staysSilentWhenEditingAnExistingEntity(): void
    {
        $this->grant([AdminEntityVoter::ADMIN_SHOW]);
        $this->logger->expects($this->never())->method('warning');

        $form = $this->buildForm(debug: true, data: new ColumnPermissionFormEntity(), fieldErrors: ['salary']);
        $form->submit(['name' => 'x']);
    }

    #[Test]
    public function staysSilentWhenRestrictedFieldHasNoValidationError(): void
    {
        $this->grant([AdminEntityVoter::ADMIN_SHOW]);
        $this->logger->expects($this->never())->method('warning');

        $form = $this->buildForm(debug: true, data: null);
        $form->submit(['name' => 'x']);
    }

    #[Test]
    public function ignoresViolationsOnUnrestrictedFields(): void
    {
        $this->grant([]);
        $this->logger->expects($this->never())->method('warning');

        $form = $this->buildForm(debug: true, data: null, rootViolationPaths: ['data.name']);
        $form->submit(['name' => 'x']);
    }

    /** @param list<string> $actions actions granted for every protected column */
    private function grant(array $actions): void
    {
        $this->permissions->method('canPerformAction')->willReturnCallback(
            static fn (string $class, string $property, string $action): bool => \in_array($action, $actions, true),
        );
    }

    /**
     * @param list<string> $fieldErrors        children that receive a validation error
     * @param list<string> $rootViolationPaths property paths reported on the root form
     * @return \Symfony\Component\Form\FormInterface<mixed>
     */
    private function buildForm(bool $debug, ?object $data, array $fieldErrors = [], array $rootViolationPaths = []): \Symfony\Component\Form\FormInterface
    {
        $builder = Forms::createFormFactoryBuilder()->getFormFactory()
            ->createBuilder(FormType::class, $data, ['data_class' => ColumnPermissionFormEntity::class]);
        foreach (['name', 'salary', 'ssn'] as $field) {
            $builder->add($field, TextType::class);
        }

        (new ColumnPermissionFormExtension($this->permissions, $debug, $this->logger))
            ->buildForm($builder, ['data_class' => ColumnPermissionFormEntity::class]);

        $this->simulateValidation($builder, $fieldErrors, $rootViolationPaths);

        return $builder->getForm();
    }

    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param list<string>                $fieldErrors
     * @param list<string>                $rootViolationPaths
     */
    private function simulateValidation(FormBuilderInterface $builder, array $fieldErrors, array $rootViolationPaths): void
    {
        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (PostSubmitEvent $event) use ($fieldErrors, $rootViolationPaths): void {
            $form = $event->getForm();
            foreach ($fieldErrors as $field) {
                if ($form->has($field)) {
                    $form->get($field)->addError(new FormError('This value should not be blank.'));
                }
            }
            foreach ($rootViolationPaths as $path) {
                $violation = new ConstraintViolation('This value should not be blank.', null, [], null, $path, null);
                $form->addError(new FormError('This value should not be blank.', null, [], null, $violation));
            }
        }, 0);
    }
}
