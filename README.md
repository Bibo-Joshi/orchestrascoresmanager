# Orchestra Scores Manager

> ⚠️ **Early Development**: This app is in an early development phase. Expect rough edges and breaking changes.

A [Nextcloud](https://nextcloud.com) app to manage an orchestra's sheet music database directly within your Nextcloud
instance.

Source code, issue tracker and releases: https://github.com/Bibo-Joshi/orchestrascoresmanager

## Features

- **Scores** – Store and manage individual scores with metadata (composer, arranger, publisher, year, difficulty, and
  more)
- **Tags** – Categorize scores with tags
- **Score Books** – Group scores into named books
- **Folder Collections** – Organize scores into folder collections with full version history
- **Setlists** – Build and manage setlists from your score database
- **Comments** – Add notes to individual scores
- **Import Script** – Migrate existing score data from an ODS spreadsheet (see `import_script/`)

## Requirements

- Nextcloud 32 or later
- PHP 8.1 or later

## Architecture

| Layer    | Technology                                              |
|----------|---------------------------------------------------------|
| Backend  | PHP 8.1+, Nextcloud MVC (Controllers, Services, Mappers)|
| Frontend | Vue 3, TypeScript, Pinia, Vite                          |
| API      | REST, OpenAPI-documented                                |
| Database | Nextcloud DB abstraction (SQLite, MySQL, MariaDB, PostgreSQL) |

The backend follows a clean separation of concerns: **Controllers** handle HTTP routing, **Services** contain business
logic, and **Mappers** provide database access. The REST API is documented with OpenAPI and a TypeScript client is
auto-generated from the spec.

## Development

Install dependencies:

```bash
composer install
npm install
```

Build the frontend:

```bash
npm run build
```

Run backend tests:

```bash
composer run test:unit
```

For more details on the Nextcloud app development workflow, see the
[Nextcloud developer documentation](https://docs.nextcloud.com/server/latest/developer_manual).
