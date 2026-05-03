# Project

WordPress plugin for database backups and restore (mysqldump + wpdb fallback).
PHP 8.3+, WordPress 6.4+, MIT license.
Language: UI, error messages and comments in **German**.

# Architecture

Functional approach — no classes. All functions prefixed with `egj_backup_*`.

| File | Responsibility |
|---|---|
| `vars.php` | Plugin constants (version, backup directory) |
| `includes/activator.php` | Plugin activation (directory, .htaccess) |
| `includes/deactivator.php` | Plugin deactivation |
| `includes/backup.php` | Create backup (mysqldump + wpdb fallback, gzip) |
| `includes/restore.php` | Upload validation, SQL execution, URL fixup |
| `includes/scheduler.php` | WordPress cron (monthly backups, error mail) |
| `admin/main.php` | Admin menu, tab navigation, download/delete handlers |
| `admin/partials/` | Template partials per tab |

# Code Conventions

- PHP 8.3+ features: full type declarations, union types, short array syntax `[]`
- WPCS-compliant: `esc_html()`, `wp_nonce_field()`, `sanitize_key()`, `wp_kses_post()` etc.
- Return errors with `WP_Error`, never exceptions for WordPress flows
- Align equals signs in multi-variable assignments (PHPCS requirement)
- KISS — no over-engineering, no OOP without reason

# Quality Tools

```bash
# via Podman (default — no local PHP required)
podman compose run --rm composer phpcs
podman compose run --rm composer phpstan
podman compose run --rm composer psalm
podman compose run --rm composer phpmd
podman compose run --rm composer analyse   # all four

# alternatively Docker
docker compose run --rm composer analyse
```

Configurations: `phpcs.xml`, `phpstan.neon`, `psalm.xml`, `.phpmd.xml`

Before every commit `composer analyse` must pass without errors.

**Important:** Any changes to quality tools or their configuration must also be reflected in:
- `.github/workflows/backup-plugin.yml` — the pipeline runs the same tools
- `README.md` — documents the available commands and what they check

# CI/CD & Deployment

Pipelines: `.github/workflows/`

| Workflow | Trigger | Target |
|---|---|---|
| `backup-plugin.yml` | Push, PR, Mondays 06:00 UTC | CI (phpcs, phpstan, psalm, phpmd) on PHP 8.3 & 8.4 |
| `deploy-test.yml` | Manual or after CI on `feature/**` | https://spielwiese.erfindergeist.org/ |
| `deploy-prod.yml` | After CI on `main` | https://erfindergeist.org/ |
| `release.yml` | Manual dispatch with version input | Bump version, tag, ZIP, GitHub Release, FTP deploy |
