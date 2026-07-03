#!/bin/bash
#
# Update project metrics and README badges.
#
# Runs the full QA suite (PHPUnit+coverage, PHPStan, PHPMD, PHP-CS-Fixer,
# Vitest) and regenerates the badges embedded in README.md.
#
# Usage: .metrics/update-metrics.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_ROOT"

echo "🔍 Generating metrics and badges..."
php .metrics/generate-badges.php

echo ""
echo "📝 Updating README.md..."
php .metrics/update-readme.php

echo ""
echo "✅ All done! Metrics updated."
echo ""
echo "Changed files:"
git diff --stat README.md .metrics/ 2>/dev/null || echo "  (no changes)"
