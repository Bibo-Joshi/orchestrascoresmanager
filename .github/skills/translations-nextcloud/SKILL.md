---
name: translations-nextcloud
description: Update Nextcloud translation source files (.pot/.po) and regenerate l10n .js/.json files.
---

Use this skill when translation strings or localization files need to be refreshed.

Note: In Copilot environments using `copilot-setup-steps.yml`, PHP/gettext/translationtool may already be available. Re-install only if missing.

Run from the app root (`.` in standalone checkout, `apps/orchestrascoresmanager` in Nextcloud checkout):

1. `if [ -d apps/orchestrascoresmanager ]; then cd apps/orchestrascoresmanager; fi` (enter app directory)
2. `sudo apt-get update && sudo apt-get install -y gettext` (install gettext tools used by msgmerge/msgfmt)
3. `curl -L -o translationtool.phar https://github.com/nextcloud/docker-ci/raw/master/translations/translationtool/translationtool.phar && chmod +x translationtool.phar` (fetch Nextcloud translation tool)
4. `find src -type f \( -name "*.ts" -o -name "*.js" -o -name "*.vue" \) | while read file; do sed -i -E "s/(^|[^a-zA-Z0-9_.])t\\(\\s*'([^']+)'/\\1t('orchestrascoresmanager', '\\2'/g" "$file"; sed -i -E "s/(^|[^a-zA-Z0-9_.])n\\(\\s*'([^']+)'\\s*,\\s*'([^']+)'/\\1n('orchestrascoresmanager', '\\2', '\\3'/g" "$file"; sed -i -E 's/(^|[^a-zA-Z0-9_.])t\\(\\s*"([^"]+)"/\\1t("orchestrascoresmanager", "\\2"/g' "$file"; sed -i -E 's/(^|[^a-zA-Z0-9_.])n\\(\\s*"([^"]+)"\\s*,\\s*"([^"]+)"/\\1n("orchestrascoresmanager", "\\2", "\\3"/g' "$file"; sed -i -E "s/(^|[^a-zA-Z0-9_.])t\\(\\s*\\x60([^\\x60]+)\\x60/\\1t('orchestrascoresmanager', \\x60\\2\\x60/g" "$file"; done` (preprocess custom `t()/n()` usage for extraction)
5. `mkdir -p translationfiles/templates && php translationtool.phar create-pot-files` (generate POT template)
6. `git checkout -- src/` (restore preprocessed source files)
7. `for lang_dir in translationfiles/*/; do lang=$(basename "$lang_dir"); [ "$lang" = "templates" ] && continue; po_file="translationfiles/$lang/orchestrascoresmanager.po"; [ -f "$po_file" ] && msgmerge --no-wrap --update --no-fuzzy-matching --backup=none "$po_file" "translationfiles/templates/orchestrascoresmanager.pot"; done` (merge new strings into PO files)
8. `for file in translationfiles/templates/orchestrascoresmanager.pot translationfiles/*/orchestrascoresmanager.po; do [ -f "$file" ] && sed -i '/^#\. TRANSLATORS/d;/^#:/d' "$file"; done` (remove noisy translator/path comments)
9. `php translationtool.phar convert-po-files` (regenerate `l10n/*.js` and `l10n/*.json`)
10. `git --no-pager diff -- translationfiles/ l10n/` (review generated translation changes)
11. `git status --porcelain translationfiles/ l10n/` (confirm changed files before commit)
