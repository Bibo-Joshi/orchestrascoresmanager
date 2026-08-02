# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.0] - 2026-08-02

### Fixed

- Fix False Positive Overtime Highlights in Setlists (#92)

### Changed

- Save Setlist Details Directly Without Save Button (#95)

## [1.1.0] - 2026-07-25

### Fixed

- Make Columns Available for Setlist PDF Export Independently of View Mode (#87)

### Added

- Allow Editing Setlist Title (#88)

### Changed

- Bump `nextcloud/ocp` package (#86)

## [1.0.1] - 2026-07-08

### Fixed

- Make Router History Independent of App Path Settings (#83)

### Changed

- Bump `nextcloud/ocp` package (#69)
- Bump @types/node from 25.5.0 to 26.0.1 (#75)
- Bump vite-plugin-stylelint from 6.1.0 to 6.3.0 (#74)
- Bump swagger-typescript-api from 13.6.6 to 13.12.3 (#76)
- Bump @nextcloud/stylelint-config from 3.2.1 to 3.2.2 (#77)
- Bump @nextcloud/auth from 2.5.3 to 2.6.0 (#78)
- Bump @nextcloud/dialogs from 7.3.0 to 7.4.0 (#79)
- Bump nextcloud/coding-standard in /vendor-bin/cs-fixer (#70)

### Security

- Fix npm Audit (#81)

## [1.0.0] - 2026-07-26

### Added

- Add Support for NC34 (#12)

### Changed

- Bump `nextcloud/ocp` package (#66)

### Removed

- Drop Support for NC32 (#12)

### Security

- Fix npm Audit (#67)
- Fix npm Audit (#61)

## [0.9.1] - 2026-06-03

### Fixed

- Use the GEMA-Excel-Template for Setlist Exports (#63)
- Consider End Time for Setlist Future/Past Filter (#64)

## [0.9.0] - 2026-05-10

### Added

- Add a Compact View Mode for Setlists (#57)

### Security

- Fix npm Audit (#56)

## [0.9.0.] - 2026-05-08

### Added

- Add User Settings for Setlist Defaults (#54)
- Add GEMA Excel export to setlist page via refactored Export menu (#51)
- Enable Cell Text Selection (#45)
- Improve UI for Different Screen Sizes (#44)
- Icon-Only Header Actions on Narrow Screens (#46)

### Changed

- Handle Displaying of Non-Active Folder Collection Versions in Setlist Details (#53)
- Summarize Folder Collection Histories (#52)
- Drop `NcTextArea` Workaround (#47)
- Sort Scores by Title by Default (#43)
- Bump `nextcloud/ocp` package (#41)
- Bump `nextcloud/ocp` package (#36)
- Bump `nextcloud/ocp` package (#19)
- Bump `nextcloud/ocp` package (#15)
- Bump `swagger-typescript-api` from 13.2.16 to 13.6.5 (#27)
- Bump `vue-tsc` from 3.2.5 to 3.2.6 (#31)
- Bump `vue-router` from 4.6.4 to 5.0.4 (#29)
- Bump `@ag-grid-community/locale` from 35.1.0 to 35.2.0 (#32)
- Bump `vite-plugin-stylelint` from 6.0.4 to 6.1.0 (#30)
- Bump `@nextcloud/dialogs` from 7.2.0 to 7.3.0 (#28)
- Bump `@nextcloud/password-confirmation` from 6.0.3 to 6.1.0 (#25)
- Bump `rimraf` from 6.1.2 to 6.1.3 (#24)
- Bump `@vue/tsconfig` from 0.9.0 to 0.9.1 (#23)

### Fixed

- Fix Two UI Problems with ScoreBooks (#49)

### Security

- Fix npm Audit (#42)
- Fix npm Audit (#34)
- Fix npm Audit (#16)

## [0.7.0] - 2026-03-20

### Added

- First release
- Manage individual scores with rich metadata (composer, arranger, publisher, year, difficulty, and more)
- Organize scores into score books and folder collections
- Track version history of folder collections
- Build and manage setlists
- Categorize scores with tags
- Add comments to scores
- Limit write access to NextCloud user groups
