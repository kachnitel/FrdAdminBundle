<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\Tests\Fixtures\ColumnPermissionFormEntity;
use Kachnitel\AdminBundle\Twig\Components\InlineEntityForm;
use Kachnitel\DynamicFormBundle\Form\DynamicEntityFormType;
use PHPUnit\Framework\Attributes as PHPUnit;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * #[ColumnPermission] on the "+ Add" dialog (InlineEntityForm), which builds a new,
 * data-less generated form: the same resolver contract must apply as in AdminEntityForm.
 */
#[PHPUnit\Group('column-permission-form')]
#[PHPUnit\Group('inline-add')]
final class ColumnPermissionInlineFormTest extends ComponentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        self::getContainer()->get('request_stack')->push($request);
    }

    #[PHPUnit\Test]
    public function showDeniedFieldsAreNotRendered(): void
    {
        $html = $this->render(new InMemoryUser('user', 'test', ['ROLE_USER']));

        self::assertMatchesRegularExpression('/\[name]/', $html, 'control: unrestricted field is rendered');
        self::assertDoesNotMatchRegularExpression('/\[salary]/', $html);
        self::assertDoesNotMatchRegularExpression('/\[ssn]/', $html);
    }

    #[PHPUnit\Test]
    public function editDeniedFieldIsRenderedDisabled(): void
    {
        $html = $this->render(new InMemoryUser('hr', 'test', ['ROLE_HR']));

        self::assertMatchesRegularExpression('/<input[^>]*\[salary][^>]*disabled/', $html);
        self::assertDoesNotMatchRegularExpression('/<input[^>]*\[ssn][^>]*disabled/', $html, 'control: ssn is editable');
        self::assertMatchesRegularExpression('/\[ssn]/', $html);
    }

    #[PHPUnit\Test]
    public function fieldsAreEditableWithBothRoles(): void
    {
        $html = $this->render(new InMemoryUser('hr_edit', 'test', ['ROLE_HR', 'ROLE_HR_EDIT']));

        self::assertMatchesRegularExpression('/\[salary]/', $html);
        self::assertDoesNotMatchRegularExpression('/<input[^>]*\[salary][^>]*disabled/', $html);
    }

    private function render(InMemoryUser $user): string
    {
        return (string) $this->createLiveComponent(
            name: InlineEntityForm::class,
            data: [
                'entityClass'   => ColumnPermissionFormEntity::class,
                'formTypeClass' => DynamicEntityFormType::class,
            ],
        )->actingAs($user)->render();
    }
}
