# GitHub Copilot Instructions for Orchestra Scores Manager

## Project Overview
NextCloud application for managing orchestra score databases. Built with PHP backend and Vue3 frontend.

## Versions & Compatibility
- **NextCloud**: 33+
- **PHP**: 8.4+ (minimum 8.1)
- **Vue**: 3.5+
- **Node**: 22.0.0+
- **TypeScript**: 5.9+

## General Coding Standards

### TypeScript over JavaScript
- Use TypeScript (`.ts`, `.vue` with `<script setup lang="ts">`) exclusively
- No plain JavaScript files in `/src`
- Leverage strict type checking configured in `tsconfig.json`

### NextCloud Ecosystem
- Prefer `@nextcloud/vue` components over custom implementations
- Use `@nextcloud/*` helper libraries (axios, dialogs, router, l10n, etc.)
- Follow NextCloud's design patterns and UI/UX conventions

### Code Comments & Documentation
- **NO** comments explaining what was just done
- Comments only for long-term maintainability (complex logic, workarounds, etc.)
- **ALWAYS** include docstrings for classes and methods with parameter descriptions
- Use PHPDoc for PHP, JSDoc/TSDoc for TypeScript

## Project Structure

### `/lib` - PHP Backend
MVC architecture with NextCloud framework:
- `Controller/` - Handle HTTP requests, delegate to Services
- `Db/` - Database entities and mappers
- `Service/` - Business logic layer
- `Policy/` - Authorization logic
- `Migration/` - Database schema changes

### `/src` - Vue3 Frontend
- `components/` - Reusable Vue components
- `pages/` - Page-level components
- `api/` - API client (auto-generated from OpenAPI)
- `types/` - TypeScript type definitions
- `router.ts` - Vue Router configuration

## GitHub Actions Setup

### Copilot Setup Workflow
The `.github/workflows/copilot-setup-steps.yml` reusable workflow provides environment setup with caching for Copilot workspace sessions.

**Update this workflow when:**
1. **PHP version changes**: Update `appinfo/info.xml` dependencies AND `composer.json` platform requirements
2. **Node.js version changes**: Update `package.json` engines field AND `.nvmrc`
3. **PHP extensions change**: Add/remove from the `extensions` list in the workflow
4. **Composer dependencies structure changes**: Modify caching strategy if vendor-bin setup changes
5. **New build tools added**: Update environment variables or install steps

**Current configuration:**
- Default PHP: 8.4 (minimum 8.1)
- Default Node: Auto-detected from package.json (22.0.0+)
- Composer caching: Based on composer.lock hash
- npm caching: Built-in via setup-node action

### PHPUnit Test Workflow
The `.github/workflows/phpunit-sqlite.yml` workflow runs backend unit tests on pull requests and pushes to main across multiple database backends.

**Update this workflow when:**
1. **Nextcloud version support changes**: Update `server-versions` matrix in phpunit-sqlite.yml
2. **PHP version support changes**: Update `php-versions` matrix in phpunit-sqlite.yml
3. **Database versions change**: Update database service images in the `services` section
4. **Test paths change**: Update `paths` filter for triggering the workflow

**Current configuration:**
- Supported NC versions: stable33, stable34, master
- PHP versions tested: 8.1, 8.2, 8.3, 8.4
- Databases: SQLite, MySQL 8.4, MariaDB 11.4, PostgreSQL 16
- All databases are defined as services and matrix selects which one to use

## Translations (l10n/IL10N)

See detailed guidelines in:
- **Frontend (Vue/TypeScript)**: `/src/.github/copilot-instructions.md`
- **Backend (PHP)**: `/lib/.github/copilot-instructions.md`

**Translation Files**: `/l10n/de.json` and `/l10n/de.js`

## Update These Instructions
When making architectural decisions or identifying patterns that should be followed consistently:
1. Update this file or add path-specific `.github/copilot-instructions.md` files
2. Keep instructions concise and actionable
3. Remove outdated patterns
