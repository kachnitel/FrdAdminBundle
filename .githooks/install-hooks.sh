#!/bin/bash
#
# Install git hooks for the project.
#
# Usage: .githooks/install-hooks.sh

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

if [ ! -d "$PROJECT_ROOT/.git" ]; then
    echo "❌ Not a git repository"
    exit 1
fi

echo "Installing git hooks..."

# Use Git's core.hooksPath to point directly at .githooks directory (Git 2.9+)
cd "$PROJECT_ROOT"
git config core.hooksPath .githooks

# Ensure hooks are executable
chmod +x .githooks/pre-commit

echo "✅ Git hooks installed successfully"
echo ""
echo "Configured hooks (via core.hooksPath):"
echo "  - pre-commit: Updates metrics before each commit"
echo ""
echo "To disable temporarily: git commit --no-verify"
echo "To uninstall: git config --unset core.hooksPath"
