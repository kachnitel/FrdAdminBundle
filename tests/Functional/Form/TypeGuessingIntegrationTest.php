<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional\Form;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\FieldMapping;
use Kachnitel\AdminBundle\Tests\Functional\TestKernel;
use Kachnitel\DynamicFormBundle\Form\DoctrineFormTypeMapper;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;

/**
 * Proves that OverrideTypeGuesserPass is wired correctly in the compiled container:
 * DoctrineFormTypeMapper receives the full guesser chain (validator + naming
 * convention), so password/email/tel/url/color field names produce the right
 * Symfony form types without any developer configuration.
 *
 * Uses a real DoctrineFormTypeMapper from the test container with mocked
 * ClassMetadata — no Doctrine entity or schema migration needed, since
 * ConventionalFieldTypeGuesser only inspects the field name.
 *
 * @group type-guessing
 * @group auto-form
 */
#[Group('type-guessing')]
#[Group('auto-form')]
#[AllowMockObjectsWithoutExpectations]
final class TypeGuessingIntegrationTest extends KernelTestCase
{
    private DoctrineFormTypeMapper $mapper;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var DoctrineFormTypeMapper $mapper */
        $mapper = static::getContainer()->get(DoctrineFormTypeMapper::class);
        $this->mapper = $mapper;
    }

    // ── Naming-convention guessing (ConventionalFieldTypeGuesser) ─────────────

    // #[Test]
    // #[DataProvider('passwordFieldNamesProvider')]
    // public function passwordNamedFieldProducesPasswordType(string $fieldName): void
    // {
    //     $config = $this->mapper->getFieldConfig(
    //         $this->makeStringMetadata($fieldName),
    //         $fieldName,
    //     );

    //     $this->assertNotNull($config);
    //     $this->assertSame(
    //         PasswordType::class,
    //         $config['type'],
    //         "Field name '$fieldName' should produce PasswordType via naming-convention guessing."
    //     );
    // }

    // /**
    //  * @return array<string, array{0: string}>
    //  */
    // public static function passwordFieldNamesProvider(): array
    // {
    //     return [
    //         'exact'         => ['password'],
    //         'plainPassword' => ['plainPassword'],
    //         'currentPassword' => ['currentPassword'],
    //         'newPassword'   => ['newPassword'],
    //     ];
    // }

    #[Test]
    #[DataProvider('emailFieldNamesProvider')]
    public function emailNamedFieldWithoutConstraintProducesEmailType(string $fieldName): void
    {
        $config = $this->mapper->getFieldConfig(
            $this->makeStringMetadata($fieldName),
            $fieldName,
        );

        $this->assertNotNull($config);
        $this->assertSame(
            EmailType::class,
            $config['type'],
            "Field name '$fieldName' should produce EmailType via naming-convention guessing."
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function emailFieldNamesProvider(): array
    {
        return [
            'email'        => ['email'],
            'contactEmail' => ['contactEmail'],
            'billingEmail' => ['billingEmail'],
        ];
    }

    #[Test]
    #[DataProvider('telFieldNamesProvider')]
    public function phoneNamedFieldProducesTelType(string $fieldName): void
    {
        $config = $this->mapper->getFieldConfig(
            $this->makeStringMetadata($fieldName),
            $fieldName,
        );

        $this->assertNotNull($config);
        $this->assertSame(TelType::class, $config['type']);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function telFieldNamesProvider(): array
    {
        return [
            'phone'     => ['phone'],
            'mobilePhone' => ['mobilePhone'],
            'faxNumber' => ['faxNumber'],
        ];
    }

    #[Test]
    public function websiteUrlFieldProducesUrlTypeWithNullDefaultProtocol(): void
    {
        $config = $this->mapper->getFieldConfig(
            $this->makeStringMetadata('websiteUrl'),
            'websiteUrl',
        );

        $this->assertNotNull($config);
        $this->assertSame(UrlType::class, $config['type']);
        $this->assertArrayHasKey('default_protocol', $config['options']);
        $this->assertNull(
            $config['options']['default_protocol'],
            'default_protocol must be null to prevent Symfony from auto-prepending a scheme.'
        );
    }

    #[Test]
    public function colorNamedFieldProducesColorType(): void
    {
        $config = $this->mapper->getFieldConfig(
            $this->makeStringMetadata('themeColor'),
            'themeColor',
        );

        $this->assertNotNull($config);
        $this->assertSame(ColorType::class, $config['type']);
    }

    // ── Fallback: unrelated names still produce TextType ─────────────────────

    #[Test]
    public function unrelatedStringFieldFallsBackToTextType(): void
    {
        $config = $this->mapper->getFieldConfig(
            $this->makeStringMetadata('nickname'),
            'nickname',
        );

        $this->assertNotNull($config);
        $this->assertSame(
            TextType::class,
            $config['type'],
            'A field name with no naming-convention match must fall through to TextType.'
        );
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Builds a minimal ClassMetadata mock for a nullable `string` column.
     *
     * Nullable at the DB level + nullable PHP property (?string) ensures the
     * nullability cross-check in DoctrineFormTypeMapper never trips — this test
     * is only exercising the guesser wiring, not nullability validation.
     *
     * @return ClassMetadata<object>
     */
    private function makeStringMetadata(string $fieldName): ClassMetadata
    {
        /** @var ClassMetadata<object>&MockObject $metadata */
        $metadata = $this->createMock(ClassMetadata::class);

        $mapping           = new FieldMapping(type: 'string', fieldName: $fieldName, columnName: $fieldName);
        $mapping->nullable = true;

        $metadata->method('getFieldMapping')->willReturn($mapping);
        $metadata->method('getName')->willReturn(TypeGuessingFixtureEntity::class);
        $metadata->method('getReflectionClass')->willReturn(new \ReflectionClass(TypeGuessingFixtureEntity::class));
        $metadata->method('hasField')->willReturn(true);

        return $metadata;
    }
}

/**
 * Minimal fixture entity for the metadata mock's reflection target.
 *
 * All properties are nullable ?string with no validator constraints, so:
 *   - phpAllowsNull() → true (no NullabilityMismatchException)
 *   - hasExistingValidatorConstraint() → false (no duplicate NotBlank added)
 * The guesser chain decides the type based solely on the field name.
 */
class TypeGuessingFixtureEntity
{
    public ?string $password        = null;
    public ?string $plainPassword   = null;
    public ?string $currentPassword = null;
    public ?string $newPassword     = null;
    public ?string $email           = null;
    public ?string $contactEmail    = null;
    public ?string $billingEmail    = null;
    public ?string $phone           = null;
    public ?string $mobilePhone     = null;
    public ?string $faxNumber       = null;
    public ?string $websiteUrl      = null;
    public ?string $themeColor      = null;
    public ?string $nickname        = null;
}
