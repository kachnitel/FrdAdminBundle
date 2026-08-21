<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\Tests\Fixtures\TestEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\TestEntityFormType;
use Kachnitel\AdminBundle\Twig\Components\AdminEntityForm;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

/**
 * Covers AdminFormSaveTrait::save()'s post-create redirect: after
 * successfully persisting a brand-new entity, the response redirects to
 * that entity's own edit page instead of re-rendering the (now stale) "new"
 * form in place.
 *
 * Editing an existing entity, and a new-entity save that fails validation,
 * are both unaffected — see AdminEntityFormTest and SaveButtonIntegrationTest
 * for the stay-in-place broadcast/toast path, which continues to apply to
 * every save() except this one new-entity/successful-persist combination.
 *
 * The "route can't be resolved at all" fallback in buildCreateRedirect()
 * (e.g. enable_generic_controller: false with no #[AdminRoutes] override) is
 * not covered here — ComponentTestKernel always registers the generic admin
 * routes, so hitting that branch needs a second kernel variant with those
 * routes absent. Flagged rather than silently skipped; happy to add that
 * kernel if this branch needs direct coverage.
 *
 * @see \Kachnitel\AdminBundle\Twig\Components\AdminFormSaveTrait::save()
 * @see \Kachnitel\AdminBundle\Twig\Components\AdminFormSaveTrait::buildCreateRedirect()
 * @see \Kachnitel\AdminBundle\Twig\Runtime\AdminRouteRuntime::getPath()
 */
#[Group('create-redirect')]
final class AdminEntityFormCreateRedirectTest extends ComponentTestCase
{
    private const FORM_NAME = 'test_entity_form';

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
    public function savingNewEntityRedirects(): void
    {
        $component = $this->mountNewForm();

        $component->set(self::FORM_NAME, ['name' => 'Brand New']);
        $component->call('save');

        $this->assertTrue($component->response()->isRedirect());
    }

    #[Test]
    public function savingNewEntityRedirectsToItsOwnEditPage(): void
    {
        $component = $this->mountNewForm();

        $component->set(self::FORM_NAME, ['name' => 'Redirect Target']);
        $component->call('save');

        $entity = $this->em->getRepository(TestEntity::class)->findOneBy(['name' => 'Redirect Target']);
        $this->assertNotNull($entity);

        $expectedPath = self::getContainer()->get('router')->generate('app_admin_entity_edit', [
            'entitySlug' => 'test-entity',
            'id'         => $entity->getId(),
        ]);

        $this->assertSame($expectedPath, $component->response()->headers->get('Location'));
    }

    #[Test]
    public function savingExistingEntityDoesNotRedirect(): void
    {
        $entity = new TestEntity();
        $entity->setName('Existing');
        $this->em->persist($entity);
        $this->em->flush();

        $component = $this->mountEditForm($entity);

        $component->set(self::FORM_NAME, ['name' => 'Existing, Updated']);
        $component->call('save');

        $this->assertFalse($component->response()->isRedirect());
    }

    #[Test]
    public function savingNewEntityWithInvalidDataDoesNotRedirect(): void
    {
        $component = $this->mountNewForm();

        $component->set(self::FORM_NAME, ['name' => '']);
        $component->call('save');

        $this->assertFalse($component->response()->isRedirect());
    }

    #[Test]
    public function savingNewEntityWithInvalidDataDoesNotPersist(): void
    {
        $component = $this->mountNewForm();

        $component->set(self::FORM_NAME, ['name' => '']);
        $component->call('save');

        $all = $this->em->getRepository(TestEntity::class)->findAll();
        $this->assertCount(0, $all);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function mountNewForm(): TestLiveComponent
    {
        return $this->createLiveComponent(
            name: AdminEntityForm::class,
            data: [
                'entityClass'   => TestEntity::class,
                'formTypeClass' => TestEntityFormType::class,
            ],
        );
    }

    private function mountEditForm(TestEntity $entity): TestLiveComponent
    {
        return $this->createLiveComponent(
            name: AdminEntityForm::class,
            data: [
                'entityClass'   => TestEntity::class,
                'entityId'      => $entity->getId(),
                'formTypeClass' => TestEntityFormType::class,
            ],
        );
    }
}
