# 🛠️ Development Guide

This guide provides information for contributing to and developing the Kachnitel Admin Bundle.

## Running Tests

```bash
# Run all tests
composer test

# Run tests with coverage (requires Xdebug)
composer coverage

# View HTML coverage report
open .coverage/index.html
```

## Code Quality

```bash
# Run PHPStan (level 6)
composer phpstan

# Update metrics and badges
composer metrics
```

## Pre-commit Hook

The project includes a pre-commit hook that automatically updates metrics before each commit:

```bash
# Install git hooks
composer install-hooks

# To skip hook temporarily
git commit --no-verify
```

The hook will:
- Run tests with coverage
- Run PHPStan analysis
- Update README badges
- Fail the commit if tests or PHPStan fail

## Metrics

Project metrics are auto-generated and stored in `.metrics/`:
- `badges.md` - Badge markdown for README
- `metrics.json` - Machine-readable metrics
- Coverage reports in `.coverage/` (gitignored)

## Creating Releases

The project uses [Conventional Commits](https://www.conventionalcommits.org/) for automated changelog generation:

```bash
# Create a new release (patch/minor/major/beta/rc/alpha)
composer release patch   # 1.0.0 -> 1.0.1
composer release minor   # 1.0.0 -> 1.1.0
composer release major   # 1.0.0 -> 2.0.0
composer release beta    # 1.0.0 -> 1.0.1-beta.1

# After reviewing, push to remote
git push origin master --tags
```

The release script will:
- Generate/update CHANGELOG.md from commit messages
- Create a new version tag
- Commit the changes

**Commit Message Format:**
```
<type>(<scope>): <subject>

<body>

<footer>
```

Types: `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `build`, `ci`, `chore`

Examples:
- `feat: Add user authentication`
- `fix: Resolve pagination edge case`
- `docs: Update installation guide`
