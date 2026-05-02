# Erfindergeist Backup — Lokale Analyse

Alle Prüfungen lokal ausführen, bevor ein Commit gemacht wird.

## Voraussetzungen

**Option A — lokal:** PHP 8.3+, [Composer](https://getcomposer.org/) 2.4+

**Option B — ohne lokales PHP:** Docker oder Podman (siehe unten)

## Einrichtung

### Mit lokalem PHP

```bash
composer install
```

### Ohne lokales PHP (Docker / Podman)

Die mitgelieferte `compose.yml` + `Dockerfile` bauen einen Container mit PHP 8.3 + Composer.  
Einmalig das Image bauen, danach wird es gecacht:

```bash
podman compose build
```

Dann wie gewohnt:

```bash
# Development (inkl. Analyse-Tools):
podman compose run --rm composer install

# Production (nur Runtime-Dependencies):
podman compose run --rm composer install --no-dev --optimize-autoloader
```

## Einzelne Prüfungen

| Befehl | Podman-Äquivalent | Prüft |
| --- | --- | --- |
| `composer phpcs` | `podman compose run --rm composer phpcs` | Code-Style (WordPress Coding Standards + PHP-Kompatibilität) |
| `composer phpstan` | `podman compose run --rm composer phpstan` | Statische Analyse — Typen, undefinierte Variablen, Logik-Fehler |
| `composer psalm` | `podman compose run --rm composer psalm` | Sicherheit — Taint-Analyse (XSS, SQL-Injection, Path Traversal) |
| `composer phpmd` | `podman compose run --rm composer phpmd` | Code-Qualität — Komplexität, Naming, ungenutzter Code |
| `composer audit` | `podman compose run --rm composer audit` | Bekannte CVEs in Abhängigkeiten |

## Alle Prüfungen auf einmal

```bash
# Lokal:
composer analyse

# Podman:
podman compose run --rm composer analyse
```

Führt phpcs → phpstan → psalm → phpmd sequentiell aus.  
`composer audit` danach separat ausführen.

## CI/CD

Die GitHub Actions Pipeline (`.github/workflows/backup-plugin.yml`) startet automatisch bei:

- jedem **Push** oder **Pull Request** mit Änderungen am Plugin
- jeden **Montag um 06:00 UTC** (zum Aufdecken neu veröffentlichter CVEs)

Die Pipeline testet auf **PHP 8.3 und 8.4** parallel.
