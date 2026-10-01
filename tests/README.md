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
| `test-admin-fixes.php` | Admin-fixes v2.4.5 die buiten de nieuwe admin vallen (privacy-regeleinden, categorie bij de automatische scan, embeds "none"). Reset, import, logfilter en cache-purge worden sinds 3.0 bij hun nieuwe plek getest. |
| `test-admin3-frame.php` | Nieuwe admin: tab-whitelist, en broncheck (geen `style="`, emoji of hex-kleuren in `includes/admin/`). |
| `test-admin3-fields.php` | Renderer: label/for, verborgen 0 bij checkboxes, kleurvelden, optionele kleuren, show_if, checkboxlijsten. |
| `test-admin3-settings.php` | Type-bewuste sanitizing, gedeeltelijke tab-invoer, idempotentie, Settings API-callback, cache-purge via option-hooks. |
| `test-admin3-actions.php` | Acties via admin-post.php: formulier, nonce, redirect met één melding. |
| `test-admin3-banner.php` | Pagina Banner: juiste instellingen per tab, kleuren herstellen per thema, pagina-uitsluiting. |
| `test-admin3-preview.php` | Preview: banner-markup gelijk aan de frontend, CSS-variabelen gelijk aan de frontend (licht/donker), sandbox-iframe. |
| `test-admin3-registry.php` | **Belangrijkste van de herindeling:** elke instelling staat op precies één tab; defaults zijn idempotent; embed-diensten; ook de privacy-instellingen. |
| `test-admin3-cookies.php` | Pagina Cookies: cookielijst via de Settings API (alles verwijderen = leeg), F12-import, CSV, AJAX-nonces, scanresultaten samenvoegen zonder overschrijven. |
| `test-admin3-privacy.php` | Privacyverklaring: rijtabellen blijven JSON, oude admin blijft werken, oude grondslag behouden, verwerkingsregister, sectievolgorde. |
| `test-admin3-log.php` | Consent log: serverside filter en zoeken, alleen geldige consent-ID's verwijderen, bulkactie uit het bovenste én onderste keuzemenu, rij-acties, bewijs met alle velden, CSV-export (datumbereik, geen terugkerende bezoeken, kolommen zoals in 2.4), bewaartermijn op de tab Bewaren, consent-versie verhogen met geschiedenis (max. 50), log leegmaken, lijsttabel laadt niet zonder WordPress. |
| `test-admin3-beheer.php` | Beheer: licentiestatus in woorden, meldingen met de tekst van de licentieserver (één keer, nooit "gelukt" bij een fout), lege sleutel niet naar de server, licentiemelding alleen op Cookiebaas-schermen; backup zonder API-sleutel, ongeldig bestand wijzigt niets, import door dezelfde sanitizing als opslaan (API-sleutel blijft), "Alles resetten" meldt een mislukt onderdeel; API-sleutel alleen leeg of 40 hex (een oude eigen sleutel blijft), Info met alle shortcodes en de nieuwe menunamen. |
| `test-admin3-overzicht.php` | Overzicht: vier statusblokken, alle vijftien compliance-controles uit 2.4 met dezelfde logica, elke "Oplossen"-link naar een bestaande pagina en tab, eenmalige melding alleen na een update vanaf 2.x. |
| `test-admin3-overstap.php` | Overstap naar 3.0: oude admin-bestanden, gedeelde nonce, oude AJAX-handlers en links naar oude slugs zijn weg; scan, cookiedatabase en frontend-AJAX zijn verhuisd met dezelfde actienamen; de scanmail linkt naar de nieuwe cookielijst; topmenu "Cookiebaas" (positie 81) met zeven pagina's; elke oude slug verwijst door vóór de "geen toestemming"-melding. |
| `test-dark-zero.php` | Donker thema: 0 voor knop- en popupafronding en overlay blijft 0 (v2.4.6). |
| `test-cache-safety.php` | **Belangrijkste.** De HTML is identiek voor elke bezoeker — geen consent-status in de server-side output (privacylek-fix v1.7.7). Advanced én basic mode. |
| `test-consent-mode.php` | Consent Mode v2 head-injectie: advanced laadt altijd, client-side cookie-lezer, `url_passthrough` optioneel, JS-delay-bescherming. |
| `test-cookie-scan.php` | Kennisbank, prefix-matcher (`_` én `-`), Google-cookies op google.com, omgevingsdetectie (login, reacties, wachtwoordposts, WooCommerce, LiteSpeed). |
| `test-settings-cache.php` | `cm_get()` / `cm_get_flush()` en de automatische flush-hook (v1.8.0). |
| `test-cookie-display.php` | Cookienamen voor bezoekers (3.1.3): ID of hash wordt `*` (`_ga_V41VJXRM2G` → `_ga_*`, Hotjar-ID, WooCommerce-hash, voorvoegsels uit de kennisbank); gewone namen (ook `__Secure-3PAPISID`) blijven; elke weergavenaam één keer; opgeslagen naam blijft exact; gebruikt in voorkeurenvenster en cookietabel. |
| `test-credit.php` | Gratis versie: vermelding "Cookiebaas" (nofollow, vaste grijze stijl) in banner en voorkeurenvenster zonder geldige licentie, niet met licentie; paginacache alleen geleegd als de geldigheid verandert (ook op de vervaldatum); nofollow blijft in de browser; automatische scan hervat na verlengen. |
| `test-browser-scan.php` | Browserscan 3.1: scanmodus alleen met geldige code én als ingelogde beheerder, full of fresh (nieuwe bezoeker); beheerder afgemeld voor de rest van het verzoek; full: consent granted, GTM-event, schone URL; fresh: denied, eigen keuze onzichtbaar en niet te overschrijven, niets gelogd; na toestemming: filters, gemeten looptijd, localStorage blijvend/sessionStorage sessie, ontvangers zonder cookies apart, al aanwezige alleen als bekend (Brinckers: `redux_*` niet); vóór toestemming: analytisch/marketing fout, eigen indeling gaat voor, functioneel mag, onbekend controleren, Google-tag in advanced mode info, blokkeren naar de patronen; Overzicht-status uit de meting. |
| `test-auto-scan-pending.php` | Automatische scan 3.1: volledige serverscan (homepage + tien nieuwste); bekend of onbekend (ook uit scanrijen, gemeten looptijd bewaard); onbekende wachten op het Overzicht en worden niet opnieuw gemeld; gekozen → cookielijst, Negeren → onthouden; Personalization uit de cookiedatabase → onbekend, database na de update opnieuw ophalen; opslag opruimen bij weigeren; Nederlandse categorieën in de mail; uninstall ruimt de nieuwe opties op. |
| `test-license-network.php` | Licentie bij een haperende verbinding (3.1.2): time-out of blokkadepagina bij activeren → sleutel niet "ongeldig", bestaande licentie blijft, uitleg over de verbinding; oude sleutel pas afmelden na geslaagde activatie; weigering van de server komt door; deactiveren verwijdert ook de sleutel, ook zonder bereikbare server. |
| `test-security.php` | Beveiligingsaudit 3.0: consent log niet te vervalsen (echt IP, herkomstcontrole), alleen pad en ingekorte IP-hash, geen crash op arrays, automatische keuzes als eigen methode; scan alleen eigen site; CSV-formules onschadelijk; licentieantwoord zonder fatal; cookiedatabase pas na geldige download vervangen; uitzonderingen op het pad; geo bij onbekend/Tor → banner; embeds alleen https van bekende diensten; grote pagina niet leeg; Google-ID's vragen unfiltered_html; uninstall en verpakking. |

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
