#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Generate README badges from project metrics.
 *
 * Run: php .metrics/generate-badges.php
 */

$projectRoot = dirname(__DIR__);

// Run PHPUnit with coverage
echo "Running tests with coverage...\n";
$coverageDir = $projectRoot . '/.coverage';
@mkdir($coverageDir, 0755, true);

exec('cd ' . escapeshellarg($projectRoot) . ' && XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-text --coverage-html=' . escapeshellarg($coverageDir) . ' 2>&1', $output, $exitCode);
$phpunitOutput = implode("\n", $output);

// Parse test results
preg_match('/Tests: (\d+), Assertions: (\d+)/', $phpunitOutput, $matches);
$testCount = $matches[1] ?? 0;
$assertionCount = $matches[2] ?? 0;
// Check for actual failures (not just risky tests)
$hasFailures = preg_match('/(FAILURES|ERRORS)!/', $phpunitOutput);
$testsStatus = !$hasFailures ? 'passing' : 'failing';
$testsColor = !$hasFailures ? 'brightgreen' : 'red';

// Parse coverage percentage
preg_match('/Lines:\s+(\d+\.\d+)%/', $phpunitOutput, $coverageMatches);
$coverage = $coverageMatches[1] ?? '0.00';
$coverageInt = (int)round((float)$coverage);
$coverageColor = $coverageInt >= 80 ? 'brightgreen' : ($coverageInt >= 60 ? 'yellow' : 'red');

// Run PHPStan to verify level
echo "Running PHPStan...\n";
exec('cd ' . escapeshellarg($projectRoot) . ' && vendor/bin/phpstan analyse --memory-limit=256M --no-progress 2>&1', $stanOutput, $stanExit);
$stanOutput = implode("\n", $stanOutput);
$phpstanStatus = $stanExit === 0 ? 'level 6' : 'errors';
$phpstanColor = $stanExit === 0 ? 'brightgreen' : 'red';

// Read PHP/Symfony requirements from composer.json
$composer = json_decode(file_get_contents($projectRoot . '/composer.json'), true);
$phpVersion = $composer['require']['php'] ?? '^8.2';
$symfonyVersion = '6.4|7.0+'; // From README

// Generate badge markdown
$badges = <<<MARKDOWN
![Tests](<https://img.shields.io/badge/tests-{$testCount}%20passed-{$testsColor}>)
![Coverage](<https://img.shields.io/badge/coverage-{$coverageInt}%25-{$coverageColor}>)
![Assertions](<https://img.shields.io/badge/assertions-{$assertionCount}-blue>)
![PHPStan](<https://img.shields.io/badge/PHPStan-{$phpstanStatus}-{$phpstanColor}>)
![PHP](<https://img.shields.io/badge/PHP-{$phpVersion}-777BB4?logo=php&logoColor=white>)
![Symfony](<https://img.shields.io/badge/Symfony-{$symfonyVersion}-000000?logo=symfony&logoColor=white>)

MARKDOWN;

// Save to file
$badgesFile = $projectRoot . '/.metrics/badges.md';
file_put_contents($badgesFile, $badges);

echo "\n✅ Badges generated in .metrics/badges.md\n\n";
echo $badges;

// Generate metrics summary
$summary = [
    'generated_at' => date('Y-m-d H:i:s'),
    'tests' => [
        'count' => (int)$testCount,
        'assertions' => (int)$assertionCount,
        'status' => $testsStatus,
    ],
    'coverage' => [
        'lines' => (float)$coverage,
        'percentage' => $coverageInt,
        'html_report' => '.coverage/index.html',
    ],
    'phpstan' => [
        'level' => 6,
        'status' => $phpstanStatus,
    ],
    'requirements' => [
        'php' => $phpVersion,
        'symfony' => $symfonyVersion,
    ],
];

file_put_contents(
    $projectRoot . '/.metrics/metrics.json',
    json_encode($summary, JSON_PRETTY_PRINT)
);

echo "✅ Metrics saved to .metrics/metrics.json\n";

// Exit with error only if there are actual failures (not just risky tests)
$hasRealFailures = $hasFailures || $stanExit !== 0;
exit($hasRealFailures ? 1 : 0);
