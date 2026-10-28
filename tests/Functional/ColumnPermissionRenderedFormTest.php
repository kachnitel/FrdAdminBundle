<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\Tests\Fixtures\ColumnPermissionLockedRow;
use Kachnitel\AdminBundle\Tests\Fixtures\ColumnPermissionParent;
use Kachnitel\AdminBundle\Tests\Fixtures\ColumnPermissionRow;
use Kachnitel\AdminBundle\Twig\Components\AdminEntityForm;
use Kachnitel\DynamicFormBundle\Form\DynamicEntityFormType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * Rendered-HTML coverage for #[ColumnPermission] on the generated form (AdminEntityForm):
 *
 *   - EntityTypeAddButton ("+ Add") is not rendered next to a disabled association field
 *     (templates/form/admin_compact.html.twig guard)
 *   - a disabled OneToMany collection renders no Add/Remove collection buttons
 *     (LiveCollectionType's `addCollectionItem` / `removeCollectionItem` actions)
 *
 * Each negative assertion has a positive control (an unrestricted or fully permitted field
 * of the same kind that MUST render the marker), so a changed marker cannot pass vacuously.
 */
#[Group('column-permission-form')]
#[Group('collections')]
#[Group('inline-add')]
final class ColumnPermissionRenderedFormTest extends ComponentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // CSRF token storage requires a session on the request stack.
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);
        self::getContainer()->get('request_stack')->push($request);
    }

    #[Test]
    public function addButtonIsRenderedForEditableAssociationsOnly(): void
    {
        $html = $this->renderEditForm(['ROLE_HR']);

        $fields = $this->addButtonFields($html);
        self::assertTrue($this->hasField($fields, 'owner'), 'SHOW-only restricted association stays editable');
        self::assertTrue($this->hasField($fields, 'hiddenTags'));
        self::assertFalse($this->hasField($fields, 'reviewer'), 'disabled ManyToOne must have no + Add button');
        self::assertFalse($this->hasField($fields, 'lockedTags'), 'disabled ManyToMany must have no + Add button');
    }

    #[Test]
    public function addButtonIsRenderedForEveryAssociationWhenFullyPermitted(): void
    {
        $fields = $this->addButtonFields($this->renderEditForm(['ROLE_HR', 'ROLE_HR_EDIT']));

        foreach (['owner', 'reviewer', 'hiddenTags', 'lockedTags'] as $field) {
            self::assertTrue($this->hasField($fields, $field), $field);
        }
    }

    #[Test]
    public function disabledCollectionRendersNoAddOrRemoveButtons(): void
    {
        $names = $this->collectionButtonNames($this->renderEditForm(['ROLE_HR']));

        self::assertNotEmpty($this->matching($names, 'rows'), 'control: unrestricted collection renders its buttons');
        self::assertSame([], $this->matching($names, 'lockedRows'), 'disabled collection must render no Add/Remove buttons');
    }

    #[Test]
    public function permittedRestrictedCollectionRendersItsButtons(): void
    {
        $names = $this->collectionButtonNames($this->renderEditForm(['ROLE_HR', 'ROLE_HR_EDIT']));

        self::assertNotEmpty($this->matching($names, 'lockedRows'));
    }

    /**
     * Logs in as a user from TestKernel's provider carrying the given roles, via actingAs().
     * A token set directly on the token storage did not reach the render (every restricted field
     * was treated as ADMIN_SHOW-denied), and a user missing from the provider can't be refreshed.
     *
     * @param list<string> $roles
     */
    private function renderEditForm(array $roles): string
    {
        $parent = (new ColumnPermissionParent())->setName('Parent');
        $parent->addRow((new ColumnPermissionRow())->setDescription('Row')->setBudget('1000'));
        $parent->addLockedRow((new ColumnPermissionLockedRow())->setDescription('Locked row'));
        $this->em->persist($parent);
        $this->em->flush();

        return (string) $this->createLiveComponent(
            name: AdminEntityForm::class,
            data: [
                'entityClass'   => ColumnPermissionParent::class,
                'entityId'      => $parent->getId(),
                'formTypeClass' => DynamicEntityFormType::class,
            ],
        )->actingAs(new InMemoryUser(\in_array('ROLE_HR_EDIT', $roles, true) ? 'hr_edit' : 'hr', 'test', $roles))
            ->render();
    }

    /** @return list<string> form field names that have an inline-add button */
    private function addButtonFields(string $html): array
    {
        preg_match_all('/data-admin-inline-add-field-name-value="([^"]*)"/', $html, $m);

        return $m[1];
    }

    /** @param list<string> $fields */
    private function hasField(array $fields, string $property): bool
    {
        return $this->matching($fields, $property) !== [];
    }

    /** @return list<string> value of `data-live-name-param` on every add/remove collection button */
    private function collectionButtonNames(string $html): array
    {
        preg_match_all('/<button\b[^>]*>/', $html, $buttons);

        $names = [];
        foreach ($buttons[0] as $button) {
            if (!preg_match('/addCollectionItem|removeCollectionItem/', $button)) {
                continue;
            }
            if (preg_match('/data-live-name-param="([^"]*)"/', $button, $name)) {
                $names[] = $name[1];
            }
        }

        return $names;
    }

    /**
     * @param list<string> $names
     * @return list<string> names that refer to the given property (`…[property]` or `…[property][n]`)
     */
    private function matching(array $names, string $property): array
    {
        return array_values(array_filter(
            $names,
            static fn (string $name): bool => preg_match('/(^|\[)' . preg_quote($property, '/') . '(\]|$)/', $name) === 1,
        ));
    }
}
