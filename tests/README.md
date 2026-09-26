# Tests — Cookiebaas

Twee lagen, bewust zonder zware afhankelijkheden (de plugin heeft geen
build-systeem).

## 1. PHP-unittests — geen afhankelijkheden

Draait de plugincode direct met een minimale WordPress-stub (`bootstrap.php`).
Elk `test-*.php` draait in een eigen proces voor schone globale staat.

```bash
php tests/run.php
```

De runner lint eerst alle plugin-PHP (`php -l`) en draait daarna elke suite.
Exit-code 0 = alles groen, 1 = er faalde iets (geschikt voor CI / pre-commit).

Een losse suite draaien kan ook:

```bash
php tests/test-cache-safety.php
```

| Suite | Borgt |
|-------|-------|
| `test-admin-fixes.php` | Admin-fixes v2.4.5: reset-handler geregistreerd, regeleinden privacyverklaring, categorie automatische scan, kleur-defaults geldig voor `input type=color`, import gaat door de sanitizing, cache-purge na elke inhoudswijziging, serverside logfilter. |
| `test-admin3-frame.php` | Nieuwe admin: tab-whitelist, en broncheck (geen `style="`, emoji of hex-kleuren in `includes/admin/`). |
| `test-admin3-fields.php` | Renderer: label/for, verborgen 0 bij checkboxes, kleurvelden, optionele kleuren, show_if, checkboxlijsten. |
| `test-admin3-settings.php` | Type-bewuste sanitizing, gedeeltelijke tab-invoer, idempotentie, Settings API-callback, cache-purge via option-hooks. |
| `test-admin3-actions.php` | Acties via admin-post.php: formulier, nonce, redirect met één melding. |
| `test-admin3-banner.php` | Pagina Banner: juiste instellingen per tab, kleuren herstellen per thema, pagina-uitsluiting. |
| `test-admin3-preview.php` | Preview: banner-markup gelijk aan de frontend, CSS-variabelen gelijk aan de frontend (licht/donker), sandbox-iframe. |
| `test-admin3-registry.php` | **Belangrijkste van de herindeling:** elke instelling staat op precies één tab; defaults zijn idempotent; embed-diensten; ook de privacy-instellingen. |
| `test-admin3-cookies.php` | Pagina Cookies: cookielijst via de Settings API (alles verwijderen = leeg), F12-import, CSV, AJAX-nonces, scanresultaten samenvoegen zonder overschrijven. |
| `test-admin3-privacy.php` | Privacyverklaring: rijtabellen blijven JSON, oude admin blijft werken, oude grondslag behouden, verwerkingsregister, sectievolgorde. |
| `test-dark-zero.php` | Donker thema: 0 voor knop- en popupafronding en overlay blijft 0 (v2.4.6). |
| `test-cache-safety.php` | **Belangrijkste.** De HTML is identiek voor elke bezoeker — geen consent-status in de server-side output (privacylek-fix v1.7.7). Advanced én basic mode. |
| `test-consent-mode.php` | Consent Mode v2 head-injectie: advanced laadt altijd, client-side cookie-lezer, `url_passthrough` optioneel, JS-delay-bescherming. |
| `test-cookie-scan.php` | Kennisbank, prefix-matcher (`_` én `-`), Google-cookies op google.com, omgevingsdetectie (login, reacties, wachtwoordposts, WooCommerce, LiteSpeed). |
| `test-settings-cache.php` | `cm_get()` / `cm_get_flush()` en de automatische flush-hook (v1.8.0). |

Nieuwe assertie toevoegen: gebruik `cm_assert( 'omschrijving', $conditie )` binnen
een `cm_test_group( 'kop' )`. Zie `bootstrap.php` voor beschikbare stubs.

## 2. End-to-end smoketest — vereist Playwright

Draait de echte consent-flow in een browser tegen een **live of staging-site**
en controleert: geen `_ga`-cookies vóór consent, cookies ná akkoord, en cookies
weg (en weg blijven) ná weigeren.

```bash
npm i -D playwright          # eenmalig, buiten de plugin
CM_SMOKE_URL=https://staging.voorbeeld.nl/ node tests/smoke.mjs
```

Deze test hoort **niet** in de distributie-zip en draait niet mee in `run.php`
(hij heeft een echte site en een browser nodig).

## Wordt niet meegeleverd

De map `tests/` wordt uitgesloten van de distributie-zip die naar klanten gaat.
