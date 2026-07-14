<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Kachnitel\AdminBundle\Controller\AbstractAdminController;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AbstractAdminController configuration and utility methods.
 *
 * Note: Protected methods (doIndex, doNew, etc.) are tested via functional tests
 * with a concrete controller implementation. This class tests public configuration
 * methods and inherited behavior.
 */
final class AbstractAdminControllerTest extends TestCase
{
    /**
     * Test a concrete implementation of AbstractAdminController
     */
    private ConcreteAdminController $controller;

    protected function setUp(): void
    {
        $this->controller = new ConcreteAdminController($this->createStub(EntityManagerInterface::class));
    }

    #[Test]
    public function getRoutePrefix(): void
    {
        $prefix = $this->controller->getRoutePrefix();
        $this->assertSame('app_entity', $prefix);
    }

    #[Test]
    public function getEntityNamespace(): void
    {
        $namespace = $this->controller->getEntityNamespace();
        $this->assertSame('App\\Entity\\', $namespace);
    }

    #[Test]
    public function getFormNamespace(): void
    {
        $namespace = $this->controller->getFormNamespace();
        $this->assertSame('App\\Form\\', $namespace);
    }

    #[Test]
    public function getFormSuffix(): void
    {
        $suffix = $this->controller->getFormSuffix();
        $this->assertSame('FormType', $suffix);
    }

    #[Test]
    public function getFormTypeBuildsCorrectClassName(): void
    {
        $formType = $this->controller->getFormType('Product');
        $this->assertSame('App\\Form\\ProductFormType', $formType);
    }

    #[Test]
    public function getEntityLabelUsingGetName(): void
    {
        $entity = new class {
            public function getId(): int { return 1; }
            public function getName(): string { return 'Test Product'; }
        };

        $label = $this->controller->getEntityLabel($entity);
        $this->assertSame('Test Product', $label);
    }

    #[Test]
    public function getEntityLabelUsingGetLabel(): void
    {
        $entity = new class {
            public function getId(): int { return 2; }
            public function getLabel(): string { return 'Test Label'; }
        };

        $label = $this->controller->getEntityLabel($entity);
        $this->assertSame('Test Label', $label);
    }

    #[Test]
    public function getEntityLabelUsingGetValue(): void
    {
        $entity = new class {
            public function getId(): int { return 3; }
            public function getValue(): string { return 'Test Value'; }
        };

        $label = $this->controller->getEntityLabel($entity);
        $this->assertSame('Test Value', $label);
    }

    #[Test]
    public function getEntityLabelFallsBackToId(): void
    {
        $entity = new class {
            public function getId(): int { return 42; }
        };

        $label = $this->controller->getEntityLabel($entity);
        $this->assertSame('#42', $label);
    }

    #[Test]
    public function getEntityLabelPrefersNameOverOthers(): void
    {
        $entity = new class {
            public function getId(): int { return 1; }
            public function getName(): string { return 'Name'; }
            public function getLabel(): string { return 'Label'; }
            public function getValue(): string { return 'Value'; }
        };

        $label = $this->controller->getEntityLabel($entity);
        $this->assertSame('Name', $label);
    }

    #[Test]
    public function getEntityLabelPrefersLabelOverValue(): void
    {
        $entity = new class {
            public function getId(): int { return 1; }
            public function getLabel(): string { return 'Label'; }
            public function getValue(): string { return 'Value'; }
        };

        $label = $this->controller->getEntityLabel($entity);
        $this->assertSame('Label', $label);
    }

    #[Test]
    public function getIndexTemplate(): void
    {
        $template = $this->controller->getIndexTemplate('Product');
        $this->assertSame('@KachnitelAdmin/admin/index.html.twig', $template);
    }

    #[Test]
    public function getShowTemplate(): void
    {
        $template = $this->controller->getShowTemplate('Product');
        $this->assertSame('@KachnitelAdmin/admin/show.html.twig', $template);
    }

    #[Test]
    public function getEditTemplate(): void
    {
        $template = $this->controller->getEditTemplate('Product');
        $this->assertSame('@KachnitelAdmin/admin/edit.html.twig', $template);
    }

    #[Test]
    public function getNewTemplate(): void
    {
        $template = $this->controller->getNewTemplate('Product');
        $this->assertSame('@KachnitelAdmin/admin/new.html.twig', $template);
    }

    #[Test]
    public function classNameToSlugConversion(): void
    {
        // Test slug conversion logic used in controller
        $classNames = [
            'Product' => 'product',
            'WorkStation' => 'work-station',
            'MyComplexEntity' => 'my-complex-entity',
            'A' => 'a',
            'ABC' => 'a-b-c',
        ];

        foreach ($classNames as $class => $expectedSlug) {
            $slug = strtolower(preg_replace('/[A-Z]/', '-$0', lcfirst($class))); // @phpstan-ignore argument.type
            $this->assertSame($expectedSlug, $slug, "Failed for class: $class");
        }
    }
}

/**
 * Concrete implementation for testing AbstractAdminController
 */
class ConcreteAdminController extends AbstractAdminController
{
    protected function getSupportedEntities(): array
    {
        return ['Product', 'Order', 'Customer'];
    }

    public function getEntityLabel(object $entity): string
    {
        return parent::getEntityLabel($entity);
    }

    public function getRoutePrefix(): string
    {
        return parent::getRoutePrefix();
    }

    public function getEntityNamespace(): string
    {
        return parent::getEntityNamespace();
    }

    public function getFormNamespace(): string
    {
        return parent::getFormNamespace();
    }

    public function getFormSuffix(): string
    {
        return parent::getFormSuffix();
    }

    public function getFormType(string $class): string
    {
        return parent::getFormType($class);
    }

    public function getIndexTemplate(string $class): string
    {
        return parent::getIndexTemplate($class);
    }

    public function getShowTemplate(string $class): string
    {
        return parent::getShowTemplate($class);
    }

    public function getEditTemplate(string $class): string
    {
        return parent::getEditTemplate($class);
    }

    public function getNewTemplate(string $class): string
    {
        return parent::getNewTemplate($class);
    }
}
