<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\RowAction;

use Kachnitel\AdminBundle\RowAction\RowActionExpressionLanguage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authorization\ExpressionLanguage;

#[CoversClass(RowActionExpressionLanguage::class)]
#[Group('row-actions')]
#[Group('expression-language')]
final class RowActionExpressionLanguageErrorHandlingTest extends TestCase
{
    private function entity(): object
    {
        return new class {
            public function getStatus(): string
            {
                return 'pending';
            }
        };
    }

    #[Test]
    public function evaluatesValidExpression(): void
    {
        $language = new RowActionExpressionLanguage(new ExpressionLanguage(), debug: true);

        $this->assertTrue($language->evaluate('entity.getStatus() == "pending"', $this->entity()));
        $this->assertFalse($language->evaluate('item.getStatus() == "done"', $this->entity()));
    }

    #[Test]
    public function debugModeThrowsOnRuntimeFailureWithContext(): void
    {
        $language = new RowActionExpressionLanguage(new ExpressionLanguage(), debug: true);
        $entity = $this->entity();

        try {
            // Property-style access on a private property is unsupported (see UPGRADE.md)
            $language->evaluate('entity.status == "pending"', $entity);
            $this->fail('Expected a RuntimeException in debug mode.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('entity.status == "pending"', $e->getMessage());
            $this->assertStringContainsString($entity::class, $e->getMessage());
            $this->assertInstanceOf(\Throwable::class, $e->getPrevious());
        }
    }

    #[Test]
    public function debugModeThrowsOnSyntaxError(): void
    {
        $language = new RowActionExpressionLanguage(new ExpressionLanguage(), debug: true);

        $this->expectException(\RuntimeException::class);

        $language->evaluate('entity.getStatus() ==', $this->entity());
    }

    #[Test]
    public function nonDebugModeLogsWarningAndReturnsFalse(): void
    {
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('hidden'), $this->arrayHasKey('expression'));

        $language = new RowActionExpressionLanguage(new ExpressionLanguage(), debug: false, logger: $logger);

        $this->assertFalse($language->evaluate('entity.status == "pending"', $this->entity()));
    }

    #[Test]
    public function nonDebugModeWithoutLoggerReturnsFalse(): void
    {
        $language = new RowActionExpressionLanguage(new ExpressionLanguage());

        $this->assertFalse($language->evaluate('entity.nope()', $this->entity()));
    }
}
