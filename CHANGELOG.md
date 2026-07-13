# Changelog — Cookiebaas

## [1.7.1] - 2026-07-13

### Opgelost
- **GA vuurde vóór consent in Consent Mode advanced**: de consent-default gebruikte `wait_for_update: 500` — na 500 ms gingen Google-tags (ook GA4 binnen GTM) alsnog cookieloze pings versturen naar google-analytics.com. Zolang er geen keuze is gemaakt staat `wait_for_update` nu op 24 uur: de tag/container laadt wél (advanced), maar álle hits — inclusief cookieloze pings — worden vastgehouden tot de bezoeker kiest. Na acceptatie vuren de tags direct zonder herlaad.
- **Weigering wint nu altijd van "Google cookies direct laden"** (`google_load_default`): na een expliciete weigering werd de consent-update alsnog op `granted` gezet en bleef GA gewoon laden. Die optie geldt nu alleen zolang er geen keuze is gemaakt.
- **Na weigering wordt de Google-tag niet meer geladen**: voorheen bleef de container in advanced-modus ook ná een weigering laden en cookieloze pings sturen. Nu wordt de tag op volgende pagina's geblokkeerd (zoals basic) en stuurt de banner bij een volledige weigering ook geen `gtag('consent','update')` meer — de wachtende tags versturen dan helemaal niets. (Uitzondering: met `google_load_default` aan is de denied-update juist nodig om al vurende tags te stoppen.)

### Opmerking
- Niet-Google tags binnen GTM (bijv. Meta Pixel) kennen geen Consent Mode en worden hier niet door tegengehouden — geef die tags in GTM zelf een consent-vereiste ("Require additional consent for tag to fire"). De admin-teksten zijn hierop aangescherpt.

## [1.7.0] - 2026-07-09

### Toegevoegd
- **Google Consent Mode v2 "advanced"** (nieuwe optie, standaard aan): de Google-tag (GTM-container of GA4) wordt altijd direct ingeladen, óók vóór consent of na weigering — maar met alle consent-signalen op `denied`. De tag plaatst dan geen cookies en tags in GTM vuren niet; Google ontvangt alleen cookieloze pings waarmee het via modellering bezoekersaantallen en conversies kan inschatten. Zodra de bezoeker toestemming geeft stuurt de plugin de bestaande `gtag('consent','update')` en vuren de tags alsnog, zonder pagina-herlaad. Uitvinken = het oude "basic" gedrag (tag volledig geblokkeerd tot consent). Instelbaar bij de Google-integratie velden.
- Nieuw `data-cm-allow` attribuut: scripts die de plugin bewust laadt worden overgeslagen door zowel de PHP output-buffer blocker als de JS MutationObserver-blocker (voorheen zouden die de eigen GTM-injectie weer blokkeren).

### Opmerking
- Universal Analytics (verouderd) blijft op basic-gedrag — UA ondersteunt geen Consent Mode en zou direct cookies plaatsen.

## [1.6.1] - 2026-07-05

### Prestaties
- **CSS-minificatie gecached**: het minificeren van `frontend.css` draaide op elke pageload; het resultaat wordt nu gecached in een transient (gekeyed op bestandsdatum, automatisch ververst bij updates)
- **Tabelcreatie alleen bij upgrade**: `dbDelta` + `INFORMATION_SCHEMA`-query draaiden op elke pageload via `plugins_loaded`; nu alleen nog bij een versie-wissel

### Opgelost
- **Uninstall completer**: verwijdert nu ook de auto-scan opties (`cm_auto_scan_*`), alle plugin-transients (GitHub release cache, CSS cache, rate limits) en de auto-scan- en licentie-cron-events (voorheen alleen de retentie-cron)
- **Updater heractiveert niet meer onvoorwaardelijk**: `activate_plugin()` na een update wordt alleen nog aangeroepen als de plugin vóór de update actief was

## [1.6.0] - 2026-07-05

### Gewijzigd
- **Licentie fail-open**: bij een ongeldige of verlopen licentie werd de banner niet getoond, maar bleef de script/embed-blocker wél actief. Bezoekers konden daardoor nooit consent geven en scripts en video's bleven permanent geblokkeerd — de site brak geruisloos. Nu trekt de plugin zich bij een ongeldige licentie volledig terug: geen banner, geen blocking, geen GA4/GTM-injectie (tracking zonder consent-mogelijkheid zou een AVG-schending zijn). De admin-melding beschrijft dit gedrag.

### Beveiliging
- **Rate limiting consent log per IP**: de publieke log-endpoint limiteerde op 5 logs per 10 minuten per `session_id`, maar die komt uit de request zelf en was dus te omzeilen (onbeperkt database-inserts mogelijk). Toegevoegd: maximaal 20 logs per 10 minuten per geanonimiseerde IP-hash via transients.
- **SSL-verificatie auto-scan**: `sslverify => false` verwijderd bij het ophalen van de eigen homepage tijdens de cookie-scan.
- **Escaping**: drie border-kleuren in de inline CSS worden nu met `esc_attr()` ge-escaped; het custom zweefknop-SVG-icoon wordt gesanitized via een `wp_kses`-whitelist (de eerdere regex-check liet event-handler attributen zoals `onload` door).

### Verwijderd
- Ongebruikte `LOG_NONCE` variabele uit de frontend-script (werd aangemaakt maar nooit meegestuurd of gecontroleerd).

## [1.5.5] - 2026-07-05

### Opgelost
- **Embed "Accepteer cookies"-knop sloeg geen consent op**: de knop op de embed-placeholder verving alleen de placeholder door het iframe, zonder consent op te slaan of te loggen. Na een pagina-refresh was de embed daardoor weer geblokkeerd. De knop geeft nu consent voor de bijbehorende categorie (analytics/marketing): consent wordt opgeslagen in de cookie, gelogd in de consent log (nieuwe methode `embed-accept`), doorgegeven aan Google Consent Mode, en alle geblokkeerde embeds én scripts van die categorie worden direct vrijgegeven.
- **Embed herstel betrouwbaarder**: iframes worden nu via `insertAdjacentHTML` direct in het live document ingevoegd in plaats van verplaatst vanuit een losse DOM-node (laadde in sommige browsers niet), met fallback naar een nieuw iframe op basis van de opgeslagen bron-URL.
- **Embed-knoppen werken nu ook in thema's die clicks onderscheppen** (WPBakery/Salient): de event listener draait in de capture-fase zodat `stopPropagation()` van het thema de plugin niet meer blokkeert.

### Verwijderd
- **`assets/js/frontend.js` verwijderd**: dit bestand werd nergens geladen (alle frontend-JS zit inline in `cm_render_frontend()`), maar bevatte een verouderde duplicaat-implementatie met afwijkende cookie-structuur. De fixes uit v1.5.1–v1.5.4 die per abuis in dit dode bestand terechtkwamen, zitten nu op de juiste plek in de inline script.

## [1.5.4] - 2026-06-18

### Opgelost
- **Embed accept-knop — consent niet opgeslagen**: de "Accepteer cookies"-knop op de embed placeholder sloeg geen consent op in WPBakery/Salient omdat WPBakery een eigen click-handler heeft die `stopPropagation()` aanroept. De event listener gebruikt nu `capture: true` zodat deze altijd als eerste vuurt.
- **WPBakery/Salient — video onzichtbaar na consent**: de CSS-reset voor responsive video-wrappers dekte de WPBakery-specifieke klassen niet af (`wpb_video_wrapper`, `wpb_wrapper`, `vc_video-bg-container`, `nectar-video-wrap`). Toegevoegd.

## [1.5.3] - 2026-06-18

### Opgelost
- **Embed placeholder — video verschijnt nog steeds niet**: twee root-causes gevonden en opgelost:
  1. `restoreEmbeds` gebruikte `createElement` + `innerHTML` + `replaceChild` (iframe aangemaakt in een detached DOM-node). Browsers laden zo'n iframe soms niet. Vervangen door `insertAdjacentHTML` die de iframe direct in het live document injecteeert.
  2. `acceptAll` herlaadde altijd de pagina na 300ms. Hierdoor verdween de net-ingevoegde iframe vóór de video kon laden. De pagina herlaadt nu alleen nog als er geblokkeerde `<script>`-elementen op de pagina staan die opnieuw moeten worden uitgevoerd. Zijn er alleen embeds, dan is `restoreEmbeds` voldoende en is een reload overbodig.

## [1.5.2] - 2026-06-18

### Opgelost
- **Embed placeholder — video verschijnt niet direct na "Accepteer cookies"**: `restoreEmbeds` werd alleen aangeroepen bij terugkerende bezoekers, niet bij eerste acceptatie via de banner. Nu worden embeds direct hersteld in `acceptAll` en `savePrefs` vóór de pagina-refresh.

## [1.5.1] - 2026-06-18

### Opgelost
- **Embed placeholder — video niet zichtbaar na consent**: na het accepteren van cookies via de cookiebanner werden YouTube-video's (en andere embed-placeholders) niet automatisch ingeladen; een pagina-refresh was nodig. De placeholders worden nu direct vervangen door de echte iframe zodra consent gegeven wordt.
- **Embed placeholder — "Accepteer"-knop op video**: de accepteerknop op individuele embed-placeholders deed niets; klikken hierop slaat nu consent op voor de bijbehorende categorie en laadt de embed direct in.

## [1.5.0] - 2026-04-14

### Opgelost
- **Privacyverklaring — rechtsgrondslag contactformulier**: werd niet opgeslagen door een onjuiste behandeling als checkbox in de AJAX handler; toonde daardoor "Rechtsgrondslag: 0" op de frontend
- **Privacyverklaring — eigen velden**: werden niet opgeslagen (zelfde oorzaak als rechtsgrondslag); toonde "0" op de frontend als er geen eigen velden ingevuld waren
- **Privacyverklaring — sectie 2.1**: sectie verdween volledig als geen contactformulier-velden aangevinkt waren; toont nu altijd de h3-kop met de melding "Geen contactformulieren op deze website."
- **Mobiel — focusring buttons**: hoge-specificiteit WCAG `:focus-visible` regels overschreven de `(hover: none)` media query waardoor de blauwe outline toch zichtbaar bleef op touch; opgelost door overeenkomende selectors toe te voegen binnen de media query

## [1.4.9] - 2026-03-31

### Toegevoegd
- **Google cookies direct laden**: nieuwe optie in Algemeen → Analytische cookies waarmee Google cookies (GA4/GTM/UA) al direct bij het openen van de website worden ingeladen, zonder te wachten op toestemming van de bezoeker. Bij inschakeling wordt `analytics_storage` in Google Consent Mode v2 standaard op `granted` gezet. De instelling geeft een duidelijke waarschuwing dat dit niet conform de AVG is (artikel 6).
- Bij het inschakelen van "Google cookies direct laden" wordt "Standaard aangevinkt in het voorkeuren-venster" automatisch meeaangevinkt (zowel in de UI als bij opslaan server-side afgedwongen).

## [1.4.8] - 2026-03-26

### Toegevoegd
- **Privacyverklaring generator**: telefoonnummer veld, rechtsgrondslag contactformulier (select), nieuwsbrief sectie met grondslag en afmeldtekst, bewaartermijnen analytics en nieuwsbrief, sectie geautomatiseerde besluitvorming (Art. 22 AVG), klacht bij AP recht toegevoegd aan sectie rechten betrokkenen
- **Kleurenbeheer**: admincontroles voor "altijd actief" badge, derde partij badge (tekst/achtergrond/rand), servicenaam kleur en lege cookie tekst — zowel licht als donker thema
- **Licentiebeheer**: licentiesleutel is nu bewerkbaar (wijzig en heractiveer), veld toont huidig domein en vervalstatus
- **Cookie CSV export**: exporteerknop in Beheer-tab genereert UTF-8 CSV met alle cookies (naam, aanbieder, categorie, grondslag, doel, looptijd, domein, wildcard)
- **Embed placeholder**: titeltekst en accepteerknoptekst zijn nu instelbaar via admin (Embeds-tab); standaard "Accepteer om te bekijken" / "Accepteer cookies"
- Nieuwe default-opties: `txt_embed_accept_btn` (NL + EN), `pv_telefoon`, `pv_cf_grondslag`, `pv_nieuwsbrief_enabled`, `pv_nieuwsbrief_grondslag`, `pv_nieuwsbrief_afmelden`, `pv_bewaar_analytics`, `pv_bewaar_nieuwsbrief`, `pv_profilering_enabled`, `pv_profilering_tekst`

### Gewijzigd
- Rechtsgrondslag contactformulier omgezet van datalist naar `<select>` (datalist filterde opties op huidige waarde waardoor slechts 1 optie zichtbaar was)
- Embed placeholder: "Max. sites" rij verwijderd uit licentietabel in plugin-beheer
- Licentie activering: bij wijziging sleutel wordt de vorige sleutel eerst gedeactiveerd bij de licentieserver

### Opgelost
- **Mobiel**: focusring (WCAG outline) niet meer zichtbaar op touch-apparaten via `@media (hover: none) and (pointer: coarse)`
- **Mobiel**: overlay verdwijnt nu volledig na sluiten banner — `visibility: hidden` met vertraagde transitie elimineert GPU-laag artefacten in safe-area zones (notch/home indicator)
- **Consent intrekking**: dienst-niveau intrekking (granulaire prefs) triggert nu ook een pagina-reload zodat reeds geladen scripts worden gestopt en cookies niet opnieuw geplaatst worden
- CSS bug: `.cm-service` gebruikte hardcoded achtergrondkleur in plaats van de `--cm-service-bg` variabele

## [1.4.7] - 2026-03-25

- Initiële versie met licentiebeheer, Google Consent Mode v2, embed blocker en privacyverklaring generator
