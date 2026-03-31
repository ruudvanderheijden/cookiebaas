# Changelog — Cookiebaas

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
