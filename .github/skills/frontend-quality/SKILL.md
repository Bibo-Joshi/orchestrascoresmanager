---
name: frontend-quality
description: Run frontend linting and style checks from package scripts when TypeScript or Vue changes are involved.
---

Use this skill for frontend quality checks in this repository.

Note: In Copilot environments using `copilot-setup-steps.yml`, `npm ci` may already be done. If `node_modules/` is present and current, you can skip install.

Run from the app root (`.` in standalone checkout, `apps/orchestrascoresmanager` in Nextcloud checkout):

1. `if [ -d apps/orchestrascoresmanager ]; then cd apps/orchestrascoresmanager; fi` (enter app directory)
2. `npm ci` (install frontend dependencies)
3. `npm run lint` (ESLint checks)
4. `npm run stylelint` (CSS/SCSS/Vue style checks)
5. `npm run build` (verify production build)
