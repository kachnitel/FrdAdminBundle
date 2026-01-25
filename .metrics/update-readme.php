#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Update README.md with current badges.
 *
 * Run: php .metrics/update-readme.php
 */

$projectRoot = dirname(__DIR__);
$readmePath = $projectRoot . '/README.md';
$badgesPath = $projectRoot . '/.metrics/badges.md';

if (!file_exists($badgesPath)) {
    echo "❌ Badges file not found. Run generate-badges.php first.\n";
    exit(1);
}

if (!file_exists($readmePath)) {
    echo "❌ README.md not found.\n";
    exit(1);
}

$readme = file_get_contents($readmePath);
$badges = trim(file_get_contents($badgesPath));

// Replace or insert badges after the title
$badgeMarker = '<!-- BADGES -->';
$badgeSection = "{$badgeMarker}\n{$badges}\n{$badgeMarker}";

if (strpos($readme, $badgeMarker) !== false) {
    // Replace existing badges
    $readme = preg_replace(
        '/<!-- BADGES -->.*?<!-- BADGES -->/s',
        $badgeSection,
        $readme
    );
    echo "✅ Updated existing badges in README.md\n";
} else {
    // Insert badges after first heading
    $readme = preg_replace(
        '/(# Kachnitel Admin Bundle\n\n)/',
        "$1{$badgeSection}\n\n",
        $readme,
        1
    );
    echo "✅ Inserted badges into README.md\n";
}

file_put_contents($readmePath, $readme);

echo "✅ README.md updated successfully\n";
