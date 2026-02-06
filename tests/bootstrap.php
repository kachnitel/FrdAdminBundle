<?php

declare(strict_types=1);

// Start output buffering early to prevent headers being sent before session configuration
ob_start();

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
    // Clear buffered output before error display
    ob_end_clean();
    echo "\n";
    echo "╔══════════════════════════════════════════════════════════════════╗\n";
    echo "║  Template sync failed - cannot run tests                         ║\n";
    echo "╚══════════════════════════════════════════════════════════════════╝\n\n";
    echo $output->fetch();
    exit($exitCode);
}

// End output buffering - tests can now configure sessions
ob_end_clean();
