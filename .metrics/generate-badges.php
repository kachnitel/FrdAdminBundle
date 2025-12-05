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
$testCount = 0;
$assertionCount = 0;

// Match: Tests: N
if (preg_match_all('/Tests:\s*(\d+)/i', $phpunitOutput, $allTests)) {
    $testCount = (int) end($allTests[1]);
}

// Match: Assertions: N
if (preg_match_all('/Assertions:\s*(\d+)/i', $phpunitOutput, $allAsserts)) {
    $assertionCount = (int) end($allAsserts[1]);
}

if ($testCount === 0 && preg_match('/OK\s*\((\d+)\s+tests?,\s+(\d+)\s+assertions?\)/i', $phpunitOutput, $m)) {
    $testCount = (int)$m[1];
    $assertionCount = (int)$m[2];
}

// Clean OK only if it is exactly "OK" or "OK (" form
$cleanOk = preg_match('/^OK\b(?!,)/m', $phpunitOutput);

// Any non-OK issues:
$hasIssues = preg_match(
    '/(FAILURES|ERRORS|RISKY|WARNINGS|INCOMPLETE|SKIPPED)/i',
    $phpunitOutput,
    $matches
);

// Overall test status
$testsStatus = ($cleanOk && !$hasIssues) ? 'passing' : "failing($matches[0])";
$testsColor  = ($cleanOk && !$hasIssues) ? 'brightgreen' : 'red';

// Parse coverage percentage
preg_match('/Lines:\s+(\d+\.\d+)%/', $phpunitOutput, $coverageMatches);
$coverage = $coverageMatches[1] ?? '0.00';
$coverageInt = (int)round((float)$coverage);
$coverageColor = $coverageInt >= 80 ? 'brightgreen' : ($coverageInt >= 60 ? 'yellow' : 'red');

// Run PHPStan to verify level
echo "Running PHPStan...\n";
exec('cd ' . escapeshellarg($projectRoot) . ' && vendor/bin/phpstan analyse --memory-limit=256M --no-progress 2>&1', $stanOutput, $stanExit);
$stanOutput = implode("\n", $stanOutput);
$phpstanStatus = $stanExit === 0 ? 'pass' : 'errors';
$phpstanColor = $stanExit === 0 ? 'brightgreen' : 'red';

// Get PHPStan level
$phpstanLevel = 0;
$neon = file_get_contents($projectRoot . '/phpstan.neon');

if (preg_match('/^\s*level:\s*(\d+)/m', $neon, $m)) {
    $phpstanLevel = (int)$m[1];
}

// Read PHP/Symfony requirements from composer.json
$composer = json_decode(file_get_contents($projectRoot . '/composer.json'), true);

// Get Symfony version
$symfonyVersion = $composer['require']['symfony/framework-bundle'] ?? '^6.4|^7.0';

$phpVersion = $composer['require']['php'] ?? '^8.2';
$phpVersionSafe = htmlentities($phpVersion);

// Generate badge markdown
$badges = <<<MARKDOWN
![Tests](<https://img.shields.io/badge/tests-{$testCount}%20passed-{$testsColor}>)
![Coverage](<https://img.shields.io/badge/coverage-{$coverageInt}%25-{$coverageColor}>)
![Assertions](<https://img.shields.io/badge/assertions-{$assertionCount}-blue>)
![PHPStan](<https://img.shields.io/badge/PHPStan-{$phpstanLevel}-{$phpstanColor}>)
![PHP](<https://img.shields.io/badge/PHP-{$phpVersionSafe}-777BB4?logo=php&logoColor=white>)
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
        'level' => $phpstanLevel,
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

$hasFailures = $hasIssues || $stanExit !== 0;
exit($hasFailures ? 1 : 0);
