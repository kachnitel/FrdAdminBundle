# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [v0.1.0-beta.1] - 2025-12-04

### Added
- Initial beta release
- Attribute-based entity configuration via `#[Admin]` attribute
- LiveComponent-powered entity list with real-time interactions
- Hierarchical template override system for customization
- Type-based property rendering with extensible type templates
- Column configuration (explicit columns, excludeColumns)
- Clickable entity ID linking to show/edit pages
- Configurable base layout integration
- Built-in filtering and search capabilities
- Batch operations with entity selection
- Pagination with configurable items per page
- Dashboard and navigation menu
- Comprehensive test suite (79 tests, 282 assertions)
- PHPStan level 6 static analysis
- Automated metrics and badges system
- Pre-commit hook for metrics updates
- Complete documentation (Configuration Guide, Template Overrides Guide)

### Requirements
- PHP 8.2 or higher
- Symfony 6.4 or 7.0+
- Doctrine ORM 2.0 or 3.0+
- Symfony UX LiveComponent 2.0+
- Symfony UX Twig Component 2.0+
