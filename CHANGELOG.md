# Changelog

All notable changes to `larapilot` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `larapilot:spec-delete` command to remove a spec together with its spec and plan files.
- Workflow transition guards: `spec-start` requires `PLANNED`, `spec-review` requires `IN PROGRESS`, `spec-approve` and `spec-request-changes` require `REVIEW`, and `spec-plan` refuses specs already in `REVIEW` or `DONE`.
- Spec codes are validated everywhere they are written to disk, preventing path traversal via crafted codes.
- Specs added without a status now default to the configured `TODO` status.
- Italian spec section names (`Storia Utente`, `Dimostra`, `Criteri di Accettazione`) are accepted by the validator.
- GitHub Actions CI (Pest across PHP 8.2–8.4 × Laravel 11/12, plus Pint and PHPStan).

### Changed

- Validation commands (`validate-prd`, `validate-spec`, `validate-plan`) exit with code `2` when validation fails; `spec-add` and `spec-plan` return an error envelope with the findings instead of a success envelope.
- Spec body validation requires marked-up sections (`**User Story**` or `## User Story`) instead of matching plain substrings.
- All backlog, spec, plan, PRD, and project config writes are atomic (temp file + rename).
- Artisan commands and config publishing stay registered when `larapilot.enabled` is `false`, so `larapilot:doctor` can diagnose a disabled install; the MCP server and mockup route remain gated.
- Project config is memoized per process instead of re-parsing `.larapilot/config.yaml` on every access.

### Fixed

- All commands taking arguments (`spec-show`, `spec-plan`, `spec-start`, `spec-review`, `spec-approve`, `spec-request-changes`, `task-done`, `validate-plan`) crashed with a container resolution error because command arguments were type-hinted in `handle()`.
- The mockup controller no longer falls back to serving unresolved paths when `realpath()` fails.

## [0.1.0] - 2026-07-08

- Initial release.
