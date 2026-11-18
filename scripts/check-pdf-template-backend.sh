#!/usr/bin/env bash
# Local PHP checks for the shared setlist templates backend.
# Run in a separate development checkout. Never runs database migrations.
set -euo pipefail
cd "$(dirname "$0")/.."
printf '\n== PHP version ==\n'
php -v | head -n 2
if ! command -v composer >/dev/null 2>&1; then
  echo 'Composer not found. Install Composer in your development environment.' >&2
  exit 1
fi
if [[ ! -f vendor-bin/phpunit/vendor/phpunit/phpunit/phpunit ]]; then
  echo 'PHPUnit dependencies missing; running composer install.' >&2
  composer install --no-interaction
fi
printf '\n== PHP lint ==\n'
composer lint
printf '\n== PHP-CS-Fixer dry run ==\n'
composer cs:check
printf '\n== PHPUnit (full backend suite) ==\n'
composer test:unit
printf '\n== Psalm ==\n'
composer psalm
printf '\nAll local PHP checks completed. Real DB migrations are not executed by this script.\n'
