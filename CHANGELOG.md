# Changelog — Cookiebaas

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
