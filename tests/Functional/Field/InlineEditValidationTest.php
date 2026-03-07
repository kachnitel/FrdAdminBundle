<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional\Field;

use Kachnitel\AdminBundle\Tests\Fixtures\InlineEditValidatableEntity;
use Kachnitel\AdminBundle\Tests\Functional\ComponentTestCase;
use Kachnitel\AdminBundle\Twig\Components\Field\FloatField;
use Kachnitel\AdminBundle\Twig\Components\Field\StringField;

/**
 * Functional tests for the validation and save-feedback path introduced in the
 * template-method refactor of AbstractEditableField.save().
 *
 * ## What is tested
 *
 *   1. canEdit() guard — save() on a field whose property is not editable throws RuntimeException
 *      before any entity mutation (security fix).
 *   2. Validation — when a Symfony validator constraint is violated, errorMessage is set,
 *      editMode stays true, and no flush occurs.
 *   3. Save success feedback — after a valid save, saveSuccess=true and editMode=false.
 *   4. Templates — error message and success indicator appear in the rendered HTML.
 *
 * Uses InlineEditValidatableEntity which has explicit #[Assert\NotBlank] and
 * #[Assert\Length] constraints.
 *
 * @group inline-edit
 * @group inline-edit-validation
 * @group inline-edit-save
 */
class InlineEditValidationTest extends ComponentTestCase
{
    private function createEntity(string $title = 'Valid Title', float $score = 50.0): InlineEditValidatableEntity
    {
        $entity = new InlineEditValidatableEntity();
        $entity->setTitle($title);
        $entity->setScore($score);
        $this->em->persist($entity);
        $this->em->flush();

        return $entity;
    }

    // ── Validation: StringField ───────────────────────────────────────────────

    /**
     * Saving a title that exceeds the #[Assert\Length(max: 20)] constraint must
     * populate errorMessage and keep the component in edit mode.
     */
    public function testSaveWithTooLongTitleSetsErrorMessage(): void
    {
        $entity = $this->createEntity();

        $component = static::getContainer()->get(StringField::class);
        $component->editMode = true;
        $component->mount($entity, 'title');
        $component->currentValue = str_repeat('x', 25); // exceeds max:20

        $component->save();

        $this->assertNotSame('', $component->errorMessage, 'errorMessage must be set when validation fails');
        $this->assertTrue($component->editMode, 'editMode must stay true after failed validation');
    }

    /**
     * Saving a blank title against #[Assert\NotBlank] must set errorMessage.
     */
    public function testSaveWithBlankTitleSetsErrorMessage(): void
    {
        $entity = $this->createEntity();

        $component = static::getContainer()->get(StringField::class);
        $component->editMode = true;
        $component->mount($entity, 'title');
        $component->currentValue = '';

        $component->save();

        $this->assertNotSame('', $component->errorMessage);
    }

    /**
     * A failed save must NOT persist the violating value to the database.
     */
    public function testValidationFailureDoesNotPersistToDB(): void
    {
        $entity = $this->createEntity('Original');
        $id     = $entity->getId();

        $component = static::getContainer()->get(StringField::class);
        $component->editMode = true;
        $component->mount($entity, 'title');
        $component->currentValue = str_repeat('x', 25);

        $component->save();

        $this->em->clear();
        $reloaded = $this->em->find(InlineEditValidatableEntity::class, $id);
        $this->assertSame('Original', $reloaded?->getTitle(), 'DB value must not change after a failed validation');
    }

    /**
     * A valid save must clear errorMessage, exit editMode, and set saveSuccess.
     */
    public function testValidSaveClearsErrorAndSetsSuccessFlag(): void
    {
        $entity = $this->createEntity('Old');

        $component = static::getContainer()->get(StringField::class);
        $component->editMode     = true;
        $component->errorMessage = 'Previous error'; // simulate prior failure
        $component->mount($entity, 'title');
        $component->currentValue = 'New';

        $component->save();

        $this->assertSame('', $component->errorMessage);
        $this->assertTrue($component->saveSuccess);
        $this->assertFalse($component->editMode);
    }

    /**
     * After a successful save, the new value must be persisted to the database.
     */
    public function testValidSavePersistsToDB(): void
    {
        $entity = $this->createEntity('Old Title');
        $id     = $entity->getId();

        $component = static::getContainer()->get(StringField::class);
        $component->editMode = true;
        $component->mount($entity, 'title');
        $component->currentValue = 'New Title';

        $component->save();

        $this->em->clear();
        $reloaded = $this->em->find(InlineEditValidatableEntity::class, $id);
        $this->assertSame('New Title', $reloaded?->getTitle());
    }

    // ── Validation: FloatField ─────────────────────────────────────────────────

    /**
     * Saving a score outside the #[Assert\Range(min:0, max:100)] constraint sets errorMessage.
     */
    public function testSaveWithOutOfRangeScoreSetsErrorMessage(): void
    {
        $entity = $this->createEntity();

        $component = static::getContainer()->get(FloatField::class);
        $component->editMode = true;
        $component->mount($entity, 'score');
        $component->currentValue = 150.0; // exceeds max:100

        $component->save();

        $this->assertNotSame('', $component->errorMessage);
        $this->assertTrue($component->editMode);
    }

    // ── activateEditing clears feedback state ─────────────────────────────────

    /**
     * activateEditing() must reset errorMessage and saveSuccess so stale feedback
     * from the previous interaction does not bleed into the new edit session.
     */
    public function testActivateEditingClearsFeedbackState(): void
    {
        $entity = $this->createEntity();

        $component = static::getContainer()->get(StringField::class);
        $component->mount($entity, 'title');
        $component->errorMessage = 'Stale error';
        $component->saveSuccess  = true;
        $component->editMode     = false;

        $component->activateEditing();

        $this->assertSame('', $component->errorMessage);
        $this->assertFalse($component->saveSuccess);
        $this->assertTrue($component->editMode);
    }

    // ── Template output ────────────────────────────────────────────────────────

    /**
     * When errorMessage is set, the rendered template must contain the error text and
     * the is-invalid CSS class to trigger Bootstrap's error styling.
     */
    public function testTemplateRendersErrorMessage(): void
    {
        $entity = $this->createEntity();

        $component = $this->createLiveComponent(
            name: 'K:Admin:Field:String',
            data: [
                'entity'       => $entity,
                'property'     => 'title',
                'editMode'     => true,
                'errorMessage' => 'Title is too long.',
            ],
        );

        $html = (string) $component->render();

        $this->assertStringContainsString('Title is too long.', $html);
        $this->assertStringContainsString('is-invalid', $html);
    }

    /**
     * When saveSuccess is true and the component is in display mode, the rendered
     * template must show the "✓ Saved" indicator.
     */
    public function testTemplateRendersSaveSuccessIndicator(): void
    {
        $entity = $this->createEntity();

        $component = $this->createLiveComponent(
            name: 'K:Admin:Field:String',
            data: [
                'entity'      => $entity,
                'property'    => 'title',
                'editMode'    => false,
                'saveSuccess' => true,
            ],
        );

        $html = (string) $component->render();

        $this->assertStringContainsString('inline-edit-saved', $html);
        $this->assertStringContainsString('✓', $html);
    }

    /**
     * Verify template does NOT show the success indicator when saveSuccess is false.
     */
    public function testTemplateDoesNotRenderSuccessIndicatorByDefault(): void
    {
        $entity = $this->createEntity();

        $component = $this->createLiveComponent(
            name: 'K:Admin:Field:String',
            data: [
                'entity'      => $entity,
                'property'    => 'title',
                'editMode'    => false,
                'saveSuccess' => false,
            ],
        );

        $html = (string) $component->render();

        $this->assertStringNotContainsString('inline-edit-saved', $html);
    }
}
