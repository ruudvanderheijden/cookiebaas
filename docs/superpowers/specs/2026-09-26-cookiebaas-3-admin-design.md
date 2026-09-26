# Cookiebaas 3.0 — admin herbouw (ontwerp)

- **Datum:** 26 september 2026
- **Status:** ter review
- **Basis:** v2.4.5 (bugfixes uit de inventaris zijn daar al in uitgebracht)

## 1. Doel

De admin van Cookiebaas moet aanvoelen als een stuk WordPress zelf, met core-componenten en zonder eigen kleuren, emoji of zelfgebouwde widgets. Elk onderwerp staat op één logische plek.

**Succes betekent:**
- Een beheerder vindt elke instelling op de plek waar hij die verwacht, en nergens dubbel.
- De admin-markup bevat geen `style="`-attributen en geen emoji. De opmaak komt van WordPress core.
- Bestaande sites merken na de update niets aan hun banner, blokkering of opgeslagen instellingen.
- Elke instelling uit `cm_default_settings()` heeft precies één plek in de nieuwe admin. Een test bewaakt dat.

## 2. Scope

**Wel:**
- Nieuwe menu-indeling.
- Native componenten.
- De admin-code opsplitsen per pagina.
- Opslaan via de Settings API.
- De preview opschonen.
- Dode code verwijderen.

**Niet:**
- **Geen wijziging van opgeslagen data.** De option-keys (`cm_settings`, `cm_cookie_list`, `cm_privacy`, `cm_consent_version` enz.) en de opslagformaten blijven gelijk. De rijtabellen van de privacyverklaring blijven bijvoorbeeld JSON-strings.
- **Geen functionele frontendwijzigingen.** De enige frontend-aanraking is een refactor: de banner-markup gaat naar een eigen functie (§5.5). De HTML-output blijft identiek.
- **Universal Analytics blijft** (veld en frontend-output), op verzoek.
- **Geen nieuwe features.** Uitzondering zijn kleine dingen die vanzelf uit de herindeling volgen, zoals het tonen van de consent-versiegeschiedenis die al wordt opgeslagen, en EN-velden voor de embed-teksten die al in de defaults staan.

## 3. Menu en indeling

Het topmenu blijft "Cookiebaas" met `dashicons-privacy` op positie 81. De submenu's krijgen nieuwe slugs; de oude slugs verwijzen door (§6).

| Menu | Slug | Tabs (`&tab=`) |
|---|---|---|
| Overzicht | `cookiebaas` | — |
| Banner | `cookiebaas-banner` | Vormgeving · Teksten · Weergave · Gedrag |
| Blokkering | `cookiebaas-blokkering` | Google · Scripts · Embeds |
| Cookies | `cookiebaas-cookies` | Cookielijst · Scannen |
| Privacyverklaring | `cookiebaas-privacy` | — |
| Consent log | `cookiebaas-log` | Registraties · Bewaren en opnieuw vragen |
| Beheer | `cookiebaas-beheer` | Licentie · Backup · Geavanceerd · Reset · Info |

**Plaatsingsregels:**
- **Binnen Banner:** kleuren staan onder *Vormgeving*, teksten onder *Teksten*, plaats en uiterlijk-gedrag onder *Weergave*, en wanneer de banner verschijnt en hoe lang de keuze geldt onder *Gedrag*.
- **Export en reset staan naast hun data.** Beheer houdt alleen de volledige backup en import, "Alles resetten" en de licentie.

### 3.1 Waar elke instelling landt

**Overzicht**
- Statusblokken (`.card`): licentie, aantal cookies met laatste scan, toestemmingen in de laatste 30 dagen, consent-versie.
- Compliance-check: de bestaande checks uit `cm_render_compliance_content()`, als lijst met een statusicoon en een link "Oplossen" naar de juiste tab.
- De actie "Opnieuw controleren" vervalt; de pagina is gewoon actueel bij het laden.

**Banner › Vormgeving**
- `color_theme`: radio "Actief thema" (Licht / Donker).
- De schakelaar "Kleuren bewerken voor: Licht | Donker" is alleen UI en wordt niet opgeslagen. Hij activeert het thema **niet**.
- Per thema (prefix `color_` of `dm_`) vijf `<details>`-groepen:
  - **Venster:**
    - kleuren `popup_bg`, `title`, `body`, `link`;
    - `radius_popup` / `dm_radius_popup`;
    - `overlay_opacity` / `dm_overlay_opacity`.
  - **Knoppen:**
    - `accept_*`, `reject_*`, `allowall_*`, `outline_*`, `prefs_*`;
    - de optionele kleuren `accept_border`, `reject_border`, `allowall_border` en `outline_hover_bg`;
    - `radius_btn` / `dm_radius_btn`.
  - **Voorkeurenvenster en cookielijst:** `close_*`, `toggle_*`, `always_*`, `expand_*`, `cat_*`, `cookie_*`, `service_*`, `badge_*`.
  - **Zweefknop:** `float_icon_*`, `float_text_*`.
  - **Placeholder voor geblokkeerde video's:** `embed_bg`, `embed_title`, `embed_body`, `embed_btn_*`.
- Actie "Standaardkleuren herstellen" voor het thema dat bewerkt wordt.
- De preview staat rechts en schuift mee.
- `color_always_on_bg` heeft geen UI (dood, zie §6.3).

**Banner › Teksten**
- `banner_language`: radio "Taal van de banner" (Nederlands / English). Het label "Beta" vervalt.
- De schakelaar "Bewerken voor: Nederlands | English" is alleen UI. Hij toont de velden zonder of met `_en`.
- Groepen per taal:
  - **Hoofdbanner:** `txt_banner_title`, `txt_banner_body` (HTML), `txt_btn_prefs`, `txt_btn_reject`, `txt_btn_accept`.
  - **Voorkeurenvenster:** `txt_prefs_title`, `txt_prefs_body` (HTML), `txt_btn_allowall`, `txt_btn_rejectall`, `txt_btn_save`.
  - **Categorieën:** `txt_cat{1,2,3}_name`, `_short`, `_long`.
  - **Zweefknop:** `txt_float_label`.
  - **Video-placeholder:** `txt_embed_title`, `txt_embed_body`, `txt_embed_accept_btn`, `txt_embed_prefs`, telkens met `_en`. De EN-varianten zijn nieuw in de UI; de sleutels bestaan al.
- `txt_embed_btn` en `_en` krijgen geen UI (dood, zie §6.3).
- De preview staat rechts.

**Banner › Weergave**
- **Cookiebanner:**
  - `banner_position` (radio);
  - `banner_width_bottom_center`, `banner_width_center`, `banner_width_compact` (number + px, gelabeld per positie);
  - `banner_mobile_padding`.
- **Voorkeurenvenster:** `prefs_cookie_detail` (radio).
- **Zweefknop:**
  - `show_float_btn`, met een AVG-uitleg en de footer-link-code;
  - `float_btn_style`;
  - icoontype (alleen UI: standaard / eigen SVG / afbeelding);
  - `float_icon_custom_svg`, `float_icon_image_url` (met `wp.media`);
  - `float_icon_size`, `float_position`.
  - Het icoontype wist de niet-gekozen SVG of URL pas **bij opslaan** (in de sanitizer), niet al bij het wisselen.

**Banner › Gedrag**
- **Standaardkeuzes:** `analytics_default`.
- **Toestemming:** `expiry_months` (number), `respect_dnt`, `respect_gpc`, `reload_after_consent`.
- **Wie ziet de banner:** `geo_enabled`, `geo_outside_eu` (alleen zichtbaar als geo aan staat).
- **Uitzonderingen:**
  - `exclude_login_page`;
  - `exclude_woocommerce_checkout`;
  - `exclude_page_ids` via een `<select multiple name="…[]">`, dat de sanitizer omzet naar de bestaande komma-string;
  - `exclude_url_patterns`.
- **Subdomeinen:** `subdomain_sharing`, `subdomain_root_domain` (alleen zichtbaar als sharing aan staat).

**Blokkering › Google**
- `ga4_measurement_id`, `gtm_container_id`, `ua_tracking_id`.
- `google_consent_mode_advanced`, `google_url_passthrough`.
- `google_load_default`, verhuisd van Algemeen.
- "Hoe werkt het?" en de gids voor niet-Google-tags via GTM worden inklapbare `<details>`-documentatie.
- Regel blijft: `google_load_default` forceert `analytics_default`.

**Blokkering › Scripts**
- `block_analytics_patterns`, `block_marketing_patterns`.

**Blokkering › Embeds**
- `embed_blocker_enabled`.
- `embed_blocked_services` als checkboxlijst `name="…[]"`. De sanitizer zet dat om: alles aangevinkt wordt `''`, niets aangevinkt wordt `'none'`, anders een komma-lijst. Dat is dezelfde semantiek als in 2.4.5, maar nu in PHP.

**Cookies › Cookielijst**
- Editor voor `cm_cookie_list`, bovenaan de pagina.
- Acties:
  - "Plakken vanuit F12";
  - "CSV exporteren", verhuisd van Beheer › Export;
  - "Lijst leegmaken".
- Alleen-lezen sectie "Ingebouwde cookies".

**Cookies › Scannen**
- **Handmatige scan:** licentie vereist.
  - De resultaten krijgen "+ Lijst" en "Alle toevoegen". Dat blijft AJAX, maar er zijn geen onopgeslagen editorwijzigingen meer om te overschrijven, omdat de editor op een andere tab staat.
- **Automatische scan:**
  - instellingen `auto_scan_mode`, `auto_scan_interval`, `auto_scan_email`;
  - tijdstip van de volgende scan;
  - "Timer resetten".
- **Cookiedatabase:** status en "Database laden/bijwerken".

**Privacyverklaring**
- Eén formulier voor `cm_privacy`, met `<h2>`-secties in **dezelfde volgorde als de uitvoer**: 1, 2.1, 2.3, 3, 4, 5, 6, 7, 8, 10, 11, 12. Bedrijfsgegevens en DPO staan bovenaan, omdat het identiteitsgegevens zijn die in meerdere secties terugkomen.
- Een sectie "Weergave van de cookietabellen" (`pv_table_*`) komt **onderaan**, niet tussen §4 en §5.
- Knoppen naast de `h1` (`page-title-action`): "Verwerkingsregister exporteren" (verhuisd van Beheer) en "Standaardtekst herstellen".
- Een shortcode-regel bovenaan met een link naar Beheer › Info.

**Consent log › Registraties**
- `CM_Log_List_Table`:
  - filters als `subsubsub`, met aantallen: Alle / Akkoord (inclusief embed) / Geweigerd / Aangepast;
  - zoekvak op consent-ID;
  - bulkactie "Verwijderen";
  - een datumbereik en "CSV exporteren" in `tablenav`;
  - native paginering;
  - rij-acties *Bewijs | Verwijderen*.
- *Bewijs* opent een detailscherm (`&consent=<id>`) met alle opgeslagen velden, inclusief `config_hash`, `url` en `user_agent`, en een printknop.

**Consent log › Bewaren en opnieuw vragen**
- `log_retention_months`, met de status van de cron.
- "Iedereen opnieuw laten kiezen": verhoogt de consent-versie, met een optionele reden. Deze actie komt uit Reset.
- De versiegeschiedenis uit `cm_consent_changelog` wordt getoond; die werd al opgeslagen, maar nergens getoond.
- "Log leegmaken".

**Beheer**
- **Licentie:** status, activeren, controleren en deactiveren.
- **Backup:** JSON-export en -import.
- **Geavanceerd:** REST API-endpoint en `api_key`. "Genereren" en "Intrekken" worden server-side acties, niet meer client-side.
- **Reset:** "Alles resetten" plus de licentie lokaal wissen.
- **Info:** versie, auteur, alle shortcodes, "Snel aan de slag", disclaimer en contact.

## 4. Onderdelen (UI-regels)

- **Paginaframe:**
  - `.wrap`, `h1.wp-heading-inline`, `hr.wp-header-end`;
  - tabs als `nav-tab-wrapper` met echte links, door de server gerenderd;
  - versie en auteur niet in de kop.
- **Formulieren:**
  - `<h2>` plus een introzin per sectie;
  - `form-table` met `th > label[for]`, het veld en `p.description`;
  - radiogroepen en checkboxlijsten in een `fieldset` met `legend.screen-reader-text`.
- **Controls:**
  - radiokaarten worden gewone radio's;
  - sliders worden `input[type=number].small-text` met een eenheid;
  - de embed-chips worden checkboxes;
  - accordeons worden `<details><summary>`, alleen voor de kleurgroepen.
- **Kleurveld:**
  - native `input[type=color]` plus een **altijd zichtbaar** hex-tekstveld (`.code`), die met elkaar in sync blijven;
  - geen `wp-color-picker`, omdat klanten hex-codes plakken.
- **Optionele kleuren:**
  - het label eindigt op "(optioneel)";
  - een leeg hex-veld toont de placeholder "Geen rand";
  - een kleur kiezen of plakken zet de rand aan;
  - "Wissen" (`button-link`) verschijnt alleen als er een waarde is;
  - er is geen checkbox "Inschakelen" meer.
- **Afhankelijke velden:** een veld-definitie kan `show_if` hebben, bijvoorbeeld `subdomain_sharing=1`. Eén klein JS-stuk verbergt of toont de rij. Zonder JS is alles zichtbaar.
- **Meldingen:**
  - `notice notice-{success|info|warning|error}`: `is-dismissible` na acties, `inline` binnen de pagina;
  - geen `alert()`;
  - `confirm()` alleen bij destructieve acties;
  - een `beforeunload`-waarschuwing bij niet-opgeslagen wijzigingen.
- **Stijl:**
  - geen `style="`, geen hardcoded hex en geen emoji in de admin-markup;
  - iconen via dashicons;
  - accenten via `var(--wp-admin-theme-color)`;
  - `admin.css` alleen voor layout: de preview-kolom, de kleurrij en het kaartenraster van Overzicht.
- **Licentiemelding:** alleen op Cookiebaas-schermen, via een check op `get_current_screen()->id`, niet meer op elk admin-scherm.
- **Mockup:** op 26 september 2026 goedgekeurd in de visuele companion. Het bestand staat lokaal en is niet gecommit, want het gebruikt de WP-core-CSS. Het toont Overzicht, Banner › Vormgeving, Banner › Weergave en Consent log.

## 5. Techniek

### 5.1 Bestanden

```
includes/admin/menu.php            registratie, assets per pagina, redirects oude slugs, licentiemelding
includes/admin/fields.php          veld-definities per tab + renderer + type-bewuste sanitizing
includes/admin/actions.php         admin-post.php-handlers (downloads, resets, consent-versie, API-sleutel)
includes/admin/ajax.php            resterende AJAX: scan, cookiedatabase, licentie, scan-resultaat → lijst
includes/admin/page-overzicht.php
includes/admin/page-banner.php
includes/admin/page-blokkering.php
includes/admin/page-cookies.php
includes/admin/page-privacy.php
includes/admin/page-log.php        incl. class CM_Log_List_Table extends WP_List_Table
includes/admin/page-beheer.php
assets/js/admin-common.js          kleurveld-sync, show_if, beforeunload
assets/js/admin-preview.js         alleen Banner › Vormgeving/Teksten
assets/js/admin-cookies.js         editor, F12-import, scan
assets/js/admin-privacy.js         rijtabellen
assets/css/admin.css               alleen layout
```

`includes/admin.php` en de huidige `assets/js/admin.js` vervallen. De stijl blijft procedureel, met `cm_`-prefix, net als de rest van de plugin. De enige klasse is de list table, omdat WordPress dat vereist.

### 5.2 Veld-definities

`cm_admin_fields()` geeft `tab → secties → velden` terug. Een veld heeft:
- `key`
- `type`: text | textarea | html | color | color_optional | number | checkbox | radio | select | multiselect | checkboxes | media | custom
- `label`
- `description`
- `options` (voor radio, select en checkboxes)
- `min` en `max` (voor number)
- `show_if`
- `render` (alleen bij custom)

Eén renderer maakt de `form-table`-rij. Voor checkboxes rendert hij een verborgen `0` vóór de checkbox, zodat uitvinken ook opgeslagen wordt.

Kleuren staan per thema één keer gedefinieerd en worden met de prefix `color_` of `dm_` gegenereerd. Teksten staan één keer gedefinieerd en worden met de suffix `''` of `_en` gegenereerd.

### 5.3 Opslaan

- `register_setting()` voor `cm_settings`, `cm_privacy` en `cm_cookie_list`. De formulieren posten naar `options.php`.
- **De sanitize-callback van `cm_settings`** is de bestaande `cm_sanitize_settings( $input, $existing )`, uitgebreid met **type-bewuste sanitizing uit de veld-definities**:
  - **color:** `#rrggbb`, anders blijft de oude waarde staan en volgt een `add_settings_error`;
  - **color_optional:** `#rrggbb` of `''`;
  - **number:** geclampt tussen min en max;
  - **radio en select:** tegen de opties gecontroleerd;
  - **checkbox:** `0` of `1`;
  - **textarea:** `sanitize_textarea_field`;
  - **html:** `wp_kses` met `a[href,target]`, `strong` en `em`;
  - **multiselect en checkboxes:** omgezet naar het bestaande stringformaat.
  Sleutels zonder veld-definitie gaan zoals nu door `sanitize_text_field`.
- Omdat elke tab alleen zijn eigen velden post, blijft de rest ongemoeid. Dat doet de bestaande merge-logica al.
- **`cm_privacy`** gebruikt `cm_sanitize_privacy`. De rijtabellen posten als arrays, en de callback zet ze om naar de bestaande JSON-strings.
- **`cm_cookie_list`** gebruikt `cm_sanitize_cookie_list`.
- **Cache legen op één plek:** hooks op `add_option_…` én `update_option_…` voor `cm_settings`, `cm_cookie_list`, `cm_privacy` en `cm_consent_version` roepen `cm_purge_page_caches()` aan. Beide zijn nodig, want de eerste keer opslaan vuurt alleen `add_option_…`. De losse aanroepen uit 2.4.5 vervallen.
- **Sanitizers moeten idempotent zijn.** WordPress hangt de sanitize-callback van `register_setting` aan `sanitize_option_{naam}` en roept hem daardoor bij **élke** `update_option` aan: ook bij migraties, resets, de cron van de automatische scan, en twee keer bij de allereerste opslag (bekend gedrag van WordPress). Een volledige, al geldige array moet ongewijzigd door de callback komen. Een test legt dat vast.
- **Via `admin-post.php`**, met nonce, capability-check en een redirect terug met een notice:
  - log-CSV, cookielijst-CSV, verwerkingsregister, JSON-backup;
  - import;
  - resetten (alles, kleuren per thema, privacy, cookielijst, log);
  - iedereen opnieuw laten kiezen;
  - API-sleutel genereren en intrekken;
  - timer van de automatische scan resetten.
- **AJAX blijft alleen voor:**
  - de scan (batches);
  - de cookiedatabase laden;
  - licentie activeren, controleren en deactiveren;
  - een scanresultaat aan de lijst toevoegen.
  Er zijn aparte nonces per actie in plaats van één gedeelde.
- `CM_DATA.settings` vervalt. Daarmee komt de API-sleutel niet meer in de JS terecht.

### 5.4 Assets

- Per pagina laden via de hook-suffixen die `add_submenu_page()` teruggeeft. De vaste lijst van 20 hooknamen vervalt.
- `wp_enqueue_media()` en `frontend.css` alleen op Banner.

### 5.5 Preview

- De banner-markup (banner, voorkeurenvenster, zweefknop) gaat uit `cm_render_frontend()` naar een eigen functie. De frontend en de preview gebruiken die allebei. Er komt een vergelijkbare helper voor de embed-placeholder, die `cm_build_embed_placeholder()` al grotendeels is.
- De preview-container heeft `inert` en laadt alleen `frontend.css`.
- `admin-preview.js` leest de formuliervelden en zet de CSS-variabelen en teksten.
- De nagebouwde preview-HTML, de ongeveer 90 regels preview-CSS en de afwijkende fallbackkleuren in JS vervallen.
- Grens: `tests/test-cache-safety.php` en de andere frontend-suites blijven ongewijzigd groen.

## 6. Compatibiliteit en migratie

### 6.1 Oude URL's

Op `admin_init` (vóór de rechtencontrole van WordPress) wordt `?page=` doorverwezen:

| Oud | Nieuw |
|---|---|
| `cookiemelding` | `cookiebaas` |
| `cookiemelding-cookies` | `cookiebaas-cookies` |
| `cookiemelding-privacy` | `cookiebaas-privacy` |
| `cookiemelding-log` | `cookiebaas-log` |
| `cookiemelding-beheer` | `cookiebaas-beheer` |

Links in al verstuurde scan-mails en in bladwijzers blijven zo werken. Oude `#tab=`-hashes vallen terug op de eerste tab.

### 6.2 Data

- Er is geen migratie nodig. Alle option-keys en formaten blijven gelijk.
- De `cm_version`-migraties in `cookiemelding.php` blijven bestaan.

### 6.3 Wat verdwijnt

**Alleen de UI** (de sleutels blijven in de defaults, zodat de data blijft):
- `txt_embed_btn` en `_en`: het veld had geen effect.
- `color_always_on_bg`: dood, had al geen UI.

**Code:**
- De 15 dode hooknamen.
- De dode JS-handlers (`#cm-reset-defaults`, `#cm-reset-consent`, `#cm-reset-log`, `#cm-reset-cookielist`, `#cm-reset-privacy`, `#cm-license-save-url`, `#cm-log-refresh-btn`, `[data-tab="logging"]`, `cmToggleFloatIconRows`, `stripTags`).
- Het taartdiagram, de ongebruikte CSS en de dode markup (`#cm-notice`).

**Handlers:** `cm_save_license_url` (dood).

**Merknaam-resten:** de backupnaam `cookiemelding-backup` wordt `cookiebaas-backup`, en de JS- en CSS-headers worden bijgewerkt.

### 6.4 Eenmalige melding

Na de update naar 3.0 toont de Overzicht-pagina één keer een wegklikbare `notice-info`: "De admin heeft een nieuwe indeling", met een link naar de tabel "Waar staat wat?" in de changelog.

## 7. Foutafhandeling

- **Een ongeldige waarde bij opslaan** (hex, getal buiten bereik, onbekende optie): de oude waarde blijft staan, er volgt een `add_settings_error` per veld, en de rest wordt gewoon opgeslagen.
- **Een mislukte `admin-post`-actie:** een redirect terug met een `notice-error` die zegt wat er misging. Er wordt nooit een succesmelding getoond als iets faalde (zie de bug in "Alles resetten" in 2.4.4).
- **AJAX (scan, cookiedatabase, licentie):** fouten verschijnen als inline `notice-error` in de betreffende sectie. Een netwerkfout geeft ook een melding; die bleef tot nu toe stil.
- **Import:** een ongeldig bestand geeft een foutmelding en er wordt niets geschreven. Bij een geldig bestand gaat alles door de sanitizers (zoals in 2.4.5).

## 8. Testen

Het blijft de bestaande opzet zonder dependencies (`php tests/run.php`), met deze nieuwe checks:

- **Volledigheid:** elke sleutel uit `cm_default_settings()` staat op precies één tab, of in een expliciete lijst "geen UI" (`txt_embed_btn`, `txt_embed_btn_en`, `color_always_on_bg`). `api_key` is een custom veld op Beheer › Geavanceerd. Dit is de belangrijkste nieuwe test.
- **Renderer:** elk veld krijgt een `label for` met een bijbehorend `id`, checkboxes krijgen een verborgen `0`, en kleurwaarden zijn `#rrggbb` (dat vangt de rgb()-bug uit 2.4.5 structureel af).
- **Sanitizing:** een volledige, geldige array komt ongewijzigd door elke sanitizer (idempotent); gedeeltelijke tab-invoer laat andere velden staan; ongeldige hex, getallen buiten bereik en onbekende enums worden afgewezen; embed-diensten en paginakeuze worden omgezet naar het bestaande stringformaat; de rijtabellen van de privacy worden omgezet naar dezelfde JSON-strings.
- **Redirects:** elke oude slug gaat naar de juiste nieuwe.
- **Log:** de `cm_log_where`-tests uit 2.4.5. De aantallen per filter gebruiken dezelfde functie, dus er is geen aparte databasetest nodig.
- **Broncheck:** geen `style="`, geen emoji en geen hardcoded hex in `includes/admin/` en in de nieuwe JS.
- **Frontend:** de bestaande suites, vooral `test-cache-safety.php`, blijven ongewijzigd groen na de refactor van de banner-markup.

Handmatig test Ruud zelf op de lokale Brinckers-site (WordPress 7.1, TranslatePress, LiteSpeed).

## 9. Uitrol

- Er wordt gebouwd op `main`, per menu-item in losse commits, zonder tussentijdse releases.
- Hotfixes voor 2.4.x tijdens de bouw gaan via een tijdelijke branch vanaf tag `v2.4.5`, en worden daarna naar `main` gecherry-pickt.
- Release 3.0.0 volgt als alle pagina's af zijn en de tests groen zijn. De changelog krijgt een tabel "Waar staat wat?" (oud → nieuw) voor bestaande klanten.

## 10. Genomen beslissingen

| Onderwerp | Besluit |
|---|---|
| Scope | Admin nieuw, data blijft |
| Bugs uit de inventaris | Eerst los uitgebracht als 2.4.5 |
| Live preview | Blijft, opgeschoond, met echte banner-markup |
| Indeling | Route A: 7 menu-items per onderwerp |
| Kleurkiezer | Kleurvlak plus een altijd zichtbaar hex-veld; geen `wp-color-picker` |
| Optionele kleuren | "(optioneel)" in het label, leeg = geen, "Wissen" alleen als er een waarde is |
| Universal Analytics | Blijft voorlopig staan |
| Opslaan | Settings API per tab; `admin-post` voor acties; AJAX alleen voor lange of live acties |
