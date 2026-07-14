<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Functional;

use Kachnitel\AdminBundle\Tests\Functional\ParatestCacheDir;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('paratest')]
final class ParatestCacheDirTest extends TestCase
{
    public function testReturnsBasePathWithoutTokenWhenEnvVarMissing(): void
    {
        $this->setEnvironmentVariable(null);

        self::assertSame(
            sys_get_temp_dir() . '/kachnitel-admin-bundle-test/cache',
            ParatestCacheDir::resolve('cache'),
        );
    }

    public function testReturnsTokenizedPathWhenEnvVarIsSet(): void
    {
        $this->setEnvironmentVariable('3');

        self::assertSame(
            sys_get_temp_dir() . '/kachnitel-admin-bundle-test/cache-token3',
            ParatestCacheDir::resolve('cache'),
        );
    }

    public function testDifferentTokensProduceDifferentPaths(): void
    {
        $this->setEnvironmentVariable('1');
        $first = ParatestCacheDir::resolve('cache');

        $this->setEnvironmentVariable('2');
        $second = ParatestCacheDir::resolve('cache');

        self::assertNotSame($first, $second);
    }

    public function testSameTokenWithDifferentBaseSuffixesNeverCollide(): void
    {
        $this->setEnvironmentVariable('7');

        $first = ParatestCacheDir::resolve('cache');
        $second = ParatestCacheDir::resolve('cache-no-auth');

        self::assertNotSame($first, $second);
    }

    private function setEnvironmentVariable(?string $value): void
    {
        if ($value === null) {
            putenv('TEST_TOKEN');

            return;
        }

        putenv('TEST_TOKEN=' . $value);
    }
}
