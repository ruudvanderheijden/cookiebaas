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
