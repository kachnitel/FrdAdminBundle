<?php

declare(strict_types=1);

// Start output buffering early to prevent headers being sent before session configuration
ob_start();

use Kachnitel\AdminBundle\Tests\Functional\TestKernel;

require dirname(__DIR__) . '/vendor/autoload.php';

// Sync test templates before running tests
$kernel = new TestKernel('test', true);
$kernel->boot();

// End output buffering - tests can now configure sessions
ob_end_clean();
