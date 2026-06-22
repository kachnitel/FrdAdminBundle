<?php
// tests/Twig/Components/AdminAction/SaveButtonTest.php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Twig\Components\AdminAction;

use Kachnitel\AdminBundle\Twig\Components\AdminAction\SaveButton;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\UX\LiveComponent\LiveResponder;

/**
 * Unit-level coverage for SaveButton's own state transitions.
 *
 */
#[CoversClass(SaveButton::class)]
#[Group('save-button')]
final class SaveButtonTest extends TestCase
{
    public function testDefaultStateIsEnabledAndNotSaving(): void
    {
        $button = $this->createSaveButton();

        self::assertFalse($button->saving);
        self::assertTrue($button->valid);
    }

    public function testTriggerSaveSetsSavingState(): void
    {
        $button = $this->createSaveButton();

        $button->triggerSave();

        self::assertTrue($button->saving);
    }

    /**
     * @param int<0, 1> $broadcastValid
     */
    #[DataProvider('formStateProvider')]
    public function testOnFormStateChangedTracksValidity(int $broadcastValid, bool $expectedValid): void
    {
        $button = $this->createSaveButton();

        $button->onFormStateChanged(valid: $broadcastValid);

        self::assertSame($expectedValid, $button->valid);
    }

    /**
     * @return array<string, array{0: int, 1: bool}>
     */
    public static function formStateProvider(): array
    {
        return [
            'valid'   => [1, true],
            'invalid' => [0, false],
        ];
    }

    public function testOnFormStateChangedClearsSavingFlag(): void
    {
        $button = $this->createSaveButton();
        $button->triggerSave();
        self::assertTrue($button->saving);

        $button->onFormStateChanged(valid: 1);

        self::assertFalse($button->saving);
        self::assertTrue($button->valid);
    }

    private function createSaveButton(): SaveButton
    {
        $button = new SaveButton();

        /**
         * ComponentToolsTrait::emit() reads a $liveResponder property that's normally
         * set via the #[Required] setLiveResponder() setter when the LiveComponent
         * framework instantiates the component through its service container. A bare
         * `new SaveButton()` skips that wiring, so any path through emit() (i.e.
         * triggerSave()) needs a LiveResponder injected manually first.
         */
        $button->setLiveResponder(new LiveResponder());

        return $button;
    }
}
