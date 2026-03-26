# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Plugin Overview

**Cookiebaas** is a GDPR-compliant WordPress cookie consent plugin (~13K lines, pure PHP + vanilla JS, no external dependencies). It manages cookie consent banners, script/embed blocking, consent logging, and Google Consent Mode v2 integration.

Plugin slug: `cookiebaas`, main file: `cookiemelding.php`, current version defined in `CM_VERSION`.

## Development Environment

This is a traditional WordPress plugin with no build system:
- No `composer.json`, no `package.json`, no webpack/Gulp
- PHP files are served directly — edit and refresh to test
- Assets are enqueued via WordPress `wp_enqueue_*` functions (no compilation step)
- To test locally, drop the plugin folder into a WordPress installation's `wp-content/plugins/` and activate it

## Architecture

### File Responsibilities

| File | Purpose |
|------|---------|
| `cookiemelding.php` | Bootstrap: constants, includes, activation/deactivation hooks, DB table creation, version migrations, cron scheduling, REST API endpoints |
| `includes/defaults.php` | All default settings (100+ keys), helper functions (`cm_get()`, `cm_get_settings()`), pre-defined cookie database, service/category mapping |
| `includes/frontend.php` | Banner HTML rendering, inline CSS injection, Google Consent Mode v2 script, script/embed blocking via output buffering, AJAX consent logging |
| `includes/admin.php` | All 5 admin pages + AJAX handlers: Settings, Cookies & Scan, Privacy Statement, Consent Log, Manage |
| `includes/privacy.php` | `[cookiebaas_privacy]` shortcode — renders full GDPR/AVG privacy statement from stored options |
| `includes/license.php` | License validation against `cookiebaas.nl` API, caching, 7-day cron refresh |
| `includes/updater.php` | `CM_GitHub_Updater` class — hooks into WordPress update system to pull releases from GitHub |

### Database Tables (created on activation)

- `{prefix}cm_consent_log` — consent records (UUID, analytics/marketing flags, method, SHA-256 IP hash, user-agent, url, config hash)
- `{prefix}cm_cookie_db` — cookie database (platform, category, cookie name, domain, retention, privacy URL, wildcard flag)

### WordPress Options

- `cm_settings` — main serialized settings array (all UI-configurable values)
- `cm_cookie_list` — custom cookies array
- `cm_privacy` — privacy statement data
- `cm_version` — installed version (used for migration checks in `cookiemelding.php`)
- `cm_license_data` — cached license response

### Settings Flow

1. Defaults defined in `cm_default_settings()` (`defaults.php`)
2. Stored in `cm_settings` option after admin save
3. Retrieved via `cm_get($key)` or `cm_get_settings()` helpers
4. Passed to frontend JS via `wp_localize_script()` as `cmData`

### Script & Embed Blocking

`frontend.php` uses PHP output buffering (`ob_start`) to intercept page HTML and:
- Strip/replace `<script>` tags matching analytics/marketing patterns
- Replace `<iframe>` embeds (YouTube, Vimeo, etc.) with placeholder blocks
- Pattern matching uses `cm_init_cookie_blocker()` with pre-defined regex lists

### REST API

```
Base: /wp-json/cookiebaas/v1/
GET  /consent/{consent_id}   — fetch single consent record
POST /consent                — log new consent
GET  /status                 — plugin status
Auth: X-Cookiebaas-Key header (or WordPress Application Password)
```

### Cron Jobs

- Log retention — daily at 12:00 local time, deletes old consent logs
- Auto-scan — detects new cookies via `wp_remote_get()` + Set-Cookie header parsing
- License refresh — every 7 days

All cron events are unscheduled on plugin deactivation (`uninstall.php` drops tables and deletes all options).

## Key Conventions

- **Language detection**: `cm_detect_lang()` in `defaults.php` returns `'nl'` or `'en'`; text strings exist in both languages in `cm_default_settings()`
- **Version migrations**: Handled in `cookiemelding.php` via `cm_version` option comparison — add new migration blocks there when changing DB schema or option structure
- **Cookie category mapping**: `cm_map_category()` in `defaults.php` translates internal slugs (`analytics`, `marketing`, `functional`) to display labels
- **Embed domain mapping**: `cm_get_embed_domains()` maps service names to domains for the script blocker — extend this when adding new service support
- **Admin AJAX handlers**: All registered with `wp_ajax_cm_*` prefix; corresponding JS in `assets/js/admin.js` calls them via `jQuery.ajax()`
- **Frontend consent state**: Stored in browser cookie `cookiebaas_consent` (JSON); JS in `assets/js/frontend.js` reads/writes this and fires `cmConsentUpdate` custom event
