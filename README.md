# Erfindergeist Backup — Local Analysis

Run all checks locally before making a commit.

## Prerequisites

Podman or Docker (no local PHP required).

## Setup

The included `compose.yml` + `Dockerfile` build a container with PHP 8.3 + Composer.
Build the image once, it will be cached afterwards:

```bash
podman compose build
```

Then install dependencies:

```bash
# Development (incl. analysis tools):
podman compose run --rm composer install

# Production (runtime dependencies only):
podman compose run --rm composer install --no-dev --optimize-autoloader
```

## Individual Checks

| Command | Podman equivalent | Checks |
| --- | --- | --- |
| `composer phpcs` | `podman compose run --rm composer phpcs` | Code style (WordPress Coding Standards + PHP compatibility) |
| `composer phpstan` | `podman compose run --rm composer phpstan` | Static analysis — types, undefined variables, logic errors |
| `composer psalm` | `podman compose run --rm composer psalm` | Security — taint analysis (XSS, SQL injection, path traversal) |
| `composer phpmd` | `podman compose run --rm composer phpmd` | Code quality — complexity, naming, unused code |
| `composer audit` | `podman compose run --rm composer audit` | Known CVEs in dependencies |

## All Checks at Once

```bash
podman compose run --rm composer analyse
```

Runs phpcs → phpstan → psalm → phpmd sequentially.
Run `composer audit` separately afterwards.

## CI/CD

The GitHub Actions pipeline (`.github/workflows/backup-plugin.yml`) triggers automatically on:

- every **push** or **pull request** with changes to the plugin
- every **Monday at 06:00 UTC** (to catch newly published CVEs)

The pipeline tests on **PHP 8.3 and 8.4** in parallel.
