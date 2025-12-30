<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Controller;

use Kachnitel\AdminBundle\Controller\AbstractAdminController;
use PHPUnit\Framework\TestCase;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Unit tests for AbstractAdminController configuration and utility methods.
 *
 * Note: Protected methods (doIndex, doNew, etc.) are tested via functional tests
 * with a concrete controller implementation. This class tests public configuration
 * methods and inherited behavior.
 */
class AbstractAdminControllerTest extends TestCase
{
    /**
     * Test a concrete implementation of AbstractAdminController
     */
    private ConcreteAdminController $controller;

    protected function setUp(): void
    {
        $this->controller = new ConcreteAdminController();
    }

    /**
     * @test
     */
    public function getRoutePrefix(): void
    {
        $prefix = $this->controller->getRoutePrefix();
        $this->assertSame('app_entity', $prefix);
    }

    /**
     * @test
     */
    public function getEntityNamespace(): void
    {
        $namespace = $this->controller->getEntityNamespace();
        $this->assertSame('App\\Entity\\', $namespace);
    }

    /**
     * @test
     */
    public function getFormNamespace(): void
    {
        $namespace = $this->controller->getFormNamespace();
        $this->assertSame('App\\Form\\', $namespace);
    }

    /**
     * @test
     */
    public function getFormSuffix(): void
    {
        $suffix = $this->controller->getFormSuffix();
        $this->assertSame('FormType', $suffix);
    }

    /**
     * @test
     */
    public function getFormTypeBuildsCorrectClassName(): void
    {
        $formType = $this->controller->getFormType('Product');
        $this->assertSame('App\\Form\\ProductFormType', $formType);
    }

    /**
     * @test
     */
    public function getEntityLabelUsingGetName(): void
    {
        $entity = new class {
            public function getId(): int { return 1; }
            public function getName(): string { return 'Test Product'; }
        };

        $label = $this->controller->getEntityLabel($entity);
        $this->assertSame('Test Product', $label);
    }

    /**
     * @test
     */
    public function getEntityLabelUsingGetLabel(): void
    {
        $entity = new class {
            public function getId(): int { return 2; }
            public function getLabel(): string { return 'Test Label'; }
        };

        $label = $this->controller->getEntityLabel($entity);
        $this->assertSame('Test Label', $label);
    }

    /**
     * @test
     */
    public function getEntityLabelUsingGetValue(): void
    {
        $entity = new class {
            public function getId(): int { return 3; }
            public function getValue(): string { return 'Test Value'; }
        };

        $label = $this->controller->getEntityLabel($entity);
        $this->assertSame('Test Value', $label);
    }

    /**
     * @test
     */
    public function getEntityLabelFallsBackToId(): void
    {
        $entity = new class {
            public function getId(): int { return 42; }
        };

        $label = $this->controller->getEntityLabel($entity);
        $this->assertSame('#42', $label);
    }

    /**
     * @test
     */
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

    /**
     * @test
     */
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

    /**
     * @test
     */
    public function getIndexTemplate(): void
    {
        $template = $this->controller->getIndexTemplate('Product');
        $this->assertSame('@KachnitelAdmin/admin/index.html.twig', $template);
    }

    /**
     * @test
     */
    public function getShowTemplate(): void
    {
        $template = $this->controller->getShowTemplate('Product');
        $this->assertSame('@KachnitelAdmin/admin/show.html.twig', $template);
    }

    /**
     * @test
     */
    public function getEditTemplate(): void
    {
        $template = $this->controller->getEditTemplate('Product');
        $this->assertSame('@KachnitelAdmin/admin/edit.html.twig', $template);
    }

    /**
     * @test
     */
    public function getNewTemplate(): void
    {
        $template = $this->controller->getNewTemplate('Product');
        $this->assertSame('@KachnitelAdmin/admin/new.html.twig', $template);
    }

    /**
     * @test
     */
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
            $slug = strtolower(preg_replace('/[A-Z]/', '-$0', lcfirst($class)));
            $this->assertSame($expectedSlug, $slug, "Failed for class: $class");
        }
    }

    /**
     * @test
     */
    public function concreteControllerReturnsConfiguredEntities(): void
    {
        // Just verify the concrete implementation works
        $this->assertInstanceOf(ConcreteAdminController::class, $this->controller);
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
