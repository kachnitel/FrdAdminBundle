<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\Tests\Fixtures\ObjectAuthEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\ObjectAuthEntityFormType;
use Kachnitel\AdminBundle\Twig\Components\AdminEntityForm;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

/**
 * Covers AdminFormSaveTrait::save()'s object-level authorization gate for
 * the new/edit form flow, using ObjectAuthEntity (enableObjectAuth:
 * true) and ObjectAuthVoter (grants only when kind === KIND_ALLOWED),
 * registered by ObjectAuthorizationTestKernel.
 *
 * Runs through a real kernel/HTTP cycle — unlike
 * GenericAdminControllerObjectAuthorizationTest's controller-double style —
 * because the entity being authorized doesn't exist (new) or isn't in its
 * final post-submission state (edit) until doSubmitForm() has bound the
 * request data onto it. TestLiveComponent::call() runs with exceptions
 * uncaught (the underlying KernelBrowser has catchExceptions(false)), so
 * AccessDeniedException thrown inside save() propagates directly here.
 *
 * @see \Kachnitel\AdminBundle\Twig\Components\AdminFormSaveTrait::save()
 * @see \Kachnitel\AdminBundle\Tests\Fixtures\ObjectAuthVoter
 * @see \Kachnitel\AdminBundle\Tests\Functional\ObjectAuthorizationTestKernel
 */
#[Group('object-authorization')]
final class AdminEntityFormObjectAuthorizationTest extends ComponentTestCase
{
    private const FORM_NAME = 'object_auth_entity_form';

    protected static function getKernelClass(): string
    {
        return ObjectAuthorizationTestKernel::class;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // CSRF token storage requires a session on the request stack.
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);
        self::getContainer()->get('request_stack')->push($request);
    }

    // ── New entity (ADMIN_NEW) ──────────────────────────────────────────────

    #[Test]
    public function savingNewAllowedEntitySucceeds(): void
    {
        $component = $this->mountNewForm();

        $component->set(self::FORM_NAME, ['name' => 'Allowed One', 'kind' => ObjectAuthEntity::KIND_ALLOWED]);
        $component->call('save');

        $all = $this->em->getRepository(ObjectAuthEntity::class)->findBy(['name' => 'Allowed One']);
        $this->assertCount(1, $all);
    }

    #[Test]
    public function savingNewForbiddenEntityIsDeniedAndNotPersisted(): void
    {
        $component = $this->mountNewForm();

        $component->set(self::FORM_NAME, ['name' => 'Forbidden One', 'kind' => ObjectAuthEntity::KIND_FORBIDDEN]);

        $this->expectException(AccessDeniedException::class);

        try {
            $component->call('save');
        } finally {
            $all = $this->em->getRepository(ObjectAuthEntity::class)->findBy(['name' => 'Forbidden One']);
            $this->assertCount(0, $all, 'A denied save must not persist the entity.');
        }
    }

    // ── Existing entity (ADMIN_EDIT) ────────────────────────────────────────

    #[Test]
    public function savingEditToAnAllowedEntitySucceeds(): void
    {
        $entity = $this->persistedEntity(ObjectAuthEntity::KIND_ALLOWED);

        $component = $this->mountEditForm($entity);
        $component->set(self::FORM_NAME, ['name' => 'Updated Name', 'kind' => ObjectAuthEntity::KIND_ALLOWED]);
        $component->call('save');

        $reloaded = $this->em->getRepository(ObjectAuthEntity::class)->find($entity->getId());
        $this->assertNotNull($reloaded);
        $this->assertSame('Updated Name', $reloaded->getName());
    }

    /**
     * The acceptance-criteria case: an edit cannot change an entity into a
     * kind the current user isn't allowed to manage. The entity starts
     * allowed — the page would have loaded and the edit form would have
     * mounted fine — but the submitted edit changes kind to forbidden. The
     * check must run against the entity's post-submission state (after
     * doSubmitForm() binds the new kind), not the state it had when the
     * component mounted; otherwise this exact bypass would go uncaught.
     */
    #[Test]
    public function savingEditThatChangesKindToForbiddenIsDeniedAndNotPersisted(): void
    {
        $entity = $this->persistedEntity(ObjectAuthEntity::KIND_ALLOWED);

        $component = $this->mountEditForm($entity);
        $component->set(self::FORM_NAME, ['name' => 'Retyped', 'kind' => ObjectAuthEntity::KIND_FORBIDDEN]);

        $this->expectException(AccessDeniedException::class);

        try {
            $component->call('save');
        } finally {
            $reloaded = $this->em->getRepository(ObjectAuthEntity::class)->find($entity->getId());
            $this->assertNotNull($reloaded);
            $this->assertSame(
                ObjectAuthEntity::KIND_ALLOWED,
                $reloaded->getKind(),
                'A denied save must not persist the kind change.',
            );
            $this->assertSame('Existing', $reloaded->getName(), 'A denied save must not persist any field change.');
        }
    }

    // ── The critical case: object authorization survives an overridden save() ──

    /**
     * OverridingSaveAdminEntityForm fully overrides save() — its own
     * persist/flush, no call back into AdminFormSaveTrait's implementation —
     * which is exactly the documented, supported pattern FORMS.md describes
     * ("Custom form components" — overriding save() entirely is supported).
     *
     * Before ObjectAuthorizedFormInterface existed, an inline check in
     * AdminFormSaveTrait::save()'s own body would have been silently
     * skipped by this exact override, since the override never runs that
     * method's body at all — it has its own. This proves the check now
     * survives it, because it lives in doSubmitForm() instead, which even
     * this override still calls: there's no other way to retrieve the
     * bound entity at all.
     */
    #[Test]
    public function objectAuthorizationSurvivesAFullyOverriddenSave(): void
    {
        $component = $this->createLiveComponent(
            name: 'Test:Form:OverridingSave',
            data: [
                'entityClass'   => ObjectAuthEntity::class,
                'formTypeClass' => ObjectAuthEntityFormType::class,
            ],
        );

        $component->set(self::FORM_NAME, ['name' => 'Snuck In', 'kind' => ObjectAuthEntity::KIND_FORBIDDEN]);

        $this->expectException(AccessDeniedException::class);

        try {
            $component->call('save');
        } finally {
            // Proves the override's own persist()/flush() calls never ran —
            // the exception is thrown inside doSubmitForm(), before save()'s
            // body gets anywhere near persisting the entity.
            $all = $this->em->getRepository(ObjectAuthEntity::class)->findBy(['name' => 'Snuck In']);
            $this->assertCount(
                0,
                $all,
                'An overridden save() must not be able to bypass object-level authorization.',
            );
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function persistedEntity(string $kind): ObjectAuthEntity
    {
        $entity = new ObjectAuthEntity();
        $entity->setName('Existing');
        $entity->setKind($kind);
        $this->em->persist($entity);
        $this->em->flush();

        return $entity;
    }

    private function mountNewForm(): TestLiveComponent
    {
        return $this->createLiveComponent(
            name: AdminEntityForm::class,
            data: [
                'entityClass'   => ObjectAuthEntity::class,
                'formTypeClass' => ObjectAuthEntityFormType::class,
            ],
        );
    }

    private function mountEditForm(ObjectAuthEntity $entity): TestLiveComponent
    {
        return $this->createLiveComponent(
            name: AdminEntityForm::class,
            data: [
                'entityClass'   => ObjectAuthEntity::class,
                'entityId'      => $entity->getId(),
                'formTypeClass' => ObjectAuthEntityFormType::class,
            ],
        );
    }
}
