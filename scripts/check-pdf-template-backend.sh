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
printf '\n== PHPUnit ==\n'
if [[ -f ../../tests/bootstrap.php ]]; then
  printf 'Nextcloud checkout detected: running unit and integration suites.\n'
  composer test:unit
else
  printf 'Standalone checkout detected: running unit tests only.\n'
  vendor-bin/phpunit/vendor/phpunit/phpunit/phpunit -c tests/phpunit.xml --testsuite 'Unit Tests' --colors=always --fail-on-warning --fail-on-risky
fi
printf '\n== Psalm ==\n'
# Psalm 5.26.1 crashes on PHP 8.5 runtime deprecations.
if command -v php8.2 >/dev/null 2>&1; then
  php8.2 vendor/bin/psalm --threads=1 --no-cache
elif php -r 'exit(PHP_VERSION_ID >= 80500 ? 0 : 1);'; then
  if command -v docker >/dev/null 2>&1; then
    bash scripts/psalm-php82-docker.sh
  else
    printf 'Psalm 5.26.1 is incompatible with PHP 8.5. Use PHP 8.2 or install Docker.\n' >&2
    exit 2
  fi
else
  composer psalm
fi
printf '\nAll local PHP checks completed. Real DB migrations are not executed by this script.\n'
