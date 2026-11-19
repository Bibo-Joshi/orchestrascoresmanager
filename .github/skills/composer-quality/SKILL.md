---
name: composer-quality
description: Run backend style, static analysis, and unit test commands from composer scripts when PHP changes are involved.
---

Use this skill for backend quality checks in this repository.

Note: In Copilot environments using `copilot-setup-steps.yml`, dependency installation may already be done. If `vendor/` is present and current, you can skip install.
Prerequisite: use a PHP runtime with required extensions (notably `openssl`), otherwise `composer install` will fail.

Run from the app root (`.` in standalone checkout, `apps/orchestrascoresmanager` in Nextcloud checkout):

1. `if [ -d apps/orchestrascoresmanager ]; then cd apps/orchestrascoresmanager; fi` (enter app directory)
2. `composer remove nextcloud/ocp --dev --no-scripts --no-update` (match CI dependency setup)
3. `composer install --no-interaction --prefer-dist` (install backend dependencies)
4. `composer run lint` (PHP syntax checks)
5. `composer run cs:check` (PHP coding style check)
6. `composer run psalm` (static analysis)
7. `composer run test:unit` (backend unit tests)

If only formatting issues are reported, run `composer run cs:fix`, then re-run `composer run cs:check`.
