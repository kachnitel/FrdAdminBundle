<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

final class ParatestCacheDir
{
    public static function resolve(string $baseSuffix): string
    {
        $token = getenv('TEST_TOKEN');

        return sys_get_temp_dir() . '/kachnitel-admin-bundle-test/' . $baseSuffix
            . ($token !== false && $token !== '' ? '-token' . $token : '');
    }
}
