#!/bin/bash
#
# Update project metrics and README badges.
#
# Usage: .metrics/update-metrics.sh

set -e

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
