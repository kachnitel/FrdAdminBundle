<?php

declare(strict_types=1);

use Kachnitel\AdminBundle\Tests\Functional\TestKernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

require dirname(__DIR__) . '/vendor/autoload.php';

// Sync test templates before running tests
$kernel = new TestKernel('test', true);
$kernel->boot();

$application = new Application($kernel);
$application->setAutoExit(false);

$input = new ArrayInput([
    'command' => 'admin:sync-test-templates',
    '--quiet' => true,
]);
$output = new BufferedOutput();

$exitCode = $application->run($input, $output);
$kernel->shutdown();

if ($exitCode !== 0) {
    echo "\n";
    echo "╔══════════════════════════════════════════════════════════════════╗\n";
    echo "║  Template sync failed - cannot run tests                         ║\n";
    echo "╚══════════════════════════════════════════════════════════════════╝\n\n";
    echo $output->fetch();
    exit($exitCode);
}
