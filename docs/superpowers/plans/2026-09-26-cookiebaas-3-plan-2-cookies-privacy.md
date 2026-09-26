# Cookiebaas 3.0 — Plan 2: Cookies en Privacyverklaring

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** De pagina's **Cookies** (tabs Cookielijst en Scannen) en **Privacyverklaring** toevoegen aan de nieuwe admin "Cookiebaas 3", WordPress-native en opgeslagen via de Settings API. De oude schermen blijven werken tot plan 3.

**Architecture:** Dit plan bouwt op plan 1 (`docs/superpowers/plans/2026-09-26-cookiebaas-3-plan-1-fundament-banner-blokkering.md`, uitgevoerd). Het veldregister krijgt twee nieuwe veldtypes:
- **`rows`:** een rijen-editor, voor de privacytabellen en de cookielijst;
- **`checkgroup`:** meerdere checkbox-instellingen in één rij.

Tabs kunnen een eigen settings-groep, eigen waarden en knoppen naast de titel krijgen. De cookielijst (`cm_cookie_list`) en de privacyverklaring (`cm_privacy`) krijgen elk een `register_setting` met een idempotente sanitize-callback. Wat bij klikken direct iets moet doen, gaat via `admin-post.php`: F12-import, CSV-downloads, leegmaken, herstellen en de scantimer. AJAX blijft alleen voor de scan, de cookiedatabase en "scanresultaat toevoegen", met een eigen nonce per actie. Het samenvoegen gebeurt voortaan op de server, dus de scan kan niets meer overschrijven.

**Tech Stack:** WordPress 7.1 (core admin-CSS), procedurele PHP met `cm_`-prefix en PHP 7.0-compatibele syntax (geen arrow functions, `match`, nullsafe of `array_key_last`), vanilla JS, tests zonder dependencies (`php tests/run.php`).

**Spec:** `docs/superpowers/specs/2026-09-26-cookiebaas-3-admin-design.md`

## Global Constraints

- **Versie:** `CM_VERSION` en de plugin-header blijven **2.4.5**. Niets in dit plan wordt uitgebracht. Commits direct op `main`, niet pushen. Commitberichten eindigen met `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- **Data blijft:** geen wijziging van option-keys of opslagformaten.
  - `cm_cookie_list` blijft een lijst van `array( name, provider, purpose, duration, category, builtin )`.
  - De drie rijtabellen in `cm_privacy` (`pv_doeleinden`, `pv_optout_links`, `pv_ontvangers`) blijven **JSON-strings**, met dezelfde sleutels per rij (doel/grondslag/termijn, naam/url, partij/doel/locatie).
  - Checkboxes blijven `'1'`/`'0'`.
- **Frontend blijft functioneel identiek.** Dit plan raakt `includes/frontend.php` en `includes/privacy.php`-uitvoer (de shortcodes) niet.
- **Nieuwe admin-code:**
  - Wat telt als nieuwe admin-code: `includes/admin/*.php`, `assets/js/admin-common.js`, `assets/js/admin-preview.js`, `assets/js/admin-cookies.js` en `assets/css/admin-layout.css`.
  - Daarin: **geen** `style="`, **geen** emoji, **geen** 6-cijferige hex-kleuren, **geen** `&#`-entities (ook niet in commentaar of strings). Echte tekens als `…`, `’` en `›` mogen wel.
  - Getest in `tests/test-admin3-frame.php`.
- **Oude admin blijft werken tot plan 3:**
  - `includes/admin.php` en `assets/js/admin.js` blijven geladen.
  - Oude AJAX-opslag van cookielijst en privacy loopt door de nieuwe sanitizers en moet de oude formaten blijven accepteren (JSON-strings voor de rijtabellen).
  - Oude scan- en database-JS stuurt de gedeelde nonce `cm_save_settings`. De AJAX-handlers accepteren die tot plan 3 náást de nieuwe nonce per actie.
- **Toegang en nonces:** capability `manage_options` voor alles, een eigen nonce per actie (`cm_<actie>`).
- **Plan 1-afspraken gelden door:**
  - `cm_sanitize_*` moeten idempotent zijn, omdat WordPress de sanitize-callback bij elke `update_option` aanroept.
  - `null` uit `options.php` (option ontbreekt in de POST) wist niets.
  - De paginacache wordt al geleegd door option-hooks (`includes/admin/settings.php`). Voeg dus **geen** losse `cm_purge_page_caches()`-aanroepen toe.
- **Taal:** alle UI-teksten zijn Nederlands. Een bestaande tekst mag beter geformuleerd worden, maar niet inhoudelijk veranderen.
- **Tijd:**
  - `cm_auto_scan_next` en `cm_auto_scan_last` staan als UTC (`gmdate`); toon ze met `wp_date( $fmt, strtotime( $v . ' UTC' ) )`.
  - `cm_cookie_db_updated` staat als lokale tijd (`current_time('mysql')`); toon die met `mysql2date()`.
- **Handmatige browsercontroles doet Ruud zelf.** Implementers slaan die stappen over en melden ze in het rapport.

## Review Focus

1. **Alle rijen verwijderen en opslaan leegt de lijst.** Dat geldt voor de cookielijst en de drie privacytabellen. Een lege tabel mag niet "niets gebeurt" opleveren. Getest in Taak 1 (rijen → `'[]'`) en Taak 2 (cookielijst `''` → `array()`).
2. **Scanresultaten toevoegen overschrijft niets.** Bestaande rijen blijven, en er komen geen dubbelingen of ingebouwde cookies bij. Getest in Taak 4.
3. **De oude admin blijft tijdens de bouw correct opslaan.** Die post de privacy-rijtabellen als JSON-string, checkboxes als `'0'`/`'1'`, en een grondslag uit de lijst. Getest in Taak 6.
4. **Scannamen of -omschrijvingen met HTML voeren nooit iets uit in de admin.** De server strip tags, en de JS gebruikt alleen `textContent`. Getest in Taak 4 (server); de JS wordt beoordeeld in de review van Taak 5.
5. **Een opgeslagen grondslag die niet in de zes AVG-opties staat** (een oude vrije tekst) blijft zichtbaar en behouden. Hij wordt niet stil vervangen door de eerste optie. Getest in Taak 1 (select toont de huidige waarde) en Taak 6 (de sanitizer houdt hem).

---

## Bestandsoverzicht

| Bestand | Verantwoordelijkheid | Taak |
|---|---|---|
| `includes/admin/menu.php` | Tab-sleutels `group`, `values`, `title_actions`; menu-items Cookies en Privacyverklaring; assets Cookies-pagina | 1, 2, 5, 7 |
| `includes/admin/fields.php` | Veldtypes `rows` en `checkgroup`, `cm_rows_decode()`, `cm_admin_render_rows()`; select toont een onbekende huidige waarde | 1 |
| `includes/admin/settings.php` | `rows`-sanitizing (`cm_sanitize_rows`); `register_setting` voor `cm_cookie_list` en `cm_privacy`, met callbacks | 1, 2, 6 |
| `includes/admin/actions.php` | `cm_admin_action_url()` (GET-links met nonce), `cm_admin_send_csv()`, nieuwe meldingscodes | 1, 3, 4, 6 |
| `includes/admin/page-cookies.php` | Tabs Cookielijst en Scannen, F12-parser, CSV-rijen, scan-statussen, herplannen van de scan-cron | 2, 3, 4 |
| `includes/admin/ajax.php` | `cm_admin_verify_ajax()`, `cm_ajax_scan_add` + `cm_merge_cookie_list()` + `cm_scan_result_to_row()` | 4 |
| `includes/admin/page-privacy.php` | Verwerkingsregister-rijen, grondslagen, acties export/herstellen (Taak 6); de pagina zelf (Taak 7) | 6, 7 |
| `assets/js/admin-common.js` | Rijen toevoegen en verwijderen | 1 |
| `assets/js/admin-cookies.js` | Scan in batches, resultaattabel, "Toevoegen", cookiedatabase laden | 5 |
| `assets/css/admin-layout.css` | Rijen-editor en scanvoortgang (alleen layout) | 1, 5 |
| `includes/admin.php` (oud) | Nonce-check van scan/database via `cm_admin_verify_ajax()`; cookie-CSV en verwerkingsregister via de nieuwe rij-builders | 3, 4, 6 |
| `includes/privacy.php` | `cm_sanitize_privacy()` type-bewust + bestaande waarden; oude AJAX-opslag geeft de bestaande waarden mee | 6 |
| `cookiemelding.php` | `require_once` van `page-cookies.php`, `ajax.php`, `page-privacy.php` | 2, 4, 6 |
| Tests | Uitbreiding van `test-admin3-{fields,settings,actions,frame,registry}.php`, nieuw: `tests/test-admin3-cookies.php`, `tests/test-admin3-privacy.php` | alle |

---

### Task 1: Fundament-uitbreidingen (rijen, checkgroep, tab-opties, actie-URL)

**Files:**
- Modify: `includes/admin/fields.php`
- Modify: `includes/admin/settings.php`
- Modify: `includes/admin/menu.php`
- Modify: `includes/admin/actions.php`
- Modify: `assets/js/admin-common.js`
- Modify: `assets/css/admin-layout.css`
- Test: `tests/test-admin3-fields.php`, `tests/test-admin3-settings.php`, `tests/test-admin3-actions.php`, `tests/test-admin3-frame.php`

**Interfaces:**
- Produces:
  - Veldtype `rows`: sleutels `columns` (array `kolom => array( 'label', 'type' => 'text'|'url'|'select', 'options', 'placeholder', 'suggestions' )`) en `add_label`. Opgeslagen als JSON-string (`cm_sanitize_field_value` geeft `wp_json_encode(...)` terug).
  - Veldtype `checkgroup`: sleutel `keys` (array `sleutel => label`). In de UI één rij. `cm_admin_field_list()` splitst hem in losse `checkbox`-velden.
  - `cm_rows_decode( $value ): array`
  - `cm_admin_render_rows( string $name, array $columns, array $rows, string $add_label ): void`
  - `cm_sanitize_rows( $raw, array $columns ): array`
  - Tab-sleutels:
    - `'group'`: settings-groep, standaard `cookiebaas_settings`;
    - `'values'`: callable die de waarden-array teruggeeft, standaard `cm_get_settings()`;
    - `'title_actions'`: callable, rendert knoppen naast de `h1`.
  - `cm_admin_action_url( string $action, array $args = array() ): string`: een link naar `admin-post.php?action=cm_<actie>` met nonce `cm_<actie>`, voor downloads.
- Consumes: alles uit plan 1 (`cm_field`, `cm_admin_render_field_row`, `cm_admin_render_control`, `cm_sanitize_field_value`, `cm_admin_register_action`).

- [ ] **Step 1: Schrijf de falende tests**

In `tests/test-admin3-fields.php`, vóór `exit( cm_test_summary() );`:

```php
cm_test_group( 'Rijen-editor' );
$cols = array(
    'doel'      => array( 'label' => 'Doel' ),
    'grondslag' => array( 'label' => 'Grondslag', 'suggestions' => array( 'Toestemming' ) ),
    'cat'       => array( 'label' => 'Categorie', 'type' => 'select', 'options' => array( 'functional' => 'Functioneel', 'marketing' => 'Marketing' ) ),
);
ob_start();
cm_admin_render_rows( 'cm_privacy[pv_doeleinden]', $cols, array( array( 'doel' => 'Contact <b>', 'grondslag' => 'Toestemming', 'cat' => 'marketing' ) ), 'Rij toevoegen' );
$h = ob_get_clean();
cm_assert( 'verborgen lege waarde vóór de rijen (alles verwijderen = leeg opslaan)', strpos( $h, '<input type="hidden" name="cm_privacy[pv_doeleinden]" value="">' ) !== false );
cm_assert( 'bestaande rij met genummerde namen, waarde ge-escaped', strpos( $h, 'name="cm_privacy[pv_doeleinden][0][doel]" value="Contact &lt;b&gt;"' ) !== false );
cm_assert( 'select behoudt de opgeslagen keuze', preg_match( '/name="cm_privacy\[pv_doeleinden\]\[0\]\[cat\]"[^>]*>.*?<option value="marketing" selected>/s', $h ) === 1 );
cm_assert( 'sjabloonrij met __i__ in een template', strpos( $h, '<template>' ) !== false && strpos( $h, '[__i__][doel]' ) !== false );
cm_assert( 'suggesties via een datalist', strpos( $h, '<datalist id=' ) !== false && strpos( $h, ' list="' ) !== false );
cm_assert( 'volgende index = aantal rijen', strpos( $h, 'data-cm-next="1"' ) !== false );
cm_assert( 'invoervelden hebben een toegankelijk label', strpos( $h, 'aria-label="Doel"' ) !== false );
$h2 = row( cm_field( 'pv_doeleinden', 'rows', 'Doeleinden', array( 'option' => 'cm_privacy', 'columns' => $cols ) ), array( 'pv_doeleinden' => '[{"doel":"Uit JSON"}]' ) );
cm_assert( 'rows-veld leest het opgeslagen JSON-formaat', strpos( $h2, 'value="Uit JSON"' ) !== false );

cm_test_group( 'Checkgroep' );
$g  = cm_field( 'pv_cf_fields', 'checkgroup', 'Verzamelde velden', array( 'option' => 'cm_privacy', 'keys' => array( 'pv_cf_voornaam' => 'Voornaam', 'pv_cf_email' => 'E-mailadres' ) ) );
$gh = row( $g, array( 'pv_cf_voornaam' => '1', 'pv_cf_email' => '0' ) );
cm_assert( 'elke sleutel een eigen checkbox met verborgen 0', substr_count( $gh, 'type="hidden"' ) === 2 && strpos( $gh, 'name="cm_privacy[pv_cf_voornaam]"' ) !== false );
cm_assert( 'aangevinkt volgens de waarden', preg_match( '/name="cm_privacy\[pv_cf_voornaam\]" data-cm-key="pv_cf_voornaam" value="1" checked/', $gh ) === 1 && preg_match( '/pv_cf_email" value="1" checked/', $gh ) === 0 );
$gl = cm_admin_field_list( 'cm_privacy', array( 'p' => array( 't' => array( 'label' => 'T', 'sections' => array( array( 'fields' => array( $g ) ) ) ) ) ) );
cm_assert( 'veldlijst splitst de groep in losse checkbox-instellingen', array_map( function ( $f ) { return $f['key'] . ':' . $f['type']; }, $gl ) === array( 'pv_cf_voornaam:checkbox', 'pv_cf_email:checkbox' ) );

cm_test_group( 'Select met een onbekende huidige waarde (Review Focus 5)' );
$sel = row( cm_field( 'pv_cf_grondslag', 'select', 'Rechtsgrondslag', array( 'options' => array( 'A' => 'A', 'B' => 'B' ) ) ), array( 'pv_cf_grondslag' => 'Oude vrije tekst' ) );
cm_assert( 'de huidige waarde blijft zichtbaar en geselecteerd', strpos( $sel, '<option value="Oude vrije tekst" selected>Oude vrije tekst</option>' ) !== false );
cm_assert( 'geen andere optie geselecteerd', substr_count( $sel, ' selected' ) === 1 );
```

In `tests/test-admin3-settings.php`, bovenaan vóór `require __DIR__ . '/bootstrap.php';`:

```php
function esc_url_raw( $s ) { $s = trim( (string) $s ); return preg_match( '#^https?://#i', $s ) ? $s : ''; }
```

En vóór `exit( cm_test_summary() );`:

```php
cm_test_group( 'Rijen (rows)' );
$rf = cm_field( 'pv_doeleinden', 'rows', 'Doeleinden', array( 'option' => 'cm_privacy', 'columns' => array(
    'doel' => array( 'label' => 'Doel' ),
    'url'  => array( 'label' => 'URL', 'type' => 'url' ),
    'cat'  => array( 'label' => 'Cat', 'type' => 'select', 'options' => array( 'functional' => 'F', 'marketing' => 'M' ) ),
) ) );
$json = cm_sanitize_field_value( $rf, array(
    3 => array( 'doel' => ' Contact <b>x</b> ', 'url' => 'javascript:alert(1)', 'cat' => 'bogus', 'extra' => 'weg' ),
    7 => array( 'doel' => '', 'url' => '', 'cat' => 'marketing' ),
), '[]' );
$rows = json_decode( $json, true );
cm_assert( 'rijen worden een JSON-string (opgeslagen formaat)', is_string( $json ) && is_array( $rows ) );
cm_assert( 'rij met alleen een keuzelijst verdwijnt; index opnieuw vanaf 0', count( $rows ) === 1 && array_keys( $rows ) === array( 0 ) );
cm_assert( 'alleen bekende kolommen, tekst opgeschoond, onveilige URL leeg, onbekende keuze wordt de eerste', $rows[0] === array( 'doel' => 'Contact x', 'url' => '', 'cat' => 'functional' ) );
cm_assert( 'alles verwijderd (lege string uit het formulier) wordt een lege lijst', cm_sanitize_field_value( $rf, '', '[{"doel":"x"}]' ) === '[]' );
$oud = json_decode( cm_sanitize_field_value( $rf, '[{"doel":"Oud"}]', '[]' ), true );
cm_assert( 'oude admin post een JSON-string: blijft werken', $oud[0]['doel'] === 'Oud' );
```

In `tests/test-admin3-actions.php`: vervang de bestaande stub `add_query_arg` door deze versie, die beide aanroepvormen van WordPress ondersteunt. Voeg er ook `wp_nonce_url` bij, vóór `require __DIR__ . '/bootstrap.php';`:

```php
function add_query_arg( $a, $b = null, $c = null ) {
    if ( is_array( $a ) ) { $args = $a; $url = $b; } else { $args = array( $a => $b ); $url = $c; }
    foreach ( $args as $k => $v ) $url .= ( strpos( $url, '?' ) === false ? '?' : '&' ) . rawurlencode( $k ) . '=' . rawurlencode( $v );
    return $url;
}
function wp_nonce_url( $url, $action ) { return $url . ( strpos( $url, '?' ) === false ? '?' : '&' ) . '_wpnonce=nonce-' . $action; }
```

En vóór `exit( cm_test_summary() );`:

```php
cm_test_group( 'Actie-URL (downloads)' );
$u = cm_admin_action_url( 'export_cookies', array( 'x' => '1' ) );
cm_assert( 'link naar admin-post.php met eigen action', strpos( $u, 'https://example.test/wp-admin/admin-post.php?' ) === 0 && strpos( $u, 'action=cm_export_cookies' ) !== false );
cm_assert( 'met extra argument en eigen nonce', strpos( $u, 'x=1' ) !== false && strpos( $u, '_wpnonce=nonce-cm_export_cookies' ) !== false );
```

In `tests/test-admin3-frame.php`, bovenaan vóór `require __DIR__ . '/bootstrap.php';`:

```php
function settings_fields( $group ) { echo '<!--group:' . $group . '-->'; }
function submit_button( $text = '' ) { echo '<!--submit:' . $text . '-->'; }
function wp_kses_post( $s ) { return (string) $s; }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
```

Na `require CM_PLUGIN_ROOT . '/includes/admin/menu.php';`:

```php
require CM_PLUGIN_ROOT . '/includes/admin/fields.php';
```

En vóór `exit( cm_test_summary() );`:

```php
cm_test_group( 'Formulier-tab: groep en waarden per tab' );
ob_start();
cm_admin_render_form_tab( 'p', 't', array(
    'group'    => 'cookiebaas_privacy',
    'values'   => function () { return array( 'pv_x' => 'uit-values' ); },
    'sections' => array( array( 'fields' => array( cm_field( 'pv_x', 'text', 'X', array( 'option' => 'cm_privacy' ) ) ) ) ),
) );
$h = ob_get_clean();
cm_assert( 'eigen settings-groep', strpos( $h, '<!--group:cookiebaas_privacy-->' ) !== false );
cm_assert( 'waarden uit de tab-callback', strpos( $h, 'value="uit-values"' ) !== false );
ob_start(); cm_admin_render_form_tab( 'p', 't', array( 'sections' => array() ) ); $d = ob_get_clean();
cm_assert( 'standaard: cookiebaas_settings', strpos( $d, '<!--group:cookiebaas_settings-->' ) !== false );
```

- [ ] **Step 2: Draai de tests en zie ze falen**

Run: `php tests/test-admin3-fields.php; php tests/test-admin3-settings.php; php tests/test-admin3-actions.php; php tests/test-admin3-frame.php`
Expected: fatals zoals "Call to undefined function cm_admin_render_rows()" of "cm_admin_action_url()", en FAILs op de select- en groep-asserties.

- [ ] **Step 3: Breid `includes/admin/fields.php` uit**

1. **Checkgroep in de veldlijst.** Vervang in `cm_admin_field_list()` de binnenste lus (`foreach ( isset( $section['fields'] ) … as $f ) { … }`) door:

```php
                foreach ( isset( $section['fields'] ) ? $section['fields'] : array() as $f ) {
                    if ( isset( $f['store'] ) && $f['store'] === false ) continue;
                    $f_option = isset( $f['option'] ) ? $f['option'] : 'cm_settings';
                    if ( $f_option !== $option ) continue;
                    if ( $f['type'] === 'checkgroup' ) {
                        // Eén rij in de UI, maar elke sleutel is een eigen checkbox-instelling
                        foreach ( $f['keys'] as $k => $label ) $list[] = cm_field( $k, 'checkbox', $label, array( 'option' => $f_option ) );
                        continue;
                    }
                    $list[] = $f;
                }
```

2. **Nieuwe helpers.** Voeg na `cm_csv_list()` toe:

```php
/** Rijen uit het opgeslagen JSON-formaat (string) of uit het formulier (array). */
function cm_rows_decode( $value ) {
    if ( is_array( $value ) ) return $value;
    $decoded = json_decode( (string) $value, true );
    return is_array( $decoded ) ? $decoded : array();
}

/**
 * Rijen-editor: tabel met invoervelden per kolom, een sjabloonrij (template,
 * index __i__) en een knop om rijen toe te voegen (admin-common.js). Het lege
 * verborgen veld zorgt dat "alle rijen verwijderd" ook echt leeg opslaat.
 */
function cm_admin_render_rows( $name, array $columns, array $rows, $add_label ) {
    $id = 'cm-rows-' . trim( preg_replace( '/[^a-z0-9_]+/', '-', strtolower( $name ) ), '-' );
    echo '<div class="cm-rows" id="' . esc_attr( $id ) . '" data-cm-next="' . count( $rows ) . '">';
    echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="">';
    echo '<table class="widefat striped cm-rows-table"><thead><tr>';
    foreach ( $columns as $c ) echo '<th scope="col">' . esc_html( $c['label'] ) . '</th>';
    echo '<td class="cm-rows-actions"><span class="screen-reader-text">Acties</span></td></tr></thead><tbody>';
    $i = 0;
    foreach ( $rows as $row ) {
        echo cm_admin_rows_tr( $name, $columns, (string) $i, is_array( $row ) ? $row : array(), $id );
        $i++;
    }
    echo '</tbody></table>';
    echo '<template>' . cm_admin_rows_tr( $name, $columns, '__i__', array(), $id ) . '</template>';
    foreach ( $columns as $col => $c ) {
        if ( empty( $c['suggestions'] ) ) continue;
        echo '<datalist id="' . esc_attr( $id . '-' . $col ) . '">';
        foreach ( $c['suggestions'] as $s ) echo '<option value="' . esc_attr( $s ) . '">';
        echo '</datalist>';
    }
    echo '<p><button type="button" class="button cm-rows-add">' . esc_html( $add_label ) . '</button></p>';
    echo '</div>';
}

/** Eén rij van de rijen-editor als HTML-string. */
function cm_admin_rows_tr( $name, array $columns, $i, array $row, $id ) {
    $html = '<tr>';
    foreach ( $columns as $col => $c ) {
        $n     = $name . '[' . $i . '][' . $col . ']';
        $v     = isset( $row[ $col ] ) && is_scalar( $row[ $col ] ) ? (string) $row[ $col ] : '';
        $label = esc_attr( $c['label'] );
        if ( isset( $c['type'] ) && $c['type'] === 'select' ) {
            $html .= '<td><select name="' . esc_attr( $n ) . '" aria-label="' . $label . '">';
            foreach ( $c['options'] as $ov => $ol ) {
                $html .= '<option value="' . esc_attr( $ov ) . '"' . ( $v === (string) $ov ? ' selected' : '' ) . '>' . esc_html( $ol ) . '</option>';
            }
            $html .= '</select></td>';
        } else {
            $html .= '<td><input type="text" class="widefat" name="' . esc_attr( $n ) . '" value="' . esc_attr( $v ) . '" aria-label="' . $label . '"'
                . ( isset( $c['placeholder'] ) ? ' placeholder="' . esc_attr( $c['placeholder'] ) . '"' : '' )
                . ( ! empty( $c['suggestions'] ) ? ' list="' . esc_attr( $id . '-' . $col ) . '"' : '' ) . '></td>';
        }
    }
    $html .= '<td class="cm-rows-actions"><button type="button" class="button-link cm-rows-remove">Verwijderen</button></td></tr>';
    return $html;
}
```

3. **In `cm_admin_render_field_row()`:**
   - voeg `'rows'` en `'checkgroup'` toe aan de `$plain`-lijst;
   - voeg na de regel waarin `$value` wordt bepaald toe:

```php
    if ( $f['type'] === 'checkgroup' ) $value = array_intersect_key( $values, $f['keys'] );
```

4. **In `cm_admin_render_control()`:**
   - voeg deze twee cases toe, vóór `case 'custom':`:

```php
        case 'rows':
            cm_admin_render_rows( $name, $f['columns'], cm_rows_decode( $value ), isset( $f['add_label'] ) ? $f['add_label'] : 'Rij toevoegen' );
            break;

        case 'checkgroup':
            $opt = isset( $f['option'] ) ? $f['option'] : 'cm_settings';
            echo '<fieldset><legend class="screen-reader-text"><span>' . esc_html( $f['label'] ) . '</span></legend>';
            foreach ( $f['keys'] as $k => $label ) {
                $kn  = $opt . '[' . $k . ']';
                $kid = 'cm-f-' . $k;
                echo '<input type="hidden" name="' . esc_attr( $kn ) . '" value="0">';
                echo '<label for="' . esc_attr( $kid ) . '"><input type="checkbox" id="' . esc_attr( $kid ) . '" name="' . esc_attr( $kn ) . '" data-cm-key="' . esc_attr( $k ) . '" value="1"'
                    . ( isset( $value[ $k ] ) && (string) $value[ $k ] === '1' ? ' checked' : '' ) . '> ' . esc_html( $label ) . '</label><br>';
            }
            echo '</fieldset>';
            break;
```

   - vervang in `case 'select':` de regel `echo '<select' . $attr . '>';` door:

```php
            echo '<select' . $attr . '>';
            // Een opgeslagen waarde buiten de opties (oude vrije tekst) blijft zichtbaar en
            // geselecteerd, in plaats van dat de browser stil de eerste optie kiest.
            if ( (string) $value !== '' && ! array_key_exists( (string) $value, cm_admin_field_options( $f ) ) ) {
                echo '<option value="' . esc_attr( $value ) . '" selected>' . esc_html( $value ) . '</option>';
            }
```

- [ ] **Step 4: Breid `includes/admin/settings.php` uit**

1. Voeg aan `cm_sanitize_field_value()`, in de `switch`, vóór `default:`, toe:

```php
        case 'rows':
            return (string) wp_json_encode( cm_sanitize_rows( $raw, $f['columns'] ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
```

2. Voeg na `cm_sanitize_field_value()` toe:

```php
/**
 * Rijen opschonen: alleen bekende kolommen, tekst via sanitize_text_field, URL's
 * via esc_url_raw, keuzelijsten tegen hun opties. Rijen zonder ingevulde tekst
 * (een keuzelijst telt niet mee) vervallen; de index begint opnieuw bij 0.
 * Accepteert de array uit het formulier én de JSON-string van de oude admin.
 */
function cm_sanitize_rows( $raw, array $columns ) {
    $clean = array();
    foreach ( cm_rows_decode( $raw ) as $row ) {
        if ( ! is_array( $row ) ) continue;
        $out    = array();
        $filled = false;
        foreach ( $columns as $col => $c ) {
            $type = isset( $c['type'] ) ? $c['type'] : 'text';
            $v    = isset( $row[ $col ] ) && is_scalar( $row[ $col ] ) ? (string) $row[ $col ] : '';
            if ( $type === 'url' ) {
                $v = esc_url_raw( trim( $v ) );
            } elseif ( $type === 'select' ) {
                $keys = array_keys( $c['options'] );
                $v    = in_array( $v, array_map( 'strval', $keys ), true ) ? $v : (string) $keys[0];
            } else {
                $v = sanitize_text_field( $v );
            }
            $out[ $col ] = $v;
            if ( $v !== '' && $type !== 'select' ) $filled = true;
        }
        if ( $filled ) $clean[] = $out;
    }
    return $clean;
}
```

- [ ] **Step 5: Breid `includes/admin/menu.php` uit**

1. Voeg in `cm_admin_render_page()` direct na de `echo '<h1 …'` toe:

```php
    if ( ! empty( $def['title_actions'] ) ) call_user_func( $def['title_actions'] );
```

2. Vervang in `cm_admin_render_form_tab()` deze twee regels:

```php
    settings_fields( 'cookiebaas_settings' );
    if ( function_exists( 'cm_admin_render_sections' ) ) {
        cm_admin_render_sections( isset( $def['sections'] ) ? $def['sections'] : array(), cm_get_settings() );
    }
```

door:

```php
    settings_fields( isset( $def['group'] ) ? $def['group'] : 'cookiebaas_settings' );
    $values = ! empty( $def['values'] ) ? call_user_func( $def['values'] ) : cm_get_settings();
    if ( function_exists( 'cm_admin_render_sections' ) ) {
        cm_admin_render_sections( isset( $def['sections'] ) ? $def['sections'] : array(), $values );
    }
```

- [ ] **Step 6: Voeg `cm_admin_action_url()` toe aan `includes/admin/actions.php`**

Voeg na `cm_admin_action_form()` toe:

```php
/** Link (GET) naar een actie, met nonce — voor downloads zoals CSV-exports. */
function cm_admin_action_url( $action, array $args = array() ) {
    $url = add_query_arg( array_merge( array( 'action' => 'cm_' . $action ), $args ), admin_url( 'admin-post.php' ) );
    return wp_nonce_url( $url, 'cm_' . $action );
}
```

`cm_admin_register_action()` controleert met `check_admin_referer()`, en die leest `$_REQUEST['_wpnonce']`. GET-links werken dus zonder aanpassing.

- [ ] **Step 7: Rijen-JS in `assets/js/admin-common.js`**

Voeg vóór het blok `/* ---- Bevestigen bij destructieve acties ---- */` toe:

```js
  /* ---- Rijen-editor (cookielijst, privacytabellen) ---- */
  document.addEventListener('click', function (e) {
    var add = e.target.closest('.cm-rows-add');
    if (add) {
      e.preventDefault();
      var wrap = add.closest('.cm-rows');
      var n = parseInt(wrap.getAttribute('data-cm-next'), 10) || 0;
      wrap.setAttribute('data-cm-next', String(n + 1));
      var body = wrap.querySelector('tbody');
      body.insertAdjacentHTML('beforeend', wrap.querySelector('template').innerHTML.split('__i__').join(String(n)));
      var first = body.lastElementChild && body.lastElementChild.querySelector('input, select');
      if (first) first.focus();
      wrap.dispatchEvent(new Event('input', { bubbles: true }));
      return;
    }
    var rm = e.target.closest('.cm-rows-remove');
    if (rm) {
      e.preventDefault();
      var table = rm.closest('.cm-rows');
      rm.closest('tr').remove();
      if (table) table.dispatchEvent(new Event('input', { bubbles: true }));
    }
  });
```

Het `input`-event bubbelt naar het `.cm-form`, dus de waarschuwing bij niet-opgeslagen wijzigingen werkt ook voor toegevoegde en verwijderde rijen.

- [ ] **Step 8: CSS**

Voeg aan `assets/css/admin-layout.css` toe:

```css
.cm-rows-table .cm-rows-actions { width: 90px; text-align: right; }
.cm-rows-table td { vertical-align: middle; }
```

- [ ] **Step 9: Draai de tests**

Run: `php tests/test-admin3-fields.php && php tests/test-admin3-settings.php && php tests/test-admin3-actions.php && php tests/test-admin3-frame.php && php tests/run.php`
Expected: alles groen, inclusief de broncheck.

- [ ] **Step 10: Commit**

```bash
git add includes/admin/fields.php includes/admin/settings.php includes/admin/menu.php includes/admin/actions.php assets/js/admin-common.js assets/css/admin-layout.css tests/test-admin3-fields.php tests/test-admin3-settings.php tests/test-admin3-actions.php tests/test-admin3-frame.php
git commit -m "feat(admin3): rijen-editor, checkgroep, tab-groepen en actie-URL's

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 2: Cookies › Cookielijst (editor en ingebouwde cookies)

**Files:**
- Create: `includes/admin/page-cookies.php`
- Modify: `includes/admin/settings.php` (register + callback voor `cm_cookie_list`)
- Modify: `includes/admin/menu.php` (menu-item Cookies)
- Modify: `cookiemelding.php` (require)
- Create: `tests/test-admin3-cookies.php`

**Interfaces:**
- Consumes:
  - `cm_admin_render_rows()` (Taak 1);
  - `cm_sanitize_cookie_list( array $raw ): array` (plan 1, `settings.php`);
  - `cm_default_cookies()` (`defaults.php`).
- Produces:
  - `cm_cookie_list_sanitize_callback( $input ): array`. `null` → bestaande lijst; niet-array (bijvoorbeeld `''`) → `array()`; array → `cm_sanitize_cookie_list( array_values( $input ) )`.
  - `cm_cookie_list_columns(): array`
  - `cm_tabs_cookies(): array` (in deze taak alleen `'lijst'`; Taak 4 voegt `'scannen'` toe)
  - `cm_render_cookie_list_tab(): void`
  - settings-groep `cookiebaas_cookies`

- [ ] **Step 1: Schrijf de falende test**

`tests/test-admin3-cookies.php`:

```php
<?php
/**
 * Nieuwe admin (3.0) — pagina Cookies.
 *
 * Borgt: de cookielijst gaat via de Settings API en alle rijen verwijderen
 * leegt de lijst echt (Review Focus 1); de editor toont de opgeslagen lijst
 * met de juiste categorie; de ingebouwde cookies staan erbij als tabel.
 */

function sanitize_text_field( $s ) { return is_scalar( $s ) ? trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) ) : ''; }
function settings_fields( $group ) { echo '<!--group:' . $group . '-->'; }
function submit_button( $text = '' ) { echo '<!--submit:' . $text . '-->'; }
function wp_kses_post( $s ) { return (string) $s; }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
require CM_PLUGIN_ROOT . '/includes/admin/menu.php';
require CM_PLUGIN_ROOT . '/includes/admin/fields.php';
require CM_PLUGIN_ROOT . '/includes/admin/settings.php';
require CM_PLUGIN_ROOT . '/includes/admin/actions.php';
require CM_PLUGIN_ROOT . '/includes/admin/page-cookies.php';

cm_test_group( 'Cookielijst opslaan via de Settings API' );
update_option( 'cm_cookie_list', array( array( 'name' => '_ga', 'provider' => 'Google Analytics', 'purpose' => 'Meet bezoek', 'duration' => '2 jaar', 'category' => 'analytics', 'builtin' => false ) ) );
cm_assert( 'null (ontbreekt in de POST) laat de lijst staan', count( cm_cookie_list_sanitize_callback( null ) ) === 1 );
cm_assert( 'lege string (alle rijen verwijderd) maakt de lijst leeg', cm_cookie_list_sanitize_callback( '' ) === array() );
$out = cm_cookie_list_sanitize_callback( array(
    4 => array( 'name' => '<b>_fbp</b>', 'provider' => '', 'purpose' => '', 'duration' => '', 'category' => 'marketing' ),
    9 => array( 'name' => '', 'category' => 'analytics' ),
) );
cm_assert( 'rijen opgeschoond, lege naam weg, opnieuw geïndexeerd', count( $out ) === 1 && $out[0]['name'] === '_fbp' && $out[0]['category'] === 'marketing' );

cm_test_group( 'Cookielijst-tab' );
ob_start(); cm_render_cookie_list_tab(); $h = ob_get_clean();
cm_assert( 'formulier naar options.php met groep cookiebaas_cookies', strpos( $h, 'action="https://example.test/wp-admin/options.php"' ) !== false && strpos( $h, '<!--group:cookiebaas_cookies-->' ) !== false );
cm_assert( 'opgeslagen cookie als rij', strpos( $h, 'name="cm_cookie_list[0][name]" value="_ga"' ) !== false );
cm_assert( 'categorie-keuze staat goed', preg_match( '/name="cm_cookie_list\[0\]\[category\]"[^>]*>.*?<option value="analytics" selected>/s', $h ) === 1 );
cm_assert( 'ingebouwde cookies als alleen-lezen tabel', strpos( $h, 'Ingebouwde cookies' ) !== false && strpos( $h, '<code>cc_cm_consent</code>' ) !== false );
cm_assert( 'pagina Cookies staat in het menu', isset( cm_admin_pages()['cookiebaas-cookies'] ) );
cm_assert( 'tab Cookielijst bestaat', isset( cm_tabs_cookies()['lijst'] ) );

exit( cm_test_summary() );
```

- [ ] **Step 2: Draai de test en zie hem falen**

Run: `php tests/test-admin3-cookies.php`
Expected: fatal "Failed opening required …/includes/admin/page-cookies.php".

- [ ] **Step 3: Schrijf `includes/admin/page-cookies.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   PAGINA COOKIES — Cookielijst · Scannen
   De cookielijst (cm_cookie_list) heeft een eigen settings-groep; de
   scan-instellingen horen bij cm_settings.
================================================================ */

function cm_tabs_cookies() {
    return array(
        'lijst' => array( 'label' => 'Cookielijst', 'render' => 'cm_render_cookie_list_tab' ),
    );
}

/** Kolommen van de cookielijst-editor (sleutels = opgeslagen formaat). */
function cm_cookie_list_columns() {
    return array(
        'name'     => array( 'label' => 'Cookie', 'placeholder' => 'Naam' ),
        'provider' => array( 'label' => 'Provider', 'placeholder' => 'Bijvoorbeeld Google Analytics' ),
        'purpose'  => array( 'label' => 'Doel', 'placeholder' => 'Waarvoor dient deze cookie?' ),
        'duration' => array( 'label' => 'Looptijd', 'placeholder' => 'Sessie' ),
        'category' => array( 'label' => 'Categorie', 'type' => 'select', 'options' => array(
            'functional' => 'Functioneel',
            'analytics'  => 'Analytisch',
            'marketing'  => 'Marketing',
        ) ),
    );
}

function cm_render_cookie_list_tab() {
    $rows = get_option( 'cm_cookie_list', array() );
    echo '<form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '" class="cm-form">';
    settings_fields( 'cookiebaas_cookies' );
    echo '<h2>Uw cookies</h2>';
    echo '<p>Deze lijst verschijnt in het voorkeurenvenster van de banner en in de cookietabel van de privacyverklaring.</p>';
    cm_admin_render_rows( 'cm_cookie_list', cm_cookie_list_columns(), is_array( $rows ) ? array_values( $rows ) : array(), 'Cookie toevoegen' );
    submit_button( 'Cookielijst opslaan' );
    echo '</form>';

    echo '<h2>Ingebouwde cookies</h2>';
    echo '<p>Deze cookies zet Cookiebaas zelf. Ze staan altijd in het voorkeurenvenster en zijn niet te verwijderen.</p>';
    echo '<table class="widefat striped"><thead><tr><th scope="col">Cookie</th><th scope="col">Provider</th><th scope="col">Doel</th><th scope="col">Looptijd</th></tr></thead><tbody>';
    foreach ( cm_default_cookies() as $ck ) {
        echo '<tr><td><code>' . esc_html( $ck['name'] ) . '</code></td><td>' . esc_html( $ck['provider'] ) . '</td><td>' . esc_html( $ck['purpose'] ) . '</td><td>' . esc_html( $ck['duration'] ) . '</td></tr>';
    }
    echo '</tbody></table>';
}
```

- [ ] **Step 4: Registreer de cookielijst in `includes/admin/settings.php`**

Voeg aan `cm_admin_register_settings()` toe, na de bestaande `register_setting`:

```php
    register_setting( 'cookiebaas_cookies', 'cm_cookie_list', array(
        'type'              => 'array',
        'sanitize_callback' => 'cm_cookie_list_sanitize_callback',
        'show_in_rest'      => false,
    ) );
```

Voeg na `cm_sanitize_cookie_list()` toe:

```php
/**
 * Sanitize-callback van cm_cookie_list. null = de option ontbrak in de POST
 * (niets wissen); een lege string = het formulier met alle rijen verwijderd
 * (lijst leeg); een array = de rijen uit de editor of een update_option-aanroep.
 */
function cm_cookie_list_sanitize_callback( $input ) {
    if ( $input === null ) {
        $existing = get_option( 'cm_cookie_list', array() );
        return is_array( $existing ) ? $existing : array();
    }
    return is_array( $input ) ? cm_sanitize_cookie_list( array_values( $input ) ) : array();
}
```

- [ ] **Step 5: Menu en laden**

Voeg in `includes/admin/menu.php` aan `cm_admin_pages()` toe, na `'cookiebaas-blokkering' => 'Blokkering',`:

```php
        'cookiebaas-cookies'    => 'Cookies',
```

Voeg in `cookiemelding.php` na de regel voor `includes/admin/page-blokkering.php` toe:

```php
require_once CM_PLUGIN_DIR . 'includes/admin/page-cookies.php';
```

- [ ] **Step 6: Draai de tests**

Run: `php tests/test-admin3-cookies.php && php tests/run.php`
Expected: alles groen. `test-admin3-registry.php` laadt het nieuwe bestand mee; de cookielijst-tab heeft geen `cm_settings`-velden, dus de volledigheidstest verandert niet.

- [ ] **Step 7: Commit**

```bash
git add includes/admin/page-cookies.php includes/admin/settings.php includes/admin/menu.php cookiemelding.php tests/test-admin3-cookies.php
git commit -m "feat(admin3): Cookies › Cookielijst via de Settings API

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3: Plakken vanuit F12, exporteren als CSV en leegmaken

**Files:**
- Modify: `includes/admin/page-cookies.php`
- Modify: `includes/admin/actions.php` (`cm_admin_send_csv()`, meldingscodes)
- Modify: `includes/admin.php` (oud `cm_ajax_export_cookies_csv()` gebruikt de rij-builder)
- Modify: `tests/test-admin3-cookies.php`

**Interfaces:**
- Consumes:
  - `cm_fallback_cookies()` en `cm_cookie_prefix_match()` (bestaande kennisbank in `includes/admin.php`: `naam of prefix => array( categorie, provider, looptijd, omschrijving )`);
  - `cm_admin_register_action()`, `cm_admin_action_form()`, `cm_admin_action_url()`.
- Produces:
  - `cm_cookie_fallback_info( string $name ): ?array`
  - `cm_f12_duration( string $expires, int $now ): string`
  - `cm_parse_f12_cookies( string $raw, array $existing_names, ?int $now = null ): array`
  - `cm_cookie_list_csv_rows( array $cookies ): array`
  - `cm_admin_send_csv( string $filename, array $rows ): void` (stuurt headers + BOM + rijen en doet `exit`)
  - `cm_render_cookie_list_tools(): void`
  - acties `cm_import_f12` (POST `cm_f12`), `cm_export_cookies` (GET), `cm_clear_cookie_list` (POST)
  - meldingscodes `f12-imported`, `f12-none`, `cookie-list-cleared`

- [ ] **Step 1: Breid de test uit**

Voeg in `tests/test-admin3-cookies.php` na `require CM_PLUGIN_ROOT . '/includes/admin/actions.php';` toe (voor de kennisbank):

```php
require CM_PLUGIN_ROOT . '/includes/admin.php';
```

Voeg bovenaan, vóór `require __DIR__ . '/bootstrap.php';`, de stubs toe voor de actieformulieren:

```php
function wp_nonce_field( $action, $name = '_wpnonce', $referer = true, $echo = true ) { $f = '<input type="hidden" name="' . $name . '" value="nonce-' . $action . '">'; if ( $echo ) echo $f; return $f; }
function wp_nonce_url( $url, $action ) { return $url . ( strpos( $url, '?' ) === false ? '?' : '&' ) . '_wpnonce=nonce-' . $action; }
function add_query_arg( $a, $b = null, $c = null ) {
    if ( is_array( $a ) ) { $args = $a; $url = $b; } else { $args = array( $a => $b ); $url = $c; }
    foreach ( $args as $k => $v ) $url .= ( strpos( $url, '?' ) === false ? '?' : '&' ) . rawurlencode( $k ) . '=' . rawurlencode( $v );
    return $url;
}
```

Voeg vóór `exit( cm_test_summary() );` toe:

```php
cm_test_group( 'Plakken vanuit F12' );
$now = strtotime( '2026-09-26 10:00:00 UTC' );
$raw = "Name\tValue\tDomain\tPath\tExpires / Max-Age\tSize\n"
     . "_ga\tGA1.2.3\t.voorbeeld.nl\t/\t2027-09-26T10:00:00.000Z\t30\n"
     . "mijn_eigen\tx\tvoorbeeld.nl\t/\tSession\t10\n"
     . "cookielawinfo-checkbox\tyes\tvoorbeeld.nl\t/\t2026-10-06T10:00:00.000Z\t20\n"
     . "_GA\tdubbel\t.voorbeeld.nl\t/\tSession\t5\n"
     . "bestaat_al\tx\tvoorbeeld.nl\t/\tSession\t5\n"
     . "\n";
$rows = cm_parse_f12_cookies( $raw, array( 'bestaat_al' ), $now );
$names = array_column( $rows, 'name' );
cm_assert( 'kopregel overgeslagen, bestaande en dubbele (hoofdletterongevoelig) namen overgeslagen', $names === array( '_ga', 'mijn_eigen', 'cookielawinfo-checkbox' ) );
cm_assert( 'een cookie die met "cookie" begint is geen kopregel', in_array( 'cookielawinfo-checkbox', $names, true ) );
cm_assert( 'verloopdatum over een jaar → "1 jaar"', $rows[0]['duration'] === '1 jaar' );
cm_assert( 'sessiecookie zonder kennis → "Sessie", functioneel, geen provider', $rows[1]['duration'] === 'Sessie' && $rows[1]['category'] === 'functional' && $rows[1]['provider'] === '' );
cm_assert( 'verloopdatum over 10 dagen → "10 dagen"', $rows[2]['duration'] === '10 dagen' );
cm_assert( 'bekende cookie krijgt categorie en provider uit de kennisbank', cm_cookie_fallback_info( 'YSC' )[0] === 'marketing' && cm_cookie_fallback_info( 'YSC' )[1] === 'YouTube' );
cm_assert( 'prefixpatroon uit de kennisbank werkt', cm_cookie_fallback_info( 'wp-settings-1' ) !== null && cm_cookie_fallback_info( 'wp-settings-1' )[1] === 'WordPress' );
cm_assert( 'enkelvoud en meervoud', cm_f12_duration( gmdate( 'c', $now + 1 * DAY_IN_SECONDS ), $now ) === '1 dag' && cm_f12_duration( gmdate( 'c', $now + 45 * DAY_IN_SECONDS ), $now ) === '2 maanden' && cm_f12_duration( gmdate( 'c', $now + 31 * DAY_IN_SECONDS ), $now ) === '1 maand' );
cm_assert( 'verlopen of onleesbare datum → leeg (dan geldt de kennisbank of "Sessie")', cm_f12_duration( '2020-01-01', $now ) === '' && cm_f12_duration( 'Session', $now ) === '' );

cm_test_group( 'CSV-export' );
$csv = cm_cookie_list_csv_rows( array( array( 'name' => '_ga', 'provider' => 'Google, Inc. "GA"', 'purpose' => 'Meet', 'duration' => '2 jaar', 'category' => 'analytics' ) ) );
cm_assert( 'kopregel met de vaste kolommen', $csv[0] === array( 'Cookie naam', 'Aanbieder', 'Categorie', 'Grondslag', 'Doel', 'Looptijd', 'Domein', 'Wildcard' ) );
cm_assert( 'rij met leesbare categorie en grondslag', $csv[1] === array( '_ga', 'Google, Inc. "GA"', 'Analytisch', 'Toestemming', 'Meet', '2 jaar', '', 'Nee' ) );

cm_test_group( 'Hulpmiddelen onder de cookielijst' );
ob_start(); cm_render_cookie_list_tools(); $t = ob_get_clean();
cm_assert( 'F12-formulier post naar admin-post met eigen nonce', strpos( $t, 'name="action" value="cm_import_f12"' ) !== false && strpos( $t, 'nonce-cm_import_f12' ) !== false && strpos( $t, 'name="cm_f12"' ) !== false );
cm_assert( 'exportlink met eigen nonce', strpos( $t, 'action=cm_export_cookies' ) !== false && strpos( $t, 'nonce-cm_export_cookies' ) !== false );
cm_assert( 'leegmaken vraagt om bevestiging', strpos( $t, 'value="cm_clear_cookie_list"' ) !== false && strpos( $t, 'data-cm-confirm=' ) !== false );
cm_assert( 'meldingen bestaan', cm_admin_notice_html( 'f12-imported' ) !== '' && cm_admin_notice_html( 'f12-none' ) !== '' && cm_admin_notice_html( 'cookie-list-cleared' ) !== '' );
```

- [ ] **Step 2: Draai de test en zie hem falen**

Run: `php tests/test-admin3-cookies.php`
Expected: fatal "Call to undefined function cm_parse_f12_cookies()".

- [ ] **Step 3: Voeg aan `includes/admin/page-cookies.php` toe**

```php
/**
 * Kennis over een cookie uit de bestaande kennisbank van de scanner
 * (cm_fallback_cookies): eerst exacte naam, dan prefixpatroon (eindigt op _ of -).
 * Geeft array( categorie, provider, looptijd, omschrijving ) of null.
 */
function cm_cookie_fallback_info( $name ) {
    $known = cm_fallback_cookies();
    if ( isset( $known[ $name ] ) ) return $known[ $name ];
    foreach ( $known as $pattern => $info ) {
        if ( cm_cookie_prefix_match( $name, $pattern ) ) return $info;
    }
    return null;
}

/** Verloopdatum uit F12 → leesbare looptijd; '' als sessie, verlopen of onleesbaar. */
function cm_f12_duration( $expires, $now ) {
    if ( ! preg_match( '/\d{4}/', (string) $expires ) ) return '';
    $ts = strtotime( (string) $expires );
    if ( ! $ts || $ts <= $now ) return '';
    $days = (int) round( ( $ts - $now ) / DAY_IN_SECONDS );
    if ( $days >= 365 ) return (int) round( $days / 365 ) . ' jaar';
    if ( $days >= 30 ) {
        $m = (int) round( $days / 30 );
        return $m . ( $m === 1 ? ' maand' : ' maanden' );
    }
    return $days . ( $days === 1 ? ' dag' : ' dagen' );
}

/**
 * Cookietabel geplakt uit de ontwikkelaarstools (F12 › Applicatie › Cookies):
 * tab-gescheiden, naam in kolom 1, verloopdatum in kolom 5. Kopregel, lege
 * regels en namen die al bestaan (hoofdletterongevoelig) worden overgeslagen.
 */
function cm_parse_f12_cookies( $raw, array $existing_names, $now = null ) {
    $now  = $now === null ? time() : (int) $now;
    $seen = array();
    foreach ( $existing_names as $n ) $seen[ strtolower( (string) $n ) ] = true;
    $rows = array();
    foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
        $line = trim( $line );
        if ( strlen( $line ) < 2 ) continue;
        $parts = explode( "\t", $line );
        $name  = count( $parts ) >= 2 ? trim( $parts[0] ) : (string) preg_split( '/\s+/', $line )[0];
        if ( $name === '' || strlen( $name ) > 128 ) continue;
        if ( $name[0] === '#' || preg_match( '/^(name|naam|cookie|cookienaam)$/i', $name ) ) continue; // kopregel
        $lower = strtolower( $name );
        if ( isset( $seen[ $lower ] ) ) continue;
        $seen[ $lower ] = true;
        $info     = cm_cookie_fallback_info( $name );
        $duration = cm_f12_duration( isset( $parts[4] ) ? $parts[4] : '', $now );
        $rows[]   = array(
            'name'     => $name,
            'provider' => $info ? $info[1] : '',
            'purpose'  => $info ? $info[3] : '',
            'duration' => $duration !== '' ? $duration : ( $info ? $info[2] : 'Sessie' ),
            'category' => $info ? $info[0] : 'functional',
        );
    }
    return $rows;
}

/** CSV-rijen van de cookielijst (voor de download en de oude AJAX-export). */
function cm_cookie_list_csv_rows( array $cookies ) {
    $labels = array( 'functional' => 'Functioneel', 'analytics' => 'Analytisch', 'marketing' => 'Marketing' );
    $bases  = array( 'functional' => 'Strikt noodzakelijk / Gerechtvaardigd belang', 'analytics' => 'Toestemming', 'marketing' => 'Toestemming' );
    $rows   = array( array( 'Cookie naam', 'Aanbieder', 'Categorie', 'Grondslag', 'Doel', 'Looptijd', 'Domein', 'Wildcard' ) );
    foreach ( $cookies as $ck ) {
        $cat    = isset( $ck['category'] ) ? $ck['category'] : 'functional';
        $rows[] = array(
            isset( $ck['name'] ) ? (string) $ck['name'] : '',
            isset( $ck['provider'] ) ? (string) $ck['provider'] : '',
            isset( $labels[ $cat ] ) ? $labels[ $cat ] : $cat,
            isset( $bases[ $cat ] ) ? $bases[ $cat ] : '',
            isset( $ck['purpose'] ) ? (string) $ck['purpose'] : '',
            isset( $ck['duration'] ) ? (string) $ck['duration'] : '',
            isset( $ck['domain'] ) ? (string) $ck['domain'] : '',
            ! empty( $ck['wildcard'] ) ? 'Ja' : 'Nee',
        );
    }
    return $rows;
}

/** Plakken vanuit F12, exporteren en leegmaken — eigen formulieren, buiten het lijstformulier. */
function cm_render_cookie_list_tools() {
    echo '<details class="cm-details postbox"><summary>Plakken vanuit de browser (F12)</summary><div class="cm-details-body">';
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    echo '<input type="hidden" name="action" value="cm_import_f12">';
    wp_nonce_field( 'cm_import_f12' );
    echo '<p><label for="cm-f12">Open de ontwikkelaarstools (F12) › Applicatie › Cookies, kopieer de tabel en plak die hier. Cookies die al in de lijst staan worden overgeslagen.</label></p>';
    echo '<p><textarea id="cm-f12" name="cm_f12" rows="8" class="large-text code"></textarea></p>';
    echo '<p><button type="submit" class="button">Toevoegen aan de lijst</button></p>';
    echo '</form></div></details>';
    echo '<p><a class="button" href="' . esc_url( cm_admin_action_url( 'export_cookies' ) ) . '">Exporteren als CSV</a> ';
    echo cm_admin_action_form( 'clear_cookie_list', 'Lijst leegmaken', array(), 'De cookielijst leegmaken? De ingebouwde cookies blijven staan.', 'button button-link-delete' );
    echo '</p>';
}

if ( function_exists( 'cm_admin_register_action' ) ) {
    cm_admin_register_action( 'import_f12', function () {
        $raw   = isset( $_POST['cm_f12'] ) ? wp_unslash( $_POST['cm_f12'] ) : '';
        $list  = get_option( 'cm_cookie_list', array() );
        $list  = is_array( $list ) ? $list : array();
        $known = array_merge( array_column( $list, 'name' ), array_column( cm_default_cookies(), 'name' ) );
        $new   = cm_parse_f12_cookies( is_string( $raw ) ? $raw : '', $known );
        if ( ! $new ) return 'f12-none';
        update_option( 'cm_cookie_list', cm_sanitize_cookie_list( array_merge( $list, $new ) ) );
        return 'f12-imported';
    } );
    cm_admin_register_action( 'export_cookies', function () {
        cm_admin_send_csv( 'cookielijst-' . gmdate( 'Y-m-d' ) . '.csv', cm_cookie_list_csv_rows( cm_get_cookie_list() ) );
    } );
    cm_admin_register_action( 'clear_cookie_list', function () {
        update_option( 'cm_cookie_list', array() );
        return 'cookie-list-cleared';
    } );
}
```

Voeg in `cm_render_cookie_list_tab()` direct na `echo '</form>';` toe:

```php
    cm_render_cookie_list_tools();
```

- [ ] **Step 4: Breid `includes/admin/actions.php` uit**

Voeg aan de array in `cm_admin_notice_messages()` toe:

```php
        'f12-imported'        => array( 'success', 'De geplakte cookies zijn toegevoegd aan de lijst. Vul waar nodig provider en doel aan.' ),
        'f12-none'            => array( 'info',    'Er zijn geen nieuwe cookies herkend. Plak de tabel uit de ontwikkelaarstools (F12 › Applicatie › Cookies).' ),
        'cookie-list-cleared' => array( 'success', 'De cookielijst is leeggemaakt. De ingebouwde cookies blijven staan.' ),
```

Voeg na `cm_admin_action_url()` toe:

```php
/** Stuur rijen als CSV-download (met BOM, zodat Excel UTF-8 herkent) en stop. */
function cm_admin_send_csv( $filename, array $rows ) {
    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
    $out = fopen( 'php://output', 'w' );
    fputs( $out, "\xEF\xBB\xBF" );
    foreach ( $rows as $row ) fputcsv( $out, $row );
    fclose( $out );
    exit;
}
```

- [ ] **Step 5: Laat de oude AJAX-export de rij-builder gebruiken (DRY)**

Vervang in `includes/admin.php`, in `cm_ajax_export_cookies_csv()`, alles vanaf `$cat_labels = array(` tot en met de regel `$csv      = implode( "\r\n", $rows );` door:

```php
    $escape = function( $v ) { return '"' . str_replace( '"', '""', (string) $v ) . '"'; };
    $lines  = array();
    foreach ( cm_cookie_list_csv_rows( $cookies ) as $r ) $lines[] = implode( ',', array_map( $escape, $r ) );
    $csv    = implode( "\r\n", $lines );
```

De regels `$cookies = cm_get_cookie_list();` en de `$filename`/`wp_send_json_success` erna blijven staan.

- [ ] **Step 6: Draai de tests**

Run: `php tests/test-admin3-cookies.php && php tests/run.php`
Expected: alles groen.

- [ ] **Step 7: Commit**

```bash
git add includes/admin/page-cookies.php includes/admin/actions.php includes/admin.php tests/test-admin3-cookies.php
git commit -m "feat(admin3): plakken vanuit F12, CSV-export en leegmaken van de cookielijst

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 4: Cookies › Scannen (serverkant)

**Files:**
- Create: `includes/admin/ajax.php`
- Modify: `includes/admin/page-cookies.php`
- Modify: `includes/admin/actions.php` (meldingscode)
- Modify: `includes/admin.php` (nonce-check in `cm_ajax_import_cookie_db`, `cm_ajax_scan_urls`, `cm_ajax_scan_batch`)
- Modify: `cookiemelding.php` (require)
- Modify: `tests/test-admin3-cookies.php`, `tests/test-admin3-registry.php`

**Interfaces:**
- Consumes:
  - de bestaande scan-handlers `cm_scan_urls` → `{ urls, total }` en `cm_scan_batch` (POST `urls[]`) → `{ scanned, http_count, script_count, cookies: [ { name, type, provider, description, duration, how } ] }`, en `cm_import_cookie_db` → `{ imported, skipped, msg }`;
  - `cm_maybe_schedule_auto_scan_cron()` en `cm_force_reset_auto_scan_cron()` (`cookiemelding.php`);
  - `cm_scan_requires_license()` (`license.php`).
- Produces:
  - `cm_admin_verify_ajax( string $action ): void` (stopt met een JSON-fout als capability of nonce niet klopt; accepteert nonce `cm_<actie>` en, tot plan 3, `cm_save_settings`)
  - `cm_scan_result_to_row( array $ck ): array`
  - `cm_merge_cookie_list( array $existing, array $new ): array` (`array( 'list' => …, 'added' => array( namen ) )`)
  - AJAX `cm_scan_add` (POST `cookies` = JSON-array van scanresultaten) → `{ added: [namen] }`
  - `cm_auto_scan_settings_changed( $old, $new ): bool`
  - `cm_tab_cookies_scannen(): array`, met DOM-contract voor Taak 5: `#cm-scan-start`, `#cm-scan-result`, `#cm-cookie-db-import`, `#cm-cookie-db-status`
  - actie `cm_reset_scan_timer`, meldingscode `scan-timer-reset`

- [ ] **Step 1: Breid de tests uit**

In `tests/test-admin3-cookies.php`, bovenaan vóór `require __DIR__ . '/bootstrap.php';`:

```php
class CM_Test_Json extends Exception { public $ok; public $data; function __construct( $ok, $data ) { $this->ok = $ok; $this->data = $data; parent::__construct( 'json' ); } }
function wp_send_json_error( $d = null, $code = null ) { throw new CM_Test_Json( false, $d ); }
function wp_send_json_success( $d = null ) { throw new CM_Test_Json( true, $d ); }
function wp_verify_nonce( $nonce, $action ) { return $nonce === 'nonce-' . $action; }
$GLOBALS['cm_test_can'] = true;
function current_user_can() { return $GLOBALS['cm_test_can']; }
```

Na `require CM_PLUGIN_ROOT . '/includes/admin/page-cookies.php';`:

```php
require CM_PLUGIN_ROOT . '/includes/admin/ajax.php';
```

Vóór `exit( cm_test_summary() );`:

```php
cm_test_group( 'AJAX-controle: rechten en nonce per actie' );
function verify_result( $nonce ) {
    $_POST = array( 'nonce' => $nonce );
    try { cm_admin_verify_ajax( 'scan' ); return 'ok'; } catch ( CM_Test_Json $e ) { return 'fout'; }
}
cm_assert( 'eigen nonce (cm_scan) wordt geaccepteerd', verify_result( 'nonce-cm_scan' ) === 'ok' );
cm_assert( 'gedeelde nonce van de oude admin wordt nog geaccepteerd', verify_result( 'nonce-cm_save_settings' ) === 'ok' );
cm_assert( 'andere nonce wordt geweigerd', verify_result( 'nonce-cm_scan_add' ) === 'fout' && verify_result( '' ) === 'fout' );
$GLOBALS['cm_test_can'] = false;
cm_assert( 'zonder rechten geweigerd, ook met geldige nonce', verify_result( 'nonce-cm_scan' ) === 'fout' );
$GLOBALS['cm_test_can'] = true;

cm_test_group( 'Scanresultaat toevoegen (Review Focus 2 en 4)' );
$r = cm_scan_result_to_row( array( 'name' => '_ga', 'type' => 'analytics', 'provider' => 'Google Analytics', 'description' => 'Meet', 'duration' => '2 jaar', 'how' => 'server' ) );
cm_assert( 'scanvelden → lijstvelden', $r === array( 'name' => '_ga', 'provider' => 'Google Analytics', 'purpose' => 'Meet', 'duration' => '2 jaar', 'category' => 'analytics' ) );
cm_assert( 'onbekend type wordt functioneel, lege looptijd "Sessie"', cm_scan_result_to_row( array( 'name' => 'x', 'type' => 'unknown' ) )['category'] === 'functional' && cm_scan_result_to_row( array( 'name' => 'x' ) )['duration'] === 'Sessie' );
$m = cm_merge_cookie_list( array( array( 'name' => '_ga', 'purpose' => 'eigen tekst' ) ), array( array( 'name' => '_ga', 'purpose' => 'scan' ), array( 'name' => '_fbp' ), array( 'name' => '_fbp' ), array( 'name' => '' ) ) );
cm_assert( 'bestaande rij blijft ongewijzigd', $m['list'][0]['purpose'] === 'eigen tekst' );
cm_assert( 'alleen nieuwe namen, zonder dubbelingen of lege namen', $m['added'] === array( '_fbp' ) && count( $m['list'] ) === 2 );

update_option( 'cm_cookie_list', array( array( 'name' => '_ga', 'provider' => 'Google Analytics', 'purpose' => 'eigen', 'duration' => '2 jaar', 'category' => 'analytics', 'builtin' => false ) ) );
$_POST = array( 'nonce' => 'nonce-cm_scan_add', 'cookies' => addslashes( json_encode( array(
    array( 'name' => '<img src=x onerror=alert(1)>_hjid', 'type' => 'analytics', 'description' => '<script>x</script>Hotjar' ),
    array( 'name' => 'cc_cm_consent', 'type' => 'functional' ),
    array( 'name' => '_ga', 'type' => 'analytics' ),
) ) ) );
try { cm_ajax_scan_add(); $res = null; } catch ( CM_Test_Json $e ) { $res = $e; }
$list = get_option( 'cm_cookie_list' );
cm_assert( 'toevoegen slaagt en meldt alleen de nieuwe naam', $res && $res->ok && $res->data['added'] === array( '_hjid' ) );
cm_assert( 'HTML uit de scan is weg', $list[1]['name'] === '_hjid' && strpos( $list[1]['purpose'], '<' ) === false );
cm_assert( 'ingebouwde cookie niet dubbel in de lijst', ! in_array( 'cc_cm_consent', array_column( $list, 'name' ), true ) );
cm_assert( 'bestaande _ga ongemoeid', $list[0]['purpose'] === 'eigen' && count( $list ) === 2 );

cm_test_group( 'Automatische scan' );
cm_assert( 'wijziging van modus of frequentie → opnieuw inplannen', cm_auto_scan_settings_changed( array( 'auto_scan_mode' => 'off', 'auto_scan_interval' => '30' ), array( 'auto_scan_mode' => 'auto', 'auto_scan_interval' => '30' ) ) );
cm_assert( 'alleen het e-mailadres gewijzigd → niet opnieuw inplannen', ! cm_auto_scan_settings_changed( array( 'auto_scan_mode' => 'notify', 'auto_scan_interval' => '30', 'auto_scan_email' => 'a@b.nl' ), array( 'auto_scan_mode' => 'notify', 'auto_scan_interval' => '30', 'auto_scan_email' => 'c@d.nl' ) ) );
$scan = cm_tabs_cookies()['scannen'];
$keys = array();
foreach ( $scan['sections'] as $s ) foreach ( isset( $s['fields'] ) ? $s['fields'] : array() as $f ) $keys[] = $f['key'];
cm_assert( 'tab Scannen bevat de drie scan-instellingen', $keys === array( 'auto_scan_mode', 'auto_scan_interval', 'auto_scan_email' ) );
cm_assert( 'melding timer resetten bestaat', cm_admin_notice_html( 'scan-timer-reset' ) !== '' );
```

In `tests/test-admin3-registry.php`: vervang de `$pending`-regels door:

```php
$pending = array( 'log_retention_months',                                                 // plan 3: Consent log
                  'api_key' );                                                            // plan 3: Beheer › Geavanceerd
```

- [ ] **Step 2: Draai de tests en zie ze falen**

Run: `php tests/test-admin3-cookies.php; php tests/test-admin3-registry.php`
Expected: fatal "Failed opening required …/includes/admin/ajax.php"; de registry-test meldt "ontbreekt: auto_scan_mode, auto_scan_interval, auto_scan_email".

- [ ] **Step 3: Schrijf `includes/admin/ajax.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   AJAX — alleen voor wat lang duurt of live moet zijn (spec §5.3):
   de scan in batches, de cookiedatabase en een scanresultaat toevoegen.
   Eigen nonce per actie.
================================================================ */

/** Stop met een JSON-fout als de gebruiker geen rechten of een ongeldige nonce heeft. */
function cm_admin_verify_ajax( $action ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'msg' => 'Geen toegang.' ), 403 );
    }
    $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
    if ( wp_verify_nonce( $nonce, 'cm_' . $action ) ) return;
    // ponytail: de oude admin (tot plan 3) stuurt nog de gedeelde nonce; weg met de oude admin
    if ( wp_verify_nonce( $nonce, 'cm_save_settings' ) ) return;
    wp_send_json_error( array( 'msg' => 'De sessie is verlopen. Herlaad de pagina en probeer het opnieuw.' ), 403 );
}

/** Scanresultaat (cm_scan_batch) → regel van de cookielijst. */
function cm_scan_result_to_row( array $ck ) {
    $type = isset( $ck['type'] ) ? (string) $ck['type'] : '';
    return array(
        'name'     => isset( $ck['name'] ) ? (string) $ck['name'] : '',
        'provider' => isset( $ck['provider'] ) ? (string) $ck['provider'] : '',
        'purpose'  => isset( $ck['description'] ) ? (string) $ck['description'] : '',
        'duration' => isset( $ck['duration'] ) && (string) $ck['duration'] !== '' ? (string) $ck['duration'] : 'Sessie',
        'category' => in_array( $type, array( 'functional', 'analytics', 'marketing' ), true ) ? $type : 'functional',
    );
}

/** Voeg nieuwe regels toe; namen die al bestaan blijven ongemoeid. */
function cm_merge_cookie_list( array $existing, array $new ) {
    $names = array();
    foreach ( $existing as $ck ) {
        if ( isset( $ck['name'] ) ) $names[ (string) $ck['name'] ] = true;
    }
    $added = array();
    foreach ( $new as $ck ) {
        $n = isset( $ck['name'] ) ? (string) $ck['name'] : '';
        if ( $n === '' || isset( $names[ $n ] ) ) continue;
        $names[ $n ] = true;
        $existing[]  = $ck;
        $added[]     = $n;
    }
    return array( 'list' => $existing, 'added' => $added );
}

/**
 * Scanresultaten aan de cookielijst toevoegen. Het samenvoegen gebeurt hier,
 * op de opgeslagen lijst — de browser stuurt nooit een hele lijst terug, dus
 * er kan niets overschreven worden.
 */
add_action( 'wp_ajax_cm_scan_add', 'cm_ajax_scan_add' );
function cm_ajax_scan_add() {
    cm_admin_verify_ajax( 'scan_add' );
    $raw = isset( $_POST['cookies'] ) ? json_decode( wp_unslash( $_POST['cookies'] ), true ) : null;
    if ( ! is_array( $raw ) ) wp_send_json_error( array( 'msg' => 'Er zijn geen cookies ontvangen.' ) );

    $builtin = array_column( cm_default_cookies(), 'name' );
    $rows    = array();
    foreach ( cm_sanitize_cookie_list( array_map( 'cm_scan_result_to_row', array_filter( $raw, 'is_array' ) ) ) as $row ) {
        if ( ! in_array( $row['name'], $builtin, true ) ) $rows[] = $row;
    }
    $current = get_option( 'cm_cookie_list', array() );
    $merged  = cm_merge_cookie_list( is_array( $current ) ? $current : array(), $rows );
    if ( $merged['added'] ) update_option( 'cm_cookie_list', cm_sanitize_cookie_list( $merged['list'] ) );
    wp_send_json_success( array( 'added' => $merged['added'] ) );
}
```

- [ ] **Step 4: Tab Scannen in `includes/admin/page-cookies.php`**

Vervang `cm_tabs_cookies()` door:

```php
function cm_tabs_cookies() {
    return array(
        'lijst'   => array( 'label' => 'Cookielijst', 'render' => 'cm_render_cookie_list_tab' ),
        'scannen' => cm_tab_cookies_scannen(),
    );
}
```

Voeg toe:

```php
function cm_tab_cookies_scannen() {
    return array(
        'label'      => 'Scannen',
        'sections'   => array(
            array( 'title' => 'Handmatige scan', 'content' => 'cm_render_manual_scan' ),
            array( 'title' => 'Cookiedatabase', 'content' => 'cm_render_cookie_db_status' ),
            array(
                'title'   => 'Automatische scan',
                'intro'   => 'Bekijkt periodiek de homepage op nieuwe cookies. Vereist een actieve licentie; zonder licentie slaat Cookiebaas de automatische scan over.',
                'content' => 'cm_render_auto_scan_status',
                'fields'  => array(
                    cm_field( 'auto_scan_mode', 'radio', 'Werkwijze', array( 'options' => array(
                        'off'    => 'Handmatig: alleen scannen via de knop hierboven',
                        'auto'   => 'Automatisch toevoegen: nieuw gevonden cookies komen direct in de cookielijst',
                        'notify' => 'Melding per e-mail: een bericht als er nieuwe cookies zijn gevonden',
                    ) ) ),
                    cm_field( 'auto_scan_interval', 'select', 'Frequentie', array(
                        'options' => array( '10' => 'Elke 10 dagen', '30' => 'Elke maand (30 dagen)', '180' => 'Elk half jaar (180 dagen)' ),
                        'show_if' => array( 'auto_scan_mode' => array( 'auto', 'notify' ) ),
                    ) ),
                    cm_field( 'auto_scan_email', 'text', 'E-mailadres', array(
                        'placeholder' => (string) get_option( 'admin_email' ),
                        'sanitize'    => function ( $raw ) { return sanitize_email( is_scalar( $raw ) ? (string) $raw : '' ); },
                        'description' => 'Leeg = het beheerdersadres van WordPress.',
                        'show_if'     => array( 'auto_scan_mode' => 'notify' ),
                    ) ),
                ),
            ),
        ),
        'after_form' => 'cm_render_scan_timer_reset',
    );
}

function cm_render_manual_scan() {
    if ( cm_scan_requires_license() ) {
        echo '<div class="notice notice-warning inline"><p>De cookiescan is een premium-functie en vereist een actieve licentie. De cookiebanner en -blokkering werken gewoon door. <a href="' . esc_url( admin_url( 'admin.php?page=cookiemelding-beheer#tab=licentie' ) ) . '">Licentie beheren</a></p></div>';
        return;
    }
    echo '<p><button type="button" class="button button-primary" id="cm-scan-start">Cookies scannen</button> <span class="description">Doorloopt alle gepubliceerde pagina’s en herkent cookies via HTTP-headers en scripts.</span></p>';
    echo '<div id="cm-scan-result" aria-live="polite"></div>';
}

function cm_render_cookie_db_status() {
    $count   = (int) get_option( 'cm_cookie_db_count', 0 );
    $updated = (string) get_option( 'cm_cookie_db_updated', '' );
    if ( $count > 0 ) {
        $when = $updated !== '' ? ', bijgewerkt op ' . mysql2date( get_option( 'date_format' ), $updated ) : '';
        echo '<p>' . esc_html( number_format_i18n( $count ) . ' cookies geladen' . $when . '.' ) . '</p>';
    } else {
        echo '<p>De database is nog niet geladen.</p>';
    }
    echo '<p><button type="button" class="button" id="cm-cookie-db-import">' . esc_html( $count > 0 ? 'Database bijwerken' : 'Database laden' ) . '</button></p>';
    echo '<div id="cm-cookie-db-status" aria-live="polite" hidden></div>';
    echo '<p class="description">De <a href="https://github.com/jkwakman/Open-Cookie-Database" target="_blank" rel="noopener">Open Cookie Database</a> (Apache 2.0, ruim 2.200 cookies) helpt de scan om cookies te herkennen en te omschrijven.</p>';
}

function cm_render_auto_scan_status() {
    $fmt  = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
    $last = (string) get_option( 'cm_auto_scan_last', '' );
    $next = (string) get_option( 'cm_auto_scan_next', '' );
    $bits = array();
    // Beide staan als UTC opgeslagen (gmdate) — wp_date zet ze om naar de tijdzone van de site
    if ( $last !== '' ) $bits[] = 'Laatste automatische scan: ' . wp_date( $fmt, strtotime( $last . ' UTC' ) ) . '.';
    if ( $next !== '' && cm_get( 'auto_scan_mode' ) !== 'off' ) $bits[] = 'Volgende scan: ' . wp_date( $fmt, strtotime( $next . ' UTC' ) ) . '.';
    if ( $bits ) echo '<p class="description">' . esc_html( implode( ' ', $bits ) ) . '</p>';
}

function cm_render_scan_timer_reset() {
    if ( cm_get( 'auto_scan_mode' ) === 'off' ) return;
    echo '<p>' . cm_admin_action_form( 'reset_scan_timer', 'Timer resetten' ) . ' <span class="description">Plant de volgende automatische scan opnieuw in, gerekend vanaf nu.</span></p>';
}

/** Herplan de scan-cron alleen als modus of frequentie echt veranderde. */
function cm_auto_scan_settings_changed( $old, $new ) {
    foreach ( array( 'auto_scan_mode', 'auto_scan_interval' ) as $k ) {
        $a = is_array( $old ) && isset( $old[ $k ] ) ? (string) $old[ $k ] : '';
        $b = is_array( $new ) && isset( $new[ $k ] ) ? (string) $new[ $k ] : '';
        if ( $a !== $b ) return true;
    }
    return false;
}
// Prioriteit 20: na cm_get_flush (10), zodat de cron de nieuwe waarden leest.
add_action( 'update_option_cm_settings', function ( $old = null, $new = null ) {
    if ( cm_auto_scan_settings_changed( $old, $new ) && function_exists( 'cm_maybe_schedule_auto_scan_cron' ) ) cm_maybe_schedule_auto_scan_cron();
}, 20, 2 );
```

Voeg in het bestaande blok `if ( function_exists( 'cm_admin_register_action' ) ) { … }` toe:

```php
    cm_admin_register_action( 'reset_scan_timer', function () {
        if ( function_exists( 'cm_force_reset_auto_scan_cron' ) ) cm_force_reset_auto_scan_cron();
        return 'scan-timer-reset';
    } );
```

Voeg aan `cm_admin_notice_messages()` in `includes/admin/actions.php` toe:

```php
        'scan-timer-reset'    => array( 'success', 'De timer is gereset. De volgende automatische scan is opnieuw ingepland.' ),
```

- [ ] **Step 5: Nonce-check van de bestaande scan- en database-handlers**

Vervang in `includes/admin.php` in drie functies het begin:
- In **`cm_ajax_import_cookie_db()`** vervang je de regels

```php
    check_ajax_referer( 'cm_save_settings', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Geen toegang' );
```

  door `    cm_admin_verify_ajax( 'import_cookie_db' );`.
- In **`cm_ajax_scan_urls()`** en **`cm_ajax_scan_batch()`** vervang je de regels

```php
    check_ajax_referer( 'cm_save_settings', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die();
```

  door `    cm_admin_verify_ajax( 'scan' );`.

- [ ] **Step 6: Laden**

Voeg in `cookiemelding.php` na de regel voor `includes/admin/page-cookies.php` toe:

```php
require_once CM_PLUGIN_DIR . 'includes/admin/ajax.php';
```

- [ ] **Step 7: Draai de tests**

Run: `php tests/test-admin3-cookies.php && php tests/test-admin3-registry.php && php tests/run.php`
Expected: alles groen. De volledigheidstest ziet de drie scan-instellingen nu op Cookies › Scannen, en de idempotentie-test met het echte register blijft groen: `auto_scan_interval` `'30'` staat in de opties, `auto_scan_mode` `'off'` ook, en een leeg e-mailadres blijft leeg.

- [ ] **Step 8: Commit**

```bash
git add includes/admin/ajax.php includes/admin/page-cookies.php includes/admin/actions.php includes/admin.php cookiemelding.php tests/test-admin3-cookies.php tests/test-admin3-registry.php
git commit -m "feat(admin3): Cookies › Scannen — instellingen, statussen, samenvoegen op de server

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 5: Scan en cookiedatabase in de browser (`admin-cookies.js`)

**Files:**
- Create: `assets/js/admin-cookies.js`
- Modify: `includes/admin/menu.php` (assets Cookies-pagina)
- Modify: `assets/css/admin-layout.css`
- Modify: `tests/test-admin3-frame.php` (broncheck scant ook `admin-cookies.js`)

**Interfaces:**
- Consumes:
  - het DOM-contract uit Taak 4 (`#cm-scan-start`, `#cm-scan-result`, `#cm-cookie-db-import`, `#cm-cookie-db-status`);
  - AJAX-acties `cm_scan_urls`, `cm_scan_batch` (nonce `cm_scan`), `cm_scan_add` (nonce `cm_scan_add`), `cm_import_cookie_db` (nonce `cm_import_cookie_db`).
- Produces: JS-global `CM_COOKIES = { ajaxUrl, nonces: { scan, scanAdd, importDb } }`.

- [ ] **Step 1: Breid de broncheck uit (falende test)**

Voeg in `tests/test-admin3-frame.php`, in de `array_merge(...)` van `$files`, na de regel voor `admin-preview.js` toe:

```php
    glob( CM_PLUGIN_ROOT . '/assets/js/admin-cookies.js' ) ?: array(),
```

Voeg vóór `exit( cm_test_summary() );` toe:

```php
cm_test_group( 'Scan-JS bestaat en gebruikt geen innerHTML met scandata' );
$js = (string) @file_get_contents( CM_PLUGIN_ROOT . '/assets/js/admin-cookies.js' );
cm_assert( 'admin-cookies.js bestaat', $js !== '' );
cm_assert( 'geen innerHTML (scandata alleen via textContent)', strpos( $js, 'innerHTML' ) === false );
```

- [ ] **Step 2: Draai de test en zie hem falen**

Run: `php tests/test-admin3-frame.php`
Expected: FAIL "admin-cookies.js bestaat".

- [ ] **Step 3: Schrijf `assets/js/admin-cookies.js`**

```js
/* Cookiebaas 3 — Cookies › Scannen: scan in batches, resultaten toevoegen,
 * cookiedatabase laden. Scandata komt alleen via textContent in de pagina. */
(function () {
  'use strict';

  var cfg = window.CM_COOKIES;
  if (!cfg) return;

  var LABELS = { functional: 'Functioneel', analytics: 'Analytisch', marketing: 'Marketing', unknown: 'Onbekend' };
  var ORDER = { functional: 0, analytics: 1, marketing: 2, unknown: 3 };

  function post(action, data) {
    var body = new URLSearchParams();
    body.append('action', action);
    Object.keys(data).forEach(function (k) {
      var v = data[k];
      if (Array.isArray(v)) v.forEach(function (x) { body.append(k + '[]', x); });
      else body.append(k, v);
    });
    return fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
      .then(function (r) { return r.json(); });
  }

  function el(tag, text, cls) {
    var e = document.createElement(tag);
    if (text !== undefined && text !== null) e.textContent = text;
    if (cls) e.className = cls;
    return e;
  }

  function notice(box, type, text) {
    box.textContent = '';
    box.className = 'notice notice-' + type + ' inline';
    box.appendChild(el('p', text));
    box.hidden = false;
  }

  /* ---- Cookiedatabase laden ---- */
  var dbBtn = document.getElementById('cm-cookie-db-import');
  var dbOut = document.getElementById('cm-cookie-db-status');
  if (dbBtn && dbOut) dbBtn.addEventListener('click', function () {
    dbBtn.disabled = true;
    notice(dbOut, 'info', 'De Open Cookie Database wordt gedownload…');
    post('cm_import_cookie_db', { nonce: cfg.nonces.importDb }).then(function (r) {
      if (r && r.success) notice(dbOut, 'success', r.data.imported + ' cookies geïmporteerd.');
      else notice(dbOut, 'error', (r && r.data && r.data.msg) || 'Het laden is mislukt.');
    }).catch(function () {
      notice(dbOut, 'error', 'Verbindingsfout. Controleer of de server internettoegang heeft.');
    }).then(function () { dbBtn.disabled = false; });
  });

  /* ---- Scan ---- */
  var scanBtn = document.getElementById('cm-scan-start');
  var result = document.getElementById('cm-scan-result');
  if (!scanBtn || !result) return;
  var found = [];

  function renderResults(pages) {
    found.sort(function (a, b) {
      var oa = ORDER[a.type] !== undefined ? ORDER[a.type] : 9;
      var ob = ORDER[b.type] !== undefined ? ORDER[b.type] : 9;
      return oa !== ob ? oa - ob : String(a.name).localeCompare(String(b.name));
    });
    result.textContent = '';
    result.appendChild(el('p', pages + ' pagina’s gescand, ' + found.length + ' cookies gevonden.'));
    if (!found.length) {
      result.appendChild(el('p', 'Geen cookies gevonden. Kijk ook in uw browser via F12 › Applicatie › Cookies.', 'description'));
      return;
    }
    var bar = el('p');
    var all = el('button', 'Alle gevonden cookies toevoegen', 'button button-primary');
    all.type = 'button';
    all.id = 'cm-scan-add-all';
    bar.appendChild(all);
    result.appendChild(bar);

    var table = el('table', null, 'widefat striped');
    var head = table.createTHead().insertRow();
    ['Cookie', 'Categorie', 'Provider', 'Omschrijving', 'Looptijd', 'Bron', ''].forEach(function (h) {
      var th = el('th', h); th.scope = 'col'; head.appendChild(th);
    });
    var body = table.createTBody();
    found.forEach(function (ck, i) {
      var tr = body.insertRow();
      var name = el('code', ck.name);
      var td = tr.insertCell(); td.appendChild(name);
      tr.insertCell().textContent = LABELS[ck.type] || ck.type || '';
      tr.insertCell().textContent = ck.provider || '';
      tr.insertCell().textContent = ck.description || '—';
      tr.insertCell().textContent = ck.duration || '';
      tr.insertCell().textContent = ck.how === 'server' ? 'HTTP-header' : 'Script';
      var add = el('button', 'Toevoegen', 'button button-small cm-scan-add-one');
      add.type = 'button';
      add.setAttribute('data-i', String(i));
      tr.insertCell().appendChild(add);
    });
    result.appendChild(table);
    result.appendChild(el('p', 'HTTP-header: gezet door de server. Script: afgeleid uit trackingscripts (de browser zet de cookie).', 'description'));
  }

  function addCookies(list, buttons) {
    buttons.forEach(function (b) { b.disabled = true; });
    return post('cm_scan_add', { nonce: cfg.nonces.scanAdd, cookies: JSON.stringify(list) }).then(function (r) {
      if (!r || !r.success) throw new Error('mislukt');
      var added = {};
      (r.data.added || []).forEach(function (n) { added[n] = true; });
      buttons.forEach(function (b) {
        var ck = found[+b.getAttribute('data-i')];
        b.textContent = ck && added[ck.name] ? 'Toegevoegd' : 'Staat al in de lijst';
      });
      return (r.data.added || []).length;
    }).catch(function () {
      buttons.forEach(function (b) { b.disabled = false; });
      return -1;
    });
  }

  result.addEventListener('click', function (e) {
    var one = e.target.closest('.cm-scan-add-one');
    if (one) { addCookies([found[+one.getAttribute('data-i')]], [one]); return; }
    var all = e.target.closest('#cm-scan-add-all');
    if (!all) return;
    all.disabled = true;
    var buttons = Array.prototype.slice.call(result.querySelectorAll('.cm-scan-add-one:not([disabled])'));
    addCookies(buttons.map(function (b) { return found[+b.getAttribute('data-i')]; }), buttons).then(function (n) {
      var msg = n > 0 ? ' ' + n + ' cookies toegevoegd.' : (n === 0 ? ' Alle cookies staan al in de lijst.' : ' Toevoegen is mislukt. Probeer het opnieuw.');
      all.parentNode.appendChild(el('span', msg, 'description'));
      if (n < 0) all.disabled = false;
    });
  });

  scanBtn.addEventListener('click', function () {
    scanBtn.disabled = true;
    found = [];
    result.textContent = '';
    var label = el('p', 'Pagina’s ophalen…');
    var progress = el('progress');
    progress.max = 100;
    progress.value = 0;
    result.appendChild(label);
    result.appendChild(progress);

    post('cm_scan_urls', { nonce: cfg.nonces.scan }).then(function (r) {
      if (!r || !r.success) throw new Error((r && r.data && r.data.msg) || 'De pagina’s konden niet worden opgehaald.');
      var urls = r.data.urls || [];
      var batches = [];
      for (var i = 0; i < urls.length; i += 5) batches.push(urls.slice(i, i + 5));
      var seen = {};
      var done = 0;
      function next(idx) {
        if (idx >= batches.length) return Promise.resolve();
        return post('cm_scan_batch', { nonce: cfg.nonces.scan, urls: batches[idx] }).then(function (b) {
          if (b && b.success) (b.data.cookies || []).forEach(function (c) {
            if (!seen[c.name]) { seen[c.name] = true; found.push(c); }
          });
        }).catch(function () { /* batch mislukt: overslaan en doorgaan */ }).then(function () {
          done += batches[idx].length;
          progress.value = Math.round((idx + 1) / batches.length * 100);
          label.textContent = done + ' van ' + urls.length + ' pagina’s gescand…';
          return next(idx + 1);
        });
      }
      return next(0).then(function () { renderResults(urls.length); });
    }).catch(function (err) {
      notice(result, 'error', err && err.message ? err.message : 'De scan is mislukt.');
    }).then(function () { scanBtn.disabled = false; });
  });
})();
```

- [ ] **Step 4: Assets in `includes/admin/menu.php`**

Voeg in `cm_admin3_assets()`, na het blok `if ( $page === 'cookiebaas-banner' ) { … }`, toe:

```php
    if ( $page === 'cookiebaas-cookies' ) {
        wp_enqueue_script( 'cm-admin-cookies', CM_PLUGIN_URL . 'assets/js/admin-cookies.js', array(), CM_VERSION, true );
        wp_localize_script( 'cm-admin-cookies', 'CM_COOKIES', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonces'  => array(
                'scan'     => wp_create_nonce( 'cm_scan' ),
                'scanAdd'  => wp_create_nonce( 'cm_scan_add' ),
                'importDb' => wp_create_nonce( 'cm_import_cookie_db' ),
            ),
        ) );
    }
```

- [ ] **Step 5: CSS**

Voeg aan `assets/css/admin-layout.css` toe:

```css
#cm-scan-result progress { width: 100%; max-width: 480px; }
#cm-scan-result table { margin-top: 8px; }
```

- [ ] **Step 6: Draai de tests**

Run: `php tests/test-admin3-frame.php && php tests/run.php`. Draai ook `node --check assets/js/admin-cookies.js` als node beschikbaar is.
Expected: alles groen; de broncheck scant nu ook `admin-cookies.js`.

- [ ] **Step 7: Handmatige check (Ruud)**

Op *Cookiebaas 3 › Cookies › Scannen*:
- "Cookies scannen" toont een voortgangsbalk en daarna een tabel.
- "Toevoegen" zet een cookie in de lijst; een tweede keer meldt "Staat al in de lijst".
- "Database bijwerken" meldt het aantal.

- [ ] **Step 8: Commit**

```bash
git add assets/js/admin-cookies.js includes/admin/menu.php assets/css/admin-layout.css tests/test-admin3-frame.php
git commit -m "feat(admin3): scan en cookiedatabase in de browser, zonder innerHTML

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 6: Privacy opslaan, verwerkingsregister en standaardtekst

**Files:**
- Create: `includes/admin/page-privacy.php` (in deze taak: grondslagen, register-rijen, acties)
- Modify: `includes/admin/settings.php` (register + callback voor `cm_privacy`)
- Modify: `includes/privacy.php` (`cm_sanitize_privacy()` type-bewust + bestaande waarden; oude AJAX-opslag geeft de bestaande waarden mee)
- Modify: `includes/admin.php` (oud `cm_ajax_export_register()` gebruikt de rij-builder en `cm_admin_send_csv`)
- Modify: `includes/admin/actions.php` (meldingscode)
- Modify: `cookiemelding.php` (require)
- Create: `tests/test-admin3-privacy.php`

**Interfaces:**
- Consumes: `cm_sanitize_field_value()` (inclusief `rows`), `cm_rows_decode()`, `cm_admin_register_action()`, `cm_admin_send_csv()`.
- Produces:
  - `cm_privacy_sanitize_callback( $input ): array`. Bij `null` of een niet-array komen de bestaande waarden terug. Anders: `cm_sanitize_privacy( array_merge( cm_default_privacy(), $existing, $input ), $existing )`.
  - `cm_sanitize_privacy( array $input, array $existing = array(), ?array $index = null ): array`. Velden van type `color`, `select`, `rows` en `checkbox` in het register gaan via `cm_sanitize_field_value()`, met de bestaande waarde (of de default) als "huidig". De rest werkt zoals nu.
  - `cm_avg_grondslagen(): array` (de 6 AVG-grondslagen, `waarde => label`)
  - `cm_register_csv_rows( array $pv, $retention_months, string $date ): array`
  - acties `cm_export_register` (GET) en `cm_reset_privacy` (POST), meldingscode `privacy-reset`
  - settings-groep `cookiebaas_privacy`

- [ ] **Step 1: Schrijf de falende test**

`tests/test-admin3-privacy.php`:

```php
<?php
/**
 * Nieuwe admin (3.0) — privacyverklaring opslaan en verwerkingsregister.
 *
 * Borgt: de rijtabellen blijven JSON-strings (data blijft); de oude admin
 * blijft correct opslaan (Review Focus 3); een oude grondslag buiten de zes
 * opties blijft behouden (Review Focus 5); het verwerkingsregister gebruikt
 * de ingevulde bewaartermijnen en de gekozen grondslag van het contactformulier.
 */

function sanitize_text_field( $s )     { return is_scalar( $s ) ? trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) ) : ''; }
function sanitize_textarea_field( $s ) { return is_scalar( $s ) ? trim( strip_tags( (string) $s ) ) : ''; }
function esc_url_raw( $s ) { $s = trim( (string) $s ); return preg_match( '#^https?://#i', $s ) ? $s : ''; }
$GLOBALS['cm_test_errors'] = array();
function add_settings_error( $setting, $code, $message ) { $GLOBALS['cm_test_errors'][] = $message; }

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
require CM_PLUGIN_ROOT . '/includes/admin/fields.php';
require CM_PLUGIN_ROOT . '/includes/admin/settings.php';
require CM_PLUGIN_ROOT . '/includes/admin/actions.php';
require CM_PLUGIN_ROOT . '/includes/privacy.php';
require CM_PLUGIN_ROOT . '/includes/admin/page-privacy.php';

$g = cm_avg_grondslagen();
$index = array(
    'pv_cf_grondslag' => cm_field( 'pv_cf_grondslag', 'select', 'Rechtsgrondslag', array( 'option' => 'cm_privacy', 'options' => $g ) ),
    'pv_table_border' => cm_field( 'pv_table_border', 'color', 'Randen', array( 'option' => 'cm_privacy' ) ),
    'pv_dpo_enabled'  => cm_field( 'pv_dpo_enabled', 'checkbox', 'DPO', array( 'option' => 'cm_privacy' ) ),
    'pv_doeleinden'   => cm_field( 'pv_doeleinden', 'rows', 'Doeleinden', array( 'option' => 'cm_privacy', 'columns' => array(
        'doel' => array( 'label' => 'Doel' ), 'grondslag' => array( 'label' => 'Grondslag' ), 'termijn' => array( 'label' => 'Bewaartermijn' ),
    ) ) ),
);

cm_test_group( 'Grondslagen' );
cm_assert( 'zes AVG-grondslagen, waarde = label', count( $g ) === 6 && isset( $g['Toestemming (Art. 6 lid 1 sub a AVG)'] ) && isset( $g['Gerechtvaardigd belang (Art. 6 lid 1 sub f AVG)'] ) );

cm_test_group( 'Nieuw formulier (arrays) en typen' );
$base = cm_default_privacy();
$out  = cm_sanitize_privacy( array_merge( $base, array(
    'pv_doeleinden'   => array( 2 => array( 'doel' => 'Contact', 'grondslag' => 'Toestemming', 'termijn' => '1 jaar' ) ),
    'pv_table_border' => 'DDD',
    'pv_dpo_enabled'  => '1',
) ), $base, $index );
cm_assert( 'rijtabel wordt JSON-string met dezelfde sleutels', json_decode( $out['pv_doeleinden'], true ) === array( array( 'doel' => 'Contact', 'grondslag' => 'Toestemming', 'termijn' => '1 jaar' ) ) );
cm_assert( 'geplakte hex genormaliseerd', $out['pv_table_border'] === '#dddddd' );
cm_assert( 'checkbox 1', $out['pv_dpo_enabled'] === '1' );

cm_test_group( 'Oude admin blijft werken (Review Focus 3)' );
$old = cm_sanitize_privacy( array_merge( $base, array(
    'pv_doeleinden'   => '[{"doel":"Oud","grondslag":"Toestemming","termijn":"2 jaar"}]',
    'pv_cf_voornaam'  => '0',
    'pv_cf_grondslag' => 'Toestemming (Art. 6 lid 1 sub a AVG)',
    'pv_cf_extra'     => "Factuurnummer\nProjectnaam",
) ), $base, $index );
cm_assert( 'JSON-string uit de oude admin blijft bruikbaar', json_decode( $old['pv_doeleinden'], true )[0]['doel'] === 'Oud' );
cm_assert( 'checkbox 0 blijft 0', $old['pv_cf_voornaam'] === '0' );
cm_assert( 'grondslag uit de lijst geaccepteerd', $old['pv_cf_grondslag'] === 'Toestemming (Art. 6 lid 1 sub a AVG)' );
cm_assert( 'tekstvak houdt regeleinden', $old['pv_cf_extra'] === "Factuurnummer\nProjectnaam" );

cm_test_group( 'Oude vrije grondslag blijft behouden (Review Focus 5)' );
$existing = array_merge( $base, array( 'pv_cf_grondslag' => 'Eigen oude tekst' ) );
$GLOBALS['cm_test_errors'] = array();
$keep = cm_sanitize_privacy( array_merge( $existing, array( 'pv_cf_grondslag' => 'Eigen oude tekst' ) ), $existing, $index );
cm_assert( 'waarde buiten de opties blijft staan', $keep['pv_cf_grondslag'] === 'Eigen oude tekst' );
cm_assert( 'met een melding om een geldige grondslag te kiezen', count( $GLOBALS['cm_test_errors'] ) === 1 && strpos( $GLOBALS['cm_test_errors'][0], 'Rechtsgrondslag' ) !== false );

cm_test_group( 'Settings API-callback' );
update_option( 'cm_privacy', array_merge( $base, array( 'pv_bedrijfsnaam' => 'Oud BV' ) ) );
cm_assert( 'null laat alles staan', cm_privacy_sanitize_callback( null )['pv_bedrijfsnaam'] === 'Oud BV' );
cm_assert( 'gedeeltelijke invoer laat andere velden staan', cm_privacy_sanitize_callback( array( 'pv_email' => 'info@voorbeeld.nl' ) )['pv_bedrijfsnaam'] === 'Oud BV' );

cm_test_group( 'Verwerkingsregister' );
$pv = array_merge( $base, array(
    'pv_bedrijfsnaam' => 'Voorbeeld BV',
    'pv_doeleinden'   => '[{"doel":"Nieuwsbrief","grondslag":"Toestemming","termijn":"Tot afmelding"}]',
    'pv_ontvangers'   => '[{"partij":"Mailchimp","doel":"Mail","locatie":"VS"}]',
    'pv_cf_grondslag' => 'Toestemming (Art. 6 lid 1 sub a AVG)',
    'pv_dpo_enabled'  => '0',
) );
$rows = cm_register_csv_rows( $pv, 36, '26-09-2026' );
cm_assert( 'kop met bedrijfsnaam en datum', $rows[0][0] === 'Verwerkingsregister — Voorbeeld BV' && $rows[0][2] === 'Datum: 26-09-2026' );
$doel = $rows[3];
cm_assert( 'doel-rij gebruikt de ingevulde bewaartermijn', $doel[0] === 'Nieuwsbrief' && $doel[5] === 'Tot afmelding' );
cm_assert( 'ontvangers met locatie', strpos( $doel[4], 'Mailchimp (VS)' ) === 0 );
$contact = null;
foreach ( $rows as $r ) if ( $r[0] === 'Contactformulier' ) $contact = $r;
cm_assert( 'contactformulier gebruikt de gekozen grondslag', $contact && $contact[3] === 'Toestemming (Art. 6 lid 1 sub a AVG)' );
$consent = null;
foreach ( $rows as $r ) if ( $r[0] === 'Cookietoestemming registratie' ) $consent = $r;
cm_assert( 'bewaartermijn consent log in maanden', $consent && $consent[5] === '36 maanden' );
cm_assert( 'geen DPO-rij als die uit staat', ! in_array( 'Functionaris Gegevensbescherming (DPO)', array_column( $rows, 0 ), true ) );
$pv['pv_dpo_enabled'] = '1'; $pv['pv_dpo_naam'] = 'Jan';
cm_assert( 'wel een DPO-rij als die aan staat', in_array( 'Functionaris Gegevensbescherming (DPO)', array_column( cm_register_csv_rows( $pv, 0, 'x' ), 0 ), true ) );
cm_assert( 'melding herstellen bestaat', cm_admin_notice_html( 'privacy-reset' ) !== '' );

exit( cm_test_summary() );
```

- [ ] **Step 2: Draai de test en zie hem falen**

Run: `php tests/test-admin3-privacy.php`
Expected: fatal "Failed opening required …/includes/admin/page-privacy.php".

- [ ] **Step 3: Schrijf `includes/admin/page-privacy.php` (deel 1)**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   PAGINA PRIVACYVERKLARING — opslag in cm_privacy (eigen settings-groep),
   verwerkingsregister (AVG art. 30) en standaardtekst herstellen.
   De pagina zelf staat onderaan (plan 2, taak 7).
================================================================ */

/** De zes AVG-grondslagen (art. 6 lid 1), als waarde => label. */
function cm_avg_grondslagen() {
    $list = array(
        'Toestemming (Art. 6 lid 1 sub a AVG)',
        'Uitvoering overeenkomst (Art. 6 lid 1 sub b AVG)',
        'Wettelijke verplichting (Art. 6 lid 1 sub c AVG)',
        'Vitaal belang (Art. 6 lid 1 sub d AVG)',
        'Publiekrechtelijke taak (Art. 6 lid 1 sub e AVG)',
        'Gerechtvaardigd belang (Art. 6 lid 1 sub f AVG)',
    );
    return array_combine( $list, $list );
}

/**
 * Rijen van het verwerkingsregister (CSV). Gebruikt de ingevulde
 * bewaartermijn per doel en de gekozen grondslag van het contactformulier.
 */
function cm_register_csv_rows( array $pv, $retention_months, $date ) {
    $pv      = array_merge( cm_default_privacy(), $pv );
    $company = (string) $pv['pv_bedrijfsnaam'];
    $empty   = array( '', '', '', '', '', '', '' );
    $rows    = array(
        array( 'Verwerkingsregister — ' . $company, 'Gegenereerd door Cookiebaas', 'Datum: ' . $date, '', '', '', '' ),
        $empty,
        array( 'Verwerkingsactiviteit', 'Categorie betrokkenen', 'Doel', 'Rechtsgrondslag', 'Ontvangers / Verwerkers', 'Bewaartermijn', 'Internationale doorgifte' ),
    );
    $recipients = '';
    foreach ( cm_rows_decode( $pv['pv_ontvangers'] ) as $o ) {
        if ( ! empty( $o['partij'] ) ) $recipients .= $o['partij'] . ' (' . ( isset( $o['locatie'] ) ? $o['locatie'] : '' ) . '); ';
    }
    $transfer = (string) $pv['pv_doorgifte'] !== '' ? (string) $pv['pv_doorgifte'] : 'Nee / Niet van toepassing';
    foreach ( cm_rows_decode( $pv['pv_doeleinden'] ) as $d ) {
        $rows[] = array(
            isset( $d['doel'] ) ? $d['doel'] : '',
            isset( $d['categorie'] ) ? $d['categorie'] : 'Websitebezoekers',
            isset( $d['doel'] ) ? $d['doel'] : '',
            isset( $d['grondslag'] ) ? $d['grondslag'] : '',
            $recipients !== '' ? $recipients : '—',
            ! empty( $d['termijn'] ) ? $d['termijn'] : '—',
            $transfer,
        );
    }
    $months = (int) $retention_months;
    $rows[] = array( 'Cookietoestemming registratie', 'Websitebezoekers', 'Vastleggen en bewaren van toestemming voor cookies (AVG art. 7 verantwoordingsplicht)', 'Wettelijke verplichting (AVG art. 7 lid 1)', 'Cookiebaas plugin / ' . $company, $months > 0 ? $months . ' maanden' : 'Niet ingesteld', 'Nee — opslag op eigen server' );
    $rows[] = array( 'Contactformulier', 'Contactpersonen / Klanten', 'Beantwoorden van contactverzoeken', (string) $pv['pv_cf_grondslag'], $company, (string) $pv['pv_bewaar_contact'] !== '' ? (string) $pv['pv_bewaar_contact'] : '—', 'Nee' );
    $rows[] = $empty;
    $rows[] = array( 'Verwerkingsverantwoordelijke', $company, (string) $pv['pv_straat'], (string) $pv['pv_postcode_plaats'], 'E-mail: ' . $pv['pv_email'], '', '' );
    if ( (string) $pv['pv_dpo_enabled'] === '1' ) {
        $rows[] = array( 'Functionaris Gegevensbescherming (DPO)', (string) $pv['pv_dpo_naam'], (string) $pv['pv_dpo_email'], (string) $pv['pv_dpo_telefoon'], '', '', '' );
    }
    return $rows;
}

if ( function_exists( 'cm_admin_register_action' ) ) {
    cm_admin_register_action( 'export_register', function () {
        cm_admin_send_csv(
            'verwerkingsregister-' . wp_date( 'Y-m-d' ) . '.csv',
            cm_register_csv_rows( (array) get_option( 'cm_privacy', array() ), cm_get( 'log_retention_months' ), wp_date( 'd-m-Y' ) )
        );
    } );
    cm_admin_register_action( 'reset_privacy', function () {
        update_option( 'cm_privacy', cm_default_privacy() );
        return 'privacy-reset';
    } );
}
```

- [ ] **Step 4: Registreer `cm_privacy` in `includes/admin/settings.php`**

Voeg aan `cm_admin_register_settings()` toe:

```php
    register_setting( 'cookiebaas_privacy', 'cm_privacy', array(
        'type'              => 'array',
        'sanitize_callback' => 'cm_privacy_sanitize_callback',
        'show_in_rest'      => false,
    ) );
```

Voeg na `cm_cookie_list_sanitize_callback()` toe:

```php
/**
 * Sanitize-callback van cm_privacy. Start altijd vanaf de bestaande waarden,
 * zodat een gedeeltelijke invoer niets wist; null laat alles staan.
 */
function cm_privacy_sanitize_callback( $input ) {
    $existing = get_option( 'cm_privacy', array() );
    $existing = is_array( $existing ) ? $existing : array();
    if ( ! is_array( $input ) ) return $existing;
    return cm_sanitize_privacy( array_merge( cm_default_privacy(), $existing, $input ), $existing );
}
```

- [ ] **Step 5: `cm_sanitize_privacy()` type-bewust in `includes/privacy.php`**

Vervang de volledige functie `cm_sanitize_privacy()` door:

```php
/**
 * Sanitize de privacyverklaring (opslaan en import). Velden die het register
 * kent als kleur, keuzelijst, rijtabel of checkbox gaan type-bewust
 * (cm_sanitize_field_value), met de bestaande waarde als terugval; de rest
 * zoals voorheen. Een ontbrekende checkbox zonder registerveld telt als uit;
 * ontbrekende tekstvelden krijgen de default.
 */
function cm_sanitize_privacy( array $input, array $existing = array(), $index = null ) {
    if ( $index === null ) $index = function_exists( 'cm_admin_field_index' ) ? cm_admin_field_index( 'cm_privacy' ) : array();
    // Tekstvakken waarvan de regeleinden er toe doen (nl2br / één item per regel)
    $textareas = array( 'pv_doeleinden', 'pv_optout_links', 'pv_ontvangers', 'pv_cf_extra', 'pv_doorgifte', 'pv_profilering_tekst', 'pv_wijzigingen_extra' );
    $typed     = array( 'color', 'select', 'rows', 'checkbox' );
    $privacy   = array();

    foreach ( cm_default_privacy() as $key => $default ) {
        $current = isset( $existing[ $key ] ) ? $existing[ $key ] : $default;
        if ( isset( $index[ $key ] ) && in_array( $index[ $key ]['type'], $typed, true ) ) {
            $privacy[ $key ] = isset( $input[ $key ] ) ? cm_sanitize_field_value( $index[ $key ], $input[ $key ], $current ) : (string) $current;
        } elseif ( in_array( $key, $textareas, true ) ) {
            // Incl. de JSON-velden: komen als geëncodeerde string binnen
            $privacy[ $key ] = isset( $input[ $key ] ) ? sanitize_textarea_field( $input[ $key ] ) : $default;
        } elseif ( ( strpos( $key, 'pv_cf_' ) === 0 && $key !== 'pv_cf_grondslag' ) || in_array( $key, array('pv_gtm','pv_ap_tonen','pv_nieuwsbrief_enabled','pv_profilering_enabled'), true ) ) {
            // Checkboxes: 1 of 0
            $privacy[ $key ] = isset( $input[ $key ] ) && (string) $input[ $key ] === '1' ? '1' : '0';
        } else {
            $privacy[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : $default;
        }
    }

    return $privacy;
}
```

Vervang in `cm_ajax_save_privacy()` de regel `update_option( 'cm_privacy', cm_sanitize_privacy( wp_unslash( $_POST ) ) );` door:

```php
    $existing = get_option( 'cm_privacy', array() );
    update_option( 'cm_privacy', cm_sanitize_privacy( wp_unslash( $_POST ), is_array( $existing ) ? $existing : array() ) );
```

**Let op:** `tests/test-admin-fixes.php` (2.4.5) roept `cm_ajax_save_privacy()` aan zonder register (het laadt `page-privacy.php` niet). Het gedrag blijft daar gelijk, en die test moet groen blijven.

- [ ] **Step 6: Laat de oude register-export de builder gebruiken (DRY)**

Vervang in `includes/admin.php` in `cm_ajax_export_register()` alles vanaf `$pv  = array_merge( cm_default_privacy(), …` tot en met `exit;` door:

```php
    cm_admin_send_csv(
        'verwerkingsregister-' . date( 'Y-m-d' ) . '.csv',
        cm_register_csv_rows( (array) get_option( 'cm_privacy', array() ), cm_get( 'log_retention_months' ), date( 'd-m-Y' ) )
    );
```

De nonce- en rechtencontrole bovenaan blijven staan.

- [ ] **Step 7: Melding en laden**

Voeg aan `cm_admin_notice_messages()` toe:

```php
        'privacy-reset'       => array( 'success', 'De privacyverklaring is teruggezet naar de standaardtekst.' ),
```

Voeg in `cookiemelding.php` na de regel voor `includes/admin/ajax.php` toe:

```php
require_once CM_PLUGIN_DIR . 'includes/admin/page-privacy.php';
```

- [ ] **Step 8: Draai de tests**

Run: `php tests/test-admin3-privacy.php && php tests/run.php`
Expected: alles groen, ook `tests/test-admin-fixes.php`.

- [ ] **Step 9: Commit**

```bash
git add includes/admin/page-privacy.php includes/admin/settings.php includes/privacy.php includes/admin.php includes/admin/actions.php cookiemelding.php tests/test-admin3-privacy.php
git commit -m "feat(admin3): privacy opslaan via de Settings API, verwerkingsregister en herstellen

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 7: De pagina Privacyverklaring

**Files:**
- Modify: `includes/admin/page-privacy.php` (tabs, secties, waarden, titelknoppen)
- Modify: `includes/admin/menu.php` (menu-item)
- Modify: `tests/test-admin3-privacy.php`, `tests/test-admin3-registry.php`, `tests/README.md`

**Interfaces:**
- Consumes:
  - veldtypes `rows` en `checkgroup`, en de tab-sleutels `group`, `values` en `title_actions` (Taak 1);
  - `cm_avg_grondslagen()` (Taak 6);
  - `cm_admin_action_url()` en `cm_admin_action_form()`.
- Produces:
  - `cm_tabs_privacy(): array` (één tab `'verklaring'`)
  - `cm_privacy_sections(): array`
  - `cm_privacy_values(): array`
  - `cm_privacy_title_actions(): void`

- [ ] **Step 1: Breid de tests uit**

Voeg in `tests/test-admin3-privacy.php` vóór `exit( cm_test_summary() );` toe:

```php
cm_test_group( 'Pagina Privacyverklaring' );
$tab = cm_tabs_privacy()['verklaring'];
cm_assert( 'eigen settings-groep en waarden', $tab['group'] === 'cookiebaas_privacy' && is_callable( $tab['values'] ) && isset( call_user_func( $tab['values'] )['pv_bedrijfsnaam'] ) );
$titles = array_map( function ( $s ) { return isset( $s['title'] ) ? $s['title'] : ''; }, $tab['sections'] );
$pos    = function ( $t ) use ( $titles ) { $i = array_search( $t, $titles, true ); return $i === false ? -1 : $i; };
cm_assert( 'secties in de volgorde van de uitvoer (11 vóór 12)', $pos( '1. Inleiding' ) < $pos( '2.1 Contactformulier' ) && $pos( '11. Wijzigingen' ) < $pos( '12. Geautomatiseerde besluitvorming' ) && $pos( '11. Wijzigingen' ) > 0 );
cm_assert( 'tabelkleuren onderaan, niet tussen 4 en 5', end( $titles ) === 'Weergave van de cookietabellen' );
cm_assert( 'land is nu te bewerken', in_array( 'pv_land', array_column( cm_admin_field_list( 'cm_privacy' ), 'key' ), true ) );
```

Voeg in `tests/test-admin3-registry.php` vóór `exit( cm_test_summary() );` toe:

```php
cm_test_group( 'Privacyverklaring: elke instelling precies één keer' );
$pkeys    = array_map( function ( $f ) { return $f['key']; }, cm_admin_field_list( 'cm_privacy' ) );
$pdups    = array_keys( array_filter( array_count_values( $pkeys ), function ( $n ) { return $n > 1; } ) );
cm_assert( 'geen privacy-sleutel dubbel' . ( $pdups ? ' — dubbel: ' . implode( ', ', $pdups ) : '' ), ! $pdups );
$pmissing = array_diff( array_keys( cm_default_privacy() ), $pkeys );
cm_assert( 'elke privacy-instelling heeft een plek' . ( $pmissing ? ' — ontbreekt: ' . implode( ', ', $pmissing ) : '' ), ! $pmissing );
$punknown = array_diff( $pkeys, array_keys( cm_default_privacy() ) );
cm_assert( 'geen privacyveld zonder default' . ( $punknown ? ' — onbekend: ' . implode( ', ', $punknown ) : '' ), ! $punknown );
$pd   = cm_default_privacy();
$pout = cm_sanitize_privacy( $pd, $pd );
$pdif = array();
foreach ( $pd as $k => $v ) {
    $same = in_array( $k, array( 'pv_doeleinden', 'pv_optout_links', 'pv_ontvangers' ), true ) ? json_decode( $pout[ $k ], true ) === json_decode( $v, true ) : (string) $pout[ $k ] === (string) $v;
    if ( ! $same ) $pdif[] = $k;
}
cm_assert( 'de privacy-defaults komen ongewijzigd door de sanitizer' . ( $pdif ? ' — veranderd: ' . implode( ', ', $pdif ) : '' ), ! $pdif );
```

De registry-test laadt nog geen `privacy.php`, maar `cm_sanitize_privacy` staat daarin. Voeg daarom in `tests/test-admin3-registry.php` na de `foreach ( glob( …/includes/admin/*.php ) … )`-regel toe:

```php
require CM_PLUGIN_ROOT . '/includes/privacy.php';
```

- [ ] **Step 2: Draai de tests en zie ze falen**

Run: `php tests/test-admin3-privacy.php; php tests/test-admin3-registry.php`
Expected: fatal "Call to undefined function cm_tabs_privacy()"; de registry-test meldt de ontbrekende privacy-sleutels.

- [ ] **Step 3: Voeg de pagina toe aan `includes/admin/page-privacy.php`**

```php
function cm_tabs_privacy() {
    return array(
        'verklaring' => array(
            'label'         => 'Privacyverklaring',
            'group'         => 'cookiebaas_privacy',
            'values'        => 'cm_privacy_values',
            'title_actions' => 'cm_privacy_title_actions',
            'sections'      => cm_privacy_sections(),
        ),
    );
}

function cm_privacy_values() {
    $saved = get_option( 'cm_privacy', array() );
    return array_merge( cm_default_privacy(), is_array( $saved ) ? $saved : array() );
}

function cm_privacy_title_actions() {
    echo '<a class="page-title-action" href="' . esc_url( cm_admin_action_url( 'export_register' ) ) . '">Verwerkingsregister exporteren</a> ';
    echo cm_admin_action_form( 'reset_privacy', 'Standaardtekst herstellen', array(), 'Alle teksten van de privacyverklaring terugzetten naar de standaard?', 'page-title-action' );
}

/** Secties in de volgorde van de uitvoer; bedrijfsgegevens en DPO bovenaan. */
function cm_privacy_sections() {
    $p = function ( $key, $type, $label, array $extra = array() ) {
        return cm_field( $key, $type, $label, array_merge( array( 'option' => 'cm_privacy' ), $extra ) );
    };
    $bases = cm_avg_grondslagen();
    return array(
        array( 'title' => 'Waar verschijnt de verklaring?', 'content' => function () {
            $url = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';
            echo '<p>Plaats de shortcode <code>[cookiebaas_privacy]</code> op uw privacypagina. De cookietabel staat ook los beschikbaar als <code>[cookiebaas_cookies]</code>. Lege velden worden niet getoond.';
            if ( $url ) echo ' Uw privacypagina: <a href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a>.';
            echo '</p>';
        } ),
        array( 'title' => 'Bedrijfsgegevens', 'fields' => array(
            $p( 'pv_bedrijfsnaam', 'text', 'Bedrijfsnaam' ),
            $p( 'pv_straat', 'text', 'Straat en huisnummer' ),
            $p( 'pv_postcode_plaats', 'text', 'Postcode en plaats', array( 'placeholder' => '1234 AB Amsterdam' ) ),
            $p( 'pv_land', 'text', 'Land' ),
            $p( 'pv_kvk', 'text', 'KVK-nummer', array( 'optional' => true ) ),
            $p( 'pv_telefoon', 'text', 'Telefoonnummer', array( 'optional' => true ) ),
            $p( 'pv_email', 'text', 'E-mailadres' ),
            $p( 'pv_versie', 'text', 'Versienummer', array( 'class' => 'small-text', 'placeholder' => '1.0' ) ),
            $p( 'pv_datum', 'text', 'Datum bijgewerkt', array( 'placeholder' => '1 januari 2026' ) ),
        ) ),
        array( 'title' => 'Functionaris Gegevensbescherming (DPO)', 'fields' => array(
            $p( 'pv_dpo_enabled', 'checkbox', 'DPO-sectie', array(
                'checkbox_label' => 'Wij hebben een Functionaris Gegevensbescherming aangesteld',
                'description'    => 'Verplicht voor overheidsorganisaties en organisaties die op grote schaal bijzondere persoonsgegevens verwerken (AVG art. 37). Voor de meeste mkb-organisaties optioneel.',
            ) ),
            $p( 'pv_dpo_naam', 'text', 'Naam', array( 'placeholder' => 'Voornaam Achternaam', 'show_if' => array( 'pv_dpo_enabled' => '1' ) ) ),
            $p( 'pv_dpo_email', 'text', 'E-mailadres', array( 'placeholder' => 'dpo@uwbedrijf.nl', 'show_if' => array( 'pv_dpo_enabled' => '1' ) ) ),
            $p( 'pv_dpo_telefoon', 'text', 'Telefoonnummer', array( 'optional' => true, 'show_if' => array( 'pv_dpo_enabled' => '1' ) ) ),
        ) ),
        array( 'title' => '1. Inleiding', 'fields' => array(
            $p( 'pv_inleiding_naam', 'text', 'Naam in de inleiding', array(
                'placeholder' => 'Leeg = de bedrijfsnaam',
                'description' => 'Verschijnt als: <em>“<strong>[naam]</strong> respecteert uw privacy…”</em>',
            ) ),
        ) ),
        array( 'title' => '2.1 Contactformulier', 'fields' => array(
            $p( 'pv_cf_fields', 'checkgroup', 'Verzamelde velden', array(
                'keys'        => array(
                    'pv_cf_voornaam'   => 'Voornaam',
                    'pv_cf_achternaam' => 'Achternaam',
                    'pv_cf_email'      => 'E-mailadres',
                    'pv_cf_website'    => 'Website (optioneel)',
                    'pv_cf_telefoon'   => 'Telefoonnummer (optioneel)',
                    'pv_cf_bericht'    => 'Uw bericht',
                    'pv_cf_bedrijf'    => 'Bedrijfsnaam (optioneel)',
                    'pv_cf_adres'      => 'Adresgegevens (optioneel)',
                    'pv_cf_privacy'    => 'Acceptatie privacyverklaring',
                ),
                'description' => 'Vink alles uit als u geen contactformulier heeft; de verklaring meldt dat dan.',
            ) ),
            $p( 'pv_cf_extra', 'textarea', 'Eigen velden', array(
                'rows'        => 3,
                'placeholder' => "Eén veld per regel, bijvoorbeeld:\nFactuurnummer\nProjectnaam",
                'description' => 'Optioneel: eigen veldnamen, één per regel. Ze worden vermeld in de verklaring.',
            ) ),
            $p( 'pv_cf_grondslag', 'select', 'Rechtsgrondslag', array( 'options' => $bases, 'description' => 'Verplicht te vermelden (Art. 13 lid 1c AVG).' ) ),
        ) ),
        array( 'title' => '2.3 Nieuwsbrief en e-mailmarketing', 'fields' => array(
            $p( 'pv_nieuwsbrief_enabled', 'checkbox', 'Nieuwsbrief', array( 'checkbox_label' => 'Wij versturen een nieuwsbrief of marketing-e-mails' ) ),
            $p( 'pv_nieuwsbrief_grondslag', 'select', 'Rechtsgrondslag', array( 'options' => $bases, 'show_if' => array( 'pv_nieuwsbrief_enabled' => '1' ) ) ),
            $p( 'pv_nieuwsbrief_afmelden', 'text', 'Afmeldpagina', array(
                'optional'    => true,
                'placeholder' => 'https://…',
                'description' => 'Leeg = de standaardtekst “via de afmeldlink onderaan elke e-mail”.',
                'show_if'     => array( 'pv_nieuwsbrief_enabled' => '1' ),
            ) ),
        ) ),
        array( 'title' => '3. Doeleinden en grondslagen', 'fields' => array(
            $p( 'pv_doeleinden', 'rows', 'Doeleinden', array(
                'columns'   => array(
                    'doel'      => array( 'label' => 'Doel' ),
                    'grondslag' => array( 'label' => 'Grondslag', 'suggestions' => array_keys( $bases ) ),
                    'termijn'   => array( 'label' => 'Bewaartermijn' ),
                ),
                'add_label' => 'Rij toevoegen',
            ) ),
        ) ),
        array(
            'title'  => '4. Cookies',
            'intro'  => 'De cookietabel in de verklaring komt uit <a href="' . esc_url( admin_url( 'admin.php?page=cookiebaas-cookies' ) ) . '">Cookies › Cookielijst</a>.',
            'fields' => array(
                $p( 'pv_gtm', 'checkbox', 'Google Tag Manager', array( 'checkbox_label' => 'Vermeld dat de website Google Tag Manager gebruikt' ) ),
                $p( 'pv_optout_links', 'rows', 'Opt-out-links', array(
                    'columns'   => array(
                        'naam' => array( 'label' => 'Naam' ),
                        'url'  => array( 'label' => 'URL', 'type' => 'url', 'placeholder' => 'https://…' ),
                    ),
                    'add_label' => 'Link toevoegen',
                ) ),
            ),
        ),
        array( 'title' => '5. Ontvangers van persoonsgegevens', 'fields' => array(
            $p( 'pv_ontvangers', 'rows', 'Ontvangers', array(
                'columns'   => array(
                    'partij'  => array( 'label' => 'Partij' ),
                    'doel'    => array( 'label' => 'Doel' ),
                    'locatie' => array( 'label' => 'Locatie', 'placeholder' => 'NL / VS / VK' ),
                ),
                'add_label' => 'Partij toevoegen',
            ) ),
        ) ),
        array( 'title' => '6. Internationale doorgifte', 'fields' => array(
            $p( 'pv_doorgifte', 'textarea', 'Eigen tekst', array( 'optional' => true, 'description' => 'Leeg = de standaardtekst.' ) ),
        ) ),
        array( 'title' => '7. Bewaartermijnen', 'fields' => array(
            $p( 'pv_bewaar_contact', 'text', 'Contactformulier', array( 'placeholder' => '3 jaar na laatste contact' ) ),
            $p( 'pv_bewaar_logs', 'text', 'Serverlogbestanden', array( 'placeholder' => 'maximaal 6 maanden' ) ),
            $p( 'pv_bewaar_analytics', 'text', 'Analytische gegevens', array( 'placeholder' => 'Zie sectie 4 (per cookie)' ) ),
            $p( 'pv_bewaar_nieuwsbrief', 'text', 'Nieuwsbriefabonnement', array( 'optional' => true, 'placeholder' => 'Tot afmelding + 1 jaar' ) ),
        ) ),
        array( 'title' => '8. Uw rechten', 'fields' => array(
            $p( 'pv_rechten_email', 'text', 'E-mailadres voor verzoeken', array( 'placeholder' => 'Leeg = het algemene e-mailadres' ) ),
            $p( 'pv_rechten_termijn', 'text', 'Reactietermijn', array( 'placeholder' => 'één maand' ) ),
        ) ),
        array( 'title' => '10. Klachten', 'fields' => array(
            $p( 'pv_ap_tonen', 'checkbox', 'Autoriteit Persoonsgegevens', array( 'checkbox_label' => 'Toon het adres van de Autoriteit Persoonsgegevens' ) ),
        ) ),
        array( 'title' => '11. Wijzigingen', 'fields' => array(
            $p( 'pv_wijzigingen_extra', 'textarea', 'Aanvullende tekst', array( 'optional' => true, 'rows' => 3 ) ),
        ) ),
        array( 'title' => '12. Geautomatiseerde besluitvorming', 'fields' => array(
            $p( 'pv_profilering_enabled', 'checkbox', 'Profilering', array(
                'checkbox_label' => 'Wij gebruiken geautomatiseerde besluitvorming of profilering (Art. 22 AVG)',
                'description'    => 'Verplicht te vermelden als u profilering of geautomatiseerde besluitvorming toepast, bijvoorbeeld remarketing via Google of Meta.',
            ) ),
            $p( 'pv_profilering_tekst', 'textarea', 'Eigen tekst', array( 'optional' => true, 'placeholder' => 'Leeg = de standaardtekst.', 'show_if' => array( 'pv_profilering_enabled' => '1' ) ) ),
        ) ),
        array(
            'title'  => 'Weergave van de cookietabellen',
            'intro'  => 'Kleuren van de tabellen in <code>[cookiebaas_privacy]</code> en <code>[cookiebaas_cookies]</code>, los van uw thema.',
            'fields' => array(
                $p( 'pv_table_header_bg', 'color', 'Koprij — achtergrond' ),
                $p( 'pv_table_header_color', 'color', 'Koprij — tekst' ),
                $p( 'pv_table_border', 'color', 'Randen' ),
                $p( 'pv_table_row_bg', 'color', 'Rij — achtergrond' ),
                $p( 'pv_table_row_alt_bg', 'color', 'Rij — achtergrond (om en om)' ),
                $p( 'pv_table_text', 'color', 'Tekst' ),
            ),
        ),
    );
}
```

Voeg in `includes/admin/menu.php` aan `cm_admin_pages()` toe, na `'cookiebaas-cookies' => 'Cookies',`:

```php
        'cookiebaas-privacy'    => 'Privacyverklaring',
```

- [ ] **Step 4: Werk `tests/README.md` bij**

Voeg aan de suitetabel toe, onder `test-admin3-registry.php`:

```markdown
| `test-admin3-cookies.php` | Pagina Cookies: cookielijst via de Settings API (alles verwijderen = leeg), F12-import, CSV, AJAX-nonces, scanresultaten samenvoegen zonder overschrijven. |
| `test-admin3-privacy.php` | Privacyverklaring: rijtabellen blijven JSON, oude admin blijft werken, oude grondslag behouden, verwerkingsregister, sectievolgorde. |
```

De rij van `test-admin3-registry.php` krijgt achteraan: "; ook de privacy-instellingen".

- [ ] **Step 5: Draai de tests**

Run: `php tests/test-admin3-privacy.php && php tests/test-admin3-registry.php && php tests/run.php`
Expected: alles groen.
- Meldt de registry-test **"veranderd: pv_…"** bij de privacy-defaults? Dan komt een default niet door zijn veldtype. Een kleur-default moet `#rrggbb` zijn, en een grondslag-default moet een van de zes opties zijn.
- Los dat op in de veld-definitie, niet in de test.

- [ ] **Step 6: Handmatige check (Ruud)**

Op *Cookiebaas 3 › Privacyverklaring*:
- Vink "Wij hebben een Functionaris Gegevensbescherming aangesteld" aan. De DPO-velden verschijnen.
- Voeg een doeleinde toe en verwijder een ontvanger. Na opslaan staat alles goed, ook in de shortcode op de site.
- "Verwerkingsregister exporteren" downloadt een CSV die in Excel leesbaar is.
- "Standaardtekst herstellen" vraagt om bevestiging.

- [ ] **Step 7: Commit**

```bash
git add includes/admin/page-privacy.php includes/admin/menu.php tests/test-admin3-privacy.php tests/test-admin3-registry.php tests/README.md
git commit -m "feat(admin3): pagina Privacyverklaring in de volgorde van de uitvoer

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

## Na plan 2

- **Cookies** en **Privacyverklaring** werken volledig in *Cookiebaas 3*. Het oude menu blijft voor Consent log en Beheer, en voor terugvallen.
- **Plan 3** (Consent log, Beheer, Overzicht, overstap, release) doet het volgende:
  - `log_retention_months` en `api_key` uit `$pending` halen;
  - het oude menu, de oude pagina's in `includes/admin.php` en `assets/js/admin.js` verwijderen;
  - de nog gebruikte handlers en kennisbank (scan, cookiedatabase, `cm_fallback_cookies`, `cm_cookie_prefix_match`, en de frontend-AJAX `cm_geo_check`/`cm_log_consent`) verhuizen naar `includes/admin/` of `includes/`;
  - de terugval op de gedeelde nonce in `cm_admin_verify_ajax()` verwijderen;
  - de licentielink op Scannen naar de nieuwe Beheer-pagina zetten.
