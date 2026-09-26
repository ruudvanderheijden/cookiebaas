# Cookiebaas 3.0 — Plan 1: fundament, Banner en Blokkering

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Een nieuwe, WordPress-native admin naast de bestaande bouwen, met het fundament (menu, veldregister, renderer, Settings API, admin-post-acties, preview) en de volledige pagina's **Banner** en **Blokkering**.

**Architecture:** De nieuwe admin staat in `includes/admin/` en registreert een eigen topmenu "Cookiebaas 3" (slug `cookiebaas`) **naast** het oude menu (`cookiemelding`). Beide schrijven naar dezelfde option `cm_settings`, dus je kunt tijdens de bouw altijd terugvallen op het oude scherm. Tabs worden beschreven als data (pagina → tab → secties → velden). Eén renderer maakt daar `form-table`-rijen van, en dezelfde definities sturen de type-bewuste sanitizing. Opslaan gaat via `options.php` (Settings API). Acties gaan via `admin-post.php`.

**Tech Stack:** WordPress 7.1 (admin-CSS van core), PHP (procedureel, `cm_`-prefix, PHP 7.2-compatibele syntax: geen arrow functions, `match` of nullsafe), vanilla JS in `assets/js/admin-*.js` (geen jQuery nodig, behalve `wp.media`), tests zonder dependencies (`php tests/run.php`).

**Spec:** `docs/superpowers/specs/2026-09-26-cookiebaas-3-admin-design.md`

**Vervolg:**
- Plan 2 (Cookies, Privacyverklaring) en plan 3 (Consent log, Beheer, Overzicht, overstap en release) volgen na dit plan en bouwen op de interfaces hieronder.
- Aan het eind van dit plan werken Banner en Blokkering volledig in de nieuwe admin. De rest staat nog in het oude menu.

## Global Constraints

- **Versie:** `CM_VERSION` en de plugin-header blijven **2.4.5**. Niets in dit plan wordt uitgebracht.
- **Commits:** direct op `main`, geen feature-branches. Commitberichten eindigen met `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- **Data blijft:** geen wijziging van option-keys of opslagformaten. Stringwaarden blijven strings: checkboxes `'1'`/`'0'`, getallen als string, en komma-lijsten voor `exclude_page_ids` en `embed_blocked_services`.
- **Frontend blijft functioneel identiek.** De enige frontend-aanraking is Taak 8 (banner-markup naar een eigen functie). De HTML-output daarvan moet byte-identiek blijven.
- **Nieuwe admin-code:**
  - Wat telt als nieuwe admin-code: `includes/admin/*.php`, `assets/js/admin-common.js`, `assets/js/admin-preview.js` en `assets/css/admin-layout.css`.
  - Daarin: **geen** `style="`, **geen** emoji, **geen** 6-cijferige hex-kleuren, en **geen** HTML-entities met een `#`, zoals `&#8217;` (gebruik de echte tekens).
  - Dit wordt getest in Taak 1.
- **Taal:** alle UI-teksten zijn Nederlands. Een nieuwe tekst wordt bewust geformuleerd; een tekst uit de oude admin mag verbeterd worden, maar niet inhoudelijk veranderd.
- **Toegang en nonces:** capability `manage_options` voor alles. Acties gebruiken een eigen nonce per actie (`cm_<actie>`), niet de gedeelde `cm_save_settings`.
- **Oude admin blijft werken** tot plan 3. Het oude `includes/admin.php` en `assets/js/admin.js` blijven geladen. Hun AJAX-opslag loopt vanaf Taak 3 door de nieuwe type-bewuste sanitizer, en die moet hun (string)formaten accepteren.
- **Afwijkingen van de spec** (bewust, klein):
  - de registerfunctie heet `cm_admin_tabs()` in plaats van `cm_admin_fields()`;
  - de sanitizers staan in `includes/admin/settings.php`;
  - de nieuwe CSS heet `assets/css/admin-layout.css`, omdat `admin.css` nog van de oude admin is;
  - de preview-stijl staat in `assets/css/preview-stage.css` (dat is de nep-site in het iframe, geen admin-markup).

## Review Focus

1. **Eén tab opslaan wist niets elders.** `options.php` post alleen de velden van die tab. Andere tabs, andere pagina's en velden van het oude scherm moeten ongemoeid blijven. Getest in Taak 3.
2. **Alles uitvinken en opslaan doet wat je verwacht:**
   - geen enkele pagina uitgesloten geeft `exclude_page_ids = ''`;
   - geen enkele dienst geblokkeerd geeft `embed_blocked_services = 'none'`;
   - alle diensten geblokkeerd geeft `''`.
   Getest in Taak 5 (pagina's) en Taak 9 (diensten).
3. **Hex-codes plakken zoals klanten dat doen:** `FFFFFF`, ` #FFF `, `#ABCDEF`. Die moeten werken (genormaliseerd naar `#ffffff` / `#abcdef`). Een echt ongeldige waarde houdt de vorige kleur en geeft een foutmelding met de veldnaam. Getest in Taak 3.
4. **De oude admin blijft tijdens de bouw correct opslaan.** Die post `embed_blocked_services` en `exclude_page_ids` als string en stuurt `cm_icon_type` mee. Getest in Taak 3 en Taak 9.
5. **De preview voert nooit scripts uit die in een tekstveld geplakt zijn.** Het iframe heeft `sandbox="allow-same-origin"` zonder `allow-scripts`. Getest in Taak 8.

---

## Bestandsoverzicht

| Bestand | Verantwoordelijkheid | Taak |
|---|---|---|
| `includes/admin/menu.php` | Topmenu "Cookiebaas 3", paginalijst, paginaframe (wrap, h1, tabs, meldingen), formulier-tab, assets per pagina | 1 |
| `includes/admin/page-overzicht.php` | Tijdelijke Overzicht-pagina (plan 3 vervangt die) | 1 |
| `includes/admin/fields.php` | Veldregister (`cm_admin_tabs`), veldlijst/index, renderer voor secties, rijen en controls | 2 |
| `assets/js/admin-common.js` | Kleurveld-sync, "Wissen", `show_if`, schakelaars (Licht/Donker, NL/EN), media-kiezer, bevestigen, waarschuwing bij niet-opgeslagen wijzigingen | 2 |
| `assets/css/admin-layout.css` | Alleen layout: kolommen, preview-kolom, kleurrij, details | 2 |
| `includes/admin/settings.php` | `register_setting`, `cm_sanitize_settings` (verplaatst en type-bewust), `cm_sanitize_field_value`, `cm_sanitize_cookie_list` (verplaatst), cache-purge via option-hooks | 3 |
| `includes/admin/actions.php` | `admin-post.php`-acties: registratie, actieformulier, redirect met melding, meldingenlijst | 4 |
| `includes/admin/page-banner.php` | Tabs Vormgeving, Teksten, Weergave, Gedrag, plus actie "standaardkleuren herstellen" | 5, 6, 7 |
| `includes/admin/preview.php` | CSS-variabelenkaart, tekstkaart, preview-HTML | 8 |
| `assets/js/admin-preview.js`, `assets/css/preview-stage.css` | Live preview in een iframe | 8 |
| `includes/frontend.php` | `cm_banner_markup()` uit `cm_render_frontend()` halen | 8 |
| `includes/admin/page-blokkering.php` | Tabs Google, Scripts, Embeds | 9 |
| `cookiemelding.php` | `require_once` per nieuw bestand | 1–9 |
| Tests | `tests/test-admin3-*.php` (per taak), plus aanpassing van `tests/test-admin-fixes.php` | alle |

**Laadvolgorde:** `cookiemelding.php` laadt nu `defaults.php`, `admin.php`, `frontend.php`, `privacy.php`, `license.php` en `updater.php`. De nieuwe bestanden komen direct na `includes/admin.php`. Functies worden pas in hooks aangeroepen, dus de volgorde binnen `includes/admin/` doet er niet toe.

---

### Task 1: Paginaframe en menu "Cookiebaas 3"

**Files:**
- Create: `includes/admin/menu.php`
- Create: `includes/admin/page-overzicht.php`
- Modify: `cookiemelding.php:22` (requires toevoegen)
- Test: `tests/test-admin3-frame.php`

**Interfaces:**
- Produces:
  - `cm_admin_pages(): array` (slug → menutitel)
  - `cm_admin_current_tab( array $tabs, string $requested ): string`
  - `cm_admin_page_tabs( string $page ): array`
  - `cm_admin_render_page(): void`
  - `cm_admin_render_form_tab( string $page, string $tab, array $def ): void`
  - `$GLOBALS['cm_admin_hooks']` (hook-suffixen van de nieuwe pagina's)
  - `cm_tabs_overzicht(): array`
- Een tab-definitie is: `array( 'label' => string, 'sections' => array, 'preview' => bool, 'render' => callable|null, 'after_form' => callable|null )`.
- Consumes (volgt in latere taken, met `function_exists`-guards): `cm_admin_tabs()` (Taak 2), `cm_admin_render_sections()` (Taak 2), `cm_admin_render_notices()` (Taak 4), `cm_admin_render_preview()` (Taak 8).

- [ ] **Step 1: Schrijf de falende test**

`tests/test-admin3-frame.php`:

```php
<?php
/**
 * Nieuwe admin (3.0) — paginaframe en broncheck.
 *
 * Borgt: de tab uit de URL wordt tegen een whitelist gecontroleerd, en de
 * nieuwe admin-code bevat geen inline styles, emoji of hardcoded hex-kleuren
 * (spec §4: alles WordPress-native).
 */

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
require CM_PLUGIN_ROOT . '/includes/admin/menu.php';

cm_test_group( 'Tab uit de URL' );
$tabs = array( 'vormgeving' => array( 'label' => 'Vormgeving' ), 'teksten' => array( 'label' => 'Teksten' ) );
cm_assert( 'bekende tab blijft', cm_admin_current_tab( $tabs, 'teksten' ) === 'teksten' );
cm_assert( 'onbekende tab valt terug op de eerste', cm_admin_current_tab( $tabs, 'bestaat-niet' ) === 'vormgeving' );
cm_assert( 'lege tab valt terug op de eerste', cm_admin_current_tab( $tabs, '' ) === 'vormgeving' );

cm_test_group( 'Menu' );
$pages = cm_admin_pages();
cm_assert( 'Overzicht is de eerste pagina (slug cookiebaas)', array_key_first( $pages ) === 'cookiebaas' );

cm_test_group( 'Broncheck nieuwe admin: geen style=, emoji of hex-kleuren' );
$files = array_merge(
    glob( CM_PLUGIN_ROOT . '/includes/admin/*.php' ),
    glob( CM_PLUGIN_ROOT . '/assets/js/admin-common.js' ) ?: array(),
    glob( CM_PLUGIN_ROOT . '/assets/js/admin-preview.js' ) ?: array(),
    glob( CM_PLUGIN_ROOT . '/assets/css/admin-layout.css' ) ?: array()
);
foreach ( $files as $file ) {
    $src = file_get_contents( $file );
    $rel = str_replace( CM_PLUGIN_ROOT . '/', '', $file );
    cm_assert( "$rel: geen style=\"",         strpos( $src, 'style="' ) === false );
    cm_assert( "$rel: geen emoji",            ! preg_match( '/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $src ) );
    cm_assert( "$rel: geen hex-kleur #rrggbb", ! preg_match( '/#[0-9a-fA-F]{6}\b/', $src ) );
    cm_assert( "$rel: geen &#-entities",      ! preg_match( '/&#x?[0-9a-fA-F]+;/', $src ) );
}

exit( cm_test_summary() );
```

- [ ] **Step 2: Draai de test en zie hem falen**

Run: `php tests/test-admin3-frame.php`
Expected: fatal "Failed opening required …/includes/admin/menu.php".

- [ ] **Step 3: Schrijf `includes/admin/menu.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   NIEUWE ADMIN (3.0) — menu en paginaframe
   Draait tijdens de bouw naast het oude menu (cookiemelding). Beide
   schrijven naar dezelfde options; plan 3 verwijdert het oude menu.
================================================================ */

/** Menupagina's van de nieuwe admin: slug → titel. Plan 2 en 3 vullen aan. */
function cm_admin_pages() {
    return array(
        'cookiebaas'            => 'Overzicht',
        'cookiebaas-banner'     => 'Banner',
        'cookiebaas-blokkering' => 'Blokkering',
    );
}

add_action( 'admin_menu', 'cm_admin3_register_menu' );
function cm_admin3_register_menu() {
    $GLOBALS['cm_admin_hooks'] = array();
    $GLOBALS['cm_admin_hooks'][] = add_menu_page(
        'Cookiebaas', 'Cookiebaas 3', 'manage_options', 'cookiebaas',
        'cm_admin_render_page', 'dashicons-privacy', 82
    );
    foreach ( cm_admin_pages() as $slug => $title ) {
        $GLOBALS['cm_admin_hooks'][] = add_submenu_page(
            'cookiebaas', $title . ' — Cookiebaas', $title, 'manage_options', $slug, 'cm_admin_render_page'
        );
    }
}

/** De gevraagde tab als die bestaat, anders de eerste tab van de pagina. */
function cm_admin_current_tab( array $tabs, $requested ) {
    if ( $requested !== '' && isset( $tabs[ $requested ] ) ) return $requested;
    $keys = array_keys( $tabs );
    return $keys ? $keys[0] : '';
}

/** Tabs van één pagina uit het register (Taak 2). */
function cm_admin_page_tabs( $page ) {
    $all = function_exists( 'cm_admin_tabs' ) ? cm_admin_tabs() : array();
    if ( isset( $all[ $page ] ) && $all[ $page ] ) return $all[ $page ];
    return array( 'overzicht' => cm_tabs_overzicht()['overzicht'] );
}

/** Rendert elke pagina van de nieuwe admin: wrap, titel, meldingen, tabs, inhoud. */
function cm_admin_render_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $page  = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'cookiebaas';
    $pages = cm_admin_pages();
    $tabs  = cm_admin_page_tabs( $page );
    $tab   = cm_admin_current_tab( $tabs, isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '' );
    $def   = $tabs[ $tab ];

    echo '<div class="wrap cm-admin">';
    echo '<h1 class="wp-heading-inline">' . esc_html( isset( $pages[ $page ] ) ? $pages[ $page ] : 'Cookiebaas' ) . '</h1>';
    echo '<hr class="wp-header-end">';
    settings_errors();
    if ( function_exists( 'cm_admin_render_notices' ) ) cm_admin_render_notices();

    if ( count( $tabs ) > 1 ) {
        echo '<nav class="nav-tab-wrapper wp-clearfix" aria-label="Onderdelen van ' . esc_attr( isset( $pages[ $page ] ) ? $pages[ $page ] : 'Cookiebaas' ) . '">';
        foreach ( $tabs as $slug => $t ) {
            $url = add_query_arg( array( 'page' => $page, 'tab' => $slug ), admin_url( 'admin.php' ) );
            $on  = $slug === $tab;
            echo '<a href="' . esc_url( $url ) . '" class="nav-tab' . ( $on ? ' nav-tab-active' : '' ) . '"' . ( $on ? ' aria-current="page"' : '' ) . '>' . esc_html( $t['label'] ) . '</a>';
        }
        echo '</nav>';
    }

    if ( ! empty( $def['render'] ) ) {
        call_user_func( $def['render'] );
    } else {
        cm_admin_render_form_tab( $page, $tab, $def );
    }
    echo '</div>';
}

/** Een tab met velden: formulier naar options.php, optioneel met preview-kolom. */
function cm_admin_render_form_tab( $page, $tab, array $def ) {
    $preview = ! empty( $def['preview'] ) && function_exists( 'cm_admin_render_preview' );
    if ( $preview ) echo '<div class="cm-cols">';
    echo '<form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '" class="cm-form">';
    settings_fields( 'cookiebaas_settings' );
    if ( function_exists( 'cm_admin_render_sections' ) ) {
        cm_admin_render_sections( isset( $def['sections'] ) ? $def['sections'] : array(), cm_get_settings() );
    }
    submit_button( 'Wijzigingen opslaan' );
    echo '</form>';
    if ( $preview ) cm_admin_render_preview( $tab );
    if ( ! empty( $def['after_form'] ) ) call_user_func( $def['after_form'] );
    if ( $preview ) echo '</div>';
}

add_action( 'admin_enqueue_scripts', 'cm_admin3_assets' );
function cm_admin3_assets( $hook ) {
    if ( empty( $GLOBALS['cm_admin_hooks'] ) || ! in_array( $hook, $GLOBALS['cm_admin_hooks'], true ) ) return;
    wp_enqueue_style( 'cm-admin-layout', CM_PLUGIN_URL . 'assets/css/admin-layout.css', array(), CM_VERSION );
    wp_enqueue_script( 'cm-admin-common', CM_PLUGIN_URL . 'assets/js/admin-common.js', array(), CM_VERSION, true );
    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    if ( $page === 'cookiebaas-banner' ) {
        wp_enqueue_media();
        if ( function_exists( 'cm_admin_preview_assets' ) ) cm_admin_preview_assets();
    }
}
```

- [ ] **Step 4: Schrijf `includes/admin/page-overzicht.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* Tijdelijke Overzicht-pagina — plan 3 vervangt die door het echte overzicht. */
function cm_tabs_overzicht() {
    return array(
        'overzicht' => array( 'label' => 'Overzicht', 'render' => 'cm_admin_render_overzicht' ),
    );
}

function cm_admin_render_overzicht() {
    echo '<div class="notice notice-info inline"><p>Deze nieuwe admin is in ontwikkeling. Wat hier nog ontbreekt, staat voorlopig in het oude menu <a href="' . esc_url( admin_url( 'admin.php?page=cookiemelding' ) ) . '">Cookiebaas</a>.</p></div>';
    echo '<ul class="ul-disc">';
    foreach ( cm_admin_pages() as $slug => $title ) {
        if ( $slug === 'cookiebaas' ) continue;
        echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '">' . esc_html( $title ) . '</a></li>';
    }
    echo '</ul>';
}
```

- [ ] **Step 5: Laad de bestanden**

In `cookiemelding.php`, direct na `require_once CM_PLUGIN_DIR . 'includes/admin.php';`:

```php
require_once CM_PLUGIN_DIR . 'includes/admin/menu.php';
require_once CM_PLUGIN_DIR . 'includes/admin/page-overzicht.php';
```

- [ ] **Step 6: Draai de tests**

Run: `php tests/test-admin3-frame.php && php tests/run.php`
Expected: `RESULT: ALL PASS` voor de nieuwe suite, en "✓ Alles groen" voor alles. `array_key_first` bestaat vanaf PHP 7.3; bij een fatal op een oudere PHP vervang je het in de test door `array_keys( $pages )[0]`.

- [ ] **Step 7: Commit**

```bash
git add includes/admin/menu.php includes/admin/page-overzicht.php cookiemelding.php tests/test-admin3-frame.php
git commit -m "feat(admin3): menu en paginaframe van de nieuwe admin naast de oude

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 2: Veldregister, renderer en gedeelde admin-JS

**Files:**
- Create: `includes/admin/fields.php`
- Create: `assets/js/admin-common.js`
- Create: `assets/css/admin-layout.css`
- Modify: `cookiemelding.php` (require)
- Test: `tests/test-admin3-fields.php`

**Interfaces:**
- Produces:
  - `cm_field( string $key, string $type, string $label, array $extra = array() ): array`
  - `cm_admin_tabs(): array` (pagina → tabs)
  - `cm_admin_field_list( string $option = 'cm_settings', ?array $tabs = null ): array` (lijst, dubbelingen blijven zichtbaar)
  - `cm_admin_field_index( string $option = 'cm_settings', ?array $tabs = null ): array` (key → veld)
  - `cm_admin_field_options( array $f ): array`
  - `cm_html_allowed(): array`
  - `cm_csv_list( $value ): array`
  - `cm_admin_render_sections( array $sections, array $values ): void`
  - `cm_admin_render_section( array $section, array $values ): void`
  - `cm_admin_render_field_row( array $f, array $values ): void`
  - `cm_admin_render_control( array $f, string $id, string $name, $value ): void`
- **Veldtypes:** `text | textarea | html | code | color | color_optional | number | checkbox | radio | select | multiselect | checkboxes | media | custom`.
- **Veldsleutels:** `key, type, label, description, placeholder, class, rows, min, max, unit, options (array|Closure), checkbox_label, show_if (array key=>waarde|lijst), notice (array type,text), value (Closure $values → waarde), sanitize (Closure $raw,$current,$f), allowed (kses-lijst voor html), all_when_empty (bool), store (false = alleen UI), option (standaard cm_settings), render (callable voor custom)`.
- **Sectiesleutels:** `title, intro, fields, content (callable), collapsible (bool), open (bool), attrs (array)`.
- **DOM-contract voor de JS:**
  - elke control heeft `data-cm-key="<key>"`;
  - rijen met een voorwaarde hebben `data-cm-show-if='{"key":"waarde"}'`;
  - een kleurrij is `.cm-color` met `input[type=color]` + `input.cm-hex` (draagt de `name`) + optioneel `.cm-color-clear`;
  - een schakelaar is `ul[data-cm-switch="naam"]` met `a[data-cm-switch-to="waarde"]`, bij panes met `data-cm-pane="naam:waarde"`;
  - de JS vuurt het event `cm-switch` af met `detail {name, value}`;
  - formulieren met `data-cm-confirm` vragen om bevestiging.

- [ ] **Step 1: Schrijf de falende test**

`tests/test-admin3-fields.php`:

```php
<?php
/**
 * Nieuwe admin (3.0) — renderer van het veldregister.
 *
 * Borgt: elk veld is gekoppeld aan zijn label (for/id), checkboxes posten een
 * 0 als ze uit staan, kleurvelden tonen nooit een ongeldige waarde in het
 * kleurvlak, optionele kleuren zijn herkenbaar en leeg toegestaan,
 * voorwaardelijke rijen dragen hun show_if.
 */

function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function wp_kses_post( $s ) { return (string) $s; }

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
require CM_PLUGIN_ROOT . '/includes/admin/fields.php';

function row( array $f, array $values ) {
    ob_start();
    cm_admin_render_field_row( $f, $values );
    return ob_get_clean();
}

cm_test_group( 'Tekstveld' );
$h = row( cm_field( 'ga4_measurement_id', 'text', 'GA4 Measurement ID' ), array( 'ga4_measurement_id' => 'G-1' ) );
cm_assert( 'label for verwijst naar het veld', strpos( $h, '<label for="cm-f-ga4_measurement_id">' ) !== false && strpos( $h, 'id="cm-f-ga4_measurement_id"' ) !== false );
cm_assert( 'name volgt de Settings API (option[key])', strpos( $h, 'name="cm_settings[ga4_measurement_id]"' ) !== false );
cm_assert( 'data-cm-key voor de JS', strpos( $h, 'data-cm-key="ga4_measurement_id"' ) !== false );

cm_test_group( 'Checkbox' );
$on  = row( cm_field( 'respect_dnt', 'checkbox', 'Do Not Track', array( 'checkbox_label' => 'Respecteer DNT' ) ), array( 'respect_dnt' => 1 ) );
$off = row( cm_field( 'respect_dnt', 'checkbox', 'Do Not Track' ), array( 'respect_dnt' => '0' ) );
$hid = strpos( $on, 'type="hidden" name="cm_settings[respect_dnt]" value="0"' );
cm_assert( 'verborgen 0 staat vóór de checkbox', $hid !== false && $hid < strpos( $on, 'type="checkbox"' ) );
cm_assert( 'aangevinkt bij 1', strpos( $on, ' checked' ) !== false );
cm_assert( 'niet aangevinkt bij 0', strpos( $off, ' checked' ) === false );

cm_test_group( 'Kleur' );
$ok  = row( cm_field( 'color_title', 'color', 'Titels' ), array( 'color_title' => '#ABCDEF' ) );
$bad = row( cm_field( 'color_title', 'color', 'Titels' ), array( 'color_title' => 'rgb(250 252 255)' ) );
cm_assert( 'geldige hex staat in kleurvlak (lowercase) en hex-veld', strpos( $ok, 'type="color" value="#abcdef"' ) !== false && strpos( $ok, 'class="code cm-hex" value="#ABCDEF"' ) !== false );
cm_assert( 'ongeldige waarde komt niet in het kleurvlak', ! preg_match( '/type="color"[^>]*value=/', $bad ) );
cm_assert( 'hex-veld (niet het kleurvlak) draagt id en name', preg_match( '/<input type="text" id="cm-f-color_title" name="cm_settings\[color_title\]"[^>]*class="code cm-hex"/', $ok ) === 1 );
$opt_empty = row( cm_field( 'color_accept_border', 'color_optional', 'Akkoord — rand', array( 'placeholder' => 'Geen rand' ) ), array( 'color_accept_border' => '' ) );
$opt_full  = row( cm_field( 'color_accept_border', 'color_optional', 'Akkoord — rand' ), array( 'color_accept_border' => '#444444' ) );
cm_assert( 'optioneel: label krijgt (optioneel)', strpos( $opt_empty, '(optioneel)' ) !== false );
cm_assert( 'optioneel leeg: placeholder en Wissen verborgen', strpos( $opt_empty, 'placeholder="Geen rand"' ) !== false && strpos( $opt_empty, 'cm-color-clear" hidden' ) !== false );
cm_assert( 'optioneel gevuld: Wissen zichtbaar', strpos( $opt_full, 'cm-color-clear"' ) !== false && strpos( $opt_full, 'cm-color-clear" hidden' ) === false );

cm_test_group( 'Radio en show_if' );
$r = row( cm_field( 'float_btn_style', 'radio', 'Stijl', array( 'options' => array( 'icon' => 'Rond icoon', 'text' => 'Tekstknop' ), 'show_if' => array( 'show_float_btn' => '1' ) ) ), array( 'float_btn_style' => 'text' ) );
cm_assert( 'fieldset met legend', strpos( $r, '<fieldset><legend class="screen-reader-text">' ) !== false );
cm_assert( 'juiste optie aangevinkt', preg_match( '/value="text" checked/', $r ) === 1 && preg_match( '/value="icon" checked/', $r ) === 0 );
cm_assert( 'rij draagt show_if als JSON', strpos( $r, 'data-cm-show-if="{&quot;show_float_btn&quot;:&quot;1&quot;}"' ) !== false );

cm_test_group( 'Checkboxlijst' );
$cb = cm_field( 'embed_blocked_services', 'checkboxes', 'Diensten', array( 'options' => array( 'YouTube' => 'YouTube', 'Vimeo' => 'Vimeo' ), 'all_when_empty' => true ) );
cm_assert( "leeg + all_when_empty: alles aangevinkt", substr_count( row( $cb, array( 'embed_blocked_services' => '' ) ), ' checked' ) === 2 );
cm_assert( "'none': niets aangevinkt", substr_count( row( $cb, array( 'embed_blocked_services' => 'none' ) ), ' checked' ) === 0 );
cm_assert( "'YouTube': één aangevinkt", substr_count( row( $cb, array( 'embed_blocked_services' => 'YouTube' ) ), ' checked' ) === 1 );
cm_assert( 'verborgen lege waarde zodat alles uit ook post', strpos( row( $cb, array() ), 'type="hidden" name="cm_settings[embed_blocked_services][]" value=""' ) !== false );

cm_test_group( 'Index en lijst' );
$tabs = array( 'p' => array(
    'a' => array( 'label' => 'A', 'sections' => array( array( 'fields' => array(
        cm_field( 'x', 'text', 'X' ),
        cm_field( 'ui_only', 'radio', 'UI', array( 'store' => false ) ),
        cm_field( 'pv_iets', 'text', 'P', array( 'option' => 'cm_privacy' ) ),
    ) ) ) ),
    'b' => array( 'label' => 'B', 'sections' => array( array( 'fields' => array( cm_field( 'x', 'text', 'X nogmaals' ) ) ) ) ),
) );
cm_assert( 'lijst toont dubbelingen', count( cm_admin_field_list( 'cm_settings', $tabs ) ) === 2 );
cm_assert( 'index: alleen opgeslagen velden van deze option', array_keys( cm_admin_field_index( 'cm_settings', $tabs ) ) === array( 'x' ) );
cm_assert( 'index per option', array_keys( cm_admin_field_index( 'cm_privacy', $tabs ) ) === array( 'pv_iets' ) );

exit( cm_test_summary() );
```

- [ ] **Step 2: Draai de test en zie hem falen**

Run: `php tests/test-admin3-fields.php`
Expected: fatal "Failed opening required …/includes/admin/fields.php".

- [ ] **Step 3: Schrijf `includes/admin/fields.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   VELDREGISTER — elke tab van de nieuwe admin is data:
   pagina → tab → secties → velden. Eén renderer maakt er
   WordPress-native form-table-rijen van; dezelfde definities sturen
   de type-bewuste sanitizing (settings.php).
================================================================ */

/** Korte constructor voor een veld-definitie. */
function cm_field( $key, $type, $label, array $extra = array() ) {
    return array_merge( array( 'key' => $key, 'type' => $type, 'label' => $label ), $extra );
}

/**
 * Alle tabs per menupagina. Elke pagina levert zijn deel via een eigen
 * functie; pagina's uit latere plannen worden overgeslagen tot ze bestaan.
 */
function cm_admin_tabs() {
    $sources = array(
        'cookiebaas'            => 'cm_tabs_overzicht',
        'cookiebaas-banner'     => 'cm_tabs_banner',
        'cookiebaas-blokkering' => 'cm_tabs_blokkering',
        'cookiebaas-cookies'    => 'cm_tabs_cookies',
        'cookiebaas-privacy'    => 'cm_tabs_privacy',
        'cookiebaas-log'        => 'cm_tabs_log',
        'cookiebaas-beheer'     => 'cm_tabs_beheer',
    );
    $tabs = array();
    foreach ( $sources as $page => $fn ) {
        if ( function_exists( $fn ) ) $tabs[ $page ] = call_user_func( $fn );
    }
    return $tabs;
}

/** Alle opgeslagen velden van één option, in registervolgorde (dubbelingen blijven zichtbaar). */
function cm_admin_field_list( $option = 'cm_settings', $tabs = null ) {
    $list = array();
    foreach ( ( $tabs === null ? cm_admin_tabs() : $tabs ) as $page_tabs ) {
        foreach ( $page_tabs as $tab ) {
            foreach ( isset( $tab['sections'] ) ? $tab['sections'] : array() as $section ) {
                foreach ( isset( $section['fields'] ) ? $section['fields'] : array() as $f ) {
                    if ( isset( $f['store'] ) && $f['store'] === false ) continue;
                    if ( ( isset( $f['option'] ) ? $f['option'] : 'cm_settings' ) !== $option ) continue;
                    $list[] = $f;
                }
            }
        }
    }
    return $list;
}

/** Sleutel → veld, voor de sanitizer. */
function cm_admin_field_index( $option = 'cm_settings', $tabs = null ) {
    $index = array();
    foreach ( cm_admin_field_list( $option, $tabs ) as $f ) $index[ $f['key'] ] = $f;
    return $index;
}

/** Opties van een radio/select/lijst; een Closure wordt pas hier uitgevoerd. */
function cm_admin_field_options( array $f ) {
    if ( ! isset( $f['options'] ) ) return array();
    return $f['options'] instanceof Closure ? call_user_func( $f['options'] ) : $f['options'];
}

/** Toegestane HTML in teksten van banner en voorkeurenvenster. */
function cm_html_allowed() {
    return array( 'a' => array( 'href' => array(), 'target' => array() ), 'strong' => array(), 'em' => array() );
}

/** Komma-string (of array) → lijst niet-lege strings. */
function cm_csv_list( $value ) {
    $parts = is_array( $value ) ? $value : explode( ',', (string) $value );
    return array_values( array_filter( array_map( 'trim', array_map( 'strval', $parts ) ), 'strlen' ) );
}

function cm_admin_render_sections( array $sections, array $values ) {
    foreach ( $sections as $section ) cm_admin_render_section( $section, $values );
}

function cm_admin_render_section( array $section, array $values ) {
    $collapsible = ! empty( $section['collapsible'] );
    $extra = isset( $section['attrs'] ) ? $section['attrs'] : array();
    $class = ( $collapsible ? 'cm-details' : 'cm-section' ) . ( isset( $extra['class'] ) ? ' ' . $extra['class'] : '' );
    unset( $extra['class'] );
    $attrs = ' class="' . esc_attr( $class ) . '"';
    foreach ( $extra as $k => $v ) {
        $attrs .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
    }
    if ( $collapsible ) {
        echo '<details' . $attrs . ( ! empty( $section['open'] ) ? ' open' : '' ) . '><summary>' . esc_html( $section['title'] ) . '</summary><div class="cm-details-body">';
    } else {
        echo '<div' . $attrs . '>';
        if ( ! empty( $section['title'] ) ) echo '<h2>' . esc_html( $section['title'] ) . '</h2>';
    }
    if ( ! empty( $section['intro'] ) ) echo '<p>' . wp_kses_post( $section['intro'] ) . '</p>';
    if ( ! empty( $section['content'] ) ) call_user_func( $section['content'], $values );
    if ( ! empty( $section['fields'] ) ) {
        echo '<table class="form-table" role="presentation"><tbody>';
        foreach ( $section['fields'] as $f ) cm_admin_render_field_row( $f, $values );
        echo '</tbody></table>';
    }
    echo $collapsible ? '</div></details>' : '</div>';
}

function cm_admin_render_field_row( array $f, array $values ) {
    $key   = $f['key'];
    $id    = 'cm-f-' . $key;
    $name  = ( isset( $f['option'] ) ? $f['option'] : 'cm_settings' ) . '[' . $key . ']';
    $value = ( isset( $f['value'] ) && $f['value'] instanceof Closure )
        ? call_user_func( $f['value'], $values )
        : ( array_key_exists( $key, $values ) ? $values[ $key ] : '' );
    $show  = ! empty( $f['show_if'] ) ? ' data-cm-show-if="' . esc_attr( wp_json_encode( $f['show_if'] ) ) . '"' : '';
    $mark  = ( $f['type'] === 'color_optional' || ! empty( $f['optional'] ) ) ? ' <span class="description">(optioneel)</span>' : '';
    $plain = in_array( $f['type'], array( 'checkbox', 'radio', 'checkboxes', 'media', 'custom' ), true );

    echo '<tr' . $show . '><th scope="row">';
    echo $plain
        ? esc_html( $f['label'] ) . $mark
        : '<label for="' . esc_attr( $id ) . '">' . esc_html( $f['label'] ) . $mark . '</label>';
    echo '</th><td>';
    cm_admin_render_control( $f, $id, $name, $value );
    if ( ! empty( $f['notice'] ) ) {
        echo '<div class="notice notice-' . esc_attr( $f['notice']['type'] ) . ' inline"><p>' . wp_kses_post( $f['notice']['text'] ) . '</p></div>';
    }
    if ( ! empty( $f['description'] ) ) echo '<p class="description">' . wp_kses_post( $f['description'] ) . '</p>';
    echo '</td></tr>';
}

function cm_admin_render_control( array $f, $id, $name, $value ) {
    $key  = esc_attr( $f['key'] );
    $attr = ' id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" data-cm-key="' . $key . '"';
    $ph   = isset( $f['placeholder'] ) ? ' placeholder="' . esc_attr( $f['placeholder'] ) . '"' : '';

    switch ( $f['type'] ) {
        case 'textarea':
        case 'html':
        case 'code':
            $class = $f['type'] === 'code' ? 'large-text code' : 'large-text';
            $rows  = isset( $f['rows'] ) ? (int) $f['rows'] : 4;
            echo '<textarea' . $attr . ' class="' . $class . '" rows="' . $rows . '"' . $ph . '>' . esc_textarea( (string) $value ) . '</textarea>';
            break;

        case 'number':
            echo '<input type="number"' . $attr . ' class="small-text" value="' . esc_attr( (string) $value ) . '"'
                . ( isset( $f['min'] ) ? ' min="' . (int) $f['min'] . '"' : '' )
                . ( isset( $f['max'] ) ? ' max="' . (int) $f['max'] . '"' : '' ) . '>';
            if ( ! empty( $f['unit'] ) ) echo ' ' . esc_html( $f['unit'] );
            break;

        case 'checkbox':
            echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0">';
            echo '<label for="' . esc_attr( $id ) . '"><input type="checkbox"' . $attr . ' value="1"' . ( (string) $value === '1' ? ' checked' : '' ) . '> '
                . wp_kses_post( isset( $f['checkbox_label'] ) ? $f['checkbox_label'] : $f['label'] ) . '</label>';
            break;

        case 'radio':
            echo '<fieldset><legend class="screen-reader-text"><span>' . esc_html( $f['label'] ) . '</span></legend>';
            $i = 0;
            foreach ( cm_admin_field_options( $f ) as $opt => $label ) {
                $oid = $id . '-' . $i++;
                echo '<label for="' . esc_attr( $oid ) . '"><input type="radio" id="' . esc_attr( $oid ) . '" name="' . esc_attr( $name ) . '" data-cm-key="' . $key . '" value="' . esc_attr( $opt ) . '"'
                    . ( (string) $value === (string) $opt ? ' checked' : '' ) . '> ' . esc_html( $label ) . '</label><br>';
            }
            echo '</fieldset>';
            break;

        case 'select':
            echo '<select' . $attr . '>';
            foreach ( cm_admin_field_options( $f ) as $opt => $label ) {
                echo '<option value="' . esc_attr( $opt ) . '"' . ( (string) $value === (string) $opt ? ' selected' : '' ) . '>' . esc_html( $label ) . '</option>';
            }
            echo '</select>';
            break;

        case 'multiselect':
            $list = cm_csv_list( $value );
            echo '<input type="hidden" name="' . esc_attr( $name ) . '[]" value="">';
            echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '[]" data-cm-key="' . $key . '" multiple size="8">';
            foreach ( cm_admin_field_options( $f ) as $opt => $label ) {
                echo '<option value="' . esc_attr( $opt ) . '"' . ( in_array( (string) $opt, $list, true ) ? ' selected' : '' ) . '>' . esc_html( $label ) . '</option>';
            }
            echo '</select>';
            break;

        case 'checkboxes':
            $list = cm_csv_list( $value );
            $all  = ! empty( $f['all_when_empty'] ) && (string) $value === '';
            echo '<fieldset><legend class="screen-reader-text"><span>' . esc_html( $f['label'] ) . '</span></legend>';
            echo '<input type="hidden" name="' . esc_attr( $name ) . '[]" value="">';
            $i = 0;
            foreach ( cm_admin_field_options( $f ) as $opt => $label ) {
                $oid = $id . '-' . $i++;
                echo '<label for="' . esc_attr( $oid ) . '"><input type="checkbox" id="' . esc_attr( $oid ) . '" name="' . esc_attr( $name ) . '[]" data-cm-key="' . $key . '" value="' . esc_attr( $opt ) . '"'
                    . ( $all || in_array( (string) $opt, $list, true ) ? ' checked' : '' ) . '> ' . esc_html( $label ) . '</label><br>';
            }
            echo '</fieldset>';
            break;

        case 'color':
        case 'color_optional':
            $v     = (string) $value;
            $valid = preg_match( '/^#[0-9a-fA-F]{6}$/', $v ) === 1;
            echo '<span class="cm-color' . ( $valid ? '' : ' cm-color-empty' ) . '">';
            echo '<input type="color"' . ( $valid ? ' value="' . esc_attr( strtolower( $v ) ) . '"' : '' ) . ' aria-label="' . esc_attr( 'Kleurkiezer: ' . $f['label'] ) . '">';
            echo '<input type="text"' . $attr . ' class="code cm-hex" value="' . esc_attr( $v ) . '" maxlength="7" size="8" spellcheck="false" autocomplete="off"' . $ph . '>';
            if ( $f['type'] === 'color_optional' ) {
                echo ' <button type="button" class="button-link cm-color-clear"' . ( $v === '' ? ' hidden' : '' ) . '>Wissen</button>';
            }
            echo '</span>';
            break;

        case 'media':
            $v = (string) $value;
            echo '<span class="cm-media"><input type="hidden"' . $attr . ' value="' . esc_attr( $v ) . '">';
            echo '<img class="cm-media-img" alt=""' . ( $v !== '' ? ' src="' . esc_url( $v ) . '"' : ' hidden' ) . '> ';
            echo '<button type="button" class="button cm-media-pick">Afbeelding kiezen</button> ';
            echo '<button type="button" class="button-link cm-media-remove"' . ( $v === '' ? ' hidden' : '' ) . '>Verwijderen</button></span>';
            break;

        case 'custom':
            call_user_func( $f['render'], $f, $id, $name, $value );
            break;

        default: // text
            echo '<input type="text"' . $attr . ' class="' . esc_attr( isset( $f['class'] ) ? $f['class'] : 'regular-text' ) . '" value="' . esc_attr( (string) $value ) . '"' . $ph . '>';
    }
}
```

- [ ] **Step 4: Schrijf `assets/js/admin-common.js`**

```js
/* Cookiebaas 3 — gedeelde admin-JS (alle nieuwe pagina's). Vanilla JS. */
(function () {
  'use strict';

  var HEX = /^#[0-9a-fA-F]{6}$/;

  /** Normaliseer wat klanten plakken: 'FFF', ' #abcdef ' → '#ffffff', '#abcdef'. */
  function normalizeHex(v) {
    v = String(v || '').trim();
    if (/^[0-9a-fA-F]{3}$|^[0-9a-fA-F]{6}$/.test(v)) v = '#' + v;
    if (/^#[0-9a-fA-F]{3}$/.test(v)) v = '#' + v[1] + v[1] + v[2] + v[2] + v[3] + v[3];
    return HEX.test(v) ? v.toLowerCase() : null;
  }

  /* ---- Kleurvelden: kleurvlak <-> hex-veld, en "Wissen" ---- */
  document.querySelectorAll('.cm-color').forEach(function (row) {
    var swatch = row.querySelector('input[type=color]');
    var hex = row.querySelector('input.cm-hex');
    var clear = row.querySelector('.cm-color-clear');
    function sync() {
      var ok = normalizeHex(hex.value);
      row.classList.toggle('cm-color-empty', !ok);
      if (ok) swatch.value = ok;
      if (clear) clear.hidden = hex.value.trim() === '';
    }
    swatch.addEventListener('input', function () {
      hex.value = swatch.value;
      sync();
      hex.dispatchEvent(new Event('input', { bubbles: true }));
    });
    hex.addEventListener('input', sync);
    hex.addEventListener('blur', function () {
      var ok = normalizeHex(hex.value);
      if (ok && ok !== hex.value) { hex.value = ok; hex.dispatchEvent(new Event('input', { bubbles: true })); }
    });
    if (clear) clear.addEventListener('click', function () {
      hex.value = '';
      sync();
      hex.dispatchEvent(new Event('input', { bubbles: true }));
      hex.focus();
    });
    sync();
  });

  /* ---- Voorwaardelijke rijen (show_if) ---- */
  function fieldValue(key) {
    var els = document.querySelectorAll('[data-cm-key="' + key + '"]');
    for (var i = 0; i < els.length; i++) {
      var el = els[i];
      if (el.type === 'radio') { if (el.checked) return el.value; continue; }
      if (el.type === 'checkbox') return el.checked ? '1' : '0';
      return el.value;
    }
    return '';
  }
  function applyShowIf() {
    document.querySelectorAll('[data-cm-show-if]').forEach(function (row) {
      var cond = JSON.parse(row.getAttribute('data-cm-show-if'));
      row.hidden = !Object.keys(cond).every(function (k) {
        var want = cond[k], have = fieldValue(k);
        return Array.isArray(want) ? want.indexOf(have) !== -1 : String(want) === have;
      });
    });
  }
  document.addEventListener('change', applyShowIf);
  applyShowIf();

  /* ---- Schakelaars (Licht/Donker, Nederlands/English) ---- */
  document.querySelectorAll('[data-cm-switch]').forEach(function (sw) {
    var name = sw.getAttribute('data-cm-switch');
    function show(val) {
      document.querySelectorAll('[data-cm-pane^="' + name + ':"]').forEach(function (p) {
        p.hidden = p.getAttribute('data-cm-pane') !== name + ':' + val;
      });
      sw.querySelectorAll('a[data-cm-switch-to]').forEach(function (a) {
        var on = a.getAttribute('data-cm-switch-to') === val;
        a.classList.toggle('current', on);
        if (on) a.setAttribute('aria-current', 'true'); else a.removeAttribute('aria-current');
      });
      document.dispatchEvent(new CustomEvent('cm-switch', { detail: { name: name, value: val } }));
    }
    sw.addEventListener('click', function (e) {
      var a = e.target.closest('a[data-cm-switch-to]');
      if (!a) return;
      e.preventDefault();
      show(a.getAttribute('data-cm-switch-to'));
    });
    var first = sw.querySelector('a.current') || sw.querySelector('a[data-cm-switch-to]');
    if (first) show(first.getAttribute('data-cm-switch-to'));
  });

  /* ---- Media-kiezer (zweefknop-afbeelding) ---- */
  document.querySelectorAll('.cm-media').forEach(function (box) {
    var input = box.querySelector('input[type=hidden]');
    var img = box.querySelector('.cm-media-img');
    var remove = box.querySelector('.cm-media-remove');
    function sync() {
      img.hidden = !input.value;
      if (input.value) img.src = input.value; else img.removeAttribute('src');
      remove.hidden = !input.value;
    }
    box.querySelector('.cm-media-pick').addEventListener('click', function () {
      if (!window.wp || !window.wp.media) return;
      var frame = window.wp.media({ title: 'Afbeelding kiezen', multiple: false, library: { type: 'image' } });
      frame.on('select', function () {
        input.value = frame.state().get('selection').first().toJSON().url;
        sync();
        input.dispatchEvent(new Event('change', { bubbles: true }));
      });
      frame.open();
    });
    remove.addEventListener('click', function () {
      input.value = '';
      sync();
      input.dispatchEvent(new Event('change', { bubbles: true }));
    });
    sync();
  });

  /* ---- Bevestigen bij destructieve acties ---- */
  document.addEventListener('submit', function (e) {
    var msg = e.target.getAttribute('data-cm-confirm');
    if (msg && !window.confirm(msg)) e.preventDefault();
  }, true);

  /* ---- Waarschuwing bij niet-opgeslagen wijzigingen ---- */
  var dirty = false;
  document.querySelectorAll('form.cm-form').forEach(function (form) {
    form.addEventListener('input', function () { dirty = true; });
    form.addEventListener('change', function () { dirty = true; });
    form.addEventListener('submit', function () { dirty = false; });
  });
  window.addEventListener('beforeunload', function (e) {
    if (!dirty) return;
    e.preventDefault();
    e.returnValue = '';
  });
})();
```

- [ ] **Step 5: Schrijf `assets/css/admin-layout.css`**

```css
/* Cookiebaas 3 — alleen layout. Kleuren en typografie komen van WordPress core. */
.cm-admin .form-table tr[hidden] { display: none; }
.cm-cols { display: grid; grid-template-columns: minmax(0, 1fr) 420px; gap: 24px; align-items: start; }
@media (max-width: 1280px) { .cm-cols { grid-template-columns: 1fr; } }
.cm-color { display: inline-flex; gap: 8px; align-items: center; }
.cm-color input[type=color] { width: 40px; height: 30px; padding: 2px; cursor: pointer; }
.cm-color.cm-color-empty input[type=color] { opacity: .35; }
.cm-details { margin: 0 0 10px; }
.cm-details > summary { padding: 12px; font-weight: 600; font-size: 14px; cursor: pointer; }
.cm-details-body { padding: 0 12px 8px; }
.cm-media-img { max-width: 64px; max-height: 64px; vertical-align: middle; }
.cm-switch { float: none; margin: 0 0 12px; }
```

Kaders en achtergronden komen van de core-klasse `postbox` (Taak 7 geeft de kleurgroepen `'attrs' => array( 'class' => 'postbox' )`), niet van eigen kleuren. Zo blijft de broncheck groen.

- [ ] **Step 6: Laad `fields.php`**

In `cookiemelding.php`, na de regel voor `includes/admin/menu.php`:

```php
require_once CM_PLUGIN_DIR . 'includes/admin/fields.php';
```

- [ ] **Step 7: Draai de tests**

Run: `php tests/test-admin3-fields.php && php tests/run.php`
Expected: alles groen, inclusief de broncheck uit Taak 1 (die scant nu ook `admin-common.js` en `admin-layout.css`).

- [ ] **Step 8: Commit**

```bash
git add includes/admin/fields.php assets/js/admin-common.js assets/css/admin-layout.css cookiemelding.php tests/test-admin3-fields.php
git commit -m "feat(admin3): veldregister, renderer en gedeelde admin-JS

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3: Settings API, type-bewuste sanitizing en cache-purge op één plek

**Files:**
- Create: `includes/admin/settings.php`
- Modify: `includes/admin.php`:
  - verplaats `cm_sanitize_settings()` (nu ongeveer regel 166–213) en `cm_sanitize_cookie_list()` (ongeveer regel 1414–1439) naar `settings.php`;
  - verwijder de losse `cm_purge_page_caches()`-aanroepen in `cm_ajax_reset_settings`, `cm_ajax_reset_cookielist`, `cm_ajax_reset_privacy`, `cm_ajax_bump_consent_version`, `cm_ajax_save_settings`, `cm_ajax_import_settings` en `cm_ajax_save_cookie_list`.
- Modify: `includes/privacy.php`: verwijder de `cm_purge_page_caches()` in `cm_ajax_save_privacy`.
- Modify: `cookiemelding.php`:
  - verwijder de `cm_purge_page_caches()` in `cm_run_auto_scan` (tak `auto`);
  - behoud de aanroep na de versie-upgrade;
  - voeg de require toe.
- Modify: `tests/test-admin-fixes.php`: require `settings.php` en versoepel de purge-tellingen.
- Test: `tests/test-admin3-settings.php`

**Interfaces:**
- Consumes: `cm_admin_field_index()`, `cm_admin_field_options()`, `cm_csv_list()`, `cm_html_allowed()` (Taak 2).
- Produces:
  - `cm_sanitize_settings( array $input, array $existing, ?array $index = null ): array`. De derde parameter is nieuw en maakt injectie in tests mogelijk. Zonder die parameter geldt `cm_admin_field_index('cm_settings')`.
  - `cm_sanitize_field_value( array $f, $raw, $current )`: altijd een string.
  - `cm_normalize_hex( $v ): ?string`
  - `cm_settings_sanitize_callback( $input ): array`
  - `cm_sanitize_cookie_list( array $raw ): array` (ongewijzigd, alleen verplaatst)
  - Settings-groep `cookiebaas_settings` voor option `cm_settings`.

- [ ] **Step 1: Schrijf de falende test**

`tests/test-admin3-settings.php`:

```php
<?php
/**
 * Nieuwe admin (3.0) — type-bewuste sanitizing en Settings API-callback.
 *
 * Borgt (spec §5.3, Review Focus 1, 3, 4):
 *   - een tab post alleen zijn eigen velden; de rest blijft staan
 *   - kleuren: klanten plakken hex in allerlei vormen → genormaliseerd;
 *     echt ongeldig → vorige waarde + foutmelding met de veldnaam
 *   - getallen geclampt, radio/select tegen de opties, checkbox 0/1
 *   - de oude admin (stringformaten, cm_icon_type) blijft correct opslaan
 *   - idempotent: een geldige array komt ongewijzigd door de callback
 *   - options.php stuurt null voor een ontbrekende option → niets wissen
 *   - elke wijziging van cm_settings leegt de paginacache (option-hook)
 */

function sanitize_textarea_field( $s ) { return is_scalar( $s ) ? trim( strip_tags( (string) $s ) ) : ''; }
function sanitize_text_field( $s )     { return is_scalar( $s ) ? trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) ) : ''; }
function wp_kses( $s, $allowed = array() ) { return strip_tags( (string) $s, '<' . implode( '><', array_keys( $allowed ) ) . '>' ); }
$GLOBALS['cm_test_errors'] = array();
function add_settings_error( $setting, $code, $message ) { $GLOBALS['cm_test_errors'][] = $message; }
$GLOBALS['cm_test_purges'] = 0;
function cm_purge_page_caches() { $GLOBALS['cm_test_purges']++; }
function absint( $v ) { return abs( (int) $v ); }

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
require CM_PLUGIN_ROOT . '/includes/admin/fields.php';
require CM_PLUGIN_ROOT . '/includes/admin/settings.php';

$index = array(
    'color_title'         => cm_field( 'color_title', 'color', 'Titels' ),
    'color_accept_border' => cm_field( 'color_accept_border', 'color_optional', 'Akkoord — rand' ),
    'radius_popup'        => cm_field( 'radius_popup', 'number', 'Hoekafronding', array( 'min' => 0, 'max' => 60 ) ),
    'banner_position'     => cm_field( 'banner_position', 'radio', 'Positie', array( 'options' => array( 'bottom-center' => 'a', 'center' => 'b' ) ) ),
    'respect_dnt'         => cm_field( 'respect_dnt', 'checkbox', 'DNT' ),
    'txt_cat1_long'       => cm_field( 'txt_cat1_long', 'textarea', 'Uitgebreid' ),
    'txt_banner_body'     => cm_field( 'txt_banner_body', 'html', 'Tekst' ),
    'exclude_page_ids'    => cm_field( 'exclude_page_ids', 'multiselect', 'Pagina\'s', array( 'sanitize' => function ( $raw ) {
        return is_array( $raw ) ? implode( ',', array_filter( array_map( 'absint', $raw ) ) ) : sanitize_text_field( $raw );
    } ) ),
);

cm_test_group( 'Kleuren' );
cm_assert( '"FFFFFF" wordt #ffffff', cm_sanitize_field_value( $index['color_title'], 'FFFFFF', '#111111' ) === '#ffffff' );
cm_assert( '" #FFF " wordt #ffffff', cm_sanitize_field_value( $index['color_title'], ' #FFF ', '#111111' ) === '#ffffff' );
cm_assert( '#ABCDEF wordt #abcdef', cm_sanitize_field_value( $index['color_title'], '#ABCDEF', '#111111' ) === '#abcdef' );
$GLOBALS['cm_test_errors'] = array();
cm_assert( 'ongeldig houdt de vorige kleur', cm_sanitize_field_value( $index['color_title'], 'rood', '#111111' ) === '#111111' );
cm_assert( 'ongeldig geeft een foutmelding met de veldnaam', count( $GLOBALS['cm_test_errors'] ) === 1 && strpos( $GLOBALS['cm_test_errors'][0], 'Titels' ) !== false );
cm_assert( 'optioneel mag leeg', cm_sanitize_field_value( $index['color_accept_border'], '', '#444444' ) === '' );
cm_assert( 'verplicht mag niet leeg', cm_sanitize_field_value( $index['color_title'], '', '#111111' ) === '#111111' );

cm_test_group( 'Getal, keuze, checkbox, tekst' );
cm_assert( 'getal boven max wordt max', cm_sanitize_field_value( $index['radius_popup'], '99', '18' ) === '60' );
cm_assert( 'getal onder min wordt min', cm_sanitize_field_value( $index['radius_popup'], '-4', '18' ) === '0' );
cm_assert( 'geen getal houdt de vorige waarde', cm_sanitize_field_value( $index['radius_popup'], 'abc', '18' ) === '18' );
cm_assert( 'onbekende radio-optie houdt de vorige waarde', cm_sanitize_field_value( $index['banner_position'], 'links', 'center' ) === 'center' );
cm_assert( 'checkbox 1 → "1", al het andere → "0"', cm_sanitize_field_value( $index['respect_dnt'], '1', '0' ) === '1' && cm_sanitize_field_value( $index['respect_dnt'], 'ja', '1' ) === '0' );
cm_assert( 'textarea houdt regeleinden', cm_sanitize_field_value( $index['txt_cat1_long'], "a\nb", '' ) === "a\nb" );
cm_assert( 'html laat link staan, script niet', cm_sanitize_field_value( $index['txt_banner_body'], '<a href="/x">x</a><script>y</script>', '' ) === '<a href="/x">x</a>y' );
cm_assert( 'eigen sanitize: array van id\'s wordt komma-string', cm_sanitize_field_value( $index['exclude_page_ids'], array( '', '12', '7' ), '' ) === '12,7' );
cm_assert( 'eigen sanitize: niets gekozen wordt leeg', cm_sanitize_field_value( $index['exclude_page_ids'], array( '' ), '5' ) === '' );
cm_assert( 'oude admin: string blijft werken', cm_sanitize_field_value( $index['exclude_page_ids'], '3,4', '' ) === '3,4' );

cm_test_group( 'Gedeeltelijke invoer (één tab)' );
$existing = array_merge( cm_default_settings(), array( 'gtm_container_id' => 'GTM-BLIJFT', 'color_title' => '#222222' ) );
$out = cm_sanitize_settings( array( 'color_title' => '#333333' ), $existing, $index );
cm_assert( 'het geposte veld verandert', $out['color_title'] === '#333333' );
cm_assert( 'een veld van een andere tab blijft staan', $out['gtm_container_id'] === 'GTM-BLIJFT' );

cm_test_group( 'Icoontype (alleen UI) wist pas bij opslaan' );
$base = array_merge( cm_default_settings(), array( 'float_icon_custom_svg' => '<svg></svg>', 'float_icon_image_url' => 'https://x.test/a.png' ) );
$o = cm_sanitize_settings( array( 'cm_icon_type' => 'custom' ), $base, $index );
cm_assert( 'custom: SVG blijft, afbeelding weg', $o['float_icon_custom_svg'] === '<svg></svg>' && $o['float_icon_image_url'] === '' );
$o = cm_sanitize_settings( array( 'cm_icon_type' => 'default' ), $base, $index );
cm_assert( 'standaard: beide weg', $o['float_icon_custom_svg'] === '' && $o['float_icon_image_url'] === '' );
cm_assert( 'cm_icon_type wordt zelf niet opgeslagen', ! array_key_exists( 'cm_icon_type', $o ) );

cm_test_group( 'Idempotent' );
$valid = array_merge( cm_default_settings(), array( 'exclude_page_ids' => '3,4', 'respect_dnt' => '1', 'color_title' => '#123456' ) );
$once  = cm_sanitize_settings( $valid, $valid, $index );
$twice = cm_sanitize_settings( $once, $once, $index );
$diff  = array();
foreach ( $index as $k => $f ) if ( (string) $once[ $k ] !== (string) $valid[ $k ] ) $diff[] = $k;
cm_assert( 'geldige waarden komen ongewijzigd door (per veld als string)' . ( $diff ? ' — veranderd: ' . implode( ', ', $diff ) : '' ), ! $diff );
cm_assert( 'twee keer sanitizen = één keer', $once === $twice );

cm_test_group( 'Settings API-callback' );
update_option( 'cm_settings', array_merge( cm_default_settings(), array( 'gtm_container_id' => 'GTM-OK' ) ) );
cm_assert( 'null (option ontbreekt in POST) wist niets', cm_settings_sanitize_callback( null )['gtm_container_id'] === 'GTM-OK' );

cm_test_group( 'Cache-purge via option-hook' );
$p = $GLOBALS['cm_test_purges'];
update_option( 'cm_settings', array( 'x' => 1 ) );
update_option( 'cm_cookie_list', array() );
update_option( 'cm_privacy', array() );
update_option( 'cm_consent_version', 2 );
cm_assert( 'elke inhoudswijziging leegt de cache', $GLOBALS['cm_test_purges'] === $p + 4 );

exit( cm_test_summary() );
```

- [ ] **Step 2: Draai de test en zie hem falen**

Run: `php tests/test-admin3-settings.php`
Expected: fatal "Failed opening required …/includes/admin/settings.php".

- [ ] **Step 3: Schrijf `includes/admin/settings.php`**

Dit bestand krijgt de nieuwe `cm_sanitize_settings()` en een letterlijke kopie van `cm_sanitize_cookie_list()`. De oude branches voor sleutels zonder veld-definitie blijven bestaan, want de oude admin post nog alle velden.

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   OPSLAAN — Settings API + type-bewuste sanitizing + cache-purge.
   Gedeeld door de nieuwe admin (options.php) en de oude admin (AJAX),
   tot plan 3 de oude admin verwijdert.
================================================================ */

add_action( 'admin_init', 'cm_admin_register_settings' );
function cm_admin_register_settings() {
    register_setting( 'cookiebaas_settings', 'cm_settings', array(
        'type'              => 'array',
        'sanitize_callback' => 'cm_settings_sanitize_callback',
        'show_in_rest'      => false,
    ) );
}

/**
 * Sanitize-callback van register_setting. WordPress roept die bij ELKE
 * update_option('cm_settings') aan (ook migraties, resets, en twee keer bij
 * de eerste opslag), dus: idempotent, en null (option ontbrak in de POST
 * van options.php) laat alles staan.
 */
function cm_settings_sanitize_callback( $input ) {
    $existing = get_option( 'cm_settings', array() );
    $existing = is_array( $existing ) ? $existing : array();
    if ( ! is_array( $input ) ) return $existing;
    return cm_sanitize_settings( $input, $existing );
}

/** Hex zoals klanten het plakken → '#rrggbb' (lowercase), of null als het geen kleur is. */
function cm_normalize_hex( $v ) {
    $v = trim( (string) $v );
    if ( preg_match( '/^[0-9a-fA-F]{3}$|^[0-9a-fA-F]{6}$/', $v ) ) $v = '#' . $v;
    if ( preg_match( '/^#([0-9a-fA-F])([0-9a-fA-F])([0-9a-fA-F])$/', $v, $m ) ) {
        $v = '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
    }
    return preg_match( '/^#[0-9a-fA-F]{6}$/', $v ) ? strtolower( $v ) : null;
}

/** Meld een ongeldige waarde; de vorige waarde blijft staan. */
function cm_sanitize_reject( array $f, $current ) {
    if ( function_exists( 'add_settings_error' ) ) {
        add_settings_error( 'cm_settings', 'cm-invalid-' . $f['key'], sprintf( 'Ongeldige waarde bij "%s". De vorige waarde is behouden.', $f['label'] ) );
    }
    return (string) $current;
}

/** Sanitize één waarde volgens zijn veld-definitie. Geeft altijd een string terug. */
function cm_sanitize_field_value( array $f, $raw, $current ) {
    if ( isset( $f['sanitize'] ) && is_callable( $f['sanitize'] ) ) {
        return (string) call_user_func( $f['sanitize'], $raw, $current, $f );
    }
    switch ( $f['type'] ) {
        case 'checkbox':
            return (string) $raw === '1' ? '1' : '0';
        case 'number':
            if ( ! is_numeric( $raw ) ) return cm_sanitize_reject( $f, $current );
            $n = (int) round( (float) $raw );
            if ( isset( $f['min'] ) ) $n = max( (int) $f['min'], $n );
            if ( isset( $f['max'] ) ) $n = min( (int) $f['max'], $n );
            return (string) $n;
        case 'radio':
        case 'select':
            return array_key_exists( (string) $raw, cm_admin_field_options( $f ) ) ? (string) $raw : cm_sanitize_reject( $f, $current );
        case 'color':
        case 'color_optional':
            if ( $f['type'] === 'color_optional' && trim( (string) $raw ) === '' ) return '';
            $hex = cm_normalize_hex( $raw );
            return $hex !== null ? $hex : cm_sanitize_reject( $f, $current );
        case 'textarea':
            return sanitize_textarea_field( is_scalar( $raw ) ? (string) $raw : '' );
        case 'html':
            return wp_kses( is_scalar( $raw ) ? (string) $raw : '', isset( $f['allowed'] ) ? $f['allowed'] : cm_html_allowed() );
        case 'code':
            return is_string( $raw ) ? $raw : ''; // frontend sanitized bij het renderen (SVG-whitelist)
        case 'media':
            return esc_url_raw( is_scalar( $raw ) ? (string) $raw : '' );
        case 'multiselect':
        case 'checkboxes':
            return is_array( $raw ) ? implode( ',', array_map( 'sanitize_text_field', cm_csv_list( $raw ) ) ) : sanitize_text_field( $raw );
        default:
            return sanitize_text_field( $raw );
    }
}

/**
 * Sanitize instellingen tegen de defaults (die dienen als whitelist).
 * Gedeeld door opslaan (nieuw en oud) en import.
 *
 * @param array      $input    Ongeslashte invoer (POST of geïmporteerde JSON).
 * @param array      $existing Basis: velden die niet in $input zitten blijven hieruit staan.
 * @param array|null $index    Veld-definities (sleutel → veld); null = het register.
 */
function cm_sanitize_settings( array $input, array $existing, $index = null ) {
    $defaults = cm_default_settings();
    if ( $index === null ) $index = function_exists( 'cm_admin_field_index' ) ? cm_admin_field_index( 'cm_settings' ) : array();

    // Zorg dat alle defaultkeys als fallback aanwezig zijn
    $settings = $existing;
    foreach ( $defaults as $key => $default ) {
        if ( ! array_key_exists( $key, $settings ) ) $settings[ $key ] = $default;
    }

    // Verwerk alleen de velden die in de invoer zitten; de rest blijft intact
    $html_fields = array( 'txt_banner_body', 'txt_prefs_body', 'txt_banner_body_en', 'txt_prefs_body_en' );
    foreach ( $defaults as $key => $default ) {
        if ( ! isset( $input[ $key ] ) ) continue;
        if ( isset( $index[ $key ] ) ) {
            $settings[ $key ] = cm_sanitize_field_value( $index[ $key ], $input[ $key ], $settings[ $key ] );
        } elseif ( in_array( $key, $html_fields, true ) ) {
            $settings[ $key ] = wp_kses( (string) $input[ $key ], cm_html_allowed() );
        } elseif ( $key === 'float_icon_custom_svg' ) {
            // Ongefilterd bewaren — frontend.php sanitized met een strikte tag/attribuut-whitelist bij het renderen
            $settings[ $key ] = is_string( $input[ $key ] ) ? $input[ $key ] : '';
        } elseif ( $key === 'float_icon_image_url' ) {
            $settings[ $key ] = esc_url_raw( (string) $input[ $key ] );
        } else {
            $settings[ $key ] = sanitize_text_field( $input[ $key ] );
        }
    }

    // Icoontype is alleen UI: wis pas bij opslaan wat niet gekozen is
    if ( isset( $input['cm_icon_type'] ) ) {
        $type = (string) $input['cm_icon_type'];
        if ( $type !== 'custom' ) $settings['float_icon_custom_svg'] = '';
        if ( $type !== 'image' )  $settings['float_icon_image_url']  = '';
    }

    // Als google_load_default aanstaat, moet analytics_default ook aanstaan
    // (als string, zoals alle gesanitizede waarden — anders is de sanitizer niet idempotent)
    if ( ! empty( $settings['google_load_default'] ) ) {
        $settings['analytics_default'] = '1';
    }

    return $settings;
}

/**
 * Sanitize een cookielijst (opslaan, import en automatische scan).
 * Verplaatst uit includes/admin.php, ongewijzigd.
 */
function cm_sanitize_cookie_list( array $raw ) {
    $clean = array();
    foreach ( $raw as $ck ) {
        if ( ! is_array( $ck ) ) continue;
        $name = sanitize_text_field( isset($ck['name']) ? $ck['name'] : '' );
        if ( ! $name ) continue;
        $cat = sanitize_text_field( isset($ck['category']) ? $ck['category'] : 'functional' );
        if ( ! in_array($cat, array('functional','analytics','marketing')) ) $cat = 'functional';
        // Normaliseer provider via centrale service-mapping
        $raw_provider = sanitize_text_field( isset($ck['provider']) ? $ck['provider'] : '' );
        $svc = cm_service_for_cookie( $name );
        $provider = $svc ? $svc['service'] : $raw_provider;
        $clean[] = array(
            'name'     => $name,
            'provider' => $provider,
            'purpose'  => sanitize_text_field( isset($ck['purpose'])   ? $ck['purpose']   : '' ),
            'duration' => sanitize_text_field( isset($ck['duration'])  ? $ck['duration']  : 'Sessie' ),
            'category' => $cat,
            'builtin'  => false,
        );
    }
    return $clean;
}

/* ---- Paginacache legen op één plek: elke inhoudswijziging, uit elke bron ---- */
function cm_purge_page_caches_on_change() {
    if ( function_exists( 'cm_purge_page_caches' ) ) cm_purge_page_caches();
}
foreach ( array( 'cm_settings', 'cm_cookie_list', 'cm_privacy', 'cm_consent_version' ) as $cm_purge_option ) {
    add_action( 'add_option_' . $cm_purge_option,    'cm_purge_page_caches_on_change' );
    add_action( 'update_option_' . $cm_purge_option, 'cm_purge_page_caches_on_change' );
}
unset( $cm_purge_option );
```

Kanttekening bij de idempotentie-test: de defaults bevatten ints (bijvoorbeeld `'show_float_btn' => 1`), en de sanitizer geeft strings terug. Daarom vergelijkt de test per veld als string. Dat klopt met hoe de oude admin al opsloeg (via `sanitize_text_field`).

- [ ] **Step 4: Pas `includes/admin.php`, `includes/privacy.php` en `cookiemelding.php` aan**

1. Verwijder uit `includes/admin.php` de complete functies `cm_sanitize_settings` en `cm_sanitize_cookie_list`, inclusief hun docblocks. Ze staan nu in `settings.php`.
2. Verwijder in `includes/admin.php` elke regel `cm_purge_page_caches();` en `if ( function_exists('cm_purge_page_caches') ) cm_purge_page_caches();` in de handlers:
   - `cm_ajax_reset_settings`, `cm_ajax_reset_cookielist`, `cm_ajax_reset_privacy`;
   - `cm_ajax_bump_consent_version`, samen met het commentaar erboven;
   - `cm_ajax_save_settings`, `cm_ajax_save_cookie_list`, samen met het commentaar erboven;
   - `cm_ajax_import_settings`: de regel `if ( $imported ) cm_purge_page_caches();`.
   Controleer: `grep -n "cm_purge_page_caches" includes/admin.php` geeft niets meer.
3. Verwijder in `includes/privacy.php` in `cm_ajax_save_privacy` de regels `// De verklaring en cookietabel staan in gecachte pagina's` en `cm_purge_page_caches();`.
4. Verwijder in `cookiemelding.php` in `cm_run_auto_scan` de regel `cm_purge_page_caches();` onder `update_option( 'cm_auto_scan_last_added', … );`. **Laat** de aanroep na de versie-upgrade (in de `plugins_loaded`-closure) staan.
5. Voeg in `cookiemelding.php` na de regel voor `includes/admin/fields.php` toe:

```php
require_once CM_PLUGIN_DIR . 'includes/admin/settings.php';
```

- [ ] **Step 5: Pas `tests/test-admin-fixes.php` aan**

Voeg na `require CM_PLUGIN_ROOT . '/includes/admin.php';` toe:

```php
require CM_PLUGIN_ROOT . '/includes/admin/fields.php';
require CM_PLUGIN_ROOT . '/includes/admin/settings.php';
```

Vervang de assertie `'paginacache geleegd na import', $GLOBALS['cm_test_purges'] === $purges + 1` door:

```php
cm_assert( 'paginacache geleegd na import', $GLOBALS['cm_test_purges'] > $purges );
```

De asserties voor privacy en cookielijst (`=== $purges + 1`) blijven kloppen: één `update_option` levert via de hook één purge op. In de bootstrap vuurt `update_option` `update_option_{naam}` alleen als de option al bestond, anders `add_option_{naam}`. Beide hooks zijn geregistreerd.

- [ ] **Step 6: Draai de tests**

Run: `php tests/test-admin3-settings.php && php tests/run.php`
Expected: alles groen. Faalt `test-admin-fixes.php` op "wp_ajax_cm_reset_settings is geregistreerd"? Dan heb je bij het verplaatsen per ongeluk de `add_action` weggehaald: die hoort in `admin.php` te blijven.

- [ ] **Step 7: Commit**

```bash
git add includes/admin/settings.php includes/admin.php includes/privacy.php cookiemelding.php tests/test-admin3-settings.php tests/test-admin-fixes.php
git commit -m "feat(admin3): Settings API, type-bewuste sanitizing en cache-purge via option-hooks

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 4: Acties via admin-post.php en meldingen

**Files:**
- Create: `includes/admin/actions.php`
- Modify: `cookiemelding.php` (require)
- Test: `tests/test-admin3-actions.php`

**Interfaces:**
- Produces:
  - `cm_admin_register_action( string $action, callable $callback ): void`. De callback geeft een meldingscode (string) terug, of streamt een download en doet zelf `exit`.
  - `cm_admin_action_form( string $action, string $label, array $args = array(), string $confirm = '', string $class = 'button' ): string`
  - `cm_admin_redirect_url( string|false $referer, string $notice ): string`
  - `cm_admin_notice_messages(): array` (code → `array( type, tekst )`)
  - `cm_admin_notice_html( string $code ): string`
  - `cm_admin_render_notices(): void`
- **Nonce per actie:** `cm_<actie>`. Hook: `admin_post_cm_<actie>`.

- [ ] **Step 1: Schrijf de falende test**

`tests/test-admin3-actions.php`:

```php
<?php
/**
 * Nieuwe admin (3.0) — acties via admin-post.php.
 *
 * Borgt: een actieformulier post naar admin-post.php met eigen action en
 * nonce, vraagt om bevestiging als dat gevraagd is, en na afloop landt de
 * gebruiker op dezelfde pagina met precies één melding.
 */

function wp_nonce_field( $action, $name = '_wpnonce', $referer = true, $echo = true ) { return '<input type="hidden" name="' . $name . '" value="nonce-' . $action . '">'; }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function remove_query_arg( $keys, $url ) {
    $p = parse_url( $url ); parse_str( isset( $p['query'] ) ? $p['query'] : '', $q );
    foreach ( (array) $keys as $k ) unset( $q[ $k ] );
    return $p['scheme'] . '://' . $p['host'] . $p['path'] . ( $q ? '?' . http_build_query( $q ) : '' );
}
function add_query_arg( $key, $value, $url ) {
    return $url . ( strpos( $url, '?' ) === false ? '?' : '&' ) . rawurlencode( $key ) . '=' . rawurlencode( $value );
}

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/admin/actions.php';

cm_test_group( 'Actieformulier' );
$f = cm_admin_action_form( 'reset_theme', 'Herstellen', array( 'theme' => 'dark' ), 'Zeker weten?' );
cm_assert( 'post naar admin-post.php', strpos( $f, 'action="https://example.test/wp-admin/admin-post.php"' ) !== false );
cm_assert( 'eigen action en nonce', strpos( $f, 'name="action" value="cm_reset_theme"' ) !== false && strpos( $f, 'nonce-cm_reset_theme' ) !== false );
cm_assert( 'extra argumenten als hidden velden', strpos( $f, 'name="theme" value="dark"' ) !== false );
cm_assert( 'bevestiging op het formulier', strpos( $f, 'data-cm-confirm="Zeker weten?"' ) !== false );

cm_test_group( 'Terug naar de pagina met één melding' );
$ref = 'https://example.test/wp-admin/admin.php?page=cookiebaas-banner&tab=vormgeving&cm_notice=oud&settings-updated=true';
$to  = cm_admin_redirect_url( $ref, 'theme-reset-dark' );
cm_assert( 'pagina en tab blijven', strpos( $to, 'page=cookiebaas-banner' ) !== false && strpos( $to, 'tab=vormgeving' ) !== false );
cm_assert( 'oude melding en settings-updated weg, nieuwe erbij', substr_count( $to, 'cm_notice=' ) === 1 && strpos( $to, 'cm_notice=theme-reset-dark' ) !== false && strpos( $to, 'settings-updated' ) === false );
cm_assert( 'zonder referer naar het overzicht', strpos( cm_admin_redirect_url( false, '' ), 'page=cookiebaas' ) !== false );

cm_test_group( 'Meldingen' );
cm_assert( 'bekende code → succesmelding', strpos( cm_admin_notice_html( 'theme-reset-light' ), 'notice notice-success is-dismissible' ) !== false );
cm_assert( 'onbekende code → niets', cm_admin_notice_html( 'bestaat-niet' ) === '' );

exit( cm_test_summary() );
```

- [ ] **Step 2: Draai de test en zie hem falen**

Run: `php tests/test-admin3-actions.php`
Expected: fatal "Failed opening required …/includes/admin/actions.php".

- [ ] **Step 3: Schrijf `includes/admin/actions.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   ACTIES — resets, downloads en andere eenmalige handelingen gaan via
   admin-post.php (eigen nonce per actie), daarna terug naar de pagina
   met een melding. Geen AJAX, geen alert().
================================================================ */

/** Meldingscode → [type, tekst]. Latere taken en plannen voegen codes toe. */
function cm_admin_notice_messages() {
    return array(
        'theme-reset-light' => array( 'success', 'De standaardkleuren van het lichte thema zijn hersteld.' ),
        'theme-reset-dark'  => array( 'success', 'De standaardkleuren van het donkere thema zijn hersteld.' ),
        'action-failed'     => array( 'error',   'De actie is mislukt. Probeer het opnieuw.' ),
    );
}

function cm_admin_notice_html( $code ) {
    $messages = cm_admin_notice_messages();
    if ( ! isset( $messages[ $code ] ) ) return '';
    return '<div class="notice notice-' . esc_attr( $messages[ $code ][0] ) . ' is-dismissible"><p>' . esc_html( $messages[ $code ][1] ) . '</p></div>';
}

function cm_admin_render_notices() {
    if ( empty( $_GET['cm_notice'] ) ) return;
    echo cm_admin_notice_html( sanitize_key( wp_unslash( $_GET['cm_notice'] ) ) );
}

/** Een knop die als eigen formulier naar admin-post.php post. */
function cm_admin_action_form( $action, $label, array $args = array(), $confirm = '', $class = 'button' ) {
    $html  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="cm-action-form"'
           . ( $confirm !== '' ? ' data-cm-confirm="' . esc_attr( $confirm ) . '"' : '' ) . '>';
    $html .= '<input type="hidden" name="action" value="' . esc_attr( 'cm_' . $action ) . '">';
    $html .= wp_nonce_field( 'cm_' . $action, '_wpnonce', true, false );
    foreach ( $args as $k => $v ) {
        $html .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
    }
    $html .= '<button type="submit" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button></form>';
    return $html;
}

/** Registreer een actie: rechten + nonce controleren, callback uitvoeren, terug met melding. */
function cm_admin_register_action( $action, $callback ) {
    add_action( 'admin_post_cm_' . $action, function () use ( $action, $callback ) {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Geen toegang.', '', array( 'response' => 403 ) );
        check_admin_referer( 'cm_' . $action );
        $notice = call_user_func( $callback );
        wp_safe_redirect( cm_admin_redirect_url( wp_get_referer(), $notice ? (string) $notice : '' ) );
        exit;
    } );
}

/** De pagina waar de actie vandaan kwam, zonder oude meldingen, met de nieuwe. */
function cm_admin_redirect_url( $referer, $notice ) {
    $url = $referer ? $referer : admin_url( 'admin.php?page=cookiebaas' );
    $url = remove_query_arg( array( 'cm_notice', 'settings-updated' ), $url );
    return $notice !== '' ? add_query_arg( 'cm_notice', $notice, $url ) : $url;
}
```

- [ ] **Step 4: Laad het bestand**

In `cookiemelding.php`, na de regel voor `includes/admin/settings.php`:

```php
require_once CM_PLUGIN_DIR . 'includes/admin/actions.php';
```

- [ ] **Step 5: Draai de tests**

Run: `php tests/test-admin3-actions.php && php tests/run.php`
Expected: alles groen.

- [ ] **Step 6: Commit**

```bash
git add includes/admin/actions.php cookiemelding.php tests/test-admin3-actions.php
git commit -m "feat(admin3): acties via admin-post.php met eigen nonce en meldingen

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 5: Banner › Weergave en Gedrag

**Files:**
- Create: `includes/admin/page-banner.php`
- Modify: `cookiemelding.php` (require)
- Test: `tests/test-admin3-banner.php`

**Interfaces:**
- Consumes: `cm_field()` (Taak 2), `cm_sanitize_settings()` / `cm_sanitize_field_value()` (Taak 3).
- Produces:
  - `cm_tabs_banner(): array` (in deze taak alleen `weergave` en `gedrag`; Taak 6 en 7 voegen tabs toe);
  - `cm_tab_banner_weergave(): array`
  - `cm_tab_banner_gedrag(): array`
  - `cm_banner_icon_type( array $values ): string`
  - `cm_sanitize_csv_ids( $raw ): string`

- [ ] **Step 1: Schrijf de falende test**

`tests/test-admin3-banner.php`:

```php
<?php
/**
 * Nieuwe admin (3.0) — pagina Banner.
 *
 * Borgt per tab dat de juiste instellingen er staan (spec §3.1), en de
 * randgevallen uit Review Focus 2: niets geselecteerd = leeg opgeslagen.
 */

function sanitize_text_field( $s ) { return is_scalar( $s ) ? trim( strip_tags( (string) $s ) ) : ''; }
function get_pages() { return array( (object) array( 'ID' => 7, 'post_title' => 'Contact' ), (object) array( 'ID' => 12, 'post_title' => 'Bedankt' ) ); }
function absint( $v ) { return abs( (int) $v ); }

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
require CM_PLUGIN_ROOT . '/includes/admin/fields.php';
require CM_PLUGIN_ROOT . '/includes/admin/settings.php';
require CM_PLUGIN_ROOT . '/includes/admin/page-banner.php';

function tab_keys( $tab ) {
    $keys = array();
    foreach ( cm_tabs_banner()[ $tab ]['sections'] as $s ) foreach ( isset( $s['fields'] ) ? $s['fields'] : array() as $f ) $keys[] = $f['key'];
    return $keys;
}

cm_test_group( 'Banner › Weergave' );
$w = tab_keys( 'weergave' );
foreach ( array( 'banner_position', 'banner_width_bottom_center', 'banner_width_center', 'banner_width_compact', 'banner_mobile_padding', 'prefs_cookie_detail', 'show_float_btn', 'float_btn_style', 'float_icon_size', 'cm_icon_type', 'float_icon_custom_svg', 'float_icon_image_url', 'float_position' ) as $k ) {
    cm_assert( "Weergave bevat $k", in_array( $k, $w, true ) );
}
cm_assert( 'icoontype: afbeelding wint van SVG', cm_banner_icon_type( array( 'float_icon_image_url' => 'x', 'float_icon_custom_svg' => '<svg/>' ) ) === 'image' );
cm_assert( 'icoontype: zonder beide standaard', cm_banner_icon_type( array() ) === 'default' );

cm_test_group( 'Banner › Gedrag' );
$g = tab_keys( 'gedrag' );
foreach ( array( 'analytics_default', 'expiry_months', 'respect_dnt', 'respect_gpc', 'reload_after_consent', 'geo_enabled', 'geo_outside_eu', 'exclude_login_page', 'exclude_woocommerce_checkout', 'exclude_page_ids', 'exclude_url_patterns', 'subdomain_sharing', 'subdomain_root_domain' ) as $k ) {
    cm_assert( "Gedrag bevat $k", in_array( $k, $g, true ) );
}

cm_test_group( 'Pagina-uitsluiting: niets gekozen = leeg' );
$index = cm_admin_field_index( 'cm_settings', array( 'cookiebaas-banner' => cm_tabs_banner() ) );
cm_assert( 'twee pagina\'s gekozen', cm_sanitize_field_value( $index['exclude_page_ids'], array( '', '7', '12' ), '' ) === '7,12' );
cm_assert( 'niets gekozen → leeg', cm_sanitize_field_value( $index['exclude_page_ids'], array( '' ), '7' ) === '' );
cm_assert( 'pagina-opties komen uit get_pages', cm_admin_field_options( $index['exclude_page_ids'] ) === array( 7 => 'Contact (ID 7)', 12 => 'Bedankt (ID 12)' ) );

exit( cm_test_summary() );
```

- [ ] **Step 2: Draai de test en zie hem falen**

Run: `php tests/test-admin3-banner.php`
Expected: fatal "Failed opening required …/includes/admin/page-banner.php".

- [ ] **Step 3: Schrijf `includes/admin/page-banner.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   PAGINA BANNER — Vormgeving · Teksten · Weergave · Gedrag
   Regel (spec §3): kleuren → Vormgeving, teksten → Teksten,
   plaats en uiterlijk-gedrag → Weergave, wanneer en hoe lang → Gedrag.
================================================================ */

function cm_tabs_banner() {
    return array(
        'weergave' => cm_tab_banner_weergave(),
        'gedrag'   => cm_tab_banner_gedrag(),
    );
}

/** Welk icoon de zweefknop gebruikt, afgeleid uit de opgeslagen waarden. */
function cm_banner_icon_type( array $values ) {
    if ( ! empty( $values['float_icon_image_url'] ) )  return 'image';
    if ( ! empty( $values['float_icon_custom_svg'] ) ) return 'custom';
    return 'default';
}

/** Array (of oude komma-string) van pagina-id's → '7,12'. */
function cm_sanitize_csv_ids( $raw ) {
    if ( ! is_array( $raw ) ) return sanitize_text_field( $raw );
    return implode( ',', array_filter( array_map( 'absint', $raw ) ) );
}

function cm_tab_banner_weergave() {
    $float_on  = array( 'show_float_btn' => '1' );
    $icon_on   = array( 'show_float_btn' => '1', 'float_btn_style' => 'icon' );
    return array(
        'label'    => 'Weergave',
        'sections' => array(
            array(
                'title'  => 'Cookiebanner',
                'fields' => array(
                    cm_field( 'banner_position', 'radio', 'Positie', array(
                        'options'     => array(
                            'bottom-center' => 'Onderaan in het midden (standaard)',
                            'center'        => 'In het midden van het scherm',
                            'bottom-left'   => 'Linksonder, compact',
                            'bottom-right'  => 'Rechtsonder, compact',
                        ),
                        'description' => 'Alleen de plek verandert; de werking blijft gelijk.',
                    ) ),
                    cm_field( 'banner_width_bottom_center', 'number', 'Breedte', array( 'min' => 400, 'max' => 1200, 'unit' => 'px', 'description' => 'Standaard 760 px.', 'show_if' => array( 'banner_position' => 'bottom-center' ) ) ),
                    cm_field( 'banner_width_center', 'number', 'Breedte', array( 'min' => 400, 'max' => 1000, 'unit' => 'px', 'description' => 'Standaard 620 px.', 'show_if' => array( 'banner_position' => 'center' ) ) ),
                    cm_field( 'banner_width_compact', 'number', 'Breedte', array( 'min' => 300, 'max' => 600, 'unit' => 'px', 'description' => 'Standaard 420 px.', 'show_if' => array( 'banner_position' => array( 'bottom-left', 'bottom-right' ) ) ) ),
                    cm_field( 'banner_mobile_padding', 'checkbox', 'Mobiel', array(
                        'checkbox_label' => 'Ruimte rond de banner op kleine schermen',
                        'description'    => 'Uit: de banner loopt tot de schermranden. Aan: een kleine marge, zodat de banner zweeft.',
                    ) ),
                ),
            ),
            array(
                'title'  => 'Voorkeurenvenster',
                'fields' => array(
                    cm_field( 'prefs_cookie_detail', 'radio', 'Detailniveau', array(
                        'options'     => array(
                            '1' => 'Gedetailleerd: categorieën met de cookies per categorie',
                            '0' => 'Vereenvoudigd: alleen categorieën met hun omschrijving',
                        ),
                        'description' => 'De schakelaars per categorie zijn altijd zichtbaar.',
                    ) ),
                ),
            ),
            array(
                'title'  => 'Zweefknop',
                'intro'  => 'Met de zweefknop kunnen bezoekers hun keuze later wijzigen. De AVG vereist dat intrekken even makkelijk is als toestemming geven.',
                'fields' => array(
                    cm_field( 'show_float_btn', 'checkbox', 'Zweefknop', array(
                        'checkbox_label' => 'Zweefknop tonen',
                        'description'    => 'Zet u de zweefknop uit, plaats dan zelf een link in de footer:<br><code>&lt;a href="#" onclick="Cookiebaas.openPrefs();return false;"&gt;Cookie-instellingen&lt;/a&gt;</code><br>Of open de banner zelf: <code>Cookiebaas.showBanner()</code>.',
                    ) ),
                    cm_field( 'float_btn_style', 'radio', 'Stijl', array( 'options' => array( 'icon' => 'Rond icoon', 'text' => 'Tekstknop' ), 'show_if' => $float_on ) ),
                    cm_field( 'float_icon_size', 'radio', 'Grootte', array( 'options' => array( 'normal' => 'Normaal (52 px)', 'small' => 'Klein (40 px)' ), 'show_if' => $icon_on ) ),
                    cm_field( 'cm_icon_type', 'radio', 'Icoon', array(
                        'store'   => false,
                        'options' => array( 'default' => 'Standaard cookie-icoon', 'custom' => 'Eigen SVG-code', 'image' => 'Afbeelding uit de mediabibliotheek' ),
                        'value'   => function ( $values ) { return cm_banner_icon_type( $values ); },
                        'show_if' => $icon_on,
                    ) ),
                    cm_field( 'float_icon_custom_svg', 'code', 'SVG-code', array(
                        'placeholder' => '<svg viewBox="0 0 24 24">…</svg>',
                        'description' => 'Plak de volledige <code>&lt;svg&gt;…&lt;/svg&gt;</code>-code. De kleur volgt de icoonkleur op de tab Vormgeving.',
                        'show_if'     => array_merge( $icon_on, array( 'cm_icon_type' => 'custom' ) ),
                    ) ),
                    cm_field( 'float_icon_image_url', 'media', 'Afbeelding', array(
                        'description' => 'SVG, WebP, JPG of PNG. De afbeelding wordt getoond zoals hij is; zorg zelf voor voldoende contrast.',
                        'show_if'     => array_merge( $icon_on, array( 'cm_icon_type' => 'image' ) ),
                    ) ),
                    cm_field( 'float_position', 'radio', 'Positie', array( 'options' => array( 'left' => 'Linksonder', 'right' => 'Rechtsonder' ), 'show_if' => $float_on ) ),
                ),
            ),
        ),
    );
}

function cm_tab_banner_gedrag() {
    return array(
        'label'    => 'Gedrag',
        'sections' => array(
            array(
                'title'  => 'Standaardkeuzes',
                'fields' => array(
                    cm_field( 'analytics_default', 'checkbox', 'Analytische cookies', array(
                        'checkbox_label' => 'Standaard aangevinkt in het voorkeurenvenster',
                        'description'    => 'Marketingcookies staan altijd standaard uit (AVG-vereiste). Staat "Google-cookies direct laden" aan (Blokkering › Google), dan staat dit automatisch ook aan.',
                    ) ),
                ),
            ),
            array(
                'title'  => 'Toestemming',
                'fields' => array(
                    cm_field( 'expiry_months', 'number', 'Keuze geldig', array( 'min' => 1, 'max' => 24, 'unit' => 'maanden', 'description' => 'Daarna vraagt de banner opnieuw om toestemming. AVG-richtlijn: maximaal 12 maanden.' ) ),
                    cm_field( 'respect_dnt', 'checkbox', 'Do Not Track', array(
                        'checkbox_label' => 'Respecteer het Do Not Track-signaal (DNT) van de browser',
                        'description'    => 'Met DNT aan worden analytische en marketingcookies automatisch geweigerd. De banner wordt overgeslagen en de keuze wordt gelogd als "dnt".',
                    ) ),
                    cm_field( 'respect_gpc', 'checkbox', 'Global Privacy Control', array(
                        'checkbox_label' => 'Respecteer het Global Privacy Control-signaal (GPC) van de browser',
                        'description'    => 'GPC is de opvolger van DNT en is in 12+ Amerikaanse staten wettelijk verplicht. Europese toezichthouders (CNIL, ICO) zien GPC als geldig bezwaar (AVG art. 21). Met GPC aan worden analytische en marketingcookies automatisch geweigerd.',
                    ) ),
                    cm_field( 'reload_after_consent', 'checkbox', 'Herladen na akkoord', array(
                        'checkbox_label' => 'Herlaad de pagina ook na het geven van toestemming',
                        'description'    => 'Standaard <strong>uit</strong>: na akkoord worden scripts en embeds direct vrijgegeven zonder herladen (geen flits; scrollpositie en formuliervelden blijven behouden). Aan geeft een schone, volledig gemeten <code>page_view</code> van de landingspagina. Bij het <strong>intrekken</strong> van toestemming wordt altijd herladen, om draaiende scripts te stoppen.',
                    ) ),
                ),
            ),
            array(
                'title'  => 'Wie ziet de banner',
                'fields' => array(
                    cm_field( 'geo_enabled', 'radio', 'Zichtbaarheid', array(
                        'options'     => array( '0' => 'Altijd tonen, voor alle bezoekers wereldwijd (standaard, veiligste keuze)', '1' => 'Alleen in landen met privacywetgeving' ),
                        'description' => 'Landen met privacywetgeving: EU/EER, VK, Zwitserland, Brazilië, Canada, India, Thailand, Indonesië, Zuid-Afrika, Japan, Zuid-Korea, Australië, Nieuw-Zeeland, Singapore, Argentinië en Mexico.',
                    ) ),
                    cm_field( 'geo_outside_eu', 'radio', 'Overige landen', array(
                        'options'     => array( 'hide' => 'Geen banner; cookies worden niet geblokkeerd', 'accept' => 'Automatisch akkoord; alle cookies direct toegestaan' ),
                        'description' => 'Is er geen land-header beschikbaar (geen Cloudflare of CDN), dan wordt de banner altijd getoond.',
                        'show_if'     => array( 'geo_enabled' => '1' ),
                    ) ),
                ),
            ),
            array(
                'title'  => 'Uitzonderingen',
                'intro'  => 'Op deze pagina\'s verschijnt geen banner.',
                'fields' => array(
                    cm_field( 'exclude_login_page', 'checkbox', 'Inlogpagina', array( 'checkbox_label' => 'Geen banner op <code>wp-login.php</code>' ) ),
                    cm_field( 'exclude_woocommerce_checkout', 'checkbox', 'WooCommerce', array( 'checkbox_label' => 'Geen banner bij afrekenen, betalen en de bestelbevestiging', 'description' => 'Werkt alleen als WooCommerce actief is.' ) ),
                    cm_field( 'exclude_page_ids', 'multiselect', 'Specifieke pagina\'s', array(
                        'options'     => function () {
                            $opts = array();
                            foreach ( get_pages( array( 'sort_column' => 'post_title', 'sort_order' => 'ASC', 'number' => 200 ) ) as $p ) {
                                $opts[ (int) $p->ID ] = $p->post_title . ' (ID ' . (int) $p->ID . ')';
                            }
                            return $opts;
                        },
                        'sanitize'    => function ( $raw ) { return cm_sanitize_csv_ids( $raw ); },
                        'description' => 'Houd Ctrl (Windows) of Cmd (Mac) ingedrukt om meerdere pagina\'s te kiezen.',
                    ) ),
                    cm_field( 'exclude_url_patterns', 'text', 'URL-patronen', array(
                        'class'       => 'large-text',
                        'placeholder' => '/bedankt, /privacyverklaring, /checkout',
                        'description' => 'Komma-gescheiden stukjes URL. Op elke pagina waarvan de URL zo\'n stukje bevat, verschijnt geen banner. Gebruikt u TranslatePress met vertaalde slugs, voeg dan ook de vertaalde varianten toe.',
                    ) ),
                ),
            ),
            array(
                'title'  => 'Subdomeinen',
                'fields' => array(
                    cm_field( 'subdomain_sharing', 'checkbox', 'Consent delen', array(
                        'checkbox_label' => 'Deel de keuze tussen subdomeinen',
                        'description'    => 'De consent-cookie wordt op het hoofddomein gezet, zodat alle subdomeinen dezelfde keuze delen.',
                    ) ),
                    cm_field( 'subdomain_root_domain', 'text', 'Hoofddomein', array(
                        'placeholder' => '.voorbeeld.nl',
                        'description' => 'Met een punt ervoor, bijvoorbeeld <code>.voorbeeld.nl</code>. Installeer de plugin op elk subdomein met dezelfde instelling en vermeld in de bannertekst welke domeinen de keuze dekt (AVG-transparantie).',
                        'show_if'     => array( 'subdomain_sharing' => '1' ),
                    ) ),
                ),
            ),
        ),
    );
}
```

- [ ] **Step 4: Laad het bestand**

In `cookiemelding.php`, na de regel voor `includes/admin/actions.php`:

```php
require_once CM_PLUGIN_DIR . 'includes/admin/page-banner.php';
```

- [ ] **Step 5: Draai de tests**

Run: `php tests/test-admin3-banner.php && php tests/run.php`
Expected: alles groen.

- [ ] **Step 6: Handmatige check (Ruud test zelf)**

Open op de lokale site *Cookiebaas 3 › Banner › Gedrag*:
- Vink "Deel de keuze tussen subdomeinen" aan en uit. De rij "Hoofddomein" verschijnt en verdwijnt.
- Sla op. De melding "Instellingen opgeslagen." verschijnt en de waarde staat ook in het oude scherm (*Cookiebaas › Instellingen › Algemeen*).
- Kies geen enkele pagina bij "Specifieke pagina's" en sla op. Daarna staan er geen pagina's meer geselecteerd.

- [ ] **Step 7: Commit**

```bash
git add includes/admin/page-banner.php cookiemelding.php tests/test-admin3-banner.php
git commit -m "feat(admin3): Banner › Weergave en Gedrag

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 6: Banner › Teksten (Nederlands en English)

**Files:**
- Modify: `includes/admin/page-banner.php`
- Modify: `tests/test-admin3-banner.php`

**Interfaces:**
- Consumes: de schakelaar-conventie uit Taak 2 (`data-cm-switch="lang"`, `data-cm-pane="lang:nl|en"`).
- Produces:
  - `cm_tab_banner_teksten(): array`
  - `cm_text_sections( string $lang ): array` (`$lang` is `'nl'` of `'en'`; bij `'en'` krijgen sleutels het achtervoegsel `_en`)
  - `cm_render_switch( string $name, array $options, string $current, string $prefix ): void`

- [ ] **Step 1: Breid de test uit**

Voeg toe aan `tests/test-admin3-banner.php`, vóór `exit( cm_test_summary() );`:

```php
cm_test_group( 'Banner › Teksten' );
$t = tab_keys( 'teksten' );
cm_assert( 'bannertaal staat op Teksten', in_array( 'banner_language', $t, true ) );
foreach ( array( 'txt_banner_title', 'txt_banner_body', 'txt_btn_prefs', 'txt_btn_reject', 'txt_btn_accept', 'txt_prefs_title', 'txt_prefs_body', 'txt_btn_allowall', 'txt_btn_rejectall', 'txt_btn_save', 'txt_cat1_name', 'txt_cat1_short', 'txt_cat1_long', 'txt_cat2_name', 'txt_cat2_short', 'txt_cat2_long', 'txt_cat3_name', 'txt_cat3_short', 'txt_cat3_long', 'txt_float_label', 'txt_embed_title', 'txt_embed_body', 'txt_embed_accept_btn', 'txt_embed_prefs' ) as $k ) {
    cm_assert( "Teksten bevat $k en {$k}_en", in_array( $k, $t, true ) && in_array( $k . '_en', $t, true ) );
}
cm_assert( 'txt_embed_btn heeft geen UI (had geen effect)', ! in_array( 'txt_embed_btn', $t, true ) );
$idx = cm_admin_field_index( 'cm_settings', array( 'cookiebaas-banner' => cm_tabs_banner() ) );
cm_assert( 'embed-voorkeurenlink behoudt class (opent het venster)', strpos( cm_sanitize_field_value( $idx['txt_embed_prefs'], 'Of pas uw <a href="#" class="cm-embed-open-prefs">voorkeuren</a> aan.', '' ), 'class="cm-embed-open-prefs"' ) !== false );
cm_assert( 'embed-tekst behoudt <strong>', cm_sanitize_field_value( $idx['txt_embed_body'], 'Voor <strong>{service}</strong>', '' ) === 'Voor <strong>{service}</strong>' );
```

Voeg bovenaan de test een realistische `wp_kses`-stub toe, vóór `require __DIR__ . '/bootstrap.php';`. Die moet attributen buiten de whitelist weghalen. De bootstrap-stub laat alles door en zou de class-test zinloos maken:

```php
function wp_kses( $s, $allowed = array() ) {
    $s = strip_tags( (string) $s, '<' . implode( '><', array_keys( $allowed ) ) . '>' );
    return preg_replace_callback( '/<([a-z]+)([^>]*)>/i', function ( $m ) use ( $allowed ) {
        $tag = strtolower( $m[1] );
        $keep = '';
        preg_match_all( '/([a-z-]+)="([^"]*)"/i', $m[2], $attrs, PREG_SET_ORDER );
        foreach ( $attrs as $a ) if ( isset( $allowed[ $tag ][ strtolower( $a[1] ) ] ) ) $keep .= ' ' . $a[1] . '="' . $a[2] . '"';
        return '<' . $tag . $keep . '>';
    }, $s );
}
```

- [ ] **Step 2: Draai de test en zie hem falen**

Run: `php tests/test-admin3-banner.php`
Expected: FAIL / `Undefined array key "teksten"`.

- [ ] **Step 3: Voeg de tab toe aan `includes/admin/page-banner.php`**

Vervang `cm_tabs_banner()` door:

```php
function cm_tabs_banner() {
    return array(
        'teksten'  => cm_tab_banner_teksten(),
        'weergave' => cm_tab_banner_weergave(),
        'gedrag'   => cm_tab_banner_gedrag(),
    );
}
```

Voeg daaronder toe:

```php
/** Schakelaar "Bewerken voor: A | B" (zie admin-common.js). */
function cm_render_switch( $name, array $options, $current, $prefix ) {
    echo '<ul class="subsubsub cm-switch" data-cm-switch="' . esc_attr( $name ) . '"><li>' . esc_html( $prefix ) . ' </li>';
    $last = array_key_last( $options );
    foreach ( $options as $value => $label ) {
        $on = (string) $value === (string) $current;
        echo '<li><a href="#" data-cm-switch-to="' . esc_attr( $value ) . '"' . ( $on ? ' class="current" aria-current="true"' : '' ) . '>' . esc_html( $label ) . '</a>' . ( $value === $last ? '' : ' |' ) . '</li>';
    }
    echo '</ul>';
}

/** Tekstsecties voor één taal; bij 'en' krijgen de sleutels het achtervoegsel _en. */
function cm_text_sections( $lang ) {
    $s      = $lang === 'en' ? '_en' : '';
    $pane   = array( 'data-cm-pane' => 'lang:' . $lang );
    $html   = 'Toegestane HTML: <code>&lt;a href=""&gt;</code>, <code>&lt;strong&gt;</code>, <code>&lt;em&gt;</code>.';
    $cats   = array( 1 => 'Functionele cookies (categorie 1)', 2 => 'Analytische cookies (categorie 2)', 3 => 'Marketingcookies (categorie 3)' );
    $sections = array(
        array( 'title' => 'Hoofdbanner', 'attrs' => $pane, 'fields' => array(
            cm_field( 'txt_banner_title' . $s, 'text', 'Titel' ),
            cm_field( 'txt_banner_body' . $s, 'html', 'Tekst', array( 'description' => $html ) ),
            cm_field( 'txt_btn_prefs' . $s, 'text', 'Knop "Cookie voorkeuren"' ),
            cm_field( 'txt_btn_reject' . $s, 'text', 'Knop "Weigeren"' ),
            cm_field( 'txt_btn_accept' . $s, 'text', 'Knop "Akkoord"' ),
        ) ),
        array( 'title' => 'Voorkeurenvenster', 'attrs' => $pane, 'fields' => array(
            cm_field( 'txt_prefs_title' . $s, 'text', 'Titel' ),
            cm_field( 'txt_prefs_body' . $s, 'html', 'Tekst', array( 'description' => $html ) ),
            cm_field( 'txt_btn_allowall' . $s, 'text', 'Knop "Alles toestaan"' ),
            cm_field( 'txt_btn_rejectall' . $s, 'text', 'Knop "Alles afwijzen"' ),
            cm_field( 'txt_btn_save' . $s, 'text', 'Knop "Keuzes opslaan"' ),
        ) ),
    );
    foreach ( $cats as $i => $title ) {
        $sections[] = array( 'title' => $title, 'attrs' => $pane, 'collapsible' => true, 'fields' => array(
            cm_field( "txt_cat{$i}_name{$s}", 'text', 'Naam' ),
            cm_field( "txt_cat{$i}_short{$s}", 'text', 'Korte omschrijving' ),
            cm_field( "txt_cat{$i}_long{$s}", 'textarea', 'Uitgebreide omschrijving' ),
        ) );
    }
    $sections[] = array( 'title' => 'Zweefknop', 'attrs' => $pane, 'fields' => array(
        cm_field( 'txt_float_label' . $s, 'text', 'Tekst', array( 'description' => 'De tekst van de tekstknop, en het schermlezerlabel van het icoon.' ) ),
    ) );
    $sections[] = array( 'title' => 'Placeholder voor geblokkeerde video\'s', 'attrs' => $pane, 'fields' => array(
        cm_field( 'txt_embed_title' . $s, 'text', 'Titel' ),
        cm_field( 'txt_embed_body' . $s, 'html', 'Tekst', array( 'description' => 'Gebruik <code>{service}</code> voor de naam van de dienst, bijvoorbeeld YouTube.' ) ),
        cm_field( 'txt_embed_accept_btn' . $s, 'text', 'Knop "Cookies accepteren"' ),
        cm_field( 'txt_embed_prefs' . $s, 'html', 'Link naar voorkeuren', array(
            'allowed'     => array( 'a' => array( 'href' => array(), 'class' => array() ), 'strong' => array(), 'em' => array() ),
            'description' => 'Houd de link <code>&lt;a href="#" class="cm-embed-open-prefs"&gt;…&lt;/a&gt;</code> intact: die opent het voorkeurenvenster.',
        ) ),
    ) );
    return $sections;
}

function cm_tab_banner_teksten() {
    $sections = array(
        array( 'title' => 'Taal', 'fields' => array(
            cm_field( 'banner_language', 'radio', 'Taal van de banner', array(
                'options'     => array( 'nl' => 'Nederlands', 'en' => 'English' ),
                'description' => 'In welke taal bezoekers de banner zien, los van de taal van de site. Gebruikt u een vertaalplugin zoals TranslatePress, laat dit dan op de standaardtaal van de site staan en vertaal via die plugin.',
            ) ),
        ) ),
        array( 'title' => 'Teksten', 'content' => function () {
            cm_render_switch( 'lang', array( 'nl' => 'Nederlands', 'en' => 'English' ), cm_detect_lang(), 'Bewerken voor:' );
        } ),
    );
    return array(
        'label'    => 'Teksten',
        'preview'  => true,
        'sections' => array_merge( $sections, cm_text_sections( 'nl' ), cm_text_sections( 'en' ) ),
    );
}
```

`array_key_last` bestaat vanaf PHP 7.3. Voor oudere PHP gebruik je `$keys = array_keys( $options ); $last = end( $keys );`.

- [ ] **Step 4: Draai de tests**

Run: `php tests/test-admin3-banner.php && php tests/run.php`
Expected: alles groen. `'preview' => true` doet nog niets zolang `cm_admin_render_preview()` niet bestaat (Taak 8). `cm_admin_render_form_tab` controleert dat.

- [ ] **Step 5: Handmatige check**

Open *Cookiebaas 3 › Banner › Teksten*:
- "Bewerken voor: Nederlands | English" wisselt de tekstvelden.
- Pas een EN-tekst aan en sla op. De tab staat daarna nog op Teksten en de EN-waarde is bewaard.

- [ ] **Step 6: Commit**

```bash
git add includes/admin/page-banner.php tests/test-admin3-banner.php
git commit -m "feat(admin3): Banner › Teksten met NL/EN, ook voor de video-placeholder

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 7: Banner › Vormgeving en "standaardkleuren herstellen"

**Files:**
- Modify: `includes/admin/page-banner.php`
- Modify: `includes/admin.php`: laat `cm_ajax_reset_theme_defaults()` `cm_reset_theme_colors()` gebruiken
- Modify: `tests/test-admin3-banner.php`

**Interfaces:**
- Consumes: `cm_render_switch()` (Taak 6), `cm_admin_register_action()`, `cm_admin_action_form()` (Taak 4).
- Produces:
  - `cm_tab_banner_vormgeving(): array`
  - `cm_color_sections( string $theme ): array` (`'light'` | `'dark'`)
  - `cm_reset_theme_colors( array $settings, string $theme ): array`
  - actie `cm_reset_theme` (POST `theme=light|dark`)
  - meldingscodes `theme-reset-light` en `theme-reset-dark` (al in Taak 4)

- [ ] **Step 1: Breid de test uit**

Voeg toe aan `tests/test-admin3-banner.php`, vóór `exit(...)`:

```php
cm_test_group( 'Banner › Vormgeving' );
$v = tab_keys( 'vormgeving' );
cm_assert( 'Vormgeving is de eerste tab', array_keys( cm_tabs_banner() )[0] === 'vormgeving' );
cm_assert( 'actief thema staat op Vormgeving', in_array( 'color_theme', $v, true ) );
$missing = array();
foreach ( array_keys( cm_default_settings() ) as $k ) {
    $is_colour = ( strpos( $k, 'color_' ) === 0 && ! in_array( $k, array( 'color_theme', 'color_always_on_bg' ), true ) ) || strpos( $k, 'dm_' ) === 0
        || in_array( $k, array( 'radius_popup', 'radius_btn', 'overlay_opacity' ), true );
    if ( $is_colour && ! in_array( $k, $v, true ) ) $missing[] = $k;
}
cm_assert( 'elke kleur, afronding en overlay van beide thema\'s staat erop' . ( $missing ? ' — ontbreekt: ' . implode( ', ', $missing ) : '' ), ! $missing );
$idx = cm_admin_field_index( 'cm_settings', array( 'cookiebaas-banner' => cm_tabs_banner() ) );
foreach ( array( 'accept_border', 'reject_border', 'allowall_border', 'outline_hover_bg' ) as $o ) {
    cm_assert( "color_$o en dm_$o zijn optioneel", $idx[ 'color_' . $o ]['type'] === 'color_optional' && $idx[ 'dm_' . $o ]['type'] === 'color_optional' );
}

cm_test_group( 'Standaardkleuren herstellen' );
$s = array_merge( cm_default_settings(), array( 'color_title' => '#123123', 'dm_title' => '#321321', 'color_theme' => 'dark', 'radius_btn' => 30, 'gtm_container_id' => 'GTM-X' ) );
$l = cm_reset_theme_colors( $s, 'light' );
cm_assert( 'licht: lichte kleur en afronding terug', $l['color_title'] === cm_default_settings()['color_title'] && $l['radius_btn'] === cm_default_settings()['radius_btn'] );
cm_assert( 'licht: donker, actief thema en overige instellingen blijven', $l['dm_title'] === '#321321' && $l['color_theme'] === 'dark' && $l['gtm_container_id'] === 'GTM-X' );
$d = cm_reset_theme_colors( $s, 'dark' );
cm_assert( 'donker: alleen dm_* terug', $d['dm_title'] === cm_default_settings()['dm_title'] && $d['color_title'] === '#123123' );
```

- [ ] **Step 2: Draai de test en zie hem falen**

Run: `php tests/test-admin3-banner.php`
Expected: FAIL / `Undefined array key "vormgeving"`.

- [ ] **Step 3: Voeg de tab en de actie toe aan `includes/admin/page-banner.php`**

Vervang `cm_tabs_banner()` door:

```php
function cm_tabs_banner() {
    return array(
        'vormgeving' => cm_tab_banner_vormgeving(),
        'teksten'    => cm_tab_banner_teksten(),
        'weergave'   => cm_tab_banner_weergave(),
        'gedrag'     => cm_tab_banner_gedrag(),
    );
}
```

Voeg toe:

```php
/** Kleurgroepen voor één thema. Licht: color_*, radius_*, overlay_opacity. Donker: dm_*. */
function cm_color_sections( $theme ) {
    $p    = $theme === 'dark' ? 'dm_' : 'color_';
    $r    = $theme === 'dark' ? 'dm_' : '';
    $pane = array( 'data-cm-pane' => 'theme:' . $theme, 'class' => 'postbox' );
    $c    = function ( $key, $label, array $extra = array() ) use ( $p ) { return cm_field( $p . $key, 'color', $label, $extra ); };
    $o    = function ( $key, $label, $ph ) use ( $p ) { return cm_field( $p . $key, 'color_optional', $label, array( 'placeholder' => $ph ) ); };
    return array(
        array( 'title' => 'Venster', 'collapsible' => true, 'open' => true, 'attrs' => $pane, 'fields' => array(
            $c( 'popup_bg', 'Achtergrond' ),
            $c( 'title', 'Titels' ),
            $c( 'body', 'Tekst' ),
            $c( 'link', 'Links' ),
            cm_field( $r . 'radius_popup', 'number', 'Hoekafronding venster', array( 'min' => 0, 'max' => 60, 'unit' => 'px' ) ),
            cm_field( $r . 'overlay_opacity', 'number', 'Donkerte achtergrond', array( 'min' => 0, 'max' => 90, 'unit' => '%', 'description' => 'Hoe donker de pagina achter het venster wordt.' ) ),
        ) ),
        array( 'title' => 'Knoppen', 'collapsible' => true, 'attrs' => $pane, 'fields' => array(
            $c( 'accept_bg', 'Akkoord — achtergrond' ),
            $c( 'accept_text', 'Akkoord — tekst' ),
            $c( 'accept_hover_bg', 'Akkoord — achtergrond bij hover' ),
            $c( 'accept_hover_text', 'Akkoord — tekst bij hover' ),
            $o( 'accept_border', 'Akkoord — rand', 'Geen rand' ),
            $c( 'reject_bg', 'Weigeren — achtergrond' ),
            $c( 'reject_text', 'Weigeren — tekst' ),
            $c( 'reject_hover_bg', 'Weigeren — achtergrond bij hover' ),
            $c( 'reject_hover_text', 'Weigeren — tekst bij hover' ),
            $o( 'reject_border', 'Weigeren — rand', 'Geen rand' ),
            $c( 'prefs_border', 'Cookie voorkeuren — rand' ),
            $c( 'prefs_text', 'Cookie voorkeuren — tekst' ),
            $c( 'prefs_hover_border', 'Cookie voorkeuren — rand bij hover' ),
            $c( 'prefs_hover_text', 'Cookie voorkeuren — tekst bij hover' ),
            $c( 'allowall_bg', 'Alles toestaan — achtergrond' ),
            $c( 'allowall_text', 'Alles toestaan — tekst' ),
            $c( 'allowall_hover_bg', 'Alles toestaan — achtergrond bij hover' ),
            $c( 'allowall_hover_text', 'Alles toestaan — tekst bij hover' ),
            $o( 'allowall_border', 'Alles toestaan — rand', 'Geen rand' ),
            $c( 'outline_border', 'Alles afwijzen — rand' ),
            $c( 'outline_text', 'Alles afwijzen — tekst' ),
            $c( 'outline_hover_border', 'Alles afwijzen — rand bij hover' ),
            $c( 'outline_hover_text', 'Alles afwijzen — tekst bij hover' ),
            $o( 'outline_hover_bg', 'Alles afwijzen — achtergrond bij hover', 'Geen achtergrond' ),
            cm_field( $r . 'radius_btn', 'number', 'Hoekafronding knoppen', array( 'min' => 0, 'max' => 60, 'unit' => 'px', 'description' => 'Geldt voor alle knoppen.' ) ),
        ) ),
        array( 'title' => 'Voorkeurenvenster en cookielijst', 'collapsible' => true, 'attrs' => $pane, 'fields' => array(
            $c( 'close_bg', 'Sluitknop — achtergrond' ),
            $c( 'close_hover_bg', 'Sluitknop — achtergrond bij hover' ),
            $c( 'close_icon', 'Sluitknop — kruisje' ),
            $c( 'toggle_on', 'Schakelaar aan' ),
            $c( 'toggle_off', 'Schakelaar uit' ),
            $c( 'always_bg', '"Altijd actief" — achtergrond' ),
            $c( 'always_on_color', '"Altijd actief" — tekst' ),
            $c( 'expand_bg', 'Uitklapicoon — achtergrond' ),
            $c( 'expand_icon', 'Uitklapicoon — icoon' ),
            $c( 'expand_open_bg', 'Uitklapicoon open — achtergrond' ),
            $c( 'expand_open_icon', 'Uitklapicoon open — icoon' ),
            $c( 'cat_header_hover', 'Categorie — achtergrond bij hover' ),
            $c( 'cat_desc', 'Categorie — omschrijving' ),
            $c( 'cat_detail', 'Detailtekst' ),
            $c( 'cookie_name', 'Cookienaam' ),
            $c( 'cookie_meta', 'Cookiegegevens' ),
            $c( 'cat_border', 'Randen', array( 'description' => 'Rand om categorieën, diensten en cookies.' ) ),
            $c( 'service_bg', 'Dienst — achtergrond' ),
            $c( 'service_name', 'Dienst — naam' ),
            $c( 'cookie_item_bg', 'Cookierij — achtergrond' ),
            $c( 'cookie_empty', 'Tekst bij lege cookielijst' ),
            $c( 'badge_text', 'Badge derde partij — tekst' ),
            $c( 'badge_bg', 'Badge derde partij — achtergrond' ),
            $c( 'badge_border', 'Badge derde partij — rand' ),
        ) ),
        array( 'title' => 'Zweefknop', 'collapsible' => true, 'attrs' => $pane, 'fields' => array(
            $c( 'float_icon_bg', 'Icoon — achtergrond' ),
            $c( 'float_icon_color', 'Icoon — kleur' ),
            $c( 'float_icon_hover_bg', 'Icoon — achtergrond bij hover' ),
            $c( 'float_icon_hover_color', 'Icoon — kleur bij hover' ),
            $c( 'float_text_bg', 'Tekstknop — achtergrond' ),
            $c( 'float_text_color', 'Tekstknop — tekst' ),
            $c( 'float_text_border', 'Tekstknop — rand' ),
            $c( 'float_text_hover_bg', 'Tekstknop — achtergrond bij hover' ),
            $c( 'float_text_hover_color', 'Tekstknop — tekst bij hover' ),
        ) ),
        array( 'title' => 'Placeholder voor geblokkeerde video\'s', 'collapsible' => true, 'attrs' => $pane, 'fields' => array(
            $c( 'embed_bg', 'Achtergrond' ),
            $c( 'embed_title', 'Titel' ),
            $c( 'embed_body', 'Tekst' ),
            $c( 'embed_btn_bg', 'Knop — achtergrond' ),
            $c( 'embed_btn_text', 'Knop — tekst' ),
            $c( 'embed_btn_hover_bg', 'Knop — achtergrond bij hover' ),
            $c( 'embed_btn_hover_text', 'Knop — tekst bij hover' ),
        ) ),
    );
}

function cm_tab_banner_vormgeving() {
    $head = array(
        array( 'title' => 'Thema', 'fields' => array(
            cm_field( 'color_theme', 'radio', 'Actief thema', array(
                'options'     => array( 'light' => 'Licht', 'dark' => 'Donker' ),
                'description' => 'Welk kleurenschema bezoekers zien.',
            ) ),
        ) ),
        array( 'title' => 'Kleuren', 'content' => function () {
            cm_render_switch( 'theme', array( 'light' => 'Licht', 'dark' => 'Donker' ), cm_get( 'color_theme' ) === 'dark' ? 'dark' : 'light', 'Bewerken voor:' );
        } ),
    );
    return array(
        'label'      => 'Vormgeving',
        'preview'    => true,
        'sections'   => array_merge( $head, cm_color_sections( 'light' ), cm_color_sections( 'dark' ) ),
        'after_form' => function () {
            echo '<p>Standaardkleuren herstellen: ';
            echo cm_admin_action_form( 'reset_theme', 'Licht', array( 'theme' => 'light' ), 'De kleuren van het lichte thema teruggezet naar de standaard?', 'button button-small' );
            echo ' ';
            echo cm_admin_action_form( 'reset_theme', 'Donker', array( 'theme' => 'dark' ), 'De kleuren van het donkere thema teruggezet naar de standaard?', 'button button-small' );
            echo '</p>';
        },
    );
}

/** Zet de kleuren (en afronding/overlay) van één thema terug naar de defaults. */
function cm_reset_theme_colors( array $settings, $theme ) {
    foreach ( cm_default_settings() as $key => $val ) {
        if ( $theme === 'dark' ) {
            if ( strpos( $key, 'dm_' ) === 0 ) $settings[ $key ] = $val;
            continue;
        }
        if ( $key === 'color_theme' ) continue;
        if ( strpos( $key, 'color_' ) === 0 || strpos( $key, 'radius_' ) === 0 || $key === 'overlay_opacity' ) {
            $settings[ $key ] = $val;
        }
    }
    return $settings;
}

if ( function_exists( 'cm_admin_register_action' ) ) {
    cm_admin_register_action( 'reset_theme', function () {
        $theme = ( isset( $_POST['theme'] ) && $_POST['theme'] === 'dark' ) ? 'dark' : 'light';
        $saved = get_option( 'cm_settings', array() );
        update_option( 'cm_settings', cm_reset_theme_colors( is_array( $saved ) ? $saved : array(), $theme ) );
        return 'theme-reset-' . $theme;
    } );
}
```

`cm_admin_action_form` zet de knoppen op één regel, omdat elke knop een eigen `<form>` is. Voeg daarom aan `assets/css/admin-layout.css` toe:

```css
.cm-action-form { display: inline; }
```

- [ ] **Step 4: Laat de oude reset-handler de nieuwe functie gebruiken (DRY)**

Vervang in `includes/admin.php` de body van `cm_ajax_reset_theme_defaults()`, na de nonce- en rechtencheck, door:

```php
    $theme    = isset( $_POST['theme'] ) && $_POST['theme'] === 'dark' ? 'dark' : 'light';
    $existing = (array) get_option( 'cm_settings', array() );
    update_option( 'cm_settings', cm_reset_theme_colors( $existing, $theme ) );
    wp_send_json_success( array( 'defaults' => cm_default_settings(), 'theme' => $theme ) );
```

- [ ] **Step 5: Draai de tests**

Run: `php tests/test-admin3-banner.php && php tests/run.php`
Expected: alles groen. Faalt "elke kleur … staat erop"? Dan noemt de melding precies welke sleutel ontbreekt of verkeerd gespeld is.

- [ ] **Step 6: Handmatige check**

Open *Banner › Vormgeving*:
- "Bewerken voor: Donker" toont de donkere kleurgroepen, **zonder** dat "Actief thema" verandert.
- Plak `FFF` in een hex-veld en klik ernaast. Het veld wordt `#ffffff` en het kleurvlak wit.
- Een optionele rand: leeg toont de grijze tekst "Geen rand". Na een kleur kiezen verschijnt "Wissen".
- "Standaardkleuren herstellen: Donker" vraagt om bevestiging en toont daarna de succesmelding.

- [ ] **Step 7: Commit**

```bash
git add includes/admin/page-banner.php includes/admin.php assets/css/admin-layout.css tests/test-admin3-banner.php
git commit -m "feat(admin3): Banner › Vormgeving, thema los van bewerken, kleuren herstellen

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 8: Live preview met de échte banner-markup

**Files:**
- Modify: `includes/frontend.php:934-1335`: haal `cm_banner_markup()` uit `cm_render_frontend()`
- Create: `includes/admin/preview.php`
- Create: `assets/js/admin-preview.js`
- Create: `assets/css/preview-stage.css`
- Modify: `cookiemelding.php` (require)
- Test: `tests/test-admin3-preview.php`

**Interfaces:**
- Produces:
  - `cm_banner_markup(): array`. Echoot banner, voorkeurenvenster en zweefknop precies zoals nu, en geeft `$cats` terug (categorie → cookies), die het frontend-script nodig heeft.
  - `cm_preview_var_map(): array`. Rijen `array( licht-sleutel, donker-sleutel|null, css-var, eenheid ''|'px'|'alpha', waarde-als-leeg )`.
  - `cm_preview_vars( array $s, string $theme ): array` (css-var → waarde)
  - `cm_preview_text_map(): array` (tekst-sleutel → `array( 'sel' => css-selector, 'html' => bool )`)
  - `cm_admin_render_preview( string $tab ): void`
  - `cm_admin_preview_assets(): void`
  - JS-global `CM_PREVIEW = { vars, initial: {light, dark}, theme, lang, texts, frontendCss, stageCss }`.
- Consumes: het `cm-switch`-event (Taak 2), `data-cm-key` op de velden (Taak 2).

- [ ] **Step 1: Leg de huidige frontend-output vast (voor de refactor)**

Maak `/tmp/cm-render.php` (buiten de repo). Het gaat om een eenmalige vergelijking:

```php
<?php
require '/Users/rvdh/Documents/GitHub/cookiebaas/tests/bootstrap.php';
function is_singular() { return false; }
function get_pages() { return array(); }
function wp_kses_post( $s ) { return $s; }
function sanitize_title( $s ) { return strtolower( preg_replace( '/[^a-z0-9]+/i', '-', $s ) ); }
function date_i18n( $f, $t = null ) { return 'date'; }
function current_user_can() { return false; }
function rest_url( $p = '' ) { return 'https://example.test/wp-json/' . $p; }
require CM_PLUGIN_ROOT . '/includes/defaults.php';
require CM_PLUGIN_ROOT . '/includes/frontend.php';
ob_start(); cm_render_frontend(); echo ob_get_clean();
```

Run: `php /tmp/cm-render.php > /tmp/cm-before.html && wc -c /tmp/cm-before.html`
Expected: ongeveer 56.000 bytes, geen PHP-fouten in de output.

- [ ] **Step 2: Schrijf de falende test**

`tests/test-admin3-preview.php`:

```php
<?php
/**
 * Nieuwe admin (3.0) — live preview.
 *
 * Borgt:
 *   - cm_banner_markup() levert exact de banner uit cm_render_frontend()
 *   - de CSS-variabelen van de preview zijn gelijk aan wat de frontend echt
 *     uitvoert (licht én donker) — één waarheid, geen afwijkende JS-fallbacks
 *   - het preview-iframe draait nooit scripts (Review Focus 5)
 */

function is_singular() { return false; }
function get_pages() { return array(); }
function wp_kses_post( $s ) { return (string) $s; }
function sanitize_title( $s ) { return strtolower( preg_replace( '/[^a-z0-9]+/i', '-', $s ) ); }
function date_i18n( $f, $t = null ) { return 'date'; }
function current_user_can() { return false; }
function rest_url( $p = '' ) { return 'https://example.test/wp-json/' . $p; }
function cm_render_switch() {} // hoort bij page-banner.php; deze test controleert alleen iframe en template

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
require CM_PLUGIN_ROOT . '/includes/frontend.php';
require CM_PLUGIN_ROOT . '/includes/admin/preview.php';

cm_test_group( 'Banner-markup' );
ob_start(); $cats = cm_banner_markup(); $markup = ob_get_clean();
$GLOBALS['cm_rendered'] = false;
ob_start(); cm_render_frontend(); $full = ob_get_clean();
cm_assert( 'markup bevat banner, voorkeurenvenster en overlay', strpos( $markup, 'id="cm-banner"' ) !== false && strpos( $markup, 'id="cm-prefs"' ) !== false && strpos( $markup, 'id="cm-overlay"' ) !== false );
cm_assert( 'markup bevat geen script', stripos( $markup, '<script' ) === false );
cm_assert( 'frontend = markup + script', strpos( $full, $markup ) === 0 && stripos( $full, '<script', strlen( $markup ) ) !== false );
cm_assert( 'categorieën worden teruggegeven', isset( $cats['analytics'], $cats['marketing'], $cats['functional'] ) );

/** Effectieve CSS-variabelen uit de frontend-output (laatste declaratie wint). */
function frontend_vars() {
    ob_start(); cm_output_inline_css(); $css = ob_get_clean();
    $css = substr( $css, 0, strpos( $css, '<style id="cm-frontend-css">' ) ?: strlen( $css ) );
    preg_match_all( '/(--cm-[a-z0-9-]+):([^;]*);/', $css, $m, PREG_SET_ORDER );
    $vars = array();
    foreach ( $m as $d ) $vars[ $d[1] ] = $d[2];
    return $vars;
}

foreach ( array( 'light', 'dark' ) as $theme ) {
    cm_test_group( "CSS-variabelen preview == frontend ($theme)" );
    cm_test_set_settings( array_merge( cm_default_settings(), array( 'color_theme' => $theme, 'color_accept_border' => '', 'dm_reject_border' => '#445566' ) ) );
    $front = frontend_vars();
    $prev  = cm_preview_vars( cm_get_settings(), $theme );
    $diff  = array();
    foreach ( $front as $var => $val ) if ( ! array_key_exists( $var, $prev ) || (string) $prev[ $var ] !== (string) $val ) $diff[] = "$var (frontend $val, preview " . ( isset( $prev[ $var ] ) ? $prev[ $var ] : '—' ) . ')';
    cm_assert( 'elke variabele gelijk' . ( $diff ? ': ' . implode( '; ', $diff ) : '' ), ! $diff );
    cm_assert( 'geen extra variabelen in de preview', ! array_diff_key( $prev, $front ) );
}

cm_test_group( 'Iframe draait geen scripts' );
ob_start(); cm_admin_render_preview( 'vormgeving' ); $html = ob_get_clean();
cm_assert( 'sandbox zonder allow-scripts', preg_match( '/<iframe[^>]*sandbox="allow-same-origin"/', $html ) === 1 && strpos( $html, 'allow-scripts' ) === false );
cm_assert( 'banner-markup staat in een template', strpos( $html, '<template id="cm-preview-markup">' ) !== false );

exit( cm_test_summary() );
```

- [ ] **Step 3: Draai de test en zie hem falen**

Run: `php tests/test-admin3-preview.php`
Expected: fatal "Failed opening required …/includes/admin/preview.php".

- [ ] **Step 4: Haal `cm_banner_markup()` uit `cm_render_frontend()`**

In `includes/frontend.php`:

1. Knip het blok uit `cm_render_frontend()` dat begint bij de regel

   `    $show_float      = cm_get('show_float_btn');`

   tot en met de regel `    <?php endif; ?>` die `<?php if ( $show_float ) : ?>` afsluit. Dat is de laatste regel vóór `    <script data-no-defer="1" nowprocket>`.

2. Zet op die plek:

```php
    $cats = cm_banner_markup();
    ?>
```

   Zodat direct daarna `    <script data-no-defer="1" nowprocket>` volgt.

3. Plak het geknipte blok in een nieuwe functie direct **boven** `function cm_render_frontend() {`:

```php
/**
 * Banner, voorkeurenvenster en zweefknop — de zichtbare markup, zonder script.
 * Gedeeld door de frontend en de admin-preview. Geeft de cookies per
 * categorie terug; het frontend-script heeft die nodig (COOKIE_NAMES).
 */
function cm_banner_markup() {
    // ← hier het geknipte blok, ongewijzigd (begint met $show_float = …, eindigt met <?php endif; ?>)
    <?php
    return $cats;
}
```

   Het geknipte blok eindigt in HTML-modus (`<?php endif; ?>`). Daarom staat erna `<?php` en dan pas `return $cats;`. Laat de commentaarregel `// ← hier …` niet staan.

- [ ] **Step 5: Controleer dat de frontend-output byte-identiek is**

Run: `php /tmp/cm-render.php > /tmp/cm-after.html && cmp /tmp/cm-before.html /tmp/cm-after.html && echo IDENTIEK`
Expected: `IDENTIEK`. Geeft `cmp` een verschil? Dan zit er vrijwel zeker een extra of ontbrekende regelovergang rond `?>` of `<?php`. Pas de witruimte aan tot het identiek is.

- [ ] **Step 6: Schrijf `includes/admin/preview.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   LIVE PREVIEW — de échte banner-markup (cm_banner_markup) met
   frontend.css in een iframe zonder scripts. De JS zet alleen
   CSS-variabelen en teksten. De variabelenkaart hieronder is getest
   tegen de echte frontend-output (tests/test-admin3-preview.php).
================================================================ */

/** [licht-sleutel, donker-sleutel|null, css-var, eenheid, waarde-als-leeg] */
function cm_preview_var_map() {
    $m = array(
        array( 'overlay_opacity', 'dm_overlay_opacity', '--cm-overlay-alpha', 'alpha', '' ),
        array( 'radius_popup', 'dm_radius_popup', '--cm-popup-radius', 'px', '' ),
        array( 'radius_btn', 'dm_radius_btn', '--cm-btn-radius', 'px', '' ),
        array( 'banner_width_bottom_center', null, '--cm-banner-w-bottom', 'px', '' ),
        array( 'banner_width_center', null, '--cm-banner-w-center', 'px', '' ),
        array( 'banner_width_compact', null, '--cm-banner-w-compact', 'px', '' ),
        array( 'color_always_on_bg', null, '--cm-always-on-bg', '', '' ),
    );
    // Kleuren waarvan de variabelenaam het achtervoegsel volgt: color_<x> / dm_<x> → --cm-<var>
    $colours = array(
        'popup_bg' => 'popup-bg', 'title' => 'title-color', 'body' => 'body-color', 'link' => 'link-color',
        'accept_bg' => 'accept-bg', 'accept_text' => 'accept-text', 'accept_hover_bg' => 'accept-hover-bg', 'accept_hover_text' => 'accept-hover-text',
        'reject_bg' => 'reject-bg', 'reject_text' => 'reject-text', 'reject_hover_bg' => 'reject-hover-bg', 'reject_hover_text' => 'reject-hover-text',
        'prefs_border' => 'prefs-border', 'prefs_text' => 'prefs-text', 'prefs_hover_border' => 'prefs-hover-border', 'prefs_hover_text' => 'prefs-hover-text',
        'allowall_bg' => 'allowall-bg', 'allowall_text' => 'allowall-text', 'allowall_hover_bg' => 'allowall-hover-bg', 'allowall_hover_text' => 'allowall-hover-text',
        'outline_border' => 'outline-border', 'outline_text' => 'outline-text', 'outline_hover_border' => 'outline-hover-border', 'outline_hover_text' => 'outline-hover-text',
        'close_bg' => 'close-bg', 'close_hover_bg' => 'close-hover-bg', 'close_icon' => 'close-icon',
        'toggle_on' => 'toggle-on', 'toggle_off' => 'toggle-off', 'always_bg' => 'always-bg', 'always_on_color' => 'always-on-color',
        'badge_text' => 'badge-text', 'badge_bg' => 'badge-bg', 'badge_border' => 'badge-border',
        'service_name' => 'service-name-color', 'cookie_empty' => 'cookie-empty-color',
        'float_icon_bg' => 'float-icon-bg', 'float_icon_color' => 'float-icon-color', 'float_icon_hover_bg' => 'float-icon-hover-bg', 'float_icon_hover_color' => 'float-icon-hover-color',
        'float_text_bg' => 'float-text-bg', 'float_text_color' => 'float-text-color', 'float_text_border' => 'float-text-border', 'float_text_hover_bg' => 'float-text-hover-bg', 'float_text_hover_color' => 'float-text-hover-color',
        'cat_border' => 'cat-border', 'service_bg' => 'service-bg', 'cookie_item_bg' => 'cookie-item-bg', 'cat_header_hover' => 'cat-header-hover',
        'cat_desc' => 'cat-desc-color', 'cat_detail' => 'cat-detail-color', 'cookie_name' => 'cookie-name-color', 'cookie_meta' => 'cookie-meta-color',
        'expand_bg' => 'expand-bg', 'expand_icon' => 'expand-icon', 'expand_open_bg' => 'expand-open-bg', 'expand_open_icon' => 'expand-open-icon',
        'embed_bg' => 'embed-bg', 'embed_title' => 'embed-title', 'embed_body' => 'embed-body',
        'embed_btn_bg' => 'embed-btn-bg', 'embed_btn_text' => 'embed-btn-text', 'embed_btn_hover_bg' => 'embed-btn-hover-bg', 'embed_btn_hover_text' => 'embed-btn-hover-text',
    );
    foreach ( $colours as $suffix => $var ) {
        $m[] = array( 'color_' . $suffix, 'dm_' . $suffix, '--cm-' . $var, '', '' );
    }
    // Optionele randen/achtergrond: leeg = transparant (zelfde als de frontend)
    foreach ( array( 'accept_border' => 'accept-border', 'reject_border' => 'reject-border', 'allowall_border' => 'allowall-border', 'outline_hover_bg' => 'outline-hover-bg' ) as $suffix => $var ) {
        $m[] = array( 'color_' . $suffix, 'dm_' . $suffix, '--cm-' . $var, '', 'transparent' );
    }
    return $m;
}

function cm_preview_format( $v, $unit, $empty ) {
    if ( $v === '' || $v === null ) return $empty;
    if ( $unit === 'px' )    return intval( $v ) . 'px';
    if ( $unit === 'alpha' ) return (string) ( intval( $v ) / 100 );
    return (string) $v;
}

/** CSS-variabelen voor een thema, zoals de frontend ze effectief zet. */
function cm_preview_vars( array $s, $theme ) {
    $vars = array();
    foreach ( cm_preview_var_map() as $row ) {
        list( $light, $dark, $var, $unit, $empty ) = $row;
        $key = ( $theme === 'dark' && $dark ) ? $dark : $light;
        $vars[ $var ] = cm_preview_format( isset( $s[ $key ] ) ? $s[ $key ] : '', $unit, $empty );
    }
    return $vars;
}

/** Tekstveld → plek in de banner-markup. */
function cm_preview_text_map() {
    return array(
        'txt_banner_title'     => array( 'sel' => '#cm-banner-title', 'html' => false ),
        'txt_banner_body'      => array( 'sel' => '#cm-banner-desc', 'html' => true ),
        'txt_btn_prefs'        => array( 'sel' => '#cm-btn-prefs', 'html' => false ),
        'txt_btn_reject'       => array( 'sel' => '#cm-btn-reject', 'html' => false ),
        'txt_btn_accept'       => array( 'sel' => '#cm-btn-accept', 'html' => false ),
        'txt_prefs_title'      => array( 'sel' => '#cm-prefs-title-h2', 'html' => false ),
        'txt_prefs_body'       => array( 'sel' => '#cm-prefs-desc', 'html' => true ),
        'txt_btn_allowall'     => array( 'sel' => '#cm-allowall-btn', 'html' => false ),
        'txt_btn_rejectall'    => array( 'sel' => '#cm-rejectall-btn', 'html' => false ),
        'txt_btn_save'         => array( 'sel' => '#cm-save-btn', 'html' => false ),
        'txt_float_label'      => array( 'sel' => '#cm-float-btn', 'html' => false ),
        'txt_embed_title'      => array( 'sel' => '.cm-embed-title', 'html' => false ),
        'txt_embed_body'       => array( 'sel' => '.cm-embed-body', 'html' => true ),
        'txt_embed_accept_btn' => array( 'sel' => '.cm-embed-accept-btn', 'html' => false ),
        'txt_embed_prefs'      => array( 'sel' => '.cm-embed-prefs-link', 'html' => true ),
    );
}

function cm_admin_preview_assets() {
    $s = cm_get_settings();
    wp_enqueue_script( 'cm-admin-preview', CM_PLUGIN_URL . 'assets/js/admin-preview.js', array( 'cm-admin-common' ), CM_VERSION, true );
    wp_localize_script( 'cm-admin-preview', 'CM_PREVIEW', array(
        'vars'        => cm_preview_var_map(),
        'initial'     => array( 'light' => cm_preview_vars( $s, 'light' ), 'dark' => cm_preview_vars( $s, 'dark' ) ),
        'theme'       => cm_get( 'color_theme' ) === 'dark' ? 'dark' : 'light',
        'lang'        => cm_detect_lang(),
        'texts'       => cm_preview_text_map(),
        'frontendCss' => CM_PLUGIN_URL . 'assets/css/frontend.css?ver=' . CM_VERSION,
        'stageCss'    => CM_PLUGIN_URL . 'assets/css/preview-stage.css?ver=' . CM_VERSION,
    ) );
}

/** De preview-kolom naast het formulier. */
function cm_admin_render_preview( $tab ) {
    $views = array( 'banner' => 'Banner', 'prefs' => 'Voorkeuren', 'float' => 'Zweefknop', 'embed' => 'Video-placeholder' );
    echo '<div class="postbox cm-preview"><div class="postbox-header"><h2 class="hndle">Voorbeeld</h2></div><div class="inside">';
    cm_render_switch( 'view', $views, 'banner', 'Toon:' );
    cm_render_switch( 'device', array( 'desktop' => 'Desktop', 'mobile' => 'Mobiel' ), 'desktop', 'Scherm:' );
    echo '<div class="cm-preview-stage" inert><iframe id="cm-preview-frame" title="Voorbeeld van de banner" sandbox="allow-same-origin" tabindex="-1"></iframe></div>';
    echo '<p class="description">Past zich direct aan terwijl u kiest. Het voorbeeld is niet klikbaar.</p>';
    echo '<template id="cm-preview-markup">';
    cm_banner_markup();
    $info = array( 'service' => 'YouTube', 'category' => 'marketing', 'icon' => '▶' );
    $src  = 'https://www.youtube.com/embed/aqz-KE-bpKQ';
    echo '<div class="cm-stage-embed">' . cm_build_embed_placeholder( '<iframe src="' . esc_attr( $src ) . '" width="560" height="315"></iframe>', $src, $info ) . '</div>';
    echo '</template>';
    echo '</div></div>';
}
```

`cm_render_switch()` komt uit `page-banner.php` (Taak 6). Hij is altijd geladen als de preview rendert, want de preview bestaat alleen op Banner. De test (Step 2) laadt `page-banner.php` niet en definieert daarom een lege stub.

- [ ] **Step 7: Schrijf `assets/js/admin-preview.js`**

```js
/* Cookiebaas 3 — live voorbeeld (Banner › Vormgeving en Teksten).
 * De banner is de échte frontend-markup met frontend.css, in een iframe
 * zonder scripts (sandbox). Dit script zet alleen CSS-variabelen en teksten. */
(function () {
  'use strict';

  var cfg = window.CM_PREVIEW;
  var frame = document.getElementById('cm-preview-frame');
  var tpl = document.getElementById('cm-preview-markup');
  if (!cfg || !frame || !tpl) return;

  var HEX = /^#[0-9a-fA-F]{6}$/;
  var state = { theme: cfg.theme, lang: cfg.lang, view: 'banner', device: 'desktop' };
  var doc = null;

  function fieldValue(key) {
    var els = document.querySelectorAll('.cm-form [data-cm-key="' + key + '"]');
    if (!els.length) return null;
    for (var i = 0; i < els.length; i++) {
      var el = els[i];
      if (el.type === 'radio') { if (el.checked) return el.value; continue; }
      if (el.type === 'checkbox') return el.checked ? '1' : '0';
      return el.value;
    }
    return null;
  }

  function fmt(v, unit, empty) {
    if (v === '' || v === null) return empty;
    if (unit === 'px' || unit === 'alpha') {
      var n = parseInt(v, 10);
      if (isNaN(n)) return null;
      return unit === 'px' ? n + 'px' : String(n / 100);
    }
    return HEX.test(v.trim()) ? v.trim().toLowerCase() : null;
  }

  function applyVars() {
    var root = doc.documentElement;
    var base = cfg.initial[state.theme] || {};
    Object.keys(base).forEach(function (name) { root.style.setProperty(name, base[name]); });
    cfg.vars.forEach(function (m) {
      var key = state.theme === 'dark' && m[1] ? m[1] : m[0];
      var out = fmt(fieldValue(key), m[3], m[4]);
      if (out) root.style.setProperty(m[2], out);
    });
  }

  function applyTexts() {
    var sfx = state.lang === 'en' ? '_en' : '';
    Object.keys(cfg.texts).forEach(function (key) {
      var t = cfg.texts[key];
      var v = fieldValue(key + sfx);
      if (v === null) return;
      if (v === '' && sfx) v = fieldValue(key) || ''; // net als de frontend: lege EN valt terug op NL
      if (key === 'txt_embed_body') v = v.replace('{service}', '<strong>YouTube</strong>');
      doc.querySelectorAll(t.sel).forEach(function (el) {
        if (key === 'txt_float_label' && el.querySelector('svg, img')) return; // icoonknop: tekst is alleen het label
        if (t.html) el.innerHTML = v; else el.textContent = v;
      });
    });
  }

  function applyView() {
    var ov = doc.getElementById('cm-overlay'), bn = doc.getElementById('cm-banner');
    var pr = doc.getElementById('cm-prefs'), fl = doc.getElementById('cm-float');
    if (ov) ov.classList.toggle('cm-active', state.view === 'banner' || state.view === 'prefs');
    if (bn) bn.classList.toggle('cm-active', state.view === 'banner');
    if (pr) pr.classList.toggle('cm-active', state.view === 'prefs');
    if (fl) fl.classList.toggle('cm-visible', state.view === 'float');
    doc.documentElement.setAttribute('data-cm-view', state.view);
  }

  function applyDevice() {
    var w = state.device === 'mobile' ? 390 : 1280;
    var h = state.device === 'mobile' ? 760 : 800;
    var stage = frame.parentNode;
    var scale = Math.min(1, stage.clientWidth / w);
    frame.style.width = w + 'px';
    frame.style.height = h + 'px';
    frame.style.transform = 'scale(' + scale + ')';
    stage.style.height = Math.round(h * scale) + 'px';
  }

  function applyAll() { if (doc) { applyVars(); applyTexts(); applyView(); } }

  function build() {
    doc = frame.contentDocument;
    doc.open();
    doc.write('<!DOCTYPE html><html><head><meta charset="utf-8">' +
      '<link rel="stylesheet" href="' + cfg.frontendCss + '">' +
      '<link rel="stylesheet" href="' + cfg.stageCss + '">' +
      '</head><body><div class="cm-stage-page"><div></div><div></div><div></div><div></div></div>' +
      tpl.innerHTML + '</body></html>');
    doc.close();
    ['cm-overlay', 'cm-banner', 'cm-prefs'].forEach(function (id) {
      var el = doc.getElementById(id);
      if (el) el.style.display = '';
    });
    applyAll();
  }

  document.addEventListener('input', function (e) { if (e.target.closest('.cm-form')) applyAll(); });
  document.addEventListener('change', function (e) { if (e.target.closest('.cm-form')) applyAll(); });
  document.addEventListener('cm-switch', function (e) {
    if (e.detail.name === 'theme') state.theme = e.detail.value;
    if (e.detail.name === 'lang') state.lang = e.detail.value;
    if (e.detail.name === 'view') state.view = e.detail.value;
    if (e.detail.name === 'device') { state.device = e.detail.value; applyDevice(); }
    applyAll();
  });
  window.addEventListener('resize', applyDevice);

  build();
  applyDevice();
})();
```

**Let op:** `admin-common.js` vuurt bij het laden al `cm-switch`-events af, vóór dit script luistert. Daarom begint `state` met `cfg.theme` en `cfg.lang`, en die zijn gelijk aan de server-side "current" van de schakelaars.

- [ ] **Step 8: Schrijf `assets/css/preview-stage.css`**

Dit is de nep-site in het iframe, geen admin-markup, dus deze CSS valt buiten de broncheck.

```css
/* Cookiebaas 3 — nep-website achter de banner in het preview-iframe. */
html, body { margin: 0; min-height: 100%; background: #f4f4f4; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
.cm-stage-page { max-width: 960px; margin: 0 auto; padding: 32px; }
.cm-stage-page div { height: 14px; background: #e2e2e2; border-radius: 7px; margin: 18px 0; }
.cm-stage-page div:first-child { height: 220px; border-radius: 10px; }
.cm-stage-embed { display: none; max-width: 640px; margin: 24px auto; }
html[data-cm-view="embed"] .cm-stage-embed { display: block; }
html[data-cm-view="embed"] .cm-stage-page { display: none; }
```

Voeg aan `assets/css/admin-layout.css` toe:

```css
.cm-preview { position: sticky; top: 48px; margin-top: 20px; }
.cm-preview .subsubsub { float: none; margin: 0 0 8px; }
.cm-preview-stage { position: relative; overflow: hidden; }
#cm-preview-frame { border: 0; transform-origin: 0 0; display: block; }
```

- [ ] **Step 9: Laad het bestand**

In `cookiemelding.php`, na de regel voor `includes/admin/page-banner.php`:

```php
require_once CM_PLUGIN_DIR . 'includes/admin/preview.php';
```

- [ ] **Step 10: Draai de tests**

Run: `php tests/test-admin3-preview.php && php tests/run.php`
Expected: alles groen, **inclusief ongewijzigd** `test-cache-safety.php`, `test-consent-mode.php`, `test-blocker.php` en `test-geo-cachesafe.php`.

Faalt "elke variabele gelijk"? De melding noemt de variabele met beide waarden. Pas dan `cm_preview_var_map()` aan, **niet** de frontend.

- [ ] **Step 11: Handmatige check**

Open *Banner › Vormgeving*:
- Het voorbeeld toont de banner.
- Wijzig "Akkoord — achtergrond". De knop in het voorbeeld verandert direct.
- "Bewerken voor: Donker" toont de donkere banner.
- "Toon: Voorkeuren / Zweefknop / Video-placeholder" wisselt de weergave.
- "Scherm: Mobiel" toont de mobiele layout.

Open *Banner › Teksten*:
- Een titel typen verandert het voorbeeld.
- "English" toont de EN-teksten.
- Een leeg EN-veld toont de NL-tekst, net als op de site.

- [ ] **Step 12: Commit**

```bash
git add includes/frontend.php includes/admin/preview.php assets/js/admin-preview.js assets/css/preview-stage.css assets/css/admin-layout.css cookiemelding.php tests/test-admin3-preview.php
git commit -m "feat(admin3): live preview met de echte banner-markup in een sandbox-iframe

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 9: Blokkering (Google, Scripts, Embeds) en de volledigheidstest

**Files:**
- Create: `includes/admin/page-blokkering.php`
- Modify: `includes/admin/menu.php` (niets: `cookiebaas-blokkering` staat al in `cm_admin_pages()`)
- Modify: `cookiemelding.php` (require)
- Create: `tests/test-admin3-registry.php`
- Modify: `tests/README.md`

**Interfaces:**
- Produces:
  - `cm_tabs_blokkering(): array`
  - `cm_embed_service_options(): array` (dienstnaam → dienstnaam, zonder functionele diensten)
  - `cm_sanitize_embed_services( $raw, array $f ): string`
- Consumes: alles uit Taak 2 t/m 8.

- [ ] **Step 1: Schrijf de falende tests**

`tests/test-admin3-registry.php`:

```php
<?php
/**
 * Nieuwe admin (3.0) — volledigheid van het register (spec §8).
 *
 * De belangrijkste test van de herindeling: elke instelling uit
 * cm_default_settings() staat op precies één tab, of in een expliciete
 * lijst. Zo kan er bij het verhuizen niets verdwijnen of dubbel staan.
 * Plan 2 en 3 verwijderen sleutels uit $pending tot die leeg is.
 */

function sanitize_text_field( $s ) { return is_scalar( $s ) ? trim( strip_tags( (string) $s ) ) : ''; }
function get_pages() { return array(); }
function absint( $v ) { return abs( (int) $v ); }

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
foreach ( glob( CM_PLUGIN_ROOT . '/includes/admin/*.php' ) as $file ) require $file;

$no_ui   = array( 'txt_embed_btn', 'txt_embed_btn_en', 'color_always_on_bg' );          // dood, sleutel blijft voor de data
$pending = array( 'auto_scan_mode', 'auto_scan_interval', 'auto_scan_email',             // plan 2: Cookies › Scannen
                  'log_retention_months',                                                 // plan 3: Consent log
                  'api_key' );                                                            // plan 3: Beheer › Geavanceerd

cm_test_group( 'Elke instelling precies één keer' );
$keys   = array_map( function ( $f ) { return $f['key']; }, cm_admin_field_list( 'cm_settings' ) );
$counts = array_count_values( $keys );
$dups   = array_keys( array_filter( $counts, function ( $n ) { return $n > 1; } ) );
cm_assert( 'geen sleutel op twee plekken' . ( $dups ? ' — dubbel: ' . implode( ', ', $dups ) : '' ), ! $dups );
$missing = array_diff( array_keys( cm_default_settings() ), $keys, $no_ui, $pending );
cm_assert( 'geen instelling zonder plek' . ( $missing ? ' — ontbreekt: ' . implode( ', ', $missing ) : '' ), ! $missing );
$unknown = array_diff( $keys, array_keys( cm_default_settings() ) );
cm_assert( 'geen veld zonder default (zou nooit opgeslagen worden)' . ( $unknown ? ' — onbekend: ' . implode( ', ', $unknown ) : '' ), ! $unknown );
cm_assert( 'geen UI-loze sleutel toch op een tab', ! array_intersect( $no_ui, $keys ) );

cm_test_group( 'Idempotent met het echte register' );
$valid = cm_default_settings();
$once  = cm_sanitize_settings( $valid, $valid );
$diff  = array();
foreach ( $valid as $k => $v ) if ( is_scalar( $v ) && (string) $once[ $k ] !== (string) $v ) $diff[] = "$k: " . var_export( $v, true ) . ' → ' . var_export( $once[ $k ], true );
cm_assert( 'de defaults komen ongewijzigd door de sanitizer' . ( $diff ? ' — ' . implode( '; ', $diff ) : '' ), ! $diff );

cm_test_group( 'Embed-diensten (Review Focus 2 en 4)' );
$idx = cm_admin_field_index( 'cm_settings' );
$all = array_keys( cm_embed_service_options() );
cm_assert( 'alle diensten aangevinkt → leeg (= alles blokkeren)', cm_sanitize_field_value( $idx['embed_blocked_services'], array_merge( array( '' ), $all ), 'x' ) === '' );
cm_assert( 'niets aangevinkt → none', cm_sanitize_field_value( $idx['embed_blocked_services'], array( '' ), '' ) === 'none' );
cm_assert( 'één dienst → alleen die', cm_sanitize_field_value( $idx['embed_blocked_services'], array( '', 'YouTube' ), '' ) === 'YouTube' );
cm_assert( 'onbekende dienst wordt genegeerd', cm_sanitize_field_value( $idx['embed_blocked_services'], array( '', 'YouTube', 'Hackdienst' ), '' ) === 'YouTube' );
cm_assert( 'oude admin post een string: blijft werken', cm_sanitize_field_value( $idx['embed_blocked_services'], 'YouTube,Vimeo', '' ) === 'YouTube,Vimeo' );
cm_assert( 'reCAPTCHA (functioneel) staat niet in de lijst', ! in_array( 'reCAPTCHA', $all, true ) && ! in_array( 'Google reCAPTCHA', $all, true ) );

cm_test_group( 'Blokkering-tabs' );
$tabs = cm_tabs_blokkering();
cm_assert( 'tabs Google, Scripts, Embeds', array_keys( $tabs ) === array( 'google', 'scripts', 'embeds' ) );

exit( cm_test_summary() );
```

- [ ] **Step 2: Draai de test en zie hem falen**

Run: `php tests/test-admin3-registry.php`
Expected: fatal "Call to undefined function cm_embed_service_options()", of de volledigheidstest noemt de Google-, script- en embed-sleutels als ontbrekend.

- [ ] **Step 3: Schrijf `includes/admin/page-blokkering.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   PAGINA BLOKKERING — Google · Scripts · Embeds
================================================================ */

function cm_tabs_blokkering() {
    return array(
        'google'  => cm_tab_blokkering_google(),
        'scripts' => cm_tab_blokkering_scripts(),
        'embeds'  => cm_tab_blokkering_embeds(),
    );
}

/** Diensten die de embed-blocker kan tegenhouden (functionele, zoals reCAPTCHA, nooit). */
function cm_embed_service_options() {
    $opts = array();
    foreach ( cm_get_embed_domains() as $info ) {
        if ( $info['category'] !== 'functional' ) $opts[ $info['service'] ] = $info['service'];
    }
    return $opts;
}

/**
 * Checkboxlijst → opgeslagen formaat: alles aangevinkt = '' (alles blokkeren,
 * de standaard), niets aangevinkt = 'none', anders een komma-lijst.
 */
function cm_sanitize_embed_services( $raw, array $f ) {
    if ( ! is_array( $raw ) ) return sanitize_text_field( $raw ); // oude admin post een string
    $all  = array_keys( cm_embed_service_options() );
    $pick = array_values( array_intersect( $all, cm_csv_list( $raw ) ) );
    if ( count( $pick ) === count( $all ) ) return '';
    return $pick ? implode( ',', $pick ) : 'none';
}

function cm_tab_blokkering_google() {
    return array(
        'label'    => 'Google',
        'sections' => array(
            array( 'title' => 'Hoe werkt het?', 'collapsible' => true, 'content' => function () {
                echo '<p>Cookiebaas laadt Google-scripts zelf, via <strong>Google Consent Mode v2</strong>:</p><ol>';
                echo '<li>Vul hieronder uw tracking-ID\'s in en sla op.</li>';
                echo '<li>Verwijder de bestaande snippets uit uw thema of uit andere plugins.</li>';
                echo '<li>Cookiebaas laadt de scripts zelf in, geblokkeerd tot de bezoeker toestemming geeft.</li>';
                echo '<li>Na akkoord worden de scripts direct actief, zonder de pagina te herladen.</li>';
                echo '</ol>';
            } ),
            array(
                'title'  => 'Google Analytics en Tag Manager',
                'intro'  => 'Gebruik <strong>óf</strong> GA4 <strong>óf</strong> GTM, niet beide. GTM heeft de voorkeur als u meerdere Google-diensten gebruikt.',
                'fields' => array(
                    cm_field( 'ga4_measurement_id', 'text', 'GA4 Measurement ID', array( 'placeholder' => 'G-XXXXXXXXXX', 'description' => 'Begint met <code>G-</code>. Te vinden in Google Analytics › Beheer › Gegevensstreams.' ) ),
                    cm_field( 'gtm_container_id', 'text', 'GTM Container ID', array( 'placeholder' => 'GTM-XXXXXXX', 'description' => 'Begint met <code>GTM-</code>. Te vinden in Google Tag Manager › Workspace.' ) ),
                    cm_field( 'ua_tracking_id', 'text', 'Universal Analytics ID (verouderd)', array( 'placeholder' => 'UA-XXXXXXXXX-X', 'description' => 'Begint met <code>UA-</code>. Google heeft Universal Analytics stopgezet; gebruik bij voorkeur GA4. Beschikbaar voor sites die nog UA-code draaien.' ) ),
                    cm_field( 'google_consent_mode_advanced', 'checkbox', 'Consent Mode: advanced', array(
                        'checkbox_label' => 'Laad de Google-tag (GTM/GA4) altijd, ook vóór toestemming, zonder cookies tot er een keuze is',
                        'description'    => 'De tag laadt direct met alle signalen op <code>denied</code>. Er worden geen cookies geplaatst, maar Google-tags versturen wel <strong>cookieloze pings</strong> voor modellering, ook vóór een keuze en na een weigering. Na akkoord vuren de tags direct volledig. Uit = <strong>basic</strong>: de tag wacht volledig op toestemming. Niet-Google tags in GTM (zoals Meta Pixel) kennen geen Consent Mode; zie "Niet-Google tags via GTM" hieronder.',
                    ) ),
                    cm_field( 'google_url_passthrough', 'checkbox', 'URL passthrough', array(
                        'checkbox_label' => 'Geef meetinformatie door via de URL zolang cookies geweigerd zijn',
                        'description'    => 'Plakt een <code>_gl=</code>-parameter aan interne links zolang er geen toestemming is. Verbetert de attributie iets, maar maakt alle interne links lelijk en kan caching per URL versnipperen. Standaard uit.',
                    ) ),
                    cm_field( 'google_load_default', 'checkbox', 'Google-cookies direct laden', array(
                        'checkbox_label' => 'Laad Google-cookies direct bij het openen van de site, zonder toestemming',
                        'notice'         => array( 'type' => 'warning', 'text' => '<strong>Dit is niet toegestaan volgens de AVG.</strong> Cookies die niet strikt noodzakelijk zijn, waaronder Google Analytics en Tag Manager, mogen pas laden nadat de bezoeker toestemming heeft gegeven. Zet dit alleen aan als u daar een geldige juridische basis voor heeft.' ),
                        'description'    => 'Staat dit aan, dan staan analytische cookies ook standaard aangevinkt (Banner › Gedrag).',
                    ) ),
                ),
            ),
            array( 'title' => 'Niet-Google tags via GTM', 'collapsible' => true, 'content' => 'cm_render_gtm_guide' ),
        ),
    );
}

/** Stappenplan voor Meta Pixel, TikTok, LinkedIn e.d. in GTM (documentatie, geen instellingen). */
function cm_render_gtm_guide() {
    echo '<p><strong>Automatisch geregeld:</strong> Google-tags (GA4, Google Ads, Floodlight) via Consent Mode v2, en Microsoft UET: de plugin pusht bij elke keuze <code>uetq consent update</code>.</p>';
    echo '<p><strong>Eenmalig instellen in GTM:</strong> niet-Google tags kennen geen universele standaard. Volg deze drie stappen.</p>';
    echo '<h3>Stap 1: maak twee variabelen (type Data Layer Variable)</h3>';
    echo '<table class="widefat striped"><thead><tr><th>Naam</th><th>Data Layer Variable Name</th><th>Gebruik voor</th></tr></thead><tbody>';
    echo '<tr><td><code>CM - Analytics Consent</code></td><td><code>cm_analytics</code></td><td>Hotjar, Matomo, Clarity e.a.</td></tr>';
    echo '<tr><td><code>CM - Marketing Consent</code></td><td><code>cm_marketing</code></td><td>Meta Pixel, TikTok, LinkedIn e.a.</td></tr>';
    echo '</tbody></table>';
    echo '<h3>Stap 2: maak twee triggers (type Custom Event, event <code>cm_consent_update</code>)</h3>';
    echo '<table class="widefat striped"><thead><tr><th>Naam</th><th>Voorwaarde</th></tr></thead><tbody>';
    echo '<tr><td><code>CM - Analytics toegestaan</code></td><td><code>CM - Analytics Consent</code> equals <code>true</code></td></tr>';
    echo '<tr><td><code>CM - Marketing toegestaan</code></td><td><code>CM - Marketing Consent</code> equals <code>true</code></td></tr>';
    echo '</tbody></table>';
    echo '<h3>Stap 3: koppel de trigger aan uw tag</h3>';
    echo '<p>Geef uw Meta Pixel-, TikTok- of LinkedIn-tag als trigger <code>CM - Marketing toegestaan</code>. De tag vuurt dan alleen na toestemming voor marketingcookies.</p>';
    echo '<p>Voorbeeld van de dataLayer-push door de plugin:</p>';
    echo '<pre class="code">{ event: "cm_consent_update", cm_analytics: true, cm_marketing: false, cm_method: "custom",
  analytics_storage: "granted", ad_storage: "denied", ad_user_data: "denied", ad_personalization: "denied" }</pre>';
}

function cm_tab_blokkering_scripts() {
    return array(
        'label'    => 'Scripts',
        'sections' => array( array(
            'title'  => 'Overige trackingscripts',
            'intro'  => 'Scripts die Cookiebaas niet zelf herkent, kunt u hier op URL laten blokkeren tot toestemming.',
            'fields' => array(
                cm_field( 'block_analytics_patterns', 'text', 'Extra analytische patronen', array( 'placeholder' => 'bijvoorbeeld mijnanalytics.nl', 'description' => 'Komma-gescheiden stukjes URL van analytische scripts.' ) ),
                cm_field( 'block_marketing_patterns', 'text', 'Extra marketingpatronen', array( 'placeholder' => 'bijvoorbeeld mijnretargeting.nl', 'description' => 'Komma-gescheiden stukjes URL van marketingscripts.' ) ),
            ),
        ) ),
    );
}

function cm_tab_blokkering_embeds() {
    return array(
        'label'    => 'Embeds',
        'sections' => array( array(
            'title'  => 'Video\'s en andere embeds',
            'fields' => array(
                cm_field( 'embed_blocker_enabled', 'checkbox', 'Embeds blokkeren', array(
                    'checkbox_label' => 'Blokkeer iframes van bekende diensten (YouTube, Vimeo enz.) tot toestemming',
                    'description'    => 'Vervangt de iframes door een placeholder. Na toestemming laadt het iframe alsnog. Teksten en kleuren van de placeholder staan bij Banner › Teksten en Banner › Vormgeving.',
                ) ),
                cm_field( 'embed_blocked_services', 'checkboxes', 'Diensten', array(
                    'options'        => function () { return cm_embed_service_options(); },
                    'all_when_empty' => true,
                    'sanitize'       => function ( $raw, $current, $f ) { return cm_sanitize_embed_services( $raw, $f ); },
                    'description'    => 'Aangevinkte diensten worden geblokkeerd tot toestemming. reCAPTCHA wordt nooit geblokkeerd, zodat formulieren blijven werken.',
                    'show_if'        => array( 'embed_blocker_enabled' => '1' ),
                ) ),
            ),
        ) ),
    );
}
```

`'content' => 'cm_render_gtm_guide'` is een functienaam-string. `cm_admin_render_section` roept die aan met `call_user_func( $section['content'], $values )`. De extra parameter negeert PHP.

- [ ] **Step 4: Laad het bestand**

In `cookiemelding.php`, na de regel voor `includes/admin/preview.php`:

```php
require_once CM_PLUGIN_DIR . 'includes/admin/page-blokkering.php';
```

- [ ] **Step 5: Draai de tests**

Run: `php tests/test-admin3-registry.php && php tests/run.php`
Expected: alles groen. Deze uitkomsten wijzen elk op iets anders:
- **"ontbreekt: …"** noemt sleutels die geen plek hebben. Voeg ze toe op de juiste tab, of neem ze op in `$pending` als een later plan ze plaatst.
- **"dubbel: …"** betekent dat een sleutel op twee tabs staat.
- **"de defaults komen ongewijzigd door"** faalt als een default zelf niet door zijn veldtype komt, bijvoorbeeld een rgb()-kleur. Los dat op in `defaults.php` of in de veld-definitie, niet in de test.

- [ ] **Step 6: Werk `tests/README.md` bij**

Voeg aan de suitetabel toe, onder de rij van `test-admin-fixes.php`:

```markdown
| `test-admin3-frame.php` | Nieuwe admin: tab-whitelist, en broncheck (geen `style="`, emoji of hex-kleuren in `includes/admin/`). |
| `test-admin3-fields.php` | Renderer: label/for, verborgen 0 bij checkboxes, kleurvelden, optionele kleuren, show_if, checkboxlijsten. |
| `test-admin3-settings.php` | Type-bewuste sanitizing, gedeeltelijke tab-invoer, idempotentie, Settings API-callback, cache-purge via option-hooks. |
| `test-admin3-actions.php` | Acties via admin-post.php: formulier, nonce, redirect met één melding. |
| `test-admin3-banner.php` | Pagina Banner: juiste instellingen per tab, kleuren herstellen per thema, pagina-uitsluiting. |
| `test-admin3-preview.php` | Preview: banner-markup gelijk aan de frontend, CSS-variabelen gelijk aan de frontend (licht/donker), sandbox-iframe. |
| `test-admin3-registry.php` | **Belangrijkste van de herindeling:** elke instelling staat op precies één tab; defaults zijn idempotent; embed-diensten. |
```

- [ ] **Step 7: Handmatige check**

Open *Cookiebaas 3 › Blokkering › Embeds*:
- Vink alle diensten uit en sla op. Op de site worden video's niet meer geblokkeerd.
- Vink alles aan en sla op. Alles wordt weer geblokkeerd.
- In het oude scherm (*Instellingen › Embeds*) staat dezelfde keuze.

- [ ] **Step 8: Commit**

```bash
git add includes/admin/page-blokkering.php cookiemelding.php tests/test-admin3-registry.php tests/README.md
git commit -m "feat(admin3): Blokkering (Google, Scripts, Embeds) en volledigheidstest van het register

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

## Na plan 1

- Banner en Blokkering werken volledig in *Cookiebaas 3*. Het oude menu blijft voor Cookies, Privacyverklaring, Consent log en Beheer, en voor terugvallen.
- **Plan 2** (Cookies, Privacyverklaring) vult `cm_admin_pages()` en `cm_admin_tabs()` aan. Het voegt aan `cm_admin_render_form_tab()` een `group`- en `values`-sleutel toe voor `cm_privacy` en `cm_cookie_list`, registreert die options, en haalt `auto_scan_*` uit `$pending`.
- **Plan 3** (Consent log, Beheer, Overzicht, overstap, release) haalt `log_retention_months` en `api_key` uit `$pending`. Het verwijdert het oude menu, de pagina's in `includes/admin.php` en `assets/js/admin.js`. Daarbij horen ook:
  - `CM_DATA.settings` weg, zodat de API-sleutel niet meer in de JS staat;
  - de redirects van de oude slugs;
  - de licentiemelding alleen nog op de Cookiebaas-schermen;
  - de eenmalige melding "nieuwe indeling";
  - het label weer "Cookiebaas".
  Het plan bereidt ook de release 3.0.0 voor, pas na akkoord van Ruud.

## Uitkomst van plan 1 (26 september 2026)

Uitgevoerd via subagent-driven development: 9 taken, elk met review, plus een eindreview met één fixronde. Commits `d3d2e1e`..`8c3d978` op `main`, niet gepusht, versie blijft 2.4.5. Alle 15 testsuites groen; de frontend-output is byte-identiek aan de start (`5da5dc9`).

**Meegenomen voor plan 2 en 3:**
- **Frontend-bug (beslissing Ruud):** in het donkere thema maakt de frontend van `dm_radius_btn`, `dm_radius_popup` en `dm_overlay_opacity` = 0 respectievelijk 6px, 18px en 75% (`?:` in `cm_output_inline_css`, `frontend.php` ~131–133). De preview spiegelt dat nu, zodat hij toont wat bezoekers zien. Na een frontend-fix (`0` moet `0` blijven) moet de 6e kolom van `cm_preview_var_map()` weg.
- **Plan 3, opruimen bij verwijderen oude admin:**
  - de fallback-branches voor html, svg en url in `cm_sanitize_settings()` en de generieke multiselect/checkboxes-branch in `cm_sanitize_field_value()` worden dan dode code;
  - de preview-assets alleen op de tabs Vormgeving/Teksten laden.
- **Plan 2 en 3, nieuwe writers van `cm_settings`:** de sanitize-callback start altijd vanaf de opgeslagen waarde. Een writer kan dus geen sleutels verwijderen, en een sleutel buiten de defaults wordt stil genegeerd.
- **Opslaan zonder wijziging leegt de paginacache niet meer**, want `update_option_*` vuurt alleen bij een verandering. Dat is bewust; de reden staat in de spec §5.3.
- **Kleinigheden:**
  - `tests/run.php` lint `includes/admin/*.php` niet apart (parse-fouten vallen wel via de suites);
  - een geplakte hex zonder `#` bereikt de preview pas bij blur;
  - de preview past de kses van de site niet toe (onschadelijk in de sandbox).
