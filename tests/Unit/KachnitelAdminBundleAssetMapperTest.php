<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit;

use Kachnitel\AdminBundle\KachnitelAdminBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Covers the optional AssetMapper integration in KachnitelAdminBundle.
 *
 * symfony/asset-mapper is an optional dependency, so the tests that need
 * AssetMapperInterface declare a stub in a separate process to avoid
 * leaking it into the rest of the suite.
 */
#[CoversClass(KachnitelAdminBundle::class)]
#[Group('asset-mapper')]
final class KachnitelAdminBundleAssetMapperTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/kachnitel_admin_asset_mapper_' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir . '/Resources/config', 0777, true);
    }

    protected function tearDown(): void
    {
        $file = $this->tmpDir . '/Resources/config/asset_mapper.php';
        if (is_file($file)) {
            unlink($file);
        }
        rmdir($this->tmpDir . '/Resources/config');
        rmdir($this->tmpDir . '/Resources');
        rmdir($this->tmpDir);
    }

    #[Test]
    public function prependsNothingWhenAssetMapperIsNotInstalled(): void
    {
        /** @disregard P1009 AssetMapper skipped */
        if (interface_exists(AssetMapperInterface::class)) {
            self::markTestSkipped('symfony/asset-mapper is installed.');
        }

        $builder = $this->createBuilder(['FrameworkBundle' => ['path' => $this->tmpDir]]);
        $this->touchAssetMapperConfig();

        $this->prepend($builder);

        self::assertSame([], $builder->getExtensionConfig('framework'));
    }

    #[Test]
    #[RunInSeparateProcess]
    public function prependsNothingWhenFrameworkBundleMetadataIsMissing(): void
    {
        $this->declareAssetMapperStub();

        $builder = $this->createBuilder([]);

        $this->prepend($builder);

        self::assertSame([], $builder->getExtensionConfig('framework'));
    }

    #[Test]
    #[RunInSeparateProcess]
    public function prependsNothingWhenFrameworkBundleHasNoAssetMapperConfig(): void
    {
        $this->declareAssetMapperStub();

        $builder = $this->createBuilder(['FrameworkBundle' => ['path' => $this->tmpDir]]);

        $this->prepend($builder);

        self::assertSame([], $builder->getExtensionConfig('framework'));
    }

    #[Test]
    #[RunInSeparateProcess]
    public function prependsAssetMapperPathWhenAvailable(): void
    {
        $this->declareAssetMapperStub();
        $this->touchAssetMapperConfig();

        $builder = $this->createBuilder(['FrameworkBundle' => ['path' => $this->tmpDir]]);

        $this->prepend($builder);

        $config = $builder->getExtensionConfig('framework');
        self::assertCount(1, $config);
        $extensionConfig = $config[0];
        self::assertArrayHasKey('asset_mapper', $extensionConfig);
        $assetMapperConfig = $extensionConfig['asset_mapper'];
        self::assertIsArray($assetMapperConfig);
        self::assertArrayHasKey('paths', $assetMapperConfig);
        $paths = $assetMapperConfig['paths'];
        self::assertIsArray($paths);
        self::assertCount(1, $paths);
        self::assertSame('@kachnitel/admin-bundle', reset($paths));
        self::assertStringEndsWith('/assets', (string) array_key_first($paths));
    }

    private function prepend(ContainerBuilder $builder): void
    {
        $twigComponentExtension = $this->createStub(ExtensionInterface::class);
        $twigComponentExtension->method('getAlias')->willReturn('twig_component');
        $builder->registerExtension($twigComponentExtension);

        $loader = new PhpFileLoader($builder, new FileLocator());
        $instanceof = [];
        $configurator = new ContainerConfigurator($builder, $loader, $instanceof, __DIR__, __FILE__);

        (new KachnitelAdminBundle())->prependExtension($configurator, $builder);

        $twigComponentConfig = $builder->getExtensionConfig('twig_component');
        self::assertCount(1, $twigComponentConfig);
        self::assertArrayHasKey('defaults', $twigComponentConfig[0]);
    }

    /**
     * @param array<string, array{path: string}> $bundlesMetadata
     */
    private function createBuilder(array $bundlesMetadata): ContainerBuilder
    {
        $builder = new ContainerBuilder();
        $builder->setParameter('kernel.bundles_metadata', $bundlesMetadata);

        return $builder;
    }

    private function touchAssetMapperConfig(): void
    {
        file_put_contents($this->tmpDir . '/Resources/config/asset_mapper.php', '<?php return [];');
    }

    private function declareAssetMapperStub(): void
    {
        /** @disregard P1009 AssetMapper stubbing */
        if (!interface_exists(AssetMapperInterface::class)) {
            eval('namespace Symfony\Component\AssetMapper; interface AssetMapperInterface {}');
        }
    }
}
