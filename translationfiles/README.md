# Translation files directory

This directory contains translation files in gettext format.

## Directory Structure

- `templates/` - Contains the `.pot` template file with all translatable strings
- `de/` - Contains German translations (`.po` file)

## Workflow

1. **Extract strings**: The `create-pot-files` command extracts translatable strings from source code
2. **Translate**: Edit the `.po` files in language directories (e.g., with [Poedit](https://poedit.net/))
3. **Generate**: The `convert-po-files` command generates the `.js` and `.json` files in `/l10n/`

## Adding New Languages

To add a new language:
1. Create a new directory with the language code (e.g., `fr/` for French)
2. Copy the `.pot` file from `templates/` and rename it to `orchestrascoresmanager.po`
3. Translate the strings in the `.po` file
4. Run `convert-po-files` to generate the final translation files

## Automated Workflow

The GitHub Actions workflow `.github/workflows/translations.yml` automatically:
- Checks if translation files are up to date on pull requests
- Provides diff and downloadable artifacts for updated translations
