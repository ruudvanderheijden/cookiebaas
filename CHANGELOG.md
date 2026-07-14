# Changelog — Cookiebaas

## [1.7.9] - 2026-07-14

### Gewijzigd
- **Herladen na akkoord is nu een instelling en staat standaard uit.** Na het geven van toestemming worden scripts en embeds weer dynamisch vrijgegeven zonder pagina-herlaad (geen flits, scrollpositie en formuliervelden blijven behouden) — het gedrag van vóór 1.7.7, nu bovenop de cache-veilige consent-afhandeling. Bij het **intrekken** van toestemming (weigeren na eerder akkoord, of een dienst uitzetten) wordt altijd herladen; dat is nodig om al draaiende scripts te stoppen. Nieuwe checkbox "Herladen na akkoord" op het Algemeen-tabblad voor wie na acceptatie een schone, volledig gemeten `page_view` van de landingspagina wil in plaats van een gemodelleerde.

## [1.7.8] - 2026-07-14

### Opgelost
- **GA-cookies (`_ga`, `_ga_XXXXXXXXXX`) konden terugkomen na een weigering.** Bij het intrekken van toestemming werden de cookies verwijderd en werd de pagina herladen, maar de `gtag('consent','update','denied')` werd op dat pad nooit verstuurd. Google dacht dus nog steeds toestemming te hebben, rondde in dat venster nog één of twee hits af (zichtbaar als `gcs=G111` ná de weigering) en schreef daarbij `_ga` en `_ga_XXXXXXXXXX` opnieuw — precies nadat de plugin ze had verwijderd. De consent-update (Google, GTM-dataLayer én Microsoft UET) wordt nu als **eerste** actie na elke keuze verstuurd, vóór het opruimen en herladen. Daarnaast volgt er vlak vóór de herlaad nog een tweede opruimronde.
- **Meer trackingcookies worden opgeruimd bij intrekking**: Google Ads (`_gcl*`), Microsoft UET (`_uetsid`, `_uetvid`), TikTok (`_ttp`, `_tt_*`), LinkedIn (`bcookie`, `lidc`, `li_sugr`, `UserMatchHistory`), Pinterest (`_pin_unauth`), Microsoft Clarity (`_clck`, `_clsk`) en WooCommerce Order Attribution (`sbjs_*`).

## [1.7.7] - 2026-07-14

### Opgelost — kritiek (privacy)
- **Paginacache serveerde de toestemming van een andere bezoeker.** De plugin rende de consent-status server-side in de HTML (`gtag('consent','update','granted')`). Vulde een bezoeker mét toestemming de paginacache van LiteSpeed/WP Rocket/Cloudflare, dan kreeg **iedere volgende bezoeker die gecachte "granted"-status** — inclusief bezoekers die nog niets gekozen of juist geweigerd hadden. Google Analytics vuurde dan volledig (`gcs=G111`) en plaatste `_ga`-cookies zonder toestemming. Vastgesteld op een productiesite met LiteSpeed: een schone browser zonder consent-cookie kreeg HTML met `cm_method: accept-all`.
  De HTML is nu volledig **cache-veilig**: identiek voor elke bezoeker. De consent-status wordt in de browser uit de cookie gelezen en de `gtag('consent','update')` wordt client-side verstuurd — synchroon vóór de Google-tag laadt, dus er worden nooit cookies met een verkeerde status gezet. Dit verklaart ook waarom cookies na een weigering leken terug te komen: de gecachte pagina gaf direct weer toestemming.
- **Blocker sloopte de tags binnen Google Tag Manager.** Scripts die GTM zélf injecteert (o.a. `gtag/js` voor GA4) werden door de scriptblocker gestript, waardoor GA4 in advanced mode nooit initialiseerde en er ook geen cookieloze pings werden verstuurd. In Consent Mode advanced worden Google's eigen meetdomeinen nu niet meer geblokkeerd — Consent Mode regelt die tags zelf. Een GTM-snippet uit een thema of andere plugin wordt in advanced mode nu ook doorgelaten (en respecteert de consent-defaults). In basic mode blijft alles geblokkeerd tot toestemming.
- **Cookies werden niet verwijderd na een weigering** (zie hierboven): de gecachte pagina zette na de herlaad direct weer `granted`, waarna GA de `_ga`-cookies opnieuw plaatste.

### Gewijzigd
- **De pagina herlaadt nu na elke keuze** (akkoord, weigeren of opslaan), zodat scripts, embeds en cookies gegarandeerd in een consistente staat komen. De consent-logging gaat via `sendBeacon` en overleeft de herlaad. Automatische afwijzing via Do Not Track / Global Privacy Control herlaadt niet (daar valt niets vrij te geven).
- **Paginacache wordt automatisch geleegd** na een plugin-update en na het opslaan van instellingen (LiteSpeed, WP Rocket, W3 Total Cache, WP Super Cache, SiteGround, WP Engine, Cachify + `cm_purge_page_caches` hook). Zo verdwijnen vergiftigde cachepagina's van oudere versies direct.

## [1.7.6] - 2026-07-14

### Gewijzigd
- **URL passthrough is nu een instelling en staat standaard uit.** De plugin zette voorheen altijd `gtag('set','url_passthrough',true)`, waardoor Google-tags bij geweigerde/nog niet gegeven consent een `_gl=`-parameter aan alle interne links plakten (lelijke URL's zoals `?_gl=1*…*_ga*…`, en mogelijke cache-fragmentatie per unieke URL). Sinds de advanced-modus laadt Google ook vóór consent, waardoor dit zichtbaar werd. Nieuwe checkbox "URL passthrough" bij de Google-integratie (standaard uit) voor wie de extra attributie/modellering bewust wil; `ads_data_redaction` blijft altijd aan.

## [1.7.5] - 2026-07-13

### Opgelost — live preview Vormgeving
- **Zeven preview-stijlen negeerden de instelling**: de admin-CSS gebruikte hardcoded kleuren of de verkeerde variabele terwijl de preview-JS de juiste waarde al doorzette. Werken nu direct: categorie-beschrijving, detailtekst, cookienaam, cookie-meta, toggle-kleur (uit), categorie-hover en de tekstkleur van de "Altijd actief"-badge (die pakte de toggle-kleur).
- **Radiogroepen lazen altijd de eerste optie**: de preview-helper `get()` las bij radio's de waarde van het eerste element in plaats van het aangevinkte — o.a. bannerpositie en zweefknop-instellingen kwamen daardoor verkeerd binnen.
- **Radio's, checkboxes en selects verversten de preview niet**: alleen tekst-, kleur- en slider-velden waren gekoppeld. Nu triggert elke wijziging de preview.

### Toegevoegd — live preview Vormgeving
- **Zweefknop-preview**: nieuw preview-blok dat stijl (icoon/tekstknop), grootte, positie (links/rechts), eigen SVG-icoon én alle kleuren incl. hover live toont; verdwijnt als de zweefknop uitstaat.
- **Embed placeholder-preview**: nieuw preview-blok met titel, tekst, knop (incl. hover) en alle embed-kleuren.
- **Dienst-rij, derde-partij-badge en lege-categorie-regel** in het voorkeuren-venster, zodat ook servicenaam-, badge- en "geen cookies"-kleuren zichtbaar zijn.
- **Linkkleur zichtbaar**: banner- en voorkeurentekst behouden nu `a`/`strong`/`em` in de preview (voorheen werd alle opmaak gestript).
- **Bannerpositie en -breedtes werken door**: linksonder/gecentreerd/rechtsonder/midden en de bijbehorende breedte-instellingen zijn nu zichtbaar in de preview, net als de optie "cookie-details tonen".

## [1.7.4] - 2026-07-13

### Toegevoegd
- **`cm_consent_update` dataLayer-event**: bij elke consentkeuze én bij elke paginalaad met bestaande consent pusht de plugin nu een event naar de GTM dataLayer met `cm_analytics`/`cm_marketing` (boolean), `cm_method` en de vier consent-signalen — exact het formaat dat de admin-handleiding "Niet-Google tags via GTM" al documenteerde maar dat nog niet bestond. Hiermee werken de beschreven GTM-triggers ("vuur Meta Pixel pas na marketing-consent") nu daadwerkelijk.
- **Microsoft UET consent update**: `window.uetq.push('consent','update',...)` bij elke keuze en bij paginalaad met bestaande consent — ook dit stond al in de handleiding maar ontbrak in de code.
- **Omgevingsdetectie cookiescan uitgebreid**: `wordpress_logged_in_` en `wordpress_sec_` worden altijd vermeld; `comment_author_` bij open reacties; `wp-postpass_` zodra er wachtwoordbeveiligde berichten zijn; bij WooCommerce de winkelwagen-cookies plus de **Order Attribution-cookies** (`sbjs_current`, `sbjs_first`, `sbjs_session` e.a., categorie analytics) die WooCommerce sinds 8.5 standaard bij álle bezoekers zet.

### Opgelost
- **Prefix-matcher herkende geen patronen eindigend op `-`**: `wp-settings-` matchte daardoor nooit een echte `wp-settings-3` cookie (nu via gedeelde `cm_cookie_prefix_match()`, ook voor de NL-beschrijvingen).
- **Dode kennisbank-entries gerepareerd**: `wordpress_logged_in` en `wordpress_sec` zonder afsluitende underscore matchten nooit een echte cookie; de WooCommerce-sessiecookie heette in twee lijsten `woocommerce_session_` terwijl de echte naam `wp_woocommerce_session_` is.

## [1.7.3] - 2026-07-13

### Opgelost
- **Kritieke scripts beschermd tegen "Delay JS" van cache-plugins**: LiteSpeed Cache (optie "Load JS Deferred: Delayed") herschreef álle inline scripts naar `type="litespeed/javascript"` en voerde ze pas uit bij de eerste gebruikersinteractie (scroll/muisbeweging). Daardoor werden de cookiebanner, het consent-default script, de scriptblocker én de GTM/GA4-loader uitgesteld — GTM leek niet te laden en de banner verscheen pas na interactie. Alle kritieke Cookiebaas-scripts dragen nu `data-no-defer="1"` (LiteSpeed) en `nowprocket` (WP Rocket "Delay JavaScript execution"), waardoor cache-plugins ze met rust laten. Geconstateerd en geverifieerd op een productiesite met LiteSpeed.

## [1.7.2] - 2026-07-13

### Toegevoegd
- **Omgevingsdetectie in de cookiescan**: cookies die de anonieme crawl principieel niet kán zien worden nu op basis van de serveromgeving toegevoegd:
  - `wordpress_test_cookie` (functioneel) — wordt alleen op de inlogpagina gezet en de scan crawlt uitsluitend gepubliceerde content; elke WordPress-site heeft hem.
  - `_lscache_vary` (functioneel) — LiteSpeed zet deze alleen als de cache-variant afwijkt (bijv. ingelogd), dus nooit richting een anonieme scan-request. Wordt toegevoegd zodra de LiteSpeed Cache-plugin actief is of de `x-litespeed-cache` header in de responses zit.
- **Google-cookies op het google.com-domein** (`NID`, `__Secure-ENID`, `__Secure-BUCKET`) toegevoegd aan de kennisbank, de YouTube-embed-detectie en de service-mapping: deze third-party cookies plaatst Google zelf zodra ingesloten content (YouTube e.d.) laadt en zijn per definitie onzichtbaar voor een server-side scan van de eigen site.

### Gewijzigd
- **Cookieloze pings mogen weer doorkomen in Consent Mode advanced** (herziening van 1.7.1, die nooit gereleased is): de Google-tag laadt altijd — ook vóór een keuze en na een weigering — met alle consent-signalen op `denied`. Google-tags plaatsen dan geen cookies en versturen geen volledige metingen, maar wél cookieloze pings (`gcs=G100`) zodat bezoekersaantallen en conversies via modellering geschat kunnen worden. `wait_for_update` staat weer op 500 ms en de banner stuurt ook bij een weigering de `gtag('consent','update')`.

### Behouden uit 1.7.1
- **Weigering wint altijd van "Google cookies direct laden"** (`google_load_default`): na een expliciete weigering werd de consent-update onterecht op `granted` gezet en bleef GA met cookies laden. Die optie geldt nu alleen zolang er geen keuze is gemaakt.
- Aangescherpte admin-uitleg, incl. de kanttekening dat niet-Google tags binnen GTM (bijv. Meta Pixel) geen Consent Mode kennen — geef die in GTM zelf een consent-vereiste ("Require additional consent for tag to fire").

## [1.7.1] - 2026-07-13 _(niet gereleased, herzien in 1.7.2)_

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
