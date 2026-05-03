# Projekt

WordPress-Plugin für Datenbank-Backups und -Restore (mysqldump + wpdb-Fallback).
PHP 8.3+, WordPress 6.4+, MIT-Lizenz.
Sprache: UI, Fehlermeldungen und Kommentare auf **Deutsch**.

# Architektur

Funktionaler Ansatz — keine Klassen. Alle Funktionen mit Präfix `egj_backup_*`.

| Datei | Aufgabe |
|---|---|
| `vars.php` | Plugin-Konstanten (Version, Backup-Verzeichnis) |
| `includes/activator.php` | Plugin-Aktivierung (Verzeichnis, .htaccess) |
| `includes/deactivator.php` | Plugin-Deaktivierung |
| `includes/backup.php` | Backup erstellen (mysqldump + wpdb-Fallback, gzip) |
| `includes/restore.php` | Upload-Validierung, SQL-Ausführung, URL-Fixup |
| `includes/scheduler.php` | WordPress-Cron (monatliche Backups, Fehlermail) |
| `admin/main.php` | Admin-Menü, Tab-Navigation, Download/Delete-Handler |
| `admin/partials/` | Template-Partials pro Tab |

# Code-Konventionen

- PHP 8.3+ Features: vollständige Typ-Deklarationen, Union-Types, kurze Array-Syntax `[]`
- WPCS-konform: `esc_html()`, `wp_nonce_field()`, `sanitize_key()`, `wp_kses_post()` etc.
- Fehlerrückgaben immer mit `WP_Error`, keine Exceptions für WordPress-Flows
- Gleichheitszeichen bei Mehrfachzuweisungen ausrichten (PHPCS-Pflicht)
- KISS — kein Over-Engineering, kein OOP ohne Grund

# Quality-Tools

```bash
# via Podman (Standard — kein lokales PHP nötig)
podman compose run --rm composer phpcs
podman compose run --rm composer phpstan
podman compose run --rm composer psalm
podman compose run --rm composer phpmd
podman compose run --rm composer analyse   # alle vier

# alternativ Docker
docker compose run --rm composer analyse
```

Konfigurationen: `phpcs.xml`, `phpstan.neon`, `psalm.xml`, `.phpmd.xml`

Vor jedem Commit muss `composer analyse` fehlerfrei durchlaufen.

# CI/CD & Deployment

Pipelines: `.github/workflows/`

| Workflow | Trigger | Ziel |
|---|---|---|
| `backup-plugin.yml` | Push, PR, Montags 06:00 UTC | CI (phpcs, phpstan, psalm, phpmd) auf PHP 8.3 & 8.4 |
| `deploy-test.yml` | Manuell oder nach CI auf `feature/**` | https://spielwiese.erfindergeist.org/ |
| `deploy-prod.yml` | Nach CI auf `main` | https://erfindergeist.org/ |
| `release.yml` | Manueller Dispatch mit Version | Version bumpen, Tag, ZIP, GitHub Release, FTP-Deploy |

**Wichtig:** Änderungen an Quality-Tools oder deren Konfigurationsdateien müssen auch in `backup-plugin.yml` nachgezogen werden — die Pipeline führt dieselben Tools aus.
