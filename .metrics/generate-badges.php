#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Generate README badges from project metrics.
 *
 * Run: php .metrics/generate-badges.php
 */

$projectRoot = dirname(__DIR__);

// ── PHPUnit (tests + coverage) ──────────────────────────────────────────────

echo "Running tests with coverage...\n";
$coverageDir = $projectRoot . '/.coverage';
@mkdir($coverageDir, 0755, true);

exec('cd ' . escapeshellarg($projectRoot) . ' && XDEBUG_MODE=coverage vendor/bin/paratest --coverage-text --coverage-html=' . escapeshellarg($coverageDir) . ' 2>&1', $output, $exitCode);
$phpunitOutput = implode("\n", $output);

// Strip ANSI color codes to allow regex parsing (fixes ParaTest color output)
$phpunitOutput = preg_replace('/\x1b\[[\d;]*m/', '', $phpunitOutput);

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

// ── PHPStan ──────────────────────────────────────────────────────────────

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

// ── PHPMD ────────────────────────────────────────────────────────────────
// phpmd/phpmd is removed from require-dev on the Symfony 8 CI matrix (see
// .github/workflows/ci.yml), so treat it as optional rather than fatal.

echo "Running PHPMD...\n";
$phpmdBin = $projectRoot . '/vendor/bin/phpmd';
$phpmdRan = false;
$phpmdViolations = 0;
$phpmdExit = 0;

if (is_file($phpmdBin)) {
    $phpmdRan = true;
    exec(
        'cd ' . escapeshellarg($projectRoot)
            . " && vendor/bin/phpmd . text ./phpmd.ruleset.xml --exclude 'tests/*,vendor/*,var/*,.phpstan/*,.coverage/*' 2>&1",
        $phpmdOutputLines,
        $phpmdExit
    );
    $phpmdOutput = implode("\n", $phpmdOutputLines);

    // Text report format: one violation per line, "<file>.php:<line>\t<message>"
    $phpmdViolations = preg_match_all('/^\S+\.php:\d+\s+\S/m', $phpmdOutput);

    // Exit code 2 = violations found (expected). Exit code 1 with zero matched
    // violation lines means PHPMD itself errored (bad ruleset, fatal, etc).
    if ($phpmdExit === 1 && $phpmdViolations === 0) {
        $phpmdViolations = -1; // sentinel: tool error, not a violation count
    }
}

if (!$phpmdRan) {
    $phpmdStatus = 'not%20installed';
    $phpmdColor = 'lightgrey';
} elseif ($phpmdViolations === -1) {
    $phpmdStatus = 'error';
    $phpmdColor = 'red';
} elseif ($phpmdViolations === 0) {
    $phpmdStatus = 'clean';
    $phpmdColor = 'brightgreen';
} else {
    $phpmdStatus = "{$phpmdViolations}%20issues";
    $phpmdColor = $phpmdViolations <= 5 ? 'yellow' : 'red';
}

// ── PHP-CS-Fixer (code style) ───────────────────────────────────────────

echo "Running PHP-CS-Fixer (dry-run)...\n";
$csFixerBin = $projectRoot . '/vendor/bin/php-cs-fixer';
$csRan = false;
$csExit = 0;

if (is_file($csFixerBin)) {
    $csRan = true;
    exec('cd ' . escapeshellarg($projectRoot) . ' && vendor/bin/php-cs-fixer fix --dry-run --diff 2>&1', $csOutputLines, $csExit);
}

if (!$csRan) {
    $csStatus = 'not%20installed';
    $csColor = 'lightgrey';
} else {
    $csStatus = $csExit === 0 ? 'clean' : 'issues%20found';
    $csColor = $csExit === 0 ? 'brightgreen' : 'red';
}

// ── Vitest (JS Stimulus controller tests) ────────────────────────────────
// Requires `npm --prefix assets install` to have been run (composer run setup).

echo "Running Vitest...\n";
$assetsDir = $projectRoot . '/assets';
$vitestRan = false;
$vitestTestCount = 0;
$vitestExit = 0;

if (is_dir($assetsDir . '/node_modules')) {
    $vitestRan = true;
    exec('npm --prefix ' . escapeshellarg($assetsDir) . ' test 2>&1', $vitestOutputLines, $vitestExit);
    $vitestOutput = implode("\n", $vitestOutputLines);

    // Strip ANSI color codes to allow regex parsing ([2m Test Files...
    $vitestOutput = preg_replace('/\x1b\[[\d;]*m/', '', $vitestOutput);

    // Vitest summary looks like: "Tests  35 passed (35)" — the number in
    // parentheses is the total, so it stays accurate if some fail/skip too.
    /**
     * example output
     *
     * Test Files 2 passed (2)
     * Tests 30 passed (30)
     */
    if (preg_match('/Tests\s+\d+\s+passed\s*\((\d+)\)/i', $vitestOutput, $vm)) {
        $vitestTestCount = (int)$vm[1];
    } elseif (preg_match('/Tests\s+(\d+)\s+passed/i', $vitestOutput, $vm)) {
        $vitestTestCount = (int)$vm[1];
    }
}

if (!$vitestRan) {
    $vitestStatus = 'not%20installed';
    $vitestColor = 'lightgrey';
} else {
    $vitestStatus = $vitestExit === 0 ? "{$vitestTestCount}%20passed" : 'failing';
    $vitestColor = $vitestExit === 0 ? 'brightgreen' : 'red';
}

// ── Requirements (PHP / Symfony) ─────────────────────────────────────────

// Read PHP/Symfony requirements from composer.json
$composer = json_decode(file_get_contents($projectRoot . '/composer.json'), true);

// Get Symfony version
$symfonyVersion = $composer['require']['symfony/framework-bundle'] ?? '^6.4|^7.0';

$phpVersion = $composer['require']['php'] ?? '^8.2';
$phpVersionSafe = htmlentities($phpVersion);

// ── Badge markdown ────────────────────────────────────────────────────────

$badges = <<<MARKDOWN
![Tests](<https://img.shields.io/badge/tests-{$testCount}%20passed-{$testsColor}>)
![Coverage](<https://img.shields.io/badge/coverage-{$coverageInt}%25-{$coverageColor}>)
![Assertions](<https://img.shields.io/badge/assertions-{$assertionCount}-blue>)
![PHPStan](<https://img.shields.io/badge/PHPStan-{$phpstanLevel}-{$phpstanColor}>)
![PHPMD](<https://img.shields.io/badge/PHPMD-{$phpmdStatus}-{$phpmdColor}>)
![Code Style](<https://img.shields.io/badge/code%20style-{$csStatus}-{$csColor}>)
![Vitest](<https://img.shields.io/badge/vitest-{$vitestStatus}-{$vitestColor}>)
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
    'phpmd' => [
        'ran' => $phpmdRan,
        'violations' => $phpmdRan ? max($phpmdViolations, 0) : null,
        'status' => !$phpmdRan ? 'not_installed' : ($phpmdViolations === 0 ? 'clean' : ($phpmdViolations === -1 ? 'error' : 'violations')),
    ],
    'code_style' => [
        'ran' => $csRan,
        'status' => !$csRan ? 'not_installed' : ($csExit === 0 ? 'clean' : 'issues_found'),
    ],
    'vitest' => [
        'ran' => $vitestRan,
        'count' => $vitestRan ? $vitestTestCount : null,
        'status' => !$vitestRan ? 'not_installed' : ($vitestExit === 0 ? 'passing' : 'failing'),
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

$hasFailures = $hasIssues
    || $stanExit !== 0
    || ($phpmdRan && $phpmdViolations !== 0)
    || ($csRan && $csExit !== 0)
    || ($vitestRan && $vitestExit !== 0);

exit($hasFailures ? 1 : 0);
