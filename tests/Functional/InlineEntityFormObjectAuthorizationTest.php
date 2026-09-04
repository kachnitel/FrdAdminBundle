<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\Tests\Fixtures\ObjectAuthEntity;
use Kachnitel\AdminBundle\Tests\Fixtures\ObjectAuthEntityFormType;
use Kachnitel\AdminBundle\Twig\Components\InlineEntityForm;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

/**
 * Covers InlineEntityForm::save()'s object-level authorization gate
 * (ADMIN_NEW — inline creation is always a new entity, never an edit).
 *
 * Exists specifically because inline creation (the "+ Add" dialog next to
 * EntityType autocomplete fields) is a second entry point into "create a
 * new entity" alongside the main New page. Without this check, object-level
 * create authorization enforced on that page could be bypassed entirely by
 * going through this dialog instead.
 *
 * Form-name derivation follows InlineEntityFormTest's documented algorithm:
 * 'Kachnitel\AdminBundle\Tests\Fixtures\ObjectAuthEntity'
 *   → non-alnum runs to '_':  'Kachnitel_AdminBundle_Tests_Fixtures_ObjectAuthEntity'
 *   → mb_strtolower:          'kachnitel_adminbundle_tests_fixtures_objectauthentity'
 *   → prepend 'inline_':      'inline_kachnitel_adminbundle_tests_fixtures_objectauthentity'
 *
 * @see \Kachnitel\AdminBundle\Twig\Components\InlineEntityForm::save()
 */
#[Group('object-authorization')]
#[Group('inline-add')]
final class InlineEntityFormObjectAuthorizationTest extends ComponentTestCase
{
    private const FORM_NAME = 'inline_kachnitel_adminbundle_tests_fixtures_objectauthentity';

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

    #[Test]
    public function savingAllowedEntityCreatesIt(): void
    {
        $component = $this->mountForm();

        $component->set(self::FORM_NAME, ['name' => 'Inline Allowed', 'kind' => ObjectAuthEntity::KIND_ALLOWED]);
        $component->call('save');

        $entities = $this->em->getRepository(ObjectAuthEntity::class)->findBy(['name' => 'Inline Allowed']);
        $this->assertCount(1, $entities);
    }

    #[Test]
    public function savingForbiddenEntityIsDeniedAndNotPersisted(): void
    {
        $component = $this->mountForm();

        $component->set(self::FORM_NAME, ['name' => 'Inline Forbidden', 'kind' => ObjectAuthEntity::KIND_FORBIDDEN]);

        $this->expectException(AccessDeniedException::class);

        try {
            $component->call('save');
        } finally {
            $entities = $this->em->getRepository(ObjectAuthEntity::class)->findBy(['name' => 'Inline Forbidden']);
            $this->assertCount(0, $entities, 'A denied inline save must not persist the entity.');
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function mountForm(): TestLiveComponent
    {
        return $this->createLiveComponent(
            name: InlineEntityForm::class,
            data: [
                'entityClass'   => ObjectAuthEntity::class,
                'formTypeClass' => ObjectAuthEntityFormType::class,
                // entityId intentionally omitted — inline creation only.
            ],
        );
    }
}
