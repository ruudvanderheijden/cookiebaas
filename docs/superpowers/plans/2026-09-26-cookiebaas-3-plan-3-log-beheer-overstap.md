# Cookiebaas 3.0 — Plan 3: Consent log, Beheer, Overzicht en overstap

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** De nieuwe admin compleet maken met de pagina's **Consent log**, **Beheer** en een echt **Overzicht**. Daarna de oude admin verwijderen, het menu "Cookiebaas" laten heten met doorverwijzingen van de oude adressen, en release 3.0.0 voorbereiden (zonder uit te brengen).

**Architecture:** Dit plan bouwt op plan 1 en plan 2 (beide uitgevoerd; zie "Uitkomst van plan 1/2" in `docs/superpowers/plans/2026-09-26-cookiebaas-3-plan-{1-fundament-banner-blokkering,2-cookies-privacy}.md`).
- **Consent log** gebruikt `WP_List_Table` (klasse `CM_Log_List_Table` in een eigen bestand dat alleen laadt als WordPress' lijsttabel er is). Filters, zoeken, paginering en bulk verwijderen gaan via een GET-formulier naar de pagina zelf; de bulkactie wordt verwerkt op de `load-`hook van de pagina, vóór er output is.
- **Beheer** heeft alleen acties via `admin-post.php`, ook de licentie. Meldingen met een eigen tekst (het antwoord van de licentieserver, het resultaat van een import) gaan via een nieuwe eenmalige melding `cm_admin_flash()`.
- **Overzicht** krijgt statusblokken en de compliance-check uit 2.4 als data-functie (`cm_compliance_checks()`), met "Oplossen"-links naar de nieuwe tabs.
- **Overstap:** wat van de oude `includes/admin.php` nog nodig is (scan, cookiedatabase, frontend-AJAX voor consent en geo) verhuist ongewijzigd naar `includes/admin/scan.php` en `includes/consent.php`. Daarna gaan `includes/admin.php`, `assets/js/admin.js` en `assets/css/admin.css` weg.

**Tech Stack:** WordPress 7.1 (core admin-CSS, `WP_List_Table`), procedurele PHP met `cm_`-prefix en PHP 7.0-compatibele syntax in de plugin (geen arrow functions, `match`, nullsafe of `array_key_last`), vanilla JS, tests zonder dependencies (`php tests/run.php`).

**Spec:** `docs/superpowers/specs/2026-09-26-cookiebaas-3-admin-design.md`

## Global Constraints

- **Versie:** `CM_VERSION` en de plugin-header blijven **2.4.6** tot Taak 10; pas die zet ze op 3.0.0. Niets in dit plan wordt uitgebracht: niet pushen, niet taggen, geen GitHub-release. Commits direct op `main`. Commitberichten eindigen met `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. (Hotfixes voor 2.4.x gaan via de tak `release/2.4.x`, niet via dit plan.)
- **Data blijft:** geen wijziging van option-keys, opslagformaten of de tabel `{prefix}cm_consent_log`. `cm_consent_changelog` blijft een lijst van `array( 'date' => <opgemaakte string>, 'version' => int, 'reason' => string )`, maximaal 50 regels.
- **Frontend blijft functioneel identiek.** De frontend-AJAX (`cm_log_consent`, `cm_geo_check`) verhuist in Taak 8 ongewijzigd, met dezelfde actienamen.
- **Nieuwe admin-code** = `includes/admin/*.php`, `assets/js/admin-common.js`, `assets/js/admin-preview.js`, `assets/js/admin-cookies.js` en `assets/css/admin-layout.css`. Daarin **geen** `style="`, **geen** emoji, **geen** 6-cijferige hex-kleuren en **geen** `&#`-entities (ook niet in commentaar of strings); echte tekens als `…`, `’`, `›`, `é` en `—` wel. Getest in `tests/test-admin3-frame.php`.
- **Oude admin blijft werken tot Taak 8.** Tot dan is `includes/admin.php` geladen. Een functie die daar staat, mag je nergens anders definiëren (PHP geeft een fatal bij dubbele functies): verhuizen = eerst uitknippen.
- **Toegang en nonces:** capability `manage_options` voor alles; een eigen nonce per actie (`cm_<actie>`); de bulkactie van de lijsttabel gebruikt de nonce van WordPress zelf (`bulk-consents`).
- **Plan 1/2-afspraken gelden door:**
  - sanitizers zijn idempotent, omdat WordPress de sanitize-callback bij élke `update_option` aanroept;
  - `null` uit `options.php` wist niets;
  - de paginacache wordt geleegd door option-hooks (`includes/admin/settings.php`), dus **geen** losse `cm_purge_page_caches()`-aanroepen;
  - meldingen via een `cm_notice`-code of `cm_admin_flash()`; **nooit** een succesmelding als iets mislukte.
- **Formulieren nooit binnen een `<p>`** (ongeldige HTML; zie plan 2). Gebruik `<div>`.
- **Taal:** alle UI-teksten zijn Nederlands. Een bestaande tekst mag beter geformuleerd worden, maar niet inhoudelijk veranderen.
- **Tijd:**
  - `created_at` in de consent log is lokale tijd (`current_time('mysql')`); toon met `mysql2date()`.
  - `cm_auto_scan_last` staat als UTC; toon met `wp_date( $fmt, strtotime( $v . ' UTC' ) )`.
  - `wp_next_scheduled()` geeft een UTC-timestamp; toon met `wp_date()`.
  - De `date` in `cm_consent_changelog` is al opgemaakt; toon die zoals hij is.
- **Handmatige browsercontroles doet Ruud zelf.** Implementers slaan die stappen over en melden ze in het rapport.

## Review Focus

1. **Bulk verwijderen via het onderste keuzemenu of zonder selectie.** Het onderste menu (`action2`) moet net zo werken als het bovenste; zonder selectie volgt een melding en wordt er niets verwijderd. Getest in Taak 1.
2. **Een ongeldig, vreemd of leeg backupbestand wijzigt niets.** Er komt een foutmelding en geen enkele option verandert. Getest in Taak 5.
3. **Oude adressen blijven werken.** Bladwijzers en links in al verstuurde scanmails (`?page=cookiemelding…`) komen op de nieuwe pagina uit, niet op "Je hebt geen toestemming". Getest in Taak 9 (doorverwijzing) en Taak 8 (de scanmail linkt naar de nieuwe pagina).
4. **"Alles resetten" meldt een mislukt onderdeel** in plaats van "gelukt" (de les uit 2.4.4). Getest in Taak 5.
5. **CSV-export met alleen een einddatum of een omgedraaid bereik** geeft een zinnige selectie in plaats van niets of alles. Getest in Taak 2.

## Rulings bij het schrijven van dit plan

- **Doorverwijzing oude slugs op `admin_page_access_denied`, niet op `admin_init`** (spec §6.1). WordPress controleert de toegang tot `?page=` al in `wp-admin/menu.php`, vóór `admin_init`; een onbekende slug eindigt daar in `wp_die()`. De hook `admin_page_access_denied` vuurt vlak daarvoor. — kost bij fout: oude links tonen "geen toestemming".
- **Licentie via `admin-post.php` in plaats van AJAX.** Spec §5.3 noemt de licentie als toegestane AJAX, niet als verplichte. Een formulier met redirect en melding is WordPress-native en heeft geen JS nodig. — kost bij fout: een paginaverversing bij activeren.
- **"Alles resetten" wist ook de licentie lokaal**, net als in 2.4 (de bevestiging zegt dat). Daarnaast is er een losse knop "Licentie lokaal wissen" (spec §3.1: "plus de licentie lokaal wissen").
- **Import behoudt de huidige API-sleutel.** Een backup bevat geen sleutel; zonder deze uitzondering zou elke import de REST-koppeling stil intrekken.
- **De fallback-takken in `cm_sanitize_settings()` blijven** (html, svg, url). Ze zijn het vangnet als het veldregister niet geladen is (tests, en elke context zonder de paginabestanden). Schrappen levert niets op en maakt de sanitizer afhankelijk van de laadvolgorde.
- **`CM_Log_List_Table` in een eigen bestand** (spec §5.1 zet hem in `page-log.php`). `WP_List_Table` bestaat alleen als WordPress hem laadt; een bestand dat vroeg stopt als de klasse ontbreekt, voorkomt een fatal op de frontend, in de registry-test (die elk bestand in `includes/admin/` laadt) en in de andere tests.
- **De compliance-check toont geen percentage meer**, maar "X van de Y controles zijn in orde", en de 2.4-informatieblokken ("Ingebouwd in Cookiebaas") vervallen: het zijn geen controles (spec §3.1: "de bestaande checks").

---

## Bestandsoverzicht

| Bestand | Verantwoordelijkheid | Taak |
|---|---|---|
| `includes/admin/page-log.php` | Consent log: filters, zoeken, verwijderen, bewijs, CSV, bewaartermijn, opnieuw laten kiezen, leegmaken | 1, 2, 3 |
| `includes/admin/class-cm-log-list-table.php` | `CM_Log_List_Table extends WP_List_Table` | 1, 2 |
| `includes/admin/page-beheer.php` | Licentie, licentiemelding, backup, reset, API-sleutel, info | 4, 5, 6 |
| `includes/admin/page-overzicht.php` | Statusblokken, compliance-check, eenmalige melding na de update | 7 |
| `includes/admin/menu.php` | Menu-items Consent log en Beheer, `cm_admin_page_url()`, eigen schermen, topmenu "Cookiebaas", doorverwijzing oude slugs, preview-assets per tab | 1, 4, 9 |
| `includes/admin/actions.php` | Meldingscodes, extra velden in actieformulieren, `cm_admin_flash()` | 1, 3, 4, 5, 6 |
| `includes/admin/scan.php` (nieuw, verhuisd) | AJAX scan + cookiedatabase, kennisbank van de scan | 8 |
| `includes/consent.php` (nieuw, verhuisd) | Frontend-AJAX: consent loggen, geo-check | 8 |
| `includes/admin.php`, `assets/js/admin.js`, `assets/css/admin.css` | Verwijderd | 8 |
| `assets/js/admin-common.js` | Bevestiging bij links, afdrukknop | 1, 2 |
| `assets/css/admin-layout.css` | Afdrukweergave, kaartenraster Overzicht | 2, 7 |
| `includes/license.php` | Licentiemelding eruit (Taak 4), oude AJAX eruit (Taak 8) | 4, 8 |
| `includes/privacy.php`, `includes/admin/ajax.php`, `includes/admin/page-cookies.php`, `includes/admin/page-privacy.php`, `includes/admin/settings.php` | Kleine aanpassingen: oude AJAX weg, nonce-terugval weg, links naar Beheer | 4, 6, 8 |
| `cookiemelding.php` | Requires, oude scan-AJAX weg, scanmail-link, vlag voor de eenmalige melding, versie | 1, 4, 7, 8, 10 |
| `uninstall.php` | Nieuwe option `cm_show_admin3_notice` opruimen | 7 |
| `CHANGELOG.md` | 3.0.0 met "Waar staat wat?" | 10 |
| Tests | Nieuw: `test-admin3-log.php`, `test-admin3-beheer.php`, `test-admin3-overzicht.php`, `test-admin3-overstap.php`. Aangepast: `test-admin-fixes.php`, `test-admin3-{actions,registry,privacy,cookies}.php`, `test-cookie-scan.php`, `bootstrap.php`, `run.php`, `README.md` | alle |

---

### Task 1: Consent log › Registraties (lijst, filters, zoeken, verwijderen)

**Files:**
- Create: `includes/admin/page-log.php`
- Create: `includes/admin/class-cm-log-list-table.php`
- Modify: `includes/admin.php` (knip `cm_log_where()` eruit)
- Modify: `includes/admin/menu.php` (menu-item, load-hook, `cm_admin_page_url()`)
- Modify: `includes/admin/actions.php` (meldingscodes)
- Modify: `assets/js/admin-common.js` (bevestiging bij links)
- Modify: `cookiemelding.php` (require)
- Test: `tests/test-admin3-log.php` (nieuw), `tests/test-admin-fixes.php` (loggroep verhuist)

**Interfaces:**
- Consumes: `cm_admin_register_action( $action, $cb )`, `cm_admin_action_url( $action, $args )`, `cm_admin_current_tab()`, `cm_admin_page_tabs()`.
- Produces:
  - `cm_admin_page_url( $page, $tab = '', array $args = array() ): string` (menu.php)
  - `cm_tabs_log(): array` (tab `registraties`; Taak 3 voegt `bewaren` toe)
  - `cm_log_table(): string`, `cm_log_filters(): array`, `cm_log_method_label( $method ): string`, `cm_log_categories_text( array $item ): string`
  - `cm_log_where( $like, $filter ): array( $sql, $args )` (verhuisd, ongewijzigd)
  - `cm_log_counts(): array`, `cm_log_current_filter(): string`
  - `cm_log_valid_ids( $raw ): string[]`, `cm_log_delete( array $ids ): int|false`, `cm_log_bulk_request( array $req ): array( $action, $ids )`, `cm_log_handle_bulk(): void`
  - `cm_log_row_actions( $consent_id ): array( 'proof' => html, 'delete' => html )`
  - `cm_log_render_registraties(): void`
  - klasse `CM_Log_List_Table`
  - meldingscodes `log-deleted`, `log-none-selected`

- [ ] **Step 1: Schrijf de test `tests/test-admin3-log.php`**

```php
<?php
/**
 * Consent log (3.0) — registraties: filters, zoeken, verwijderen.
 *
 * Borgt: het filter werkt serverside en 'Akkoord' telt embed-accept mee;
 * alleen geldige consent-ID's worden verwijderd; de bulkactie werkt ook
 * vanuit het onderste keuzemenu; de lijsttabel laadt niet zonder WordPress.
 */

function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function add_query_arg( $a, $b = null, $c = null ) {
    if ( is_array( $a ) ) { $args = $a; $url = $b; } else { $args = array( $a => $b ); $url = $c; }
    foreach ( $args as $k => $v ) $url .= ( strpos( $url, '?' ) === false ? '?' : '&' ) . rawurlencode( $k ) . '=' . rawurlencode( $v );
    return $url;
}
function wp_nonce_url( $url, $action ) { return $url . ( strpos( $url, '?' ) === false ? '?' : '&' ) . '_wpnonce=nonce-' . $action; }

/** Neemt query's op; prepare() vult de placeholders zichtbaar in. */
class CM_Test_Wpdb {
    public $prefix  = 'wp_';
    public $queries = array();
    public $result  = 2;
    public function prepare( $sql, $args ) {
        foreach ( (array) $args as $a ) $sql = preg_replace( '/%[sd]/', is_int( $a ) ? (string) $a : "'" . addslashes( $a ) . "'", $sql, 1 );
        return $sql;
    }
    public function query( $sql ) { $this->queries[] = $sql; return $this->result; }
}
$GLOBALS['wpdb'] = new CM_Test_Wpdb();

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/admin/menu.php';
require CM_PLUGIN_ROOT . '/includes/admin/actions.php';
require CM_PLUGIN_ROOT . '/includes/admin/page-log.php';

$good = 'a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d';

cm_test_group( 'Filter en zoeken (serverside)' );
list( $sql, $args ) = cm_log_where( '', 'accept-all' );
cm_assert( 'akkoord telt embed-accept mee (zoals de statistiek)', $sql === 'WHERE method IN (%s,%s)' && $args === array( 'accept-all', 'embed-accept' ) );
list( $sql, $args ) = cm_log_where( '%abc%', 'reject-all' );
cm_assert( 'zoeken + filter combineren', $sql === 'WHERE consent_id LIKE %s AND method IN (%s)' && $args === array( '%abc%', 'reject-all' ) );
list( $sql, $args ) = cm_log_where( '', 'all' );
cm_assert( 'alles = geen WHERE', $sql === '' && $args === array() );
list( $sql, $args ) = cm_log_where( '', "x' OR 1=1" );
cm_assert( 'onbekend filter wordt genegeerd', $sql === '' && $args === array() );
$_GET = array( 'filter' => 'reject-all' );
cm_assert( 'bekend filter uit de URL', cm_log_current_filter() === 'reject-all' );
$_GET = array( 'filter' => 'bestaat-niet' );
cm_assert( 'onbekend filter uit de URL → alle', cm_log_current_filter() === 'all' );
$_GET = array();

cm_test_group( "Alleen geldige consent-ID's" );
cm_assert( 'geldige ID blijft, hoofdletters worden kleine letters', cm_log_valid_ids( array( strtoupper( $good ) ) ) === array( $good ) );
cm_assert( 'onzin, SQL en arrays vallen af', cm_log_valid_ids( array( "x' OR 1=1", array( $good ), '', 'a1b2' ) ) === array() );
cm_assert( 'dubbele ID telt één keer', cm_log_valid_ids( array( $good, $good ) ) === array( $good ) );
cm_assert( 'één ID als string werkt ook', cm_log_valid_ids( $good ) === array( $good ) );

cm_test_group( 'Bulkactie, boven of onder (Review Focus 1)' );
list( $a, $ids ) = cm_log_bulk_request( array( 'action' => 'cm_delete', 'consent' => array( $good, 'fout' ) ) );
cm_assert( 'bovenste keuzemenu', $a === 'cm_delete' && $ids === array( $good ) );
list( $a, $ids ) = cm_log_bulk_request( array( 'action' => '-1', 'action2' => 'cm_delete', 'consent' => array( $good ) ) );
cm_assert( 'onderste keuzemenu telt ook', $a === 'cm_delete' && $ids === array( $good ) );
list( $a, $ids ) = cm_log_bulk_request( array( 'action' => 'cm_delete' ) );
cm_assert( 'zonder selectie: actie wel, geen ID\'s', $a === 'cm_delete' && $ids === array() );
list( $a, $ids ) = cm_log_bulk_request( array() );
cm_assert( 'geen actie → -1', $a === '-1' && $ids === array() );

cm_test_group( 'Verwijderen' );
$n = cm_log_delete( array( $good, 'fout' ) );
cm_assert( 'één DELETE op de geldige ID', $n === 2 && strpos( end( $wpdb->queries ), "DELETE FROM `wp_cm_consent_log` WHERE consent_id IN ('" . $good . "')" ) === 0 );
$before = count( $wpdb->queries );
cm_assert( 'zonder geldige ID: geen query, 0', cm_log_delete( array( 'fout' ) ) === 0 && count( $wpdb->queries ) === $before );
$wpdb->result = false;
cm_assert( 'databasefout → false', cm_log_delete( array( $good ) ) === false );
$wpdb->result = 2;

cm_test_group( 'Rij-acties' );
$ra = cm_log_row_actions( $good );
cm_assert( 'Bewijs opent het detailscherm', strpos( $ra['proof'], 'page=cookiebaas-log' ) !== false && strpos( $ra['proof'], 'consent=' . $good ) !== false );
cm_assert( 'Verwijderen via admin-post, met eigen nonce en bevestiging', strpos( $ra['delete'], 'action=cm_delete_consent' ) !== false && strpos( $ra['delete'], 'nonce-cm_delete_consent' ) !== false && strpos( $ra['delete'], 'data-cm-confirm=' ) !== false );

cm_test_group( 'Labels' );
cm_assert( 'bekende methodes', cm_log_method_label( 'embed-accept' ) === 'Geaccepteerd via embed' && cm_log_method_label( 'pageload' ) === 'Terugkerend bezoek' );
cm_assert( 'onbekende methode blijft zichtbaar', cm_log_method_label( 'iets' ) === 'iets' );
cm_assert( 'toestemming per categorie', cm_log_categories_text( array( 'analytics' => '1', 'marketing' => '0' ) ) === 'Analytisch: ja · Marketing: nee' );

cm_test_group( 'Pagina en menu' );
cm_assert( 'menu-item Consent log', isset( cm_admin_pages()['cookiebaas-log'] ) );
cm_assert( 'tab Registraties', isset( cm_tabs_log()['registraties'] ) );
cm_assert( 'paginalink met tab en extra argumenten', cm_admin_page_url( 'cookiebaas-log', 'registraties', array( 'filter' => 'custom' ) ) === 'https://example.test/wp-admin/admin.php?page=cookiebaas-log&tab=registraties&filter=custom' );
require CM_PLUGIN_ROOT . '/includes/admin/class-cm-log-list-table.php';
cm_assert( 'lijsttabel laadt niet zonder WP_List_Table (geen fatal in tests of op de frontend)', ! class_exists( 'CM_Log_List_Table', false ) );

exit( cm_test_summary() );
```

- [ ] **Step 2: Draai de test en zie hem falen**

Run: `php tests/test-admin3-log.php`
Expected: fatal "Failed opening required …/includes/admin/page-log.php".

- [ ] **Step 3: Knip `cm_log_where()` uit de oude admin**

Knip in `includes/admin.php` het blok vanaf de regel `/**` boven ` * WHERE-clausule voor de consent log: zoeken op consent-ID en filteren op` tot en met de sluitende `}` van `function cm_log_where( $like, $filter )` eruit. Dat blok wordt in Step 4 **ongewijzigd** geplakt. De oude `cm_ajax_get_log()` roept de functie tijdens een request aan; dan is `page-log.php` al geladen.

Haal in `tests/test-admin-fixes.php` de groep `cm_test_group( 'Consent log: filter serverside' );` met de vier asserties eronder weg. Die staan nu in `tests/test-admin3-log.php`.

- [ ] **Step 4: Maak `includes/admin/page-log.php`**

Vervang de commentaarregel `/* HIER het uit includes/admin.php geknipte blok … */` door het blok dat je in Step 3 hebt geknipt (docblock plus `cm_log_where()`), zonder iets te wijzigen.

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   CONSENT LOG — registraties (WP_List_Table) en bewaren/opnieuw vragen.
   De tabel {prefix}cm_consent_log blijft ongewijzigd.
================================================================ */

function cm_tabs_log() {
    return array(
        'registraties' => array( 'label' => 'Registraties', 'render' => 'cm_log_render_registraties' ),
    );
}

function cm_log_table() {
    global $wpdb;
    return $wpdb->prefix . 'cm_consent_log';
}

/** Filters boven de lijst: sleutel → label. */
function cm_log_filters() {
    return array( 'all' => 'Alle', 'accept-all' => 'Akkoord', 'reject-all' => 'Geweigerd', 'custom' => 'Aangepast' );
}

/** Opgeslagen methode → leesbare keuze (lijst, bewijs en CSV). */
function cm_log_method_label( $method ) {
    $labels = array(
        'accept-all'   => 'Geaccepteerd',
        'reject-all'   => 'Geweigerd',
        'custom'       => 'Aangepast',
        'embed-accept' => 'Geaccepteerd via embed',
        'pageload'     => 'Terugkerend bezoek',
    );
    return isset( $labels[ $method ] ) ? $labels[ $method ] : (string) $method;
}

function cm_log_categories_text( array $item ) {
    return 'Analytisch: ' . ( ! empty( $item['analytics'] ) ? 'ja' : 'nee' ) . ' · Marketing: ' . ( ! empty( $item['marketing'] ) ? 'ja' : 'nee' );
}

/* HIER het uit includes/admin.php geknipte blok met cm_log_where() plakken, ongewijzigd. */

/** Het filter uit de URL, alleen als het bestaat. */
function cm_log_current_filter() {
    $f = isset( $_GET['filter'] ) ? sanitize_key( wp_unslash( $_GET['filter'] ) ) : 'all';
    return array_key_exists( $f, cm_log_filters() ) ? $f : 'all';
}

/** Aantal registraties per filter, voor de links boven de lijst. */
function cm_log_counts() {
    global $wpdb;
    $table  = cm_log_table();
    $counts = array();
    // ponytail: één COUNT per filter; één GROUP BY-query als de log erg groot wordt
    foreach ( array_keys( cm_log_filters() ) as $filter ) {
        list( $where, $args ) = cm_log_where( '', $filter );
        $sql = "SELECT COUNT(*) FROM `{$table}` {$where}";
        $counts[ $filter ] = (int) $wpdb->get_var( $args ? $wpdb->prepare( $sql, $args ) : $sql );
    }
    return $counts;
}

/** Consent-ID's uit een verzoek: alleen geldige UUID's, in kleine letters, zonder dubbelingen. */
function cm_log_valid_ids( $raw ) {
    $ids = array();
    foreach ( (array) $raw as $id ) {
        if ( ! is_scalar( $id ) ) continue;
        $id = strtolower( trim( (string) $id ) );
        if ( preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $id ) ) $ids[ $id ] = $id;
    }
    return array_values( $ids );
}

/** Verwijder registraties op consent-ID. Het aantal verwijderde rijen, of false bij een databasefout. */
function cm_log_delete( array $ids ) {
    global $wpdb;
    $ids = cm_log_valid_ids( $ids );
    if ( ! $ids ) return 0;
    $table = cm_log_table();
    $in    = implode( ',', array_fill( 0, count( $ids ), '%s' ) );
    return $wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE consent_id IN ({$in})", $ids ) );
}

/** Bulkactie uit de lijst: het bovenste of het onderste keuzemenu, plus de aangevinkte ID's. */
function cm_log_bulk_request( array $req ) {
    $action = isset( $req['action'] ) && is_string( $req['action'] ) ? $req['action'] : '-1';
    if ( $action === '-1' || $action === '' ) {
        $action = isset( $req['action2'] ) && is_string( $req['action2'] ) ? $req['action2'] : '-1';
    }
    return array( $action, cm_log_valid_ids( isset( $req['consent'] ) ? $req['consent'] : array() ) );
}

/** Bulk verwijderen. Hangt aan de load-hook van de pagina, dus vóór er output is. */
function cm_log_handle_bulk() {
    list( $action, $ids ) = cm_log_bulk_request( wp_unslash( $_REQUEST ) );
    if ( $action !== 'cm_delete' ) return;
    check_admin_referer( 'bulk-consents' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Geen toegang.', '', array( 'response' => 403 ) );
    if ( ! $ids ) {
        $code = 'log-none-selected';
    } else {
        $code = cm_log_delete( $ids ) === false ? 'action-failed' : 'log-deleted';
    }
    wp_safe_redirect( cm_admin_page_url( 'cookiebaas-log', 'registraties', array( 'cm_notice' => $code ) ) );
    exit;
}

/** Rij-acties: Bewijs | Verwijderen. */
function cm_log_row_actions( $consent_id ) {
    $proof  = cm_admin_page_url( 'cookiebaas-log', 'registraties', array( 'consent' => $consent_id ) );
    $delete = cm_admin_action_url( 'delete_consent', array( 'consent' => $consent_id ) );
    return array(
        'proof'  => '<a href="' . esc_url( $proof ) . '">Bewijs</a>',
        'delete' => '<a href="' . esc_url( $delete ) . '" class="submitdelete" data-cm-confirm="' . esc_attr( 'Deze registratie definitief verwijderen?' ) . '">Verwijderen</a>',
    );
}

function cm_log_render_registraties() {
    if ( ! class_exists( 'WP_List_Table' ) ) require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
    require_once __DIR__ . '/class-cm-log-list-table.php';
    $table = new CM_Log_List_Table();
    $table->prepare_items();
    $table->views();
    echo '<form method="get">';
    echo '<input type="hidden" name="page" value="cookiebaas-log"><input type="hidden" name="tab" value="registraties">';
    $filter = cm_log_current_filter();
    if ( $filter !== 'all' ) echo '<input type="hidden" name="filter" value="' . esc_attr( $filter ) . '">';
    $table->search_box( 'Zoeken op consent-ID', 'cm-log' );
    $table->display();
    echo '</form>';
}

if ( function_exists( 'cm_admin_register_action' ) ) {
    cm_admin_register_action( 'delete_consent', function () {
        $n = cm_log_delete( array( isset( $_GET['consent'] ) ? wp_unslash( $_GET['consent'] ) : '' ) );
        return $n ? 'log-deleted' : 'action-failed';
    } );
}
```

- [ ] **Step 5: Maak `includes/admin/class-cm-log-list-table.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Alleen als WordPress' lijsttabel geladen is (cm_log_render_registraties doet dat eerst).
// Zo geeft dit bestand geen fatal op de frontend of in de tests.
if ( ! class_exists( 'WP_List_Table' ) ) return;

class CM_Log_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( array( 'singular' => 'consent', 'plural' => 'consents', 'ajax' => false ) );
    }

    public function get_columns() {
        return array(
            'cb'         => '<input type="checkbox">',
            'consent_id' => 'Consent-ID',
            'method'     => 'Keuze',
            'categories' => 'Toestemming',
            'url'        => 'Pagina',
            'created_at' => 'Datum',
        );
    }

    protected function get_bulk_actions() {
        return array( 'cm_delete' => 'Verwijderen' );
    }

    protected function get_views() {
        $current = cm_log_current_filter();
        $counts  = cm_log_counts();
        $views   = array();
        foreach ( cm_log_filters() as $key => $label ) {
            $url  = cm_admin_page_url( 'cookiebaas-log', 'registraties', array( 'filter' => $key ) );
            $attr = $key === $current ? ' class="current" aria-current="page"' : '';
            $views[ $key ] = '<a href="' . esc_url( $url ) . '"' . $attr . '>' . esc_html( $label ) . ' <span class="count">(' . esc_html( number_format_i18n( $counts[ $key ] ) ) . ')</span></a>';
        }
        return $views;
    }

    public function prepare_items() {
        global $wpdb;
        $per    = 25;
        $table  = cm_log_table();
        $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        list( $where, $args ) = cm_log_where( $search !== '' ? '%' . $wpdb->esc_like( $search ) . '%' : '', cm_log_current_filter() );
        $count = "SELECT COUNT(*) FROM `{$table}` {$where}";
        $total = (int) $wpdb->get_var( $args ? $wpdb->prepare( $count, $args ) : $count );
        $rows  = $wpdb->get_results( $wpdb->prepare(
            "SELECT consent_id, analytics, marketing, method, url, created_at FROM `{$table}` {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            array_merge( $args, array( $per, ( $this->get_pagenum() - 1 ) * $per ) )
        ), ARRAY_A );
        $this->items = $rows ? $rows : array();
        $this->set_pagination_args( array( 'total_items' => $total, 'per_page' => $per ) );
        $this->_column_headers = array( $this->get_columns(), array(), array(), 'consent_id' );
    }

    public function no_items() {
        echo 'Er zijn geen registraties gevonden.';
    }

    protected function column_cb( $item ) {
        return '<input type="checkbox" name="consent[]" value="' . esc_attr( $item['consent_id'] ) . '">';
    }

    protected function column_consent_id( $item ) {
        return '<code>' . esc_html( $item['consent_id'] ) . '</code>';
    }

    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'method':     return esc_html( cm_log_method_label( $item['method'] ) );
            case 'categories': return esc_html( cm_log_categories_text( $item ) );
            case 'url':        return esc_html( $item['url'] );
            case 'created_at': return esc_html( mysql2date( 'j F Y, H:i', $item['created_at'] ) );
        }
        return '';
    }

    protected function handle_row_actions( $item, $column_name, $primary ) {
        return $column_name === $primary ? $this->row_actions( cm_log_row_actions( $item['consent_id'] ) ) : '';
    }
}
```

- [ ] **Step 6: Menu, meldingen, JS en require**

In `includes/admin/menu.php`:

1. Voeg `'cookiebaas-log' => 'Consent log',` toe als laatste regel van de array in `cm_admin_pages()`.
2. Vervang de `foreach` in `cm_admin3_register_menu()` door:

```php
    foreach ( cm_admin_pages() as $slug => $title ) {
        $hook = add_submenu_page(
            'cookiebaas', $title . ' — Cookiebaas', $title, 'manage_options', $slug, 'cm_admin_render_page'
        );
        $GLOBALS['cm_admin_hooks'][] = $hook;
        // Bulkacties van de lijsttabel verwerken vóór er output is
        if ( $slug === 'cookiebaas-log' && function_exists( 'cm_log_handle_bulk' ) ) add_action( 'load-' . $hook, 'cm_log_handle_bulk' );
    }
```

3. Voeg direct onder `cm_admin_current_tab()` toe:

```php
/** Link naar een pagina (en tab) van de admin, met eventueel extra argumenten. */
function cm_admin_page_url( $page, $tab = '', array $args = array() ) {
    $query = array( 'page' => $page );
    if ( $tab !== '' ) $query['tab'] = $tab;
    return add_query_arg( array_merge( $query, $args ), admin_url( 'admin.php' ) );
}
```

In `includes/admin/actions.php`: voeg deze twee regels toe aan de array in `cm_admin_notice_messages()`:

```php
        'log-deleted'         => array( 'success', 'De geselecteerde registraties zijn verwijderd.' ),
        'log-none-selected'   => array( 'info',    'Er is niets geselecteerd. Vink eerst de registraties aan die u wilt verwijderen.' ),
```

In `assets/js/admin-common.js`: voeg direct onder het blok `/* ---- Bevestigen bij destructieve acties ---- */` (de `submit`-listener) toe:

```js
  /* Ook bij destructieve links, zoals "Verwijderen" in een tabelrij */
  document.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('a[data-cm-confirm]') : null;
    if (a && !window.confirm(a.getAttribute('data-cm-confirm'))) e.preventDefault();
  }, true);
```

In `cookiemelding.php`: voeg na `require_once CM_PLUGIN_DIR . 'includes/admin/page-overzicht.php';` toe:

```php
require_once CM_PLUGIN_DIR . 'includes/admin/page-log.php';
```

- [ ] **Step 7: Draai de tests**

Run: `php tests/test-admin3-log.php && php tests/test-admin-fixes.php && php tests/test-admin3-registry.php && php tests/test-admin3-frame.php`
Expected: alles PASS. De registry-test laadt `class-cm-log-list-table.php` via zijn glob; dat bestand stopt meteen, zonder fatal.

- [ ] **Step 8: README, volledige suite, commit**

Voeg in `tests/README.md` in de tabel, na de regel van `test-admin3-privacy.php`, toe:

```
| `test-admin3-log.php` | Consent log: serverside filter en zoeken, alleen geldige consent-ID's verwijderen, bulkactie uit het bovenste én onderste keuzemenu, rij-acties, labels, lijsttabel laadt niet zonder WordPress. |
```

Run: `php tests/run.php`
Expected: alles groen.

```bash
git add includes/admin/page-log.php includes/admin/class-cm-log-list-table.php includes/admin.php includes/admin/menu.php includes/admin/actions.php assets/js/admin-common.js cookiemelding.php tests/test-admin3-log.php tests/test-admin-fixes.php tests/README.md
git commit -m "feat(admin3): consent log als WordPress-lijst met filters, zoeken en verwijderen

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 2: Consent log — bewijs en CSV-export

**Files:**
- Modify: `includes/admin/page-log.php`
- Modify: `includes/admin/class-cm-log-list-table.php` (datumbereik in `tablenav`)
- Modify: `assets/js/admin-common.js` (afdrukknop)
- Modify: `assets/css/admin-layout.css` (afdrukweergave)
- Test: `tests/test-admin3-log.php`

**Interfaces:**
- Consumes: `cm_log_valid_ids()`, `cm_log_method_label()`, `cm_log_table()`, `cm_admin_page_url()`, `cm_admin_send_csv( $filename, $rows )`, `cm_admin_register_action()`.
- Produces:
  - `cm_log_get( $consent_id ): array|null`
  - `cm_log_proof_rows( array $row ): array` (lijst van `array( label, waarde )`)
  - `cm_log_render_proof( $consent_id ): void`
  - `cm_log_date_range( $from, $to ): array( $from_dt|null, $to_dt|null )`
  - `cm_log_export_where( $from_dt, $to_dt ): array( $sql, $args )`
  - `cm_log_csv_rows( array $rows ): array`
  - actie `export_log` (GET, met `from` en `to`)

- [ ] **Step 1: Breid de test uit**

Voeg in `tests/test-admin3-log.php` bovenaan, bij de andere stubs (vóór `require __DIR__ . '/bootstrap.php';`), toe:

```php
function mysql2date( $format, $date ) { return 'fmt:' . $date; }
```

Voeg in de klasse `CM_Test_Wpdb` deze methode toe (voor `cm_log_get`):

```php
    public function get_row( $sql, $output = null ) { $this->queries[] = $sql; return null; }
```

Voeg vóór `exit( cm_test_summary() );` toe:

```php
cm_test_group( 'CSV-export: datumbereik (Review Focus 5)' );
cm_assert( 'van en tot', cm_log_date_range( '2026-01-01', '2026-01-31' ) === array( '2026-01-01 00:00:00', '2026-01-31 23:59:59' ) );
cm_assert( 'alleen tot: alles tot en met die dag', cm_log_date_range( '', '2026-01-31' ) === array( null, '2026-01-31 23:59:59' ) );
cm_assert( 'omgedraaid bereik wordt rechtgezet', cm_log_date_range( '2026-02-01', '2026-01-01' ) === array( '2026-01-01 00:00:00', '2026-02-01 23:59:59' ) );
cm_assert( 'onzin telt als leeg', cm_log_date_range( '31-01-2026', array() ) === array( null, null ) );
list( $w, $args ) = cm_log_export_where( '2026-01-01 00:00:00', null );
cm_assert( 'terugkerende bezoeken nooit in de export', $w === "WHERE method != 'pageload' AND created_at >= %s" && $args === array( '2026-01-01 00:00:00' ) );
list( $w, $args ) = cm_log_export_where( null, '2026-01-31 23:59:59' );
cm_assert( 'alleen een einddatum', $w === "WHERE method != 'pageload' AND created_at <= %s" && $args === array( '2026-01-31 23:59:59' ) );
list( $w, $args ) = cm_log_export_where( null, null );
cm_assert( 'zonder bereik alleen het pageload-filter', $w === "WHERE method != 'pageload'" && $args === array() );

cm_test_group( 'CSV-export: rijen' );
$rows = cm_log_csv_rows( array( array( 'consent_id' => '', 'method' => 'accept-all', 'analytics' => '1', 'marketing' => '0', 'url' => 'https://x.test/', 'plugin_version' => '2.4.6', 'created_at' => '2026-01-02 10:00:00' ) ) );
cm_assert( 'kopregel zoals in 2.4', $rows[0] === array( 'Consent ID', 'Consent Status', 'Analytisch', 'Marketing', 'Pagina', 'Plugin versie', 'Datum/Tijd' ) );
cm_assert( 'rij met label, Ja/Nee en een streepje voor een lege ID', $rows[1] === array( '—', 'Geaccepteerd', 'Ja', 'Nee', 'https://x.test/', '2.4.6', '2026-01-02 10:00:00' ) );

cm_test_group( 'Bewijs' );
$proof = cm_log_proof_rows( array( 'consent_id' => $good, 'method' => 'custom', 'analytics' => '0', 'marketing' => '1', 'url' => 'https://x.test/p', 'user_agent' => 'Firefox (Desktop)', 'ip_hash' => 'abc', 'session_id' => 's1', 'config_hash' => 'h1', 'plugin_version' => '2.4.6', 'created_at' => '2026-01-02 10:00:00' ) );
$labels = array_map( function ( $r ) { return $r[0]; }, $proof );
cm_assert( 'alle opgeslagen velden staan erin', $labels === array( 'Consent-ID', 'Datum en tijd', 'Keuze', 'Analytische cookies', 'Marketingcookies', 'Pagina', 'Browser en apparaat', 'IP-adres (gehasht)', 'Sessie', 'Configuratie-hash', 'Pluginversie' ) );
cm_assert( 'keuze als label, categorieën als Ja/Nee', $proof[2][1] === 'Aangepast' && $proof[3][1] === 'Nee' && $proof[4][1] === 'Ja' );
cm_assert( 'datum via mysql2date', $proof[1][1] === 'fmt:2026-01-02 10:00:00' );
cm_assert( 'ontbrekend veld → leeg, geen notice', cm_log_proof_rows( array() )[5][1] === '' );
$before = count( $wpdb->queries );
cm_assert( 'ongeldige ID → geen query, geen registratie', cm_log_get( "x' OR 1=1" ) === null && count( $wpdb->queries ) === $before );
cm_assert( 'geldige ID die niet bestaat → null', cm_log_get( $good ) === null && count( $wpdb->queries ) === $before + 1 );
```

- [ ] **Step 2: Draai de test en zie hem falen**

Run: `php tests/test-admin3-log.php`
Expected: fatal "Call to undefined function cm_log_date_range()".

- [ ] **Step 3: Voeg bewijs en export toe aan `includes/admin/page-log.php`**

Voeg deze functies toe vóór `function cm_log_render_registraties()`:

```php
/** Eén registratie op consent-ID, of null. */
function cm_log_get( $consent_id ) {
    global $wpdb;
    $ids = cm_log_valid_ids( array( $consent_id ) );
    if ( ! $ids ) return null;
    $table = cm_log_table();
    $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE consent_id = %s LIMIT 1", $ids[0] ), ARRAY_A );
    return is_array( $row ) ? $row : null;
}

/** Alle opgeslagen velden van een registratie als array( label, waarde ), voor het bewijs. */
function cm_log_proof_rows( array $row ) {
    $get = function ( $key ) use ( $row ) { return isset( $row[ $key ] ) ? (string) $row[ $key ] : ''; };
    $yes = function ( $key ) use ( $get ) { return $get( $key ) === '1' ? 'Ja' : 'Nee'; };
    return array(
        array( 'Consent-ID', $get( 'consent_id' ) ),
        array( 'Datum en tijd', $get( 'created_at' ) !== '' ? mysql2date( 'j F Y, H:i:s', $get( 'created_at' ) ) : '' ),
        array( 'Keuze', cm_log_method_label( $get( 'method' ) ) ),
        array( 'Analytische cookies', $yes( 'analytics' ) ),
        array( 'Marketingcookies', $yes( 'marketing' ) ),
        array( 'Pagina', $get( 'url' ) ),
        array( 'Browser en apparaat', $get( 'user_agent' ) ),
        array( 'IP-adres (gehasht)', $get( 'ip_hash' ) ),
        array( 'Sessie', $get( 'session_id' ) ),
        array( 'Configuratie-hash', $get( 'config_hash' ) ),
        array( 'Pluginversie', $get( 'plugin_version' ) ),
    );
}

function cm_log_render_proof( $consent_id ) {
    echo '<p class="cm-no-print"><a href="' . esc_url( cm_admin_page_url( 'cookiebaas-log', 'registraties' ) ) . '">Terug naar de registraties</a></p>';
    $row = cm_log_get( $consent_id );
    if ( ! $row ) {
        echo '<div class="notice notice-error inline"><p>Deze registratie bestaat niet (meer).</p></div>';
        return;
    }
    echo '<h2>Bewijs van toestemming</h2>';
    echo '<p>Dit is alles wat Cookiebaas over deze keuze heeft opgeslagen. Het IP-adres is alleen gehasht bewaard en niet terug te rekenen.</p>';
    echo '<table class="form-table" role="presentation"><tbody>';
    foreach ( cm_log_proof_rows( $row ) as $r ) {
        echo '<tr><th scope="row">' . esc_html( $r[0] ) . '</th><td>' . ( $r[1] !== '' ? esc_html( $r[1] ) : '—' ) . '</td></tr>';
    }
    echo '</tbody></table>';
    echo '<p class="cm-no-print"><button type="button" class="button" data-cm-print>Afdrukken of opslaan als pdf</button></p>';
}

/** 'JJJJ-MM-DD' van/tot → datetime-grenzen (null = open). Een omgedraaid bereik wordt rechtgezet. */
function cm_log_date_range( $from, $to ) {
    $valid = function ( $d ) { return is_string( $d ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ? $d : ''; };
    $from  = $valid( $from );
    $to    = $valid( $to );
    if ( $from !== '' && $to !== '' && $from > $to ) {
        $swap = $from; $from = $to; $to = $swap;
    }
    return array( $from !== '' ? $from . ' 00:00:00' : null, $to !== '' ? $to . ' 23:59:59' : null );
}

/** WHERE voor de export: nooit terugkerende bezoeken, optioneel binnen het bereik. */
function cm_log_export_where( $from_dt, $to_dt ) {
    $where = array( "method != 'pageload'" );
    $args  = array();
    if ( $from_dt ) { $where[] = 'created_at >= %s'; $args[] = $from_dt; }
    if ( $to_dt )   { $where[] = 'created_at <= %s'; $args[] = $to_dt; }
    return array( 'WHERE ' . implode( ' AND ', $where ), $args );
}

/** Rijen voor de CSV, met dezelfde kolommen als in 2.4. */
function cm_log_csv_rows( array $rows ) {
    $out = array( array( 'Consent ID', 'Consent Status', 'Analytisch', 'Marketing', 'Pagina', 'Plugin versie', 'Datum/Tijd' ) );
    foreach ( $rows as $r ) {
        $out[] = array(
            ! empty( $r['consent_id'] ) ? $r['consent_id'] : '—',
            cm_log_method_label( isset( $r['method'] ) ? $r['method'] : '' ),
            ! empty( $r['analytics'] ) ? 'Ja' : 'Nee',
            ! empty( $r['marketing'] ) ? 'Ja' : 'Nee',
            isset( $r['url'] ) ? $r['url'] : '',
            isset( $r['plugin_version'] ) ? $r['plugin_version'] : '',
            isset( $r['created_at'] ) ? $r['created_at'] : '',
        );
    }
    return $out;
}
```

Zet bovenaan in `cm_log_render_registraties()` (vóór `if ( ! class_exists( 'WP_List_Table' ) )`):

```php
    if ( isset( $_GET['consent'] ) && is_string( $_GET['consent'] ) ) {
        cm_log_render_proof( sanitize_text_field( wp_unslash( $_GET['consent'] ) ) );
        return;
    }
```

Zet onderaan in `cm_log_render_registraties()`, na `echo '</form>';`, het exportformulier. De datumvelden staan in de tablenav van de lijst en horen via `form="cm-log-export"` bij dit formulier, want formulieren mogen niet genest worden:

```php
    echo '<form id="cm-log-export" method="get" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    echo '<input type="hidden" name="action" value="cm_export_log">';
    wp_nonce_field( 'cm_export_log', '_wpnonce', false );
    echo '</form>';
```

Voeg in het blok `if ( function_exists( 'cm_admin_register_action' ) ) {` onderaan toe:

```php
    cm_admin_register_action( 'export_log', function () {
        global $wpdb;
        list( $from, $to ) = cm_log_date_range(
            isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '',
            isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : ''
        );
        list( $where, $args ) = cm_log_export_where( $from, $to );
        $table = cm_log_table();
        $sql   = "SELECT consent_id, method, analytics, marketing, url, plugin_version, created_at FROM `{$table}` {$where} ORDER BY created_at DESC";
        $rows  = $wpdb->get_results( $args ? $wpdb->prepare( $sql, $args ) : $sql, ARRAY_A );
        cm_admin_send_csv( 'consent-log-' . wp_date( 'Y-m-d' ) . '.csv', cm_log_csv_rows( $rows ? $rows : array() ) );
    } );
```

- [ ] **Step 4: Datumbereik in de lijst, afdrukken**

Voeg in `includes/admin/class-cm-log-list-table.php` in de klasse, na `get_bulk_actions()`, toe:

```php
    protected function extra_tablenav( $which ) {
        if ( $which !== 'top' ) return;
        echo '<div class="alignleft actions">';
        echo '<label for="cm-log-from">Van</label> <input type="date" id="cm-log-from" name="from" form="cm-log-export"> ';
        echo '<label for="cm-log-to">tot en met</label> <input type="date" id="cm-log-to" name="to" form="cm-log-export"> ';
        echo '<button type="submit" class="button" form="cm-log-export">CSV exporteren</button>';
        echo '</div>';
    }
```

Voeg in `assets/js/admin-common.js` na het blok voor bevestiging bij links (Taak 1) toe:

```js
  /* Afdrukken (bewijs van toestemming) */
  document.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('[data-cm-print]')) window.print();
  });
```

Voeg onderaan `assets/css/admin-layout.css` toe:

```css
/* Bewijs van toestemming afdrukken: alleen de inhoud */
@media print {
  #adminmenumain, #wpadminbar, #wpfooter, .nav-tab-wrapper, .notice, .cm-no-print { display: none !important; }
  #wpcontent, #wpbody-content { margin-left: 0; padding-left: 0; }
}
```

- [ ] **Step 5: Draai de tests**

Run: `php tests/test-admin3-log.php && php tests/test-admin3-frame.php`
Expected: alles PASS. De broncheck dekt ook de nieuwe CSS en JS.

- [ ] **Step 6: Handmatige check (Ruud)**

Op Brinckers, onder Consent log › Registraties:
- "Bewijs" toont alle velden;
- "Afdrukken of opslaan als pdf" drukt alleen de inhoud af;
- CSV-export met en zonder datums werkt;
- verwijderen per rij en in bulk (via het bovenste en het onderste menu) werkt.

- [ ] **Step 7: README, volledige suite, commit**

Vervang in `tests/README.md` de omschrijving van `test-admin3-log.php` door:

```
| `test-admin3-log.php` | Consent log: serverside filter en zoeken, alleen geldige consent-ID's verwijderen, bulkactie uit het bovenste én onderste keuzemenu, rij-acties, bewijs met alle velden, CSV-export (datumbereik, geen terugkerende bezoeken, kolommen zoals in 2.4), lijsttabel laadt niet zonder WordPress. |
```

Run: `php tests/run.php`
Expected: alles groen.

```bash
git add includes/admin/page-log.php includes/admin/class-cm-log-list-table.php assets/js/admin-common.js assets/css/admin-layout.css tests/test-admin3-log.php tests/README.md
git commit -m "feat(admin3): bewijs per registratie en CSV-export met datumbereik

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3: Consent log › Bewaren en opnieuw vragen

**Files:**
- Modify: `includes/admin/page-log.php`
- Modify: `includes/admin/actions.php` (extra velden in actieformulieren, meldingscodes)
- Test: `tests/test-admin3-log.php`, `tests/test-admin3-actions.php`, `tests/test-admin3-registry.php`

**Interfaces:**
- Consumes: `cm_field()`, `cm_admin_field_index()`, `cm_admin_field_options()`, `cm_admin_action_form()`, `cm_log_table()`.
- Produces:
  - `cm_admin_action_form( $action, $label, array $args = array(), $confirm = '', $class = 'button', $fields = '' )`: nieuwe laatste parameter met extra (al ge-escapete) velden vóór de knop
  - `cm_tab_log_bewaren(): array`
  - `cm_log_retention_status_text( $months, $next ): string`, `cm_render_log_retention_status(): void`
  - `cm_bump_consent_version( $reason = '' ): int`
  - `cm_render_consent_changelog( $log ): void`, `cm_render_log_bewaren_tools(): void`
  - `cm_log_clear(): bool`
  - acties `bump_consent_version` (POST `reason`) en `clear_log`
  - meldingscodes `consent-version-bumped`, `log-cleared`

- [ ] **Step 1: Breid de tests uit**

Voeg in `tests/test-admin3-log.php` bovenaan, bij de stubs, toe:

```php
function sanitize_text_field( $s ) { return is_scalar( $s ) ? trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) ) : ''; }
function date_i18n( $format, $ts = null ) { return 'datum'; }
function wp_date( $format, $ts = null ) { return 'wpdate:' . $ts; }
```

Voeg na `require CM_PLUGIN_ROOT . '/includes/admin/actions.php';` toe:

```php
require CM_PLUGIN_ROOT . '/includes/admin/fields.php';
```

Voeg vóór `exit( cm_test_summary() );` toe:

```php
cm_test_group( 'Iedereen opnieuw laten kiezen' );
update_option( 'cm_consent_version', 4 );
update_option( 'cm_consent_changelog', array() );
cm_assert( 'versie gaat één omhoog', cm_bump_consent_version( '<b>Nieuwe dienst</b>' ) === 5 && (int) get_option( 'cm_consent_version' ) === 5 );
$log = get_option( 'cm_consent_changelog' );
cm_assert( 'reden, versie en datum in de geschiedenis, zonder HTML', count( $log ) === 1 && $log[0]['version'] === 5 && $log[0]['reason'] === 'Nieuwe dienst' && $log[0]['date'] === 'datum' );
update_option( 'cm_consent_changelog', array_fill( 0, 50, array( 'date' => 'x', 'version' => 1, 'reason' => '' ) ) );
cm_bump_consent_version();
cm_assert( 'maximaal 50 regels, de nieuwste blijft', count( get_option( 'cm_consent_changelog' ) ) === 50 && end( $GLOBALS['cm_test_options']['cm_consent_changelog'] )['version'] === 6 );

cm_test_group( 'Versiegeschiedenis' );
ob_start(); cm_render_consent_changelog( array( array( 'date' => '1 jan', 'version' => 2, 'reason' => 'A' ), array( 'date' => '2 jan', 'version' => 3, 'reason' => '<script>' ) ) ); $h = ob_get_clean();
cm_assert( 'nieuwste bovenaan', strpos( $h, '<td>3</td>' ) < strpos( $h, '<td>2</td>' ) );
cm_assert( 'reden ge-escaped', strpos( $h, '<script>' ) === false && strpos( $h, '&lt;script&gt;' ) !== false );
ob_start(); cm_render_consent_changelog( 'geen array' ); $h = ob_get_clean();
cm_assert( 'lege of kapotte geschiedenis → uitleg', strpos( $h, 'nog niet eerder' ) !== false );

cm_test_group( 'Bewaartermijn' );
$f = cm_admin_field_index( 'cm_settings', array( 'cookiebaas-log' => cm_tabs_log() ) );
cm_assert( 'log_retention_months staat op Bewaren als keuzelijst', isset( cm_tabs_log()['bewaren'], $f['log_retention_months'] ) && $f['log_retention_months']['type'] === 'select' );
cm_assert( 'de standaard (36) en "nooit" zijn opties', array_key_exists( '36', cm_admin_field_options( $f['log_retention_months'] ) ) && array_key_exists( '0', cm_admin_field_options( $f['log_retention_months'] ) ) );
cm_assert( 'status: uit', strpos( cm_log_retention_status_text( 0, false ), 'niet automatisch' ) !== false );
cm_assert( 'status: nog niet ingepland', strpos( cm_log_retention_status_text( 36, false ), 'volgende paginabezoek' ) !== false );
cm_assert( 'status: ingepland, met tijdstip', strpos( cm_log_retention_status_text( 36, 1767261600 ), 'Volgende controle: wpdate:1767261600' ) !== false );

cm_test_group( 'Log leegmaken' );
$wpdb->result = 0;
cm_assert( 'TRUNCATE gelukt (0 rijen telt ook als gelukt)', cm_log_clear() === true && strpos( end( $wpdb->queries ), 'TRUNCATE TABLE `wp_cm_consent_log`' ) === 0 );
$wpdb->result = false;
cm_assert( 'databasefout → false', cm_log_clear() === false );
$wpdb->result = 2;
```

Voeg in `tests/test-admin3-actions.php` vóór `exit( cm_test_summary() );` toe:

```php
cm_test_group( 'Actieformulier met extra velden' );
$f = cm_admin_action_form( 'bump_consent_version', 'Doe', array(), '', 'button', '<input type="text" name="reason">' );
cm_assert( 'extra velden staan vóór de knop', strpos( $f, '<input type="text" name="reason">' ) !== false && strpos( $f, '<input type="text" name="reason">' ) < strpos( $f, '<button' ) );
```

Vervang in `tests/test-admin3-registry.php` de regels die `$pending` vullen door:

```php
$pending = array( 'api_key' );                                                            // plan 3: Beheer › Geavanceerd (Taak 6)
```

- [ ] **Step 2: Draai de tests en zie ze falen**

Run: `php tests/test-admin3-log.php; php tests/test-admin3-actions.php; php tests/test-admin3-registry.php`
Expected: fatal "Call to undefined function cm_bump_consent_version()". De actietest faalt op "extra velden", en de registry-test meldt dat `log_retention_months` ontbreekt.

- [ ] **Step 3: Extra velden in actieformulieren (`includes/admin/actions.php`)**

Vervang `cm_admin_action_form()` door:

```php
/** Een knop die als eigen formulier naar admin-post.php post; $fields = extra (al ge-escapete) velden vóór de knop. */
function cm_admin_action_form( $action, $label, array $args = array(), $confirm = '', $class = 'button', $fields = '' ) {
    $html  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="cm-action-form"'
           . ( $confirm !== '' ? ' data-cm-confirm="' . esc_attr( $confirm ) . '"' : '' ) . '>';
    $html .= '<input type="hidden" name="action" value="' . esc_attr( 'cm_' . $action ) . '">';
    $html .= wp_nonce_field( 'cm_' . $action, '_wpnonce', true, false );
    foreach ( $args as $k => $v ) {
        $html .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
    }
    $html .= $fields;
    $html .= '<button type="submit" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button></form>';
    return $html;
}
```

Voeg toe aan `cm_admin_notice_messages()`:

```php
        'consent-version-bumped' => array( 'success', 'De consent-versie is verhoogd. Elke bezoeker ziet de banner opnieuw.' ),
        'log-cleared'            => array( 'success', 'De consent log is leeggemaakt.' ),
```

- [ ] **Step 4: De tab Bewaren (`includes/admin/page-log.php`)**

Vervang `cm_tabs_log()` door:

```php
function cm_tabs_log() {
    return array(
        'registraties' => array( 'label' => 'Registraties', 'render' => 'cm_log_render_registraties' ),
        'bewaren'      => cm_tab_log_bewaren(),
    );
}

function cm_tab_log_bewaren() {
    return array(
        'label'      => 'Bewaren en opnieuw vragen',
        'sections'   => array(
            array(
                'title'   => 'Bewaartermijn',
                'intro'   => 'De AVG (artikel 5 lid 1e) vraagt persoonsgegevens niet langer te bewaren dan nodig. 36 maanden geeft genoeg bewijs bij een klacht, zonder onnodig lang te bewaren.',
                'content' => 'cm_render_log_retention_status',
                'fields'  => array(
                    cm_field( 'log_retention_months', 'select', 'Registraties verwijderen', array(
                        'options'     => array(
                            '0'  => 'Nooit automatisch',
                            '3'  => 'Na 3 maanden',
                            '6'  => 'Na 6 maanden',
                            '12' => 'Na 12 maanden',
                            '24' => 'Na 24 maanden',
                            '36' => 'Na 36 maanden (aanbevolen)',
                        ),
                        'description' => 'Oudere registraties worden elke dag rond 12:00 uur automatisch verwijderd.',
                    ) ),
                ),
            ),
        ),
        'after_form' => 'cm_render_log_bewaren_tools',
    );
}
```

Voeg vóór het blok `if ( function_exists( 'cm_admin_register_action' ) ) {` toe:

```php
function cm_log_retention_status_text( $months, $next ) {
    $months = (int) $months;
    if ( $months <= 0 ) return 'Registraties worden nu niet automatisch verwijderd.';
    $text = 'Registraties ouder dan ' . $months . ' maanden worden verwijderd.';
    return $next
        ? $text . ' Volgende controle: ' . wp_date( 'j F Y, H:i', $next ) . '.'
        : $text . ' De dagelijkse controle wordt bij het volgende paginabezoek ingepland.';
}

function cm_render_log_retention_status() {
    echo '<p>' . esc_html( cm_log_retention_status_text( cm_get( 'log_retention_months' ), wp_next_scheduled( 'cm_log_retention_cron' ) ) ) . '</p>';
}

/** Verhoog de consent-versie (iedereen kiest opnieuw) en houd de geschiedenis bij. Geeft de nieuwe versie terug. */
function cm_bump_consent_version( $reason = '' ) {
    $new = (int) get_option( 'cm_consent_version', 1 ) + 1;
    update_option( 'cm_consent_version', $new );
    $log   = get_option( 'cm_consent_changelog', array() );
    $log   = is_array( $log ) ? $log : array();
    $log[] = array(
        'date'    => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
        'version' => $new,
        'reason'  => sanitize_text_field( is_scalar( $reason ) ? (string) $reason : '' ),
    );
    update_option( 'cm_consent_changelog', array_slice( $log, -50 ) );
    return $new;
}

/** Versiegeschiedenis, nieuwste bovenaan. */
function cm_render_consent_changelog( $log ) {
    $log = is_array( $log ) ? array_reverse( $log ) : array();
    if ( ! $log ) {
        echo '<p class="description">Er is nog niet eerder om nieuwe toestemming gevraagd.</p>';
        return;
    }
    echo '<table class="widefat striped"><thead><tr><th scope="col">Versie</th><th scope="col">Datum</th><th scope="col">Reden</th></tr></thead><tbody>';
    foreach ( $log as $e ) {
        $e = (array) $e;
        echo '<tr><td>' . esc_html( isset( $e['version'] ) ? $e['version'] : '' ) . '</td>'
           . '<td>' . esc_html( isset( $e['date'] ) ? $e['date'] : '' ) . '</td>'
           . '<td>' . esc_html( ! empty( $e['reason'] ) ? $e['reason'] : '—' ) . '</td></tr>';
    }
    echo '</tbody></table>';
}

function cm_render_log_bewaren_tools() {
    echo '<div class="cm-section"><h2>Iedereen opnieuw laten kiezen</h2>';
    echo '<p>Verhoogt de consent-versie. Elke bezoeker ziet de banner dan opnieuw, bijvoorbeeld na een nieuwe dienst of een gewijzigde privacyverklaring. De huidige versie is ' . esc_html( (int) get_option( 'cm_consent_version', 1 ) ) . '.</p>';
    $reason = '<p><label for="cm-bump-reason">Reden (optioneel, alleen voor uw eigen overzicht)</label><br>'
            . '<input type="text" id="cm-bump-reason" name="reason" class="regular-text" maxlength="200"></p>';
    echo '<div>' . cm_admin_action_form( 'bump_consent_version', 'Iedereen opnieuw laten kiezen', array(), 'Alle bezoekers krijgen de banner opnieuw te zien. Doorgaan?', 'button', $reason ) . '</div>';
    echo '<h3>Eerdere keren</h3>';
    cm_render_consent_changelog( get_option( 'cm_consent_changelog', array() ) );
    echo '</div>';

    echo '<div class="cm-section"><h2>Log leegmaken</h2>';
    echo '<p>Verwijdert alle registraties definitief. Exporteer eerst een CSV als u het bewijs wilt bewaren.</p>';
    echo '<div>' . cm_admin_action_form( 'clear_log', 'Log leegmaken', array(), 'Alle registraties definitief verwijderen? Dit kan niet ongedaan worden gemaakt.', 'button button-link-delete' ) . '</div>';
    echo '</div>';
}

/** Leeg de hele log. False alleen bij een databasefout (0 rijen telt als gelukt). */
function cm_log_clear() {
    global $wpdb;
    return $wpdb->query( 'TRUNCATE TABLE `' . cm_log_table() . '`' ) !== false;
}
```

Voeg in het blok `if ( function_exists( 'cm_admin_register_action' ) ) {` onderaan toe:

```php
    cm_admin_register_action( 'bump_consent_version', function () {
        cm_bump_consent_version( isset( $_POST['reason'] ) ? wp_unslash( $_POST['reason'] ) : '' );
        return 'consent-version-bumped';
    } );
    cm_admin_register_action( 'clear_log', function () {
        return cm_log_clear() ? 'log-cleared' : 'action-failed';
    } );
```

De cron voor de bewaartermijn hoeft niet opnieuw ingepland te worden bij opslaan: `cookiemelding.php` roept `cm_maybe_schedule_retention_cron()` al bij elke `plugins_loaded` aan. De dagelijkse run leest het aantal maanden pas bij het draaien.

- [ ] **Step 5: Draai de tests**

Run: `php tests/test-admin3-log.php && php tests/test-admin3-actions.php && php tests/test-admin3-registry.php && php tests/test-admin3-frame.php`
Expected: alles PASS. De registry-test telt `log_retention_months` nu op precies één plek.

- [ ] **Step 6: Handmatige check (Ruud)**

Onder Consent log › Bewaren en opnieuw vragen:
- de bewaartermijn opslaan werkt, en de status toont de volgende controle;
- "Iedereen opnieuw laten kiezen" met een reden verschijnt in "Eerdere keren", en de banner verschijnt opnieuw op de site;
- "Log leegmaken" vraagt eerst om bevestiging.

- [ ] **Step 7: README, volledige suite, commit**

Vervang in `tests/README.md` de omschrijving van `test-admin3-log.php` door:

```
| `test-admin3-log.php` | Consent log: serverside filter en zoeken, alleen geldige consent-ID's verwijderen, bulkactie uit het bovenste én onderste keuzemenu, rij-acties, bewijs met alle velden, CSV-export (datumbereik, geen terugkerende bezoeken, kolommen zoals in 2.4), bewaartermijn op de tab Bewaren, consent-versie verhogen met geschiedenis (max. 50), log leegmaken, lijsttabel laadt niet zonder WordPress. |
```

Run: `php tests/run.php`
Expected: alles groen.

```bash
git add includes/admin/page-log.php includes/admin/actions.php tests/test-admin3-log.php tests/test-admin3-actions.php tests/test-admin3-registry.php tests/README.md
git commit -m "feat(admin3): bewaartermijn, iedereen opnieuw laten kiezen met geschiedenis, log leegmaken

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 4: Beheer › Licentie, meldingen met eigen tekst, licentiemelding alleen op eigen schermen

**Files:**
- Create: `includes/admin/page-beheer.php`
- Modify: `includes/admin/actions.php` (`cm_admin_flash()`, weergave)
- Modify: `includes/admin/menu.php` (menu-item Beheer, `cm_admin_is_own_screen()`)
- Modify: `includes/license.php` (oude licentiemelding eruit)
- Modify: `includes/admin/page-cookies.php` (licentielink naar Beheer)
- Modify: `cookiemelding.php` (require)
- Modify: `tests/bootstrap.php` (stub `get_current_user_id`)
- Test: `tests/test-admin3-beheer.php` (nieuw)

**Interfaces:**
- Consumes: `cm_license_get(): array` (sleutels `key`, `status`, `expires_at`, `domain`, `last_check`), `cm_license_is_valid(): bool`, `cm_license_get_domain(): string`, `cm_license_activate( $key ): array( 'success', 'message'|'error' )`, `cm_license_deactivate(): array`, `cm_license_check_status(): void` (alle uit `includes/license.php`), `cm_admin_action_form()` met `$fields`, `cm_admin_page_url()`.
- Produces:
  - `cm_admin_flash( $type, $text ): void` (eenmalige melding, per gebruiker)
  - `cm_admin_is_own_screen( $screen_id ): bool`
  - `cm_tabs_beheer(): array` (tab `licentie`; Taak 5 en 6 vullen aan)
  - `cm_license_summary( array $lic, $valid ): array( $type, $word, $text )`
  - `cm_license_flash( array $result ): void`, `cm_license_activate_request( $key ): array`
  - `cm_render_beheer_licentie(): void`, `cm_admin_license_notice(): void`
  - acties `license_activate` (POST `license_key`), `license_check`, `license_deactivate`

- [ ] **Step 1: Schrijf de test `tests/test-admin3-beheer.php`**

De test laadt het hele register, net als `test-admin3-registry.php`, zodat Taak 5 en 6 de echte veldtypes hebben.

```php
<?php
/**
 * Beheer (3.0) — licentie, meldingen met eigen tekst, backup, reset, geavanceerd en info.
 *
 * Borgt: de licentieserver bepaalt of een melding "gelukt" of "mislukt" is,
 * een lege sleutel gaat niet naar de server, en de licentiemelding staat
 * alleen op de eigen schermen van Cookiebaas.
 */

function sanitize_text_field( $s ) { return is_scalar( $s ) ? trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) ) : ''; }
function sanitize_textarea_field( $s ) { return is_scalar( $s ) ? trim( strip_tags( (string) $s ) ) : ''; }
function wp_kses( $s, $allowed = array() ) { return strip_tags( (string) $s, '<' . implode( '><', array_keys( $allowed ) ) . '>' ); }
function get_pages() { return array(); }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function add_query_arg( $a, $b = null, $c = null ) {
    if ( is_array( $a ) ) { $args = $a; $url = $b; } else { $args = array( $a => $b ); $url = $c; }
    foreach ( $args as $k => $v ) $url .= ( strpos( $url, '?' ) === false ? '?' : '&' ) . rawurlencode( $k ) . '=' . rawurlencode( $v );
    return $url;
}
function wp_nonce_url( $url, $action ) { return $url . ( strpos( $url, '?' ) === false ? '?' : '&' ) . '_wpnonce=nonce-' . $action; }
function wp_nonce_field( $action, $name = '_wpnonce', $referer = true, $echo = true ) { $f = '<input type="hidden" name="' . $name . '" value="nonce-' . $action . '">'; if ( $echo ) echo $f; return $f; }
function date_i18n( $format, $ts = null ) { return 'datum'; }
function wp_date( $format, $ts = null ) { return 'wpdate:' . $ts; }
function get_current_user_id() { return 7; }
function get_current_screen() { return (object) array( 'id' => $GLOBALS['cm_test_screen'] ); }
$GLOBALS['cm_test_transients'] = array();
function get_transient( $k ) { return isset( $GLOBALS['cm_test_transients'][ $k ] ) ? $GLOBALS['cm_test_transients'][ $k ] : false; }
function set_transient( $k, $v, $e = 0 ) { $GLOBALS['cm_test_transients'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['cm_test_transients'][ $k ] ); return true; }
$GLOBALS['cm_test_errors'] = array();
function add_settings_error( $setting, $code, $message ) { $GLOBALS['cm_test_errors'][] = $message; }
function get_settings_errors() { return $GLOBALS['cm_test_errors']; }
$GLOBALS['cm_test_purges'] = 0;
function cm_purge_page_caches() { $GLOBALS['cm_test_purges']++; }

// Licentie: stubs in plaats van includes/license.php (die praat met de server)
$GLOBALS['cm_test_lic']         = array( 'key' => '', 'status' => '', 'expires_at' => '', 'domain' => '', 'last_check' => 0 );
$GLOBALS['cm_test_valid']       = false;
$GLOBALS['cm_test_activations'] = array();
function cm_license_get() { return $GLOBALS['cm_test_lic']; }
function cm_license_is_valid() { return $GLOBALS['cm_test_valid']; }
function cm_license_get_domain() { return 'example.test'; }
function cm_license_activate( $key ) { $GLOBALS['cm_test_activations'][] = $key; return array( 'success' => true, 'message' => 'Licentie geactiveerd.' ); }

class CM_Test_Wpdb {
    public $prefix  = 'wp_';
    public $queries = array();
    public $result  = 0;
    public function prepare( $sql, $args ) { return $sql; }
    public function query( $sql ) { $this->queries[] = $sql; return $this->result; }
}
$GLOBALS['wpdb'] = new CM_Test_Wpdb();

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
foreach ( glob( CM_PLUGIN_ROOT . '/includes/admin/*.php' ) as $file ) require $file;
require CM_PLUGIN_ROOT . '/includes/privacy.php';

cm_test_group( 'Pagina en menu' );
cm_assert( 'menu-item Beheer', isset( cm_admin_pages()['cookiebaas-beheer'] ) );
cm_assert( 'tab Licentie', isset( cm_tabs_beheer()['licentie'] ) );

cm_test_group( 'Licentiestatus in woorden' );
cm_assert( 'actief', cm_license_summary( array( 'key' => 'K' ), true )[1] === 'Actief' );
cm_assert( 'geen sleutel', cm_license_summary( array( 'key' => '' ), false )[1] === 'Geen licentie' );
cm_assert( 'verlopen', cm_license_summary( array( 'key' => 'K', 'status' => 'expired' ), false )[1] === 'Verlopen' );
cm_assert( 'anders ongeldig', cm_license_summary( array( 'key' => 'K', 'status' => 'invalid' ), false )[1] === 'Ongeldig' );

cm_test_group( 'Meldingen met eigen tekst' );
$_GET = array();
cm_license_flash( array( 'success' => true, 'message' => 'Licentie geactiveerd.' ) );
ob_start(); cm_admin_render_notices(); $h = ob_get_clean();
cm_assert( 'succes van de server → succesmelding', strpos( $h, 'notice-success' ) !== false && strpos( $h, 'Licentie geactiveerd.' ) !== false );
ob_start(); cm_admin_render_notices(); $h = ob_get_clean();
cm_assert( 'de melding verschijnt één keer', $h === '' );
cm_license_flash( array( 'success' => false ) );
ob_start(); cm_admin_render_notices(); $h = ob_get_clean();
cm_assert( 'fout zonder tekst → nette foutmelding, nooit "gelukt"', strpos( $h, 'notice-error' ) !== false && strpos( $h, 'licentieserver' ) !== false );
cm_admin_flash( 'onzin', '<b>x</b>' );
ob_start(); cm_admin_render_notices(); $h = ob_get_clean();
cm_assert( 'onbekend type → info, tekst ge-escaped', strpos( $h, 'notice-info' ) !== false && strpos( $h, '<b>' ) === false );

cm_test_group( 'Activeren' );
cm_assert( 'lege sleutel → fout, de server wordt niet benaderd', cm_license_activate_request( '  ' )['success'] === false && ! $GLOBALS['cm_test_activations'] );
cm_assert( 'sleutel → naar de server', cm_license_activate_request( 'CB-1' )['success'] === true && $GLOBALS['cm_test_activations'] === array( 'CB-1' ) );

cm_test_group( 'Licentie-tab' );
$GLOBALS['cm_test_lic'] = array( 'key' => 'CB-AAAA', 'status' => 'expired', 'expires_at' => '2026-01-01', 'domain' => 'example.test', 'last_check' => 1767261600 );
ob_start(); cm_render_beheer_licentie(); $h = ob_get_clean();
cm_assert( 'status en sleutel zichtbaar', strpos( $h, 'Verlopen' ) !== false && strpos( $h, 'CB-AAAA' ) !== false );
cm_assert( 'controleren en deactiveren als eigen acties', strpos( $h, 'value="cm_license_check"' ) !== false && strpos( $h, 'value="cm_license_deactivate"' ) !== false );
cm_assert( 'activeren met een sleutelveld', strpos( $h, 'value="cm_license_activate"' ) !== false && strpos( $h, 'name="license_key"' ) !== false );
cm_assert( 'geen formulier binnen een alinea', ! preg_match( '#<p>(?:(?!</p>).)*<form#s', $h ) );

cm_test_group( 'Licentiemelding alleen op eigen schermen' );
$GLOBALS['cm_admin_hooks'] = array( 'toplevel_page_cookiebaas', 'cookiebaas_page_cookiebaas-log' );
$GLOBALS['cm_test_lic']    = array( 'key' => '', 'status' => '' );
$GLOBALS['cm_test_screen'] = 'dashboard';
ob_start(); cm_admin_license_notice(); $h = ob_get_clean();
cm_assert( 'niet op het dashboard', $h === '' );
$GLOBALS['cm_test_screen'] = 'cookiebaas_page_cookiebaas-log';
ob_start(); cm_admin_license_notice(); $h = ob_get_clean();
cm_assert( 'wel op een Cookiebaas-scherm, met link naar Beheer › Licentie', strpos( $h, 'notice-warning' ) !== false && strpos( $h, 'page=cookiebaas-beheer&tab=licentie' ) !== false );
$_GET = array( 'page' => 'cookiebaas-beheer' );
$GLOBALS['cm_admin_hooks'][] = 'cookiebaas_page_cookiebaas-beheer';
$GLOBALS['cm_test_screen']   = 'cookiebaas_page_cookiebaas-beheer';
ob_start(); cm_admin_license_notice(); $h = ob_get_clean();
cm_assert( 'niet dubbel op de tab Licentie zelf', $h === '' );
$_GET = array();
$GLOBALS['cm_test_valid'] = true;
$GLOBALS['cm_test_screen'] = 'toplevel_page_cookiebaas';
ob_start(); cm_admin_license_notice(); $h = ob_get_clean();
cm_assert( 'geldige licentie → geen melding', $h === '' );
$GLOBALS['cm_test_valid'] = false;

exit( cm_test_summary() );
```

- [ ] **Step 2: Draai de test en zie hem falen**

Run: `php tests/test-admin3-beheer.php`
Expected: fatal "Call to undefined function cm_tabs_beheer()" (of een PASS/FAIL-lijst met failures).

- [ ] **Step 3: Meldingen met eigen tekst (`includes/admin/actions.php`)**

Voeg onder `cm_admin_notice_html()` toe:

```php
/** Eenmalige melding met eigen tekst, bijvoorbeeld het antwoord van de licentieserver. */
function cm_admin_flash( $type, $text ) {
    $type = in_array( $type, array( 'success', 'info', 'warning', 'error' ), true ) ? $type : 'info';
    set_transient( 'cm_flash_' . get_current_user_id(), array( $type, (string) $text ), 5 * MINUTE_IN_SECONDS );
}
```

Vervang `cm_admin_render_notices()` door:

```php
function cm_admin_render_notices() {
    $key   = 'cm_flash_' . get_current_user_id();
    $flash = get_transient( $key );
    if ( is_array( $flash ) && count( $flash ) === 2 ) {
        delete_transient( $key );
        echo '<div class="notice notice-' . esc_attr( $flash[0] ) . ' is-dismissible"><p>' . esc_html( $flash[1] ) . '</p></div>';
    }
    if ( empty( $_GET['cm_notice'] ) || isset( $_GET['settings-updated'] ) ) return;
    echo cm_admin_notice_html( sanitize_key( wp_unslash( $_GET['cm_notice'] ) ) );
}
```

Voeg in `tests/bootstrap.php`, direct na de regel met `is_ssl`, toe:

```php
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return 1; } }
```

- [ ] **Step 4: Menu (`includes/admin/menu.php`)**

1. Voeg `'cookiebaas-beheer' => 'Beheer',` toe als laatste regel van `cm_admin_pages()`.
2. Voeg onder `cm_admin_page_url()` toe:

```php
/** Is dit scherm een pagina van Cookiebaas? (voor meldingen die alleen daar horen) */
function cm_admin_is_own_screen( $screen_id ) {
    $hooks = isset( $GLOBALS['cm_admin_hooks'] ) ? (array) $GLOBALS['cm_admin_hooks'] : array();
    return in_array( (string) $screen_id, $hooks, true );
}
```

- [ ] **Step 5: Maak `includes/admin/page-beheer.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   BEHEER — licentie, backup, geavanceerd (REST API), reset en info.
   Alle acties via admin-post.php; geen AJAX.
================================================================ */

function cm_tabs_beheer() {
    return array(
        'licentie' => array( 'label' => 'Licentie', 'render' => 'cm_render_beheer_licentie' ),
    );
}

/** Licentiestatus: array( notice-type, woord, uitleg ), voor Beheer en Overzicht. */
function cm_license_summary( array $lic, $valid ) {
    if ( $valid ) return array( 'success', 'Actief', 'De cookiescan en de automatische scan zijn beschikbaar.' );
    if ( empty( $lic['key'] ) ) return array( 'warning', 'Geen licentie', 'De cookiebanner en de scriptblokkering werken gewoon; alleen de cookiescan is gepauzeerd.' );
    $word = isset( $lic['status'] ) && $lic['status'] === 'expired' ? 'Verlopen' : 'Ongeldig';
    return array( 'warning', $word, 'De cookiebanner en de scriptblokkering blijven werken; de cookiescan is gepauzeerd tot u de licentie verlengt of een geldige sleutel activeert.' );
}

/** Antwoord van de licentieserver → melding. Nooit "gelukt" zonder success. */
function cm_license_flash( array $result ) {
    if ( ! empty( $result['success'] ) ) {
        cm_admin_flash( 'success', ! empty( $result['message'] ) ? $result['message'] : 'Gelukt.' );
    } else {
        cm_admin_flash( 'error', ! empty( $result['error'] ) ? $result['error'] : 'De licentieserver gaf geen bruikbaar antwoord. Probeer het later opnieuw.' );
    }
}

/** Activeren: een lege sleutel gaat niet naar de server. */
function cm_license_activate_request( $key ) {
    $key = trim( (string) $key );
    return $key === '' ? array( 'success' => false, 'error' => 'Vul een licentiesleutel in.' ) : cm_license_activate( $key );
}

function cm_render_beheer_licentie() {
    $lic = cm_license_get();
    list( $type, $word, $text ) = cm_license_summary( $lic, cm_license_is_valid() );
    echo '<div class="notice notice-' . esc_attr( $type ) . ' inline"><p><strong>' . esc_html( $word ) . '.</strong> ' . esc_html( $text ) . '</p></div>';

    if ( ! empty( $lic['key'] ) ) {
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row">Licentiesleutel</th><td><code>' . esc_html( $lic['key'] ) . '</code></td></tr>';
        echo '<tr><th scope="row">Status</th><td>' . esc_html( $word );
        if ( ! empty( $lic['expires_at'] ) ) echo esc_html( ' — verloopt op ' . date_i18n( 'j F Y', strtotime( $lic['expires_at'] ) ) );
        echo '</td></tr>';
        echo '<tr><th scope="row">Domein</th><td><code>' . esc_html( ! empty( $lic['domain'] ) ? $lic['domain'] : cm_license_get_domain() ) . '</code></td></tr>';
        echo '<tr><th scope="row">Laatste controle</th><td>' . esc_html( ! empty( $lic['last_check'] ) ? date_i18n( 'j F Y, H:i', $lic['last_check'] ) : 'Nog niet gecontroleerd' ) . '</td></tr>';
        echo '</tbody></table>';
        echo '<div>' . cm_admin_action_form( 'license_check', 'Status controleren' ) . ' '
           . cm_admin_action_form( 'license_deactivate', 'Deactiveren', array(), 'De licentie op deze website deactiveren? De cookiescan pauzeert tot u opnieuw activeert.', 'button button-link-delete' ) . '</div>';
    }

    echo '<h2>' . esc_html( ! empty( $lic['key'] ) ? 'Andere sleutel activeren' : 'Licentie activeren' ) . '</h2>';
    $field = '<p><label for="cm-license-key">Licentiesleutel</label><br>'
           . '<input type="text" id="cm-license-key" name="license_key" class="regular-text code" placeholder="CB-XXXXX-XXXXX-XXXXX-XXXXX" autocomplete="off" required></p>';
    echo '<div>' . cm_admin_action_form( 'license_activate', 'Activeren', array(), '', 'button button-primary', $field ) . '</div>';
}

/** Waarschuwing bij een ontbrekende of ongeldige licentie: alleen op de schermen van Cookiebaas. */
add_action( 'admin_notices', 'cm_admin_license_notice' );
function cm_admin_license_notice() {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || ! cm_admin_is_own_screen( $screen->id ) || cm_license_is_valid() ) return;
    // Op de tab Licentie zelf staat de status al
    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    $tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
    if ( $page === 'cookiebaas-beheer' && in_array( $tab, array( '', 'licentie' ), true ) ) return;
    $lic  = cm_license_get();
    if ( empty( $lic['key'] ) ) {
        $text = 'Geen licentie geactiveerd. De cookiebanner en de scriptblokkering werken gewoon door; alleen de cookiescan is gepauzeerd.';
        $link = 'Licentie activeren';
    } else {
        $text = 'Uw licentie is ' . ( isset( $lic['status'] ) && $lic['status'] === 'expired' ? 'verlopen' : 'ongeldig' ) . '. De cookiebanner en de scriptblokkering blijven werken; alleen de cookiescan is gepauzeerd tot u de licentie verlengt.';
        $link = 'Licentie beheren';
    }
    echo '<div class="notice notice-warning"><p><strong>Cookiebaas:</strong> ' . esc_html( $text ) . ' <a href="' . esc_url( cm_admin_page_url( 'cookiebaas-beheer', 'licentie' ) ) . '">' . esc_html( $link ) . '</a></p></div>';
}

if ( function_exists( 'cm_admin_register_action' ) ) {
    cm_admin_register_action( 'license_activate', function () {
        cm_license_flash( cm_license_activate_request( isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '' ) );
        return '';
    } );
    cm_admin_register_action( 'license_check', function () {
        cm_license_check_status();
        list( , $word ) = cm_license_summary( cm_license_get(), cm_license_is_valid() );
        cm_admin_flash( 'info', 'Status gecontroleerd: ' . $word . '.' );
        return '';
    } );
    cm_admin_register_action( 'license_deactivate', function () {
        cm_license_flash( cm_license_deactivate() );
        return '';
    } );
}
```

- [ ] **Step 6: Oude licentiemelding weg, links, require**

In `includes/license.php`: verwijder het hele blok vanaf de kop `/* ===… ADMIN NOTICE — waarschuwing bij ongeldige licentie ===… */` tot en met de sluitende `}` van `cm_license_admin_notice()`, inclusief `add_action( 'admin_notices', 'cm_license_admin_notice' );`. De oude licentie-AJAX blijft staan tot Taak 8, want de oude admin gebruikt die nog.

In `includes/admin/page-cookies.php`: vervang in de licentiemelding van de handmatige scan `esc_url( admin_url( 'admin.php?page=cookiemelding-beheer#tab=licentie' ) )` door `esc_url( cm_admin_page_url( 'cookiebaas-beheer', 'licentie' ) )`.

In `cookiemelding.php`: voeg na `require_once CM_PLUGIN_DIR . 'includes/admin/page-log.php';` toe:

```php
require_once CM_PLUGIN_DIR . 'includes/admin/page-beheer.php';
```

- [ ] **Step 7: Draai de tests**

Run: `php tests/test-admin3-beheer.php && php tests/test-admin3-actions.php && php tests/test-admin3-cookies.php && php tests/test-admin3-registry.php && php tests/test-license-failopen.php`
Expected: alles PASS.

- [ ] **Step 8: Handmatige check (Ruud)**

Onder Beheer › Licentie:
- activeren met een geldige en met een ongeldige sleutel geeft de melding van de server;
- "Status controleren" en "Deactiveren" werken;
- de gele licentiemelding staat alleen op Cookiebaas-pagina's, niet op het dashboard.

- [ ] **Step 9: README, volledige suite, commit**

Voeg in `tests/README.md` na de regel van `test-admin3-log.php` toe:

```
| `test-admin3-beheer.php` | Beheer: licentiestatus in woorden, meldingen met de tekst van de licentieserver (één keer, nooit "gelukt" bij een fout), lege sleutel niet naar de server, licentiemelding alleen op Cookiebaas-schermen. |
```

Run: `php tests/run.php`
Expected: alles groen.

```bash
git add includes/admin/page-beheer.php includes/admin/actions.php includes/admin/menu.php includes/license.php includes/admin/page-cookies.php cookiemelding.php tests/bootstrap.php tests/test-admin3-beheer.php tests/README.md
git commit -m "feat(admin3): Beheer › Licentie via admin-post, melding alleen op eigen schermen

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 5: Beheer › Backup en Reset

**Files:**
- Modify: `includes/admin/page-beheer.php`
- Modify: `includes/admin/actions.php` (meldingscodes)
- Modify: `tests/test-admin-fixes.php` (import- en resetgroep verhuizen)
- Test: `tests/test-admin3-beheer.php`

**Interfaces:**
- Consumes: `cm_sanitize_settings( $input, $existing )`, `cm_sanitize_cookie_list( $raw )`, `cm_sanitize_privacy( $input )`, `cm_log_clear()`, `cm_bump_consent_version( $reason )`, `cm_admin_flash()`, `cm_admin_action_url()`, `cm_admin_action_form()`.
- Produces:
  - `cm_backup_payload(): array`
  - `cm_import_backup( $raw ): array( 'ok' => bool, 'message' => string )`
  - `cm_reset_everything(): string[]` (de onderdelen die mislukten)
  - `cm_license_reset_local(): void`
  - `cm_render_beheer_backup()`, `cm_render_beheer_reset()`
  - acties `export_backup` (GET), `import_backup` (POST multipart, bestand `cm_backup`), `reset_all`, `license_reset`
  - meldingscodes `reset-all-done`, `license-cleared`

- [ ] **Step 1: Breid de test uit**

Voeg in `tests/test-admin3-beheer.php` vóór `exit( cm_test_summary() );` toe:

```php
cm_test_group( 'Backup maken' );
update_option( 'cm_settings', array_merge( cm_default_settings(), array( 'api_key' => str_repeat( 'a', 40 ), 'gtm_container_id' => 'GTM-ABC' ) ) );
$b = cm_backup_payload();
cm_assert( 'metadata van Cookiebaas', $b['_meta']['plugin'] === 'cookiebaas' && isset( $b['_meta']['version'], $b['_meta']['exported'] ) );
cm_assert( 'instellingen zonder API-sleutel', $b['settings']['gtm_container_id'] === 'GTM-ABC' && ! array_key_exists( 'api_key', $b['settings'] ) );
cm_assert( 'cookielijst en privacy gaan mee', array_key_exists( 'cookie_list', $b ) && array_key_exists( 'privacy', $b ) );

cm_test_group( 'Ongeldig bestand wijzigt niets (Review Focus 2)' );
$snap = $GLOBALS['cm_test_options'];
$junk = array(
    'leeg'            => '',
    'geen JSON'       => 'geen json',
    'zonder _meta'    => json_encode( array( 'settings' => array( 'gtm_container_id' => 'X' ) ) ),
    'andere plugin'   => json_encode( array( '_meta' => array( 'plugin' => 'andere-plugin' ), 'settings' => array( 'gtm_container_id' => 'X' ) ) ),
    'zonder inhoud'   => json_encode( array( '_meta' => array( 'plugin' => 'cookiebaas' ) ) ),
);
foreach ( $junk as $label => $raw ) {
    $r = cm_import_backup( $raw );
    cm_assert( "geweigerd: $label", $r['ok'] === false && $r['message'] !== '' );
}
cm_assert( 'geen enkele option gewijzigd', $GLOBALS['cm_test_options'] === $snap );

cm_test_group( 'Import gaat door dezelfde sanitizing als opslaan' );
$purges = $GLOBALS['cm_test_purges'];
$export = array(
    '_meta'       => array( 'plugin' => 'cookiemelding', 'version' => '2.4.4' ),
    'settings'    => array( 'txt_banner_title' => '<script>alert(1)</script>Hallo', 'txt_banner_body' => '<a href="/p">Lees</a><script>x</script>', 'onbekende_sleutel' => 'x', 'google_load_default' => '1', 'analytics_default' => '0' ),
    'cookie_list' => array( array( 'name' => '<b>_ga</b>', 'category' => 'bogus' ), array( 'name' => '' ) ),
    'privacy'     => array( 'pv_doorgifte' => "A\nB", 'pv_bedrijfsnaam' => '<i>X</i>' ),
);
$r = cm_import_backup( json_encode( $export ) );
$s = get_option( 'cm_settings' );
cm_assert( 'import slaagt, ook een oude export (cookiemelding)', $r['ok'] === true && strpos( $r['message'], 'instellingen' ) !== false );
cm_assert( 'script-tag uit tekstveld verwijderd', $s['txt_banner_title'] === 'alert(1)Hallo' );
cm_assert( 'HTML-veld houdt link, verliest script', $s['txt_banner_body'] === '<a href="/p">Lees</a>x' );
cm_assert( 'onbekende sleutel niet opgeslagen', ! array_key_exists( 'onbekende_sleutel', $s ) );
cm_assert( 'ontbrekende sleutels krijgen de standaard', $s['gtm_container_id'] === '' );
cm_assert( 'google_load_default forceert analytics_default', (int) $s['analytics_default'] === 1 );
cm_assert( 'de huidige API-sleutel blijft staan', $s['api_key'] === str_repeat( 'a', 40 ) );
$cl = get_option( 'cm_cookie_list' );
cm_assert( 'cookielijst gesanitized: lege naam weg, tags weg, categorie gevalideerd', count( $cl ) === 1 && $cl[0]['name'] === '_ga' && $cl[0]['category'] === 'functional' );
$pv = get_option( 'cm_privacy' );
cm_assert( 'privacy: regeleinde behouden, tags weg', $pv['pv_doorgifte'] === "A\nB" && $pv['pv_bedrijfsnaam'] === 'X' );
cm_assert( 'privacy: ontbrekende checkbox krijgt de standaard', $pv['pv_ap_tonen'] === cm_default_privacy()['pv_ap_tonen'] );
cm_assert( 'paginacache geleegd na import', $GLOBALS['cm_test_purges'] > $purges );

cm_test_group( 'Ongeldige waarde in de backup → melding' );
$GLOBALS['cm_test_errors'] = array();
$r = cm_import_backup( json_encode( array( '_meta' => array( 'plugin' => 'cookiebaas' ), 'settings' => array( 'color_popup_bg' => 'rood' ) ) ) );
cm_assert( 'import slaagt, met een waarschuwing', $r['ok'] === true && strpos( $r['message'], 'ongeldig' ) !== false );
cm_assert( 'ongeldige kleur wordt de standaard', get_option( 'cm_settings' )['color_popup_bg'] === cm_default_settings()['color_popup_bg'] );

cm_test_group( 'Alles resetten (Review Focus 4)' );
update_option( 'cm_settings', array_merge( cm_default_settings(), array( 'gtm_container_id' => 'GTM-WEG' ) ) );
update_option( 'cm_cookie_list', array( array( 'name' => 'x' ) ) );
update_option( 'cm_license_data', array( 'key' => 'K' ) );
update_option( 'cm_consent_version', 2 );
$wpdb->result = 0;
cm_assert( 'alles gelukt → geen fouten', cm_reset_everything() === array() );
cm_assert( 'instellingen terug naar de standaard', get_option( 'cm_settings' )['gtm_container_id'] === '' );
cm_assert( 'cookielijst leeg', get_option( 'cm_cookie_list' ) === array() );
cm_assert( 'privacy terug naar de standaard', get_option( 'cm_privacy' ) === cm_default_privacy() );
cm_assert( 'consent log leeggemaakt', strpos( end( $wpdb->queries ), 'TRUNCATE' ) === 0 );
cm_assert( 'iedereen kiest opnieuw', (int) get_option( 'cm_consent_version' ) === 3 );
cm_assert( 'licentie lokaal gewist', get_option( 'cm_license_data' ) === false );
$wpdb->result = false;
cm_assert( 'mislukte log → gemeld, niet "alles gelukt"', cm_reset_everything() === array( 'consent log' ) );
$wpdb->result = 0;

cm_test_group( 'Tabs Backup en Reset' );
cm_assert( 'volgorde Licentie, Backup, Reset', array_keys( cm_tabs_beheer() ) === array( 'licentie', 'backup', 'reset' ) );
ob_start(); cm_render_beheer_backup(); $h = ob_get_clean();
cm_assert( 'download en upload', strpos( $h, 'action=cm_export_backup' ) !== false && strpos( $h, 'enctype="multipart/form-data"' ) !== false && strpos( $h, 'name="cm_backup"' ) !== false );
ob_start(); cm_render_beheer_reset(); $h = ob_get_clean();
cm_assert( 'alles resetten en licentie wissen, met bevestiging', strpos( $h, 'value="cm_reset_all"' ) !== false && strpos( $h, 'value="cm_license_reset"' ) !== false && substr_count( $h, 'data-cm-confirm=' ) === 2 );
```

Haal in `tests/test-admin-fixes.php` de groepen `cm_test_group( 'Reset instellingen' );` en `cm_test_group( 'Import gaat door dezelfde sanitizing als opslaan' );` met hun regels weg. Ze staan nu hierboven, tegen de nieuwe functies. De oude AJAX-handlers verdwijnen in Taak 8.

- [ ] **Step 2: Draai de test en zie hem falen**

Run: `php tests/test-admin3-beheer.php`
Expected: fatal "Call to undefined function cm_backup_payload()".

- [ ] **Step 3: Backup en reset (`includes/admin/page-beheer.php`)**

Vervang `cm_tabs_beheer()` door:

```php
function cm_tabs_beheer() {
    return array(
        'licentie' => array( 'label' => 'Licentie', 'render' => 'cm_render_beheer_licentie' ),
        'backup'   => array( 'label' => 'Backup', 'render' => 'cm_render_beheer_backup' ),
        'reset'    => array( 'label' => 'Reset', 'render' => 'cm_render_beheer_reset' ),
    );
}
```

Voeg vóór het blok `if ( function_exists( 'cm_admin_register_action' ) ) {` toe:

```php
/** Inhoud van de backup: alles wat de admin instelt, zonder API-sleutel en licentie. */
function cm_backup_payload() {
    $settings = get_option( 'cm_settings', cm_default_settings() );
    $settings = is_array( $settings ) ? $settings : array();
    unset( $settings['api_key'] );
    return array(
        '_meta'       => array(
            'plugin'   => 'cookiebaas',
            'version'  => CM_VERSION,
            'exported' => current_time( 'c' ),
            'site'     => get_bloginfo( 'url' ),
        ),
        'settings'    => $settings,
        'cookie_list' => get_option( 'cm_cookie_list', array() ),
        'privacy'     => get_option( 'cm_privacy', cm_default_privacy() ),
    );
}

/**
 * Backup terugzetten. Een ongeldig bestand schrijft niets. Alles gaat door
 * dezelfde sanitizers als opslaan; ongeldige waarden worden de standaard.
 * De huidige API-sleutel blijft staan: die zit niet in een backup.
 */
function cm_import_backup( $raw ) {
    $data = is_string( $raw ) && $raw !== '' ? json_decode( $raw, true ) : null;
    $meta = is_array( $data ) && isset( $data['_meta'] ) && is_array( $data['_meta'] ) ? $data['_meta'] : array();
    if ( ! isset( $meta['plugin'] ) || ! in_array( $meta['plugin'], array( 'cookiebaas', 'cookiemelding' ), true ) ) {
        return array( 'ok' => false, 'message' => 'Dit is geen backup van Cookiebaas. Er is niets gewijzigd.' );
    }
    $has = function ( $k ) use ( $data ) { return isset( $data[ $k ] ) && is_array( $data[ $k ] ); };
    if ( ! $has( 'settings' ) && ! $has( 'cookie_list' ) && ! $has( 'privacy' ) ) {
        return array( 'ok' => false, 'message' => 'De backup bevat geen instellingen, cookielijst of privacyverklaring. Er is niets gewijzigd.' );
    }

    $errors = count( get_settings_errors() );
    $done   = array();
    if ( $has( 'settings' ) ) {
        $base = array_merge( cm_default_settings(), array( 'api_key' => (string) cm_get( 'api_key' ) ) );
        update_option( 'cm_settings', cm_sanitize_settings( $data['settings'], $base ) );
        $done[] = 'instellingen';
    }
    if ( $has( 'cookie_list' ) ) {
        $list = cm_sanitize_cookie_list( $data['cookie_list'] );
        update_option( 'cm_cookie_list', $list );
        $done[] = count( $list ) === 1 ? '1 cookie' : count( $list ) . ' cookies';
    }
    if ( $has( 'privacy' ) ) {
        update_option( 'cm_privacy', cm_sanitize_privacy( array_merge( cm_default_privacy(), $data['privacy'] ) ) );
        $done[] = 'privacyverklaring';
    }
    $message = 'Teruggezet: ' . implode( ', ', $done ) . '.';
    if ( count( get_settings_errors() ) > $errors ) {
        $message .= ' Sommige waarden in de backup waren ongeldig; daar staat nu de standaardwaarde.';
    }
    return array( 'ok' => true, 'message' => $message );
}

function cm_license_reset_local() {
    delete_option( 'cm_license_data' );
    delete_option( 'cm_license_api_url' );
}

/** Zet alles terug. Geeft de onderdelen terug die mislukten (leeg = alles gelukt). */
function cm_reset_everything() {
    $failed = array();
    update_option( 'cm_settings', cm_default_settings() );
    update_option( 'cm_cookie_list', array() );
    update_option( 'cm_privacy', cm_default_privacy() );
    if ( ! cm_log_clear() ) $failed[] = 'consent log';
    cm_bump_consent_version( 'Alles gereset' );
    cm_license_reset_local();
    return $failed;
}

function cm_render_beheer_backup() {
    echo '<h2>Backup maken</h2>';
    echo '<p>Download de instellingen, de cookielijst en de privacyverklaring als één JSON-bestand: als backup, of om over te zetten naar een andere website. De consent log, de cookiedatabase, de licentie en de API-sleutel gaan niet mee.</p>';
    echo '<p><a class="button button-primary" href="' . esc_url( cm_admin_action_url( 'export_backup' ) ) . '">Backup downloaden (.json)</a></p>';

    echo '<h2>Backup terugzetten</h2>';
    echo '<p>Zet een eerder gemaakte backup terug. Ongeldige waarden worden de standaardwaarde; een bestand dat geen backup van Cookiebaas is, wijzigt niets.</p>';
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data" class="cm-action-form" data-cm-confirm="' . esc_attr( 'De huidige instellingen, cookielijst en privacyverklaring worden overschreven door de backup. Doorgaan?' ) . '">';
    echo '<input type="hidden" name="action" value="cm_import_backup">';
    wp_nonce_field( 'cm_import_backup' );
    echo '<p><label for="cm-backup-file">Backupbestand (.json)</label><br><input type="file" id="cm-backup-file" name="cm_backup" accept=".json,application/json" required></p>';
    echo '<button type="submit" class="button">Terugzetten en overschrijven</button>';
    echo '</form>';
}

function cm_render_beheer_reset() {
    echo '<p>Losse onderdelen zet u op hun eigen plek terug: de kleuren onder Banner › Vormgeving, de cookielijst onder Cookies, de privacyverklaring op de pagina Privacyverklaring, en de consent log onder Consent log › Bewaren en opnieuw vragen.</p>';

    echo '<h2>Licentie lokaal wissen</h2>';
    echo '<p>Wist de licentiegegevens op deze website, zonder de licentieserver te benaderen. Gebruik dit als deactiveren niet lukt. De cookiebanner en de scriptblokkering blijven werken; de cookiescan pauzeert.</p>';
    echo '<div>' . cm_admin_action_form( 'license_reset', 'Licentie lokaal wissen', array(), 'De licentiegegevens op deze website wissen?' ) . '</div>';

    echo '<h2>Alles resetten</h2>';
    echo '<p>Zet alles in één keer terug. De instellingen (ook de API-sleutel), de cookielijst en de privacyverklaring gaan naar de standaard, de consent log wordt leeggemaakt, elke bezoeker ziet de banner opnieuw en de licentie wordt lokaal gewist. Dit kan niet ongedaan worden gemaakt.</p>';
    echo '<div>' . cm_admin_action_form( 'reset_all', 'Alles resetten', array(), 'Alles resetten? Instellingen, cookielijst, privacyverklaring, consent log en licentie worden gewist. Dit kan niet ongedaan worden gemaakt.', 'button button-link-delete' ) . '</div>';
}
```

Voeg in het blok `if ( function_exists( 'cm_admin_register_action' ) ) {` onderaan toe:

```php
    cm_admin_register_action( 'export_backup', function () {
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="cookiebaas-backup-' . wp_date( 'Y-m-d' ) . '.json"' );
        echo wp_json_encode( cm_backup_payload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        exit;
    } );
    cm_admin_register_action( 'import_backup', function () {
        $f   = isset( $_FILES['cm_backup'] ) && is_array( $_FILES['cm_backup'] ) ? $_FILES['cm_backup'] : array();
        $ok  = isset( $f['error'], $f['size'], $f['tmp_name'] ) && (int) $f['error'] === UPLOAD_ERR_OK
            && (int) $f['size'] <= MB_IN_BYTES && is_uploaded_file( $f['tmp_name'] );
        if ( ! $ok ) {
            cm_admin_flash( 'error', 'Kies een backupbestand (.json, maximaal 1 MB). Er is niets gewijzigd.' );
            return '';
        }
        $r = cm_import_backup( (string) file_get_contents( $f['tmp_name'] ) );
        cm_admin_flash( $r['ok'] ? ( strpos( $r['message'], 'ongeldig' ) !== false ? 'warning' : 'success' ) : 'error', $r['message'] );
        return '';
    } );
    cm_admin_register_action( 'reset_all', function () {
        $failed = cm_reset_everything();
        if ( ! $failed ) return 'reset-all-done';
        cm_admin_flash( 'error', 'Niet gelukt: ' . implode( ', ', $failed ) . '. De rest is wel teruggezet.' );
        return '';
    } );
    cm_admin_register_action( 'license_reset', function () {
        cm_license_reset_local();
        return 'license-cleared';
    } );
```

Voeg toe aan `cm_admin_notice_messages()` in `includes/admin/actions.php`:

```php
        'reset-all-done'         => array( 'success', 'Alles is teruggezet naar de standaard. Elke bezoeker ziet de banner opnieuw.' ),
        'license-cleared'        => array( 'success', 'De licentiegegevens zijn van deze website gewist. De cookiescan pauzeert tot u opnieuw een licentie activeert.' ),
```

- [ ] **Step 4: Draai de tests**

Run: `php tests/test-admin3-beheer.php && php tests/test-admin-fixes.php && php tests/test-admin3-frame.php`
Expected: alles PASS.

- [ ] **Step 5: Handmatige check (Ruud)**

- Een backup downloaden en weer terugzetten werkt; het bestand heet `cookiebaas-backup-JJJJ-MM-DD.json`.
- Een willekeurig ander JSON-bestand geeft een foutmelding en wijzigt niets.
- Test "Alles resetten" alleen op een testsite.

- [ ] **Step 6: README, volledige suite, commit**

Vervang in `tests/README.md` de omschrijvingen van `test-admin-fixes.php` en `test-admin3-beheer.php` door:

```
| `test-admin-fixes.php` | Admin-fixes v2.4.5 die buiten de nieuwe admin vallen (privacy-regeleinden, categorie bij de automatische scan, embeds "none"). Reset, import, logfilter en cache-purge worden sinds 3.0 bij hun nieuwe plek getest. |
| `test-admin3-beheer.php` | Beheer: licentiestatus in woorden, meldingen met de tekst van de licentieserver (één keer, nooit "gelukt" bij een fout), lege sleutel niet naar de server, licentiemelding alleen op Cookiebaas-schermen; backup zonder API-sleutel, ongeldig bestand wijzigt niets, import door dezelfde sanitizing als opslaan (API-sleutel blijft), "Alles resetten" meldt een mislukt onderdeel. |
```

Run: `php tests/run.php`
Expected: alles groen.

```bash
git add includes/admin/page-beheer.php includes/admin/actions.php tests/test-admin3-beheer.php tests/test-admin-fixes.php tests/README.md
git commit -m "feat(admin3): Beheer › Backup en Reset via admin-post, eerlijke meldingen

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 6: Beheer › Geavanceerd (API-sleutel) en Info

**Files:**
- Modify: `includes/admin/page-beheer.php`
- Modify: `includes/admin/actions.php` (meldingscodes)
- Modify: `includes/admin/page-privacy.php` (link naar Beheer › Info)
- Test: `tests/test-admin3-beheer.php`, `tests/test-admin3-registry.php`, `tests/test-admin3-privacy.php`

**Interfaces:**
- Consumes: `cm_field()`, `cm_admin_field_index()`, `cm_sanitize_settings()`, `cm_admin_action_form()`, `rest_url()`.
- Produces:
  - `cm_sanitize_api_key( $raw, $current ): string`
  - `cm_set_api_key( $key ): void`
  - `cm_render_beheer_geavanceerd()`, `cm_render_beheer_info()`
  - tabs `geavanceerd` en `info`; het veld `api_key` (type `custom`, met `sanitize`) staat in de secties van `geavanceerd`
  - acties `api_key_generate`, `api_key_revoke`
  - meldingscodes `api-key-created`, `api-key-revoked`

- [ ] **Step 1: Breid de tests uit**

Voeg in `tests/test-admin3-beheer.php` bij de stubs bovenaan toe:

```php
function rest_url( $path = '' ) { return 'https://example.test/wp-json/' . $path; }
```

Voeg vóór `exit( cm_test_summary() );` toe:

```php
cm_test_group( 'API-sleutel' );
cm_assert( 'leeg of 40 hex-tekens wordt bewaard', cm_sanitize_api_key( '', 'oud' ) === '' && cm_sanitize_api_key( str_repeat( 'B', 40 ), 'oud' ) === str_repeat( 'b', 40 ) );
cm_assert( 'iets anders → de oude sleutel blijft', cm_sanitize_api_key( '<script>', 'oud' ) === 'oud' && cm_sanitize_api_key( array(), 'oud' ) === 'oud' );
$idx = cm_admin_field_index( 'cm_settings' );
cm_assert( 'api_key staat op Beheer › Geavanceerd, met eigen sanitizer', isset( cm_tabs_beheer()['geavanceerd'], $idx['api_key'] ) && $idx['api_key']['sanitize'] === 'cm_sanitize_api_key' );
update_option( 'cm_settings', array_merge( cm_default_settings(), array( 'api_key' => '' ) ) );
cm_set_api_key( str_repeat( 'c', 40 ) );
$full = get_option( 'cm_settings' );
cm_assert( 'sleutel zetten', $full['api_key'] === str_repeat( 'c', 40 ) );
cm_assert( 'gewoon opslaan laat een geldige sleutel staan (idempotent)', cm_sanitize_settings( $full, $full )['api_key'] === str_repeat( 'c', 40 ) );
$own = array_merge( $full, array( 'api_key' => 'mijn-eigen-sleutel' ) );
cm_assert( 'een zelfgekozen sleutel uit 2.x blijft staan', cm_sanitize_settings( $own, $own )['api_key'] === 'mijn-eigen-sleutel' );

cm_test_group( 'Tab Geavanceerd' );
cm_get_flush();
ob_start(); cm_render_beheer_geavanceerd(); $h = ob_get_clean();
cm_assert( 'endpoint, sleutel en beide acties', strpos( $h, 'wp-json/cookiebaas/v1/consent/' ) !== false && strpos( $h, str_repeat( 'c', 40 ) ) !== false && strpos( $h, 'value="cm_api_key_generate"' ) !== false && strpos( $h, 'value="cm_api_key_revoke"' ) !== false );
cm_assert( 'geen formulier binnen een alinea', ! preg_match( '#<p>(?:(?!</p>).)*<form#s', $h ) );
cm_set_api_key( '' );
cm_get_flush();
ob_start(); cm_render_beheer_geavanceerd(); $h = ob_get_clean();
cm_assert( 'zonder sleutel: geen intrekknop, wel uitleg', strpos( $h, 'value="cm_api_key_revoke"' ) === false && strpos( $h, 'applicatiewachtwoord' ) !== false );

cm_test_group( 'Tab Info' );
cm_assert( 'volgorde van de tabs zoals in de spec', array_keys( cm_tabs_beheer() ) === array( 'licentie', 'backup', 'geavanceerd', 'reset', 'info' ) );
ob_start(); cm_render_beheer_info(); $h = ob_get_clean();
cm_assert( 'alle shortcodes', strpos( $h, '[cookiebaas_privacy]' ) !== false && strpos( $h, '[cookiebaas_cookies]' ) !== false && strpos( $h, '[cookiebaas_voorkeuren]' ) !== false );
cm_assert( 'versie, disclaimer en contact', strpos( $h, CM_VERSION ) !== false && strpos( $h, 'Disclaimer' ) !== false && strpos( $h, 'cookiebaas.nl' ) !== false );
cm_assert( 'snel aan de slag noemt de nieuwe menu’s', strpos( $h, 'Blokkering › Google' ) !== false && strpos( $h, 'Cookies &amp; scan' ) === false && strpos( $h, 'Instellingen' ) === false );
```

Haal in `tests/test-admin3-registry.php` `$pending` helemaal weg:
- verwijder de regel `$pending = array( 'api_key' ); …`;
- maak van de `$missing`-regel `$missing = array_diff( array_keys( cm_default_settings() ), $keys, $no_ui );`;
- haal in het docblock de zin "Plan 2 en 3 verwijderen sleutels uit $pending tot die leeg is." weg.

Voeg in `tests/test-admin3-privacy.php` vóór `exit( cm_test_summary() );` toe:

```php
cm_test_group( 'Shortcode-regel en idempotentie' );
$first = cm_privacy_sections()[0];
ob_start(); call_user_func( $first['content'], array() ); $h = ob_get_clean();
cm_assert( 'shortcode-regel linkt naar Beheer › Info', strpos( $h, 'page=cookiebaas-beheer&tab=info' ) !== false );
update_option( 'cm_privacy', cm_default_privacy() );
$once  = cm_privacy_sanitize_callback( cm_default_privacy() );
update_option( 'cm_privacy', $once );
$twice = cm_privacy_sanitize_callback( $once );
cm_assert( 'twee keer opslaan = één keer (spec §5.3)', $once === $twice );
```

- [ ] **Step 2: Draai de tests en zie ze falen**

Run: `php tests/test-admin3-beheer.php; php tests/test-admin3-registry.php; php tests/test-admin3-privacy.php`
Expected: fatal "Call to undefined function cm_sanitize_api_key()". De registry-test meldt dat `api_key` ontbreekt, en de privacytest faalt op de link naar Beheer › Info.

- [ ] **Step 3: Geavanceerd en Info (`includes/admin/page-beheer.php`)**

Vervang `cm_tabs_beheer()` door:

```php
function cm_tabs_beheer() {
    return array(
        'licentie'    => array( 'label' => 'Licentie', 'render' => 'cm_render_beheer_licentie' ),
        'backup'      => array( 'label' => 'Backup', 'render' => 'cm_render_beheer_backup' ),
        'geavanceerd' => array(
            'label'    => 'Geavanceerd',
            'render'   => 'cm_render_beheer_geavanceerd',
            // Alleen voor het register en de sanitizer: de sleutel wordt nooit via een
            // formulier gepost, alleen gezet door de acties Sleutel maken en Intrekken.
            'sections' => array( array( 'fields' => array(
                cm_field( 'api_key', 'custom', 'API-sleutel', array( 'sanitize' => 'cm_sanitize_api_key' ) ),
            ) ) ),
        ),
        'reset'       => array( 'label' => 'Reset', 'render' => 'cm_render_beheer_reset' ),
        'info'        => array( 'label' => 'Info', 'render' => 'cm_render_beheer_info' ),
    );
}
```

Voeg vóór het blok `if ( function_exists( 'cm_admin_register_action' ) ) {` toe:

```php
/**
 * API-sleutel: leeg, of 40 hex-tekens zoals "Sleutel maken" die maakt. Iets
 * anders laat de huidige sleutel staan, ook een zelfgekozen sleutel uit 2.x
 * (WordPress haalt elke opslag van cm_settings door deze sanitizer).
 */
function cm_sanitize_api_key( $raw, $current ) {
    if ( ! is_string( $raw ) ) return (string) $current;
    $raw = strtolower( trim( $raw ) );
    return ( $raw === '' || preg_match( '/^[a-f0-9]{40}$/', $raw ) ) ? $raw : (string) $current;
}

function cm_set_api_key( $key ) {
    $s = get_option( 'cm_settings', array() );
    $s = is_array( $s ) ? $s : array();
    $s['api_key'] = (string) $key;
    update_option( 'cm_settings', $s );
}

function cm_render_beheer_geavanceerd() {
    $key      = (string) cm_get( 'api_key' );
    $endpoint = rest_url( 'cookiebaas/v1/consent/' );
    echo '<h2>REST API</h2>';
    echo '<p>Controleer een toestemming vanuit een CRM, e-mailplatform of andere externe dienst. Het endpoint geeft de keuze terug zonder persoonsgegevens (geen IP-adres, geen browser).</p>';
    echo '<table class="form-table" role="presentation"><tbody>';
    echo '<tr><th scope="row">Endpoint</th><td><code>' . esc_html( $endpoint . '{consent_id}' ) . '</code></td></tr>';
    echo '<tr><th scope="row">API-sleutel</th><td>';
    if ( $key !== '' ) {
        echo '<code>' . esc_html( $key ) . '</code>';
        echo '<p class="description">Stuur de sleutel mee als HTTP-header <code>X-Cookiebaas-Key</code>.</p>';
    } else {
        echo '<p>Geen sleutel.</p><p class="description">Zonder sleutel is het endpoint alleen bereikbaar met een WordPress-applicatiewachtwoord.</p>';
    }
    echo '<div>' . cm_admin_action_form( 'api_key_generate', $key !== '' ? 'Nieuwe sleutel maken' : 'Sleutel maken', array(), $key !== '' ? 'Een nieuwe sleutel maken? De huidige sleutel werkt daarna niet meer.' : '' );
    if ( $key !== '' ) {
        echo ' ' . cm_admin_action_form( 'api_key_revoke', 'Intrekken', array(), 'De API-sleutel intrekken? Externe koppelingen die hem gebruiken, verliezen direct toegang.', 'button button-link-delete' );
    }
    echo '</div></td></tr>';
    echo '</tbody></table>';

    if ( $key !== '' ) {
        echo '<h3>Voorbeeld</h3>';
        echo '<pre><code>' . esc_html( 'curl -H "X-Cookiebaas-Key: ' . $key . '" "' . $endpoint . '{consent_id}"' ) . '</code></pre>';
    }
    echo '<h3>Voorbeeldantwoord</h3>';
    echo '<pre><code>' . esc_html( "{\n  \"consent_id\": \"a1b2c3d4-...\",\n  \"status\": \"Geaccepteerd\",\n  \"method\": \"accept-all\",\n  \"analytics\": true,\n  \"marketing\": true,\n  \"config_hash\": \"a3f9d2b1c4e87f20\",\n  \"timestamp\": \"2026-03-17 10:25:00\",\n  \"verified\": true\n}" ) . '</code></pre>';
    echo '<p class="description">Statuswaarden: <code>Geaccepteerd</code>, <code>Geweigerd</code>, <code>Aangepast</code>, <code>Terugkerend bezoek</code>. HTTP 404 als de consent-ID niet bestaat.</p>';
}

/** Tekst van de disclaimer: array( kop, alinea ). */
function cm_disclaimer_paragraphs() {
    return array(
        array( '1. Geen juridisch advies', 'De Cookiebaas plugin is een technisch hulpmiddel en biedt geen juridisch advies. De plugin vervangt op geen enkele wijze de noodzaak om een gekwalificeerde juridisch adviseur te raadplegen over uw specifieke situatie met betrekking tot de AVG/GDPR, de ePrivacy-richtlijn, de Telecommunicatiewet of andere toepasselijke wet- en regelgeving. Het gebruik van deze plugin garandeert niet dat uw website voldoet aan geldende privacywetgeving.' ),
        array( '2. “Zoals beschikbaar”', 'De Cookiebaas plugin wordt aangeboden “as is” en “as available”, zonder enige garantie van welke aard dan ook, uitdrukkelijk noch stilzwijgend. Dit omvat, maar is niet beperkt tot, garanties van verkoopbaarheid, geschiktheid voor een bepaald doel, niet-inbreuk, juistheid, volledigheid, of ononderbroken en foutloze werking.' ),
        array( '3. Beperking van aansprakelijkheid', 'Ruud van der Heijden en eventuele bijdragers zijn in geen geval aansprakelijk voor enige directe, indirecte, incidentele, speciale, gevolg- of voorbeeldschade (inclusief maar niet beperkt tot boetes van toezichthouders, verlies van gegevens, gederfde winst, bedrijfsonderbreking of reputatieschade) die voortvloeit uit of verband houdt met het gebruik of het onvermogen tot gebruik van deze plugin, zelfs indien op de hoogte gesteld van de mogelijkheid van dergelijke schade.' ),
        array( '4. Verantwoordelijkheid van de gebruiker', 'De website-eigenaar blijft te allen tijde zelf verantwoordelijk voor de naleving van privacywetgeving. Dit omvat onder meer: het correct configureren van de plugin, het actueel houden van de cookielijst en privacyverklaring, het testen of cookies daadwerkelijk geblokkeerd worden vóór consent, het inschakelen van een juridisch adviseur bij twijfel, en het periodiek controleren van de compliance-check.' ),
        array( '5. Geen garantie op compliance', 'Hoewel de Cookiebaas plugin is ontworpen met de AVG, EDPB-richtlijnen en AP-handhavingscriteria als uitgangspunt, kan de ontwikkelaar niet garanderen dat de plugin in alle situaties en jurisdicties volledige compliance biedt. Wet- en regelgeving verandert regelmatig en de interpretatie ervan kan per toezichthouder en per rechtsgebied verschillen.' ),
        array( '6. Diensten van derden', 'De plugin interageert met diensten van derden (Google Analytics, Google Tag Manager, YouTube, Vimeo, Meta/Facebook, etc.). De ontwikkelaar heeft geen controle over en is niet verantwoordelijk voor het gedrag, de cookiepraktijken of het privacybeleid van deze diensten. Het is de verantwoordelijkheid van de website-eigenaar om te controleren of het gebruik van deze diensten in overeenstemming is met de toepasselijke wetgeving.' ),
        array( '7. Updates en ondersteuning', 'Er is geen verplichting tot het leveren van updates, bugfixes, beveiligingspatches of ondersteuning. Eventuele updates worden naar eigen inzicht van de ontwikkelaar beschikbaar gesteld.' ),
        array( '8. Aanvaarding', 'Door deze plugin te installeren, te activeren en/of te gebruiken, verklaart u dat u deze disclaimer en de daarin vervatte beperkingen van aansprakelijkheid hebt gelezen, begrepen en aanvaard. Indien u niet akkoord gaat met deze voorwaarden, dient u de plugin onmiddellijk te deactiveren en te verwijderen.' ),
    );
}

function cm_render_beheer_info() {
    echo '<h2>Plugin</h2>';
    echo '<table class="form-table" role="presentation"><tbody>';
    echo '<tr><th scope="row">Versie</th><td>' . esc_html( CM_VERSION ) . '</td></tr>';
    echo '<tr><th scope="row">Gemaakt door</th><td><a href="https://www.cookiebaas.nl/" target="_blank" rel="noopener">Ruud van der Heijden</a></td></tr>';
    echo '<tr><th scope="row">Naam van de toestemmingscookie</th><td><code>cc_cm_consent</code></td></tr>';
    echo '</tbody></table>';

    echo '<h2>Snel aan de slag</h2><ol>';
    foreach ( array(
        'Vul onder Blokkering › Google uw GA4- of GTM-ID in.',
        'Pas onder Banner de kleuren, teksten en weergave aan naar uw huisstijl.',
        'Laad onder Cookies › Scannen de cookiedatabase en voer een scan uit.',
        'Vul de Privacyverklaring in en plaats de shortcode [cookiebaas_privacy] op uw privacypagina.',
        'Test: verwijder de cookie cc_cm_consent en controleer met de ontwikkelaarstools (F12) dat cookies pas na akkoord verschijnen.',
    ) as $step ) {
        echo '<li>' . esc_html( $step ) . '</li>';
    }
    echo '</ol>';

    echo '<h2>Shortcodes</h2>';
    echo '<table class="form-table" role="presentation"><tbody>';
    echo '<tr><th scope="row">Volledige privacyverklaring</th><td><code>[cookiebaas_privacy]</code></td></tr>';
    echo '<tr><th scope="row">Alleen de cookieparagraaf</th><td><code>[cookiebaas_cookies]</code></td></tr>';
    echo '<tr><th scope="row">Pagina met cookievoorkeuren</th><td><code>[cookiebaas_voorkeuren]</code></td></tr>';
    echo '</tbody></table>';

    echo '<h2>Disclaimer en aansprakelijkheid</h2>';
    foreach ( cm_disclaimer_paragraphs() as $p ) {
        echo '<h3>' . esc_html( $p[0] ) . '</h3><p>' . esc_html( $p[1] ) . '</p>';
    }

    echo '<h2>Contact en ondersteuning</h2>';
    echo '<p>Voor vragen: <a href="https://www.cookiebaas.nl/" target="_blank" rel="noopener">cookiebaas.nl</a>.</p>';
}
```

Voeg in het blok `if ( function_exists( 'cm_admin_register_action' ) ) {` onderaan toe:

```php
    cm_admin_register_action( 'api_key_generate', function () {
        cm_set_api_key( bin2hex( random_bytes( 20 ) ) );
        return 'api-key-created';
    } );
    cm_admin_register_action( 'api_key_revoke', function () {
        cm_set_api_key( '' );
        return 'api-key-revoked';
    } );
```

Voeg toe aan `cm_admin_notice_messages()` in `includes/admin/actions.php`:

```php
        'api-key-created'        => array( 'success', 'Er is een nieuwe API-sleutel gemaakt. Zet hem in uw externe koppelingen.' ),
        'api-key-revoked'        => array( 'success', 'De API-sleutel is ingetrokken.' ),
```

- [ ] **Step 4: Link naar Beheer › Info op de privacypagina**

In `includes/admin/page-privacy.php`, in de eerste sectie van `cm_privacy_sections()` ("Waar verschijnt de verklaring?"): vervang de regel `echo '</p>';` onderaan die closure door:

```php
            echo ' Alle shortcodes staan onder <a href="' . esc_url( admin_url( 'admin.php?page=cookiebaas-beheer&tab=info' ) ) . '">Beheer › Info</a>.</p>';
```

- [ ] **Step 5: Draai de tests**

Run: `php tests/test-admin3-beheer.php && php tests/test-admin3-registry.php && php tests/test-admin3-privacy.php && php tests/test-admin3-frame.php`
Expected: alles PASS. De registry-test telt nu elke instelling op precies één plek, zonder uitzonderingslijst voor plan 3.

- [ ] **Step 6: Handmatige check (Ruud)**

- Onder Beheer › Geavanceerd werken "Sleutel maken" en "Intrekken".
- Een `curl` met de sleutel geeft de consent terug; na intrekken niet meer.
- Beheer › Info toont de nieuwe menunamen.

- [ ] **Step 7: README, volledige suite, commit**

Vervang in `tests/README.md` de omschrijving van `test-admin3-beheer.php` door:

```
| `test-admin3-beheer.php` | Beheer: licentiestatus in woorden, meldingen met de tekst van de licentieserver (één keer, nooit "gelukt" bij een fout), lege sleutel niet naar de server, licentiemelding alleen op Cookiebaas-schermen; backup zonder API-sleutel, ongeldig bestand wijzigt niets, import door dezelfde sanitizing als opslaan (API-sleutel blijft), "Alles resetten" meldt een mislukt onderdeel; API-sleutel alleen leeg of 40 hex (een oude eigen sleutel blijft), Info met alle shortcodes en de nieuwe menunamen. |
```

Run: `php tests/run.php`
Expected: alles groen.

```bash
git add includes/admin/page-beheer.php includes/admin/actions.php includes/admin/page-privacy.php tests/test-admin3-beheer.php tests/test-admin3-registry.php tests/test-admin3-privacy.php tests/README.md
git commit -m "feat(admin3): Beheer › Geavanceerd (API-sleutel server-side) en Info

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 7: Overzicht

**Files:**
- Modify: `includes/admin/page-overzicht.php` (volledig vervangen)
- Modify: `assets/css/admin-layout.css` (kaartenraster)
- Modify: `cookiemelding.php` (vlag voor de eenmalige melding)
- Modify: `uninstall.php`
- Test: `tests/test-admin3-overzicht.php` (nieuw)

**Interfaces:**
- Consumes: `cm_license_summary()`, `cm_license_get()`, `cm_license_is_valid()`, `cm_log_table()`, `cm_admin_page_url()`, `cm_privacy_values()`, `cm_get_cookie_list()`, `cm_admin_tabs()`.
- Produces:
  - `cm_overzicht_data(): array` (database en options; niet getest)
  - `cm_overzicht_cards( array $data ): array` (lijst van `array( titel, waarde, toelichting, url, linktekst )`)
  - `cm_compliance_checks( array $s, array $pv, array $cookies ): array` (elk: `group`, `title`, `desc`, `ref`, `status` ok|warn|fail, `url`, `label`, `detail`)
  - `cm_render_compliance_table( array $checks ): void`
  - `cm_flag_admin3_notice( $stored_version ): void`, `cm_render_admin3_welcome_notice(): void`, option `cm_show_admin3_notice`

- [ ] **Step 1: Schrijf de test `tests/test-admin3-overzicht.php`**

```php
<?php
/**
 * Overzicht (3.0) — statusblokken, compliance-check en de eenmalige melding.
 *
 * Borgt: alle vijftien controles uit 2.4 blijven, met dezelfde logica;
 * elke "Oplossen"-link wijst naar een bestaande pagina en tab van de nieuwe
 * admin; de melding "nieuwe indeling" verschijnt één keer, alleen na een
 * update vanaf 2.x.
 */

function sanitize_text_field( $s ) { return is_scalar( $s ) ? trim( strip_tags( (string) $s ) ) : ''; }
function sanitize_textarea_field( $s ) { return is_scalar( $s ) ? trim( strip_tags( (string) $s ) ) : ''; }
function get_pages() { return array(); }
function absint( $v ) { return abs( (int) $v ); }
function add_query_arg( $a, $b = null, $c = null ) {
    if ( is_array( $a ) ) { $args = $a; $url = $b; } else { $args = array( $a => $b ); $url = $c; }
    foreach ( $args as $k => $v ) $url .= ( strpos( $url, '?' ) === false ? '?' : '&' ) . rawurlencode( $k ) . '=' . rawurlencode( $v );
    return $url;
}
function wp_date( $format, $ts = null ) { return 'wpdate:' . $ts; }

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
foreach ( glob( CM_PLUGIN_ROOT . '/includes/admin/*.php' ) as $file ) require $file;
require CM_PLUGIN_ROOT . '/includes/privacy.php';

cm_test_group( 'Statusblokken' );
$cards = cm_overzicht_cards( array( 'license' => 'Actief', 'license_ok' => true, 'cookies' => 1, 'last_scan' => '2026-09-01 10:00:00', 'accept' => 5, 'reject' => 2, 'custom' => 1, 'version' => 3 ) );
cm_assert( 'vier blokken', count( $cards ) === 4 );
cm_assert( 'licentie, met link naar Beheer › Licentie', $cards[0][1] === 'Actief' && strpos( $cards[0][3], 'page=cookiebaas-beheer&tab=licentie' ) !== false );
cm_assert( 'cookies (enkelvoud) met de laatste scan', $cards[1][1] === '1 cookie' && strpos( $cards[1][2], 'wpdate:' ) !== false );
cm_assert( 'toestemmingen in 30 dagen', $cards[2][1] === '8' && strpos( $cards[2][2], '5 akkoord, 2 geweigerd, 1 aangepast' ) !== false );
cm_assert( 'consent-versie, met link naar Bewaren', $cards[3][1] === '3' && strpos( $cards[3][3], 'tab=bewaren' ) !== false );
$cards = cm_overzicht_cards( array( 'license' => 'Geen licentie', 'license_ok' => false, 'cookies' => 0, 'last_scan' => '', 'accept' => 0, 'reject' => 0, 'custom' => 0, 'version' => 1 ) );
cm_assert( 'nog geen scan', $cards[1][1] === '0 cookies' && strpos( $cards[1][2], 'Nog geen automatische scan' ) !== false );

cm_test_group( 'Compliance-check' );
$s      = cm_default_settings();
$pv     = cm_default_privacy();
$checks = cm_compliance_checks( $s, $pv, cm_default_cookies() );
cm_assert( 'alle vijftien controles uit 2.4', count( $checks ) === 15 );
cm_assert( 'alleen de statussen ok, warn en fail', ! array_diff( array_unique( array_column( $checks, 'status' ) ), array( 'ok', 'warn', 'fail' ) ) );
$tabs = cm_admin_tabs();
$bad  = array();
foreach ( $checks as $c ) {
    parse_str( (string) parse_url( $c['url'], PHP_URL_QUERY ), $q );
    $page = isset( $q['page'] ) ? $q['page'] : '';
    $tab  = isset( $q['tab'] ) ? $q['tab'] : '';
    if ( ! isset( $tabs[ $page ] ) || ( $tab !== '' && ! isset( $tabs[ $page ][ $tab ] ) ) ) $bad[] = $c['title'] . ' → ' . $c['url'];
}
cm_assert( 'elke "Oplossen"-link wijst naar een bestaande pagina en tab' . ( $bad ? ': ' . implode( '; ', $bad ) : '' ), ! $bad );
$ent = array_filter( $checks, function ( $c ) { return preg_match( '/&[a-z]+;/', $c['title'] . $c['desc'] . $c['ref'] . $c['detail'] ); } );
cm_assert( 'teksten met echte tekens, zonder HTML-entities', ! $ent );
$by = array();
foreach ( cm_compliance_checks( array_merge( $s, array( 'analytics_default' => '1', 'show_float_btn' => '0', 'expiry_months' => 24, 'log_retention_months' => 0 ) ), array_merge( $pv, array( 'pv_bedrijfsnaam' => '' ) ), cm_default_cookies() ) as $c ) $by[ $c['title'] ] = $c;
cm_assert( 'vooraf aangevinkt → niet in orde', $by['2. Geen vooraf aangevinkte keuzes']['status'] === 'fail' );
cm_assert( 'geen zweefknop → niet in orde', $by['7. Intrekken is even makkelijk als geven']['status'] === 'fail' );
cm_assert( 'geldigheid langer dan 12 maanden → aandacht', $by['Toestemming verloopt binnen 12 maanden']['status'] === 'warn' );
cm_assert( 'geen bewaartermijn → aandacht, met uitleg', $by['Registraties worden automatisch opgeschoond']['status'] === 'warn' && $by['Registraties worden automatisch opgeschoond']['detail'] !== '' );
cm_assert( 'alleen ingebouwde cookies → aandacht', $by['Elke cookie heeft een doel en looptijd']['status'] === 'warn' );
cm_assert( 'lege bedrijfsnaam → niet in orde', $by['Bedrijfsnaam ingevuld']['status'] === 'fail' );

cm_test_group( 'Weergave van de check' );
ob_start(); cm_render_compliance_table( $checks ); $h = ob_get_clean();
cm_assert( 'statusicoon per controle', substr_count( $h, 'class="dashicons dashicons-' ) === 15 );
cm_assert( '"Oplossen" alleen bij wat niet in orde is', substr_count( $h, 'Oplossen:' ) === count( array_filter( $checks, function ( $c ) { return $c['status'] !== 'ok'; } ) ) );

cm_test_group( 'Eenmalige melding na de update (spec §6.4)' );
cm_flag_admin3_notice( '0' );
cm_assert( 'nieuwe installatie → geen melding', get_option( 'cm_show_admin3_notice' ) === false );
cm_flag_admin3_notice( '3.0.0' );
cm_assert( 'al op 3.0 → geen melding', get_option( 'cm_show_admin3_notice' ) === false );
cm_flag_admin3_notice( '2.4.6' );
cm_assert( 'update vanaf 2.x → melding klaarzetten', (bool) get_option( 'cm_show_admin3_notice' ) );
ob_start(); cm_render_admin3_welcome_notice(); $h = ob_get_clean();
cm_assert( 'melding met link naar "Waar staat wat?"', strpos( $h, 'notice-info' ) !== false && strpos( $h, '#waar-staat-wat' ) !== false );
ob_start(); cm_render_admin3_welcome_notice(); $h = ob_get_clean();
cm_assert( 'maar één keer', $h === '' );

exit( cm_test_summary() );
```

- [ ] **Step 2: Draai de test en zie hem falen**

Run: `php tests/test-admin3-overzicht.php`
Expected: fatal "Call to undefined function cm_overzicht_cards()".

- [ ] **Step 3: Vervang `includes/admin/page-overzicht.php` volledig**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   OVERZICHT — statusblokken en de compliance-check. De pagina is actueel
   bij het laden; "Opnieuw controleren" is niet meer nodig.
================================================================ */

function cm_tabs_overzicht() {
    return array(
        'overzicht' => array( 'label' => 'Overzicht', 'render' => 'cm_admin_render_overzicht' ),
    );
}

/** Gegevens voor de statusblokken (database en options). */
function cm_overzicht_data() {
    global $wpdb;
    $table = cm_log_table();
    $stats = $wpdb->get_row(
        "SELECT SUM(CASE WHEN method IN ('accept-all','embed-accept') THEN 1 ELSE 0 END) AS accept,
                SUM(CASE WHEN method = 'reject-all' THEN 1 ELSE 0 END) AS reject,
                SUM(CASE WHEN method = 'custom' THEN 1 ELSE 0 END) AS custom
         FROM `{$table}` WHERE created_at >= NOW() - INTERVAL 30 DAY AND method != 'pageload'",
        ARRAY_A
    );
    $stats   = is_array( $stats ) ? $stats : array();
    $managed = array_filter( cm_get_cookie_list(), function ( $c ) { return empty( $c['builtin'] ); } );
    list( $type, $word ) = cm_license_summary( cm_license_get(), cm_license_is_valid() );
    return array(
        'license'    => $word,
        'license_ok' => $type === 'success',
        'cookies'    => count( $managed ),
        'last_scan'  => (string) get_option( 'cm_auto_scan_last', '' ),
        'accept'     => isset( $stats['accept'] ) ? (int) $stats['accept'] : 0,
        'reject'     => isset( $stats['reject'] ) ? (int) $stats['reject'] : 0,
        'custom'     => isset( $stats['custom'] ) ? (int) $stats['custom'] : 0,
        'version'    => (int) get_option( 'cm_consent_version', 1 ),
    );
}

/** Statusblokken: array( titel, waarde, toelichting, url, linktekst ). */
function cm_overzicht_cards( array $d ) {
    $scan = $d['last_scan'] !== ''
        ? 'Laatste automatische scan: ' . wp_date( 'j F Y', strtotime( $d['last_scan'] . ' UTC' ) ) . '.'
        : 'Nog geen automatische scan.';
    return array(
        array( 'Licentie', $d['license'], $d['license_ok'] ? 'De cookiescan is beschikbaar.' : 'Banner en blokkering werken; de cookiescan is gepauzeerd.', cm_admin_page_url( 'cookiebaas-beheer', 'licentie' ), 'Licentie beheren' ),
        array( 'Cookies', $d['cookies'] === 1 ? '1 cookie' : $d['cookies'] . ' cookies', $scan, cm_admin_page_url( 'cookiebaas-cookies', 'lijst' ), 'Cookielijst bekijken' ),
        array( 'Toestemmingen', (string) ( $d['accept'] + $d['reject'] + $d['custom'] ), 'Laatste 30 dagen: ' . $d['accept'] . ' akkoord, ' . $d['reject'] . ' geweigerd, ' . $d['custom'] . ' aangepast.', cm_admin_page_url( 'cookiebaas-log', 'registraties' ), 'Consent log openen' ),
        array( 'Consent-versie', (string) $d['version'], 'Verhoog de versie om iedereen opnieuw te laten kiezen.', cm_admin_page_url( 'cookiebaas-log', 'bewaren' ), 'Opnieuw laten kiezen' ),
    );
}

/** De controles uit 2.4, met dezelfde logica en links naar de nieuwe tabs. */
function cm_compliance_checks( array $s, array $pv, array $cookies ) {
    $luminance = function ( $hex ) {
        $hex = ltrim( (string) $hex, '#' );
        if ( strlen( $hex ) === 3 ) $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        if ( strlen( $hex ) !== 6 ) return 0.5;
        return 0.299 * hexdec( substr( $hex, 0, 2 ) ) / 255 + 0.587 * hexdec( substr( $hex, 2, 2 ) ) / 255 + 0.114 * hexdec( substr( $hex, 4, 2 ) ) / 255;
    };
    $get        = function ( $k ) use ( $s ) { return isset( $s[ $k ] ) ? trim( (string) $s[ $k ] ) : ''; };
    $reject_lum = $luminance( $get( 'color_reject_bg' ) !== '' ? $get( 'color_reject_bg' ) : 'f5f2ee' );
    $accept_lum = $luminance( $get( 'color_accept_bg' ) !== '' ? $get( 'color_accept_bg' ) : '111111' );
    $prominence = $reject_lum < 0.5 || abs( $reject_lum - $accept_lum ) < 0.45;
    $body       = $get( 'txt_banner_body' );
    $has_link   = strpos( $body, 'href' ) !== false || strpos( $body, 'privac' ) !== false;
    $self_load  = preg_match( '/^G-[A-Z0-9]+$/i', $get( 'ga4_measurement_id' ) ) || preg_match( '/^GTM-[A-Z0-9]+$/i', $get( 'gtm_container_id' ) ) || preg_match( '/^UA-[0-9]+-[0-9]+$/i', $get( 'ua_tracking_id' ) );
    $blocking   = $get( 'block_analytics_patterns' ) !== '' || $get( 'block_marketing_patterns' ) !== '' || $self_load;
    $expiry     = (int) ( $get( 'expiry_months' ) !== '' ? $get( 'expiry_months' ) : 12 );
    $retention  = (int) $get( 'log_retention_months' );
    $cats_ok    = $get( 'txt_cat1_long' ) !== '' && $get( 'txt_cat2_long' ) !== '' && $get( 'txt_cat3_long' ) !== '';
    $managed    = array_filter( $cookies, function ( $c ) { return empty( $c['builtin'] ); } );
    $incomplete = array_filter( $managed, function ( $c ) { return empty( $c['purpose'] ) || empty( $c['duration'] ); } );

    $banner  = function ( $tab ) { return cm_admin_page_url( 'cookiebaas-banner', $tab ); };
    $block   = function ( $tab ) { return cm_admin_page_url( 'cookiebaas-blokkering', $tab ); };
    $privacy = cm_admin_page_url( 'cookiebaas-privacy' );
    $checks  = array();
    $add = function ( $group, $title, $desc, $ref, $status, $url, $label, $detail = '' ) use ( &$checks ) {
        $checks[] = compact( 'group', 'title', 'desc', 'ref', 'status', 'url', 'label', 'detail' );
    };

    $g = 'AP-vuistregels voor cookiebanners';
    $add( $g, '1. Weigeren is even makkelijk als accepteren', 'De banner heeft een knop “Akkoord” én een knop “Weigeren” op dezelfde laag, even opvallend. Weigeren kost geen extra klik.', 'AP-vuistregel 1 · AVG art. 7 lid 3 · EDPB 05/2020', $prominence ? 'ok' : 'warn', $banner( 'vormgeving' ), 'Knopkleuren aanpassen', $prominence ? '' : 'De weigerknop is veel lichter dan de akkoordknop.' );
    $add( $g, '2. Geen vooraf aangevinkte keuzes', 'Analytische en marketingcookies staan in het voorkeurenvenster standaard uit. Toestemming vraagt een actieve handeling (opt-in).', 'AP-vuistregel 2 · HvJEU Planet49 (C-673/17)', empty( $s['analytics_default'] ) ? 'ok' : 'fail', $banner( 'gedrag' ), 'Standaardkeuzes aanpassen' );
    $add( $g, '4. Duidelijke uitleg over het doel', 'Het voorkeurenvenster toont per categorie een beschrijving, en per dienst of cookie het doel, de looptijd en de aanbieder.', 'AP-vuistregel 4 · AVG art. 13 · Tw art. 11.7a lid 1', $cats_ok ? 'ok' : 'warn', $banner( 'teksten' ), 'Teksten aanvullen', $cats_ok ? '' : 'Niet alle cookiecategorieën hebben een beschrijving.' );
    $add( $g, '7. Intrekken is even makkelijk als geven', 'Een zweefknop op elke pagina opent de cookievoorkeuren opnieuw, met dezelfde knoppen. Bij intrekken worden de cookies actief verwijderd.', 'AP-vuistregel 7 · AVG art. 7 lid 3 · AP-normuitleg 2024', ! empty( $s['show_float_btn'] ) ? 'ok' : 'fail', $banner( 'weergave' ), 'Zweefknop inschakelen', ! empty( $s['show_float_btn'] ) ? '' : 'De zweefknop staat uit: bezoekers kunnen hun toestemming niet makkelijk intrekken.' );
    $add( $g, '8. Geen misleidend ontwerp', 'De knoppen zijn even groot en hebben dezelfde stijl. Geen kleurverschil dat akkoord bevoordeelt, geen verwarrende teksten of dubbele ontkenningen.', 'AP-vuistregel 8 · EDPB-richtlijnen 3/2022', $prominence ? 'ok' : 'warn', $banner( 'vormgeving' ), 'Knopkleuren aanpassen' );
    $add( $g, '9. Link naar de privacyverklaring', 'De bannertekst linkt naar de privacyverklaring, zodat bezoekers zich vóór hun keuze kunnen informeren.', 'AP-vuistregel 9 · AVG art. 13/14', $has_link ? 'ok' : 'warn', $banner( 'teksten' ), 'Bannertekst aanpassen' );

    $g = 'Techniek';
    $add( $g, 'Scripts geblokkeerd vóór toestemming', 'Drie lagen: de output-buffer in PHP, een MutationObserver in JavaScript en Google Consent Mode v2. Scripts laden pas na toestemming.', 'Tw art. 11.7a · ePrivacyrichtlijn', $blocking ? 'ok' : 'warn', $block( 'google' ), 'Blokkering instellen', $blocking ? '' : 'Er is geen GA4- of GTM-ID en geen blokkeerpatroon ingesteld.' );
    $add( $g, 'Embeds geblokkeerd vóór toestemming', 'YouTube, Vimeo, Google Maps, Spotify, TikTok en meer worden automatisch geblokkeerd en vervangen door een placeholder.', 'Tw art. 11.7a · AP-standpunt over ingesloten content', ! empty( $s['embed_blocker_enabled'] ) ? 'ok' : 'warn', $block( 'embeds' ), 'Embedblokkering inschakelen' );
    $add( $g, 'Google Consent Mode v2', 'Automatische koppeling met GA4 en GTM: standaard “denied”, na toestemming “granted”.', 'Google EU User Consent Policy · Digital Markets Act', $self_load ? 'ok' : 'warn', $block( 'google' ), 'GA4- of GTM-ID invullen', $self_load ? '' : 'Vul een GA4- of GTM-ID in voor automatische Consent Mode v2.' );
    $add( $g, 'Toestemming verloopt binnen 12 maanden', 'De toestemmingscookie verloopt na de ingestelde periode. De AP adviseert maximaal 12 maanden.', 'AP-handhavingscriteria · EDPB-aanbeveling', $expiry <= 12 ? 'ok' : 'warn', $banner( 'gedrag' ), 'Geldigheid aanpassen', $expiry <= 12 ? '' : 'Nu ingesteld: ' . $expiry . ' maanden.' );

    $g = 'Verantwoordingsplicht (AVG art. 5 lid 2)';
    $ret_ok = $retention > 0 && $retention <= 36;
    $add( $g, 'Registraties worden automatisch opgeschoond', 'Registraties in de consent log worden dagelijks verwijderd na de ingestelde bewaartermijn.', 'AVG art. 5 lid 1e (opslagbeperking)', $ret_ok ? 'ok' : 'warn', cm_admin_page_url( 'cookiebaas-log', 'bewaren' ), 'Bewaartermijn instellen', $retention === 0 ? 'Registraties worden nooit automatisch verwijderd.' : ( $retention > 36 ? 'Overweeg een kortere bewaartermijn.' : '' ) );
    $cookie_ok = count( $managed ) > 0 && count( $incomplete ) === 0;
    $add( $g, 'Elke cookie heeft een doel en looptijd', 'Per cookie staan het doel en de bewaartermijn in het voorkeurenvenster.', 'AVG art. 13 · Tw art. 11.7a lid 1', $cookie_ok ? 'ok' : 'warn', cm_admin_page_url( 'cookiebaas-cookies', 'lijst' ), 'Cookielijst aanvullen', count( $managed ) === 0 ? 'Er staan nog geen eigen cookies in de lijst.' : ( count( $incomplete ) > 0 ? count( $incomplete ) . ' cookie(s) missen een doel of looptijd.' : '' ) );

    $g = 'Privacyverklaring';
    $add( $g, 'Bedrijfsnaam ingevuld', 'De verwerkingsverantwoordelijke staat duidelijk in de privacyverklaring.', 'AVG art. 13 lid 1a', ! empty( $pv['pv_bedrijfsnaam'] ) ? 'ok' : 'fail', $privacy, 'Privacyverklaring aanvullen' );
    $add( $g, 'Contactgegevens ingevuld', 'Contactgegevens zijn verplicht, zodat betrokkenen hun rechten kunnen uitoefenen.', 'AVG art. 13 lid 1a', ! empty( $pv['pv_email'] ) ? 'ok' : 'fail', $privacy, 'Privacyverklaring aanvullen' );
    $add( $g, 'Datum van bijwerken ingevuld', 'Bezoekers zien wanneer de privacyverklaring voor het laatst is bijgewerkt.', 'AVG art. 13 · transparantiebeginsel', ! empty( $pv['pv_datum'] ) ? 'ok' : 'warn', $privacy, 'Datum invullen' );

    return $checks;
}

function cm_render_compliance_table( array $checks ) {
    $status = array(
        'ok'   => array( 'yes-alt', 'In orde' ),
        'warn' => array( 'warning', 'Aandacht nodig' ),
        'fail' => array( 'dismiss', 'Niet in orde' ),
    );
    $group = null;
    echo '<table class="widefat striped cm-checks"><tbody>';
    foreach ( $checks as $c ) {
        if ( $c['group'] !== $group ) {
            $group = $c['group'];
            echo '<tr><th colspan="3" scope="colgroup"><strong>' . esc_html( $group ) . '</strong></th></tr>';
        }
        list( $icon, $label ) = $status[ $c['status'] ];
        echo '<tr>';
        echo '<td class="cm-check-status"><span class="dashicons dashicons-' . esc_attr( $icon ) . '" aria-hidden="true"></span> ' . esc_html( $label ) . '</td>';
        echo '<td><strong>' . esc_html( $c['title'] ) . '</strong><br>' . esc_html( $c['desc'] );
        if ( $c['status'] !== 'ok' && $c['detail'] !== '' ) echo '<br><em>' . esc_html( $c['detail'] ) . '</em>';
        echo '<br><span class="description">' . esc_html( $c['ref'] ) . '</span></td>';
        echo '<td>' . ( $c['status'] !== 'ok' ? '<a href="' . esc_url( $c['url'] ) . '">Oplossen: ' . esc_html( $c['label'] ) . '</a>' : '' ) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

/** Na de update naar 3.0 één keer laten zien dat de admin een nieuwe indeling heeft (niet bij een nieuwe installatie). */
function cm_flag_admin3_notice( $stored_version ) {
    if ( $stored_version !== '0' && version_compare( $stored_version, '3.0.0', '<' ) ) update_option( 'cm_show_admin3_notice', 1 );
}

function cm_render_admin3_welcome_notice() {
    if ( ! get_option( 'cm_show_admin3_notice' ) ) return;
    delete_option( 'cm_show_admin3_notice' );
    echo '<div class="notice notice-info is-dismissible"><p>De admin van Cookiebaas heeft een nieuwe indeling: elk onderwerp heeft nu een eigen menu-item. <a href="https://github.com/ruudvanderheijden/cookiebaas/blob/main/CHANGELOG.md#waar-staat-wat" target="_blank" rel="noopener">Waar staat wat?</a></p></div>';
}

function cm_admin_render_overzicht() {
    cm_render_admin3_welcome_notice();
    echo '<div class="cm-cards">';
    foreach ( cm_overzicht_cards( cm_overzicht_data() ) as $card ) {
        echo '<div class="card"><h2 class="title">' . esc_html( $card[0] ) . '</h2>'
           . '<p><strong>' . esc_html( $card[1] ) . '</strong></p>'
           . '<p>' . esc_html( $card[2] ) . '</p>'
           . '<p><a href="' . esc_url( $card[3] ) . '">' . esc_html( $card[4] ) . '</a></p></div>';
    }
    echo '</div>';

    $checks = cm_compliance_checks( cm_get_settings(), cm_privacy_values(), cm_get_cookie_list() );
    $ok     = count( array_filter( $checks, function ( $c ) { return $c['status'] === 'ok'; } ) );
    echo '<h2>Compliance-check</h2>';
    echo '<p>' . esc_html( $ok . ' van de ' . count( $checks ) . ' controles zijn in orde.' ) . ' Deze check is geen juridisch advies; lees de <a href="' . esc_url( cm_admin_page_url( 'cookiebaas-beheer', 'info' ) ) . '">disclaimer</a>.</p>';
    cm_render_compliance_table( $checks );
}
```

- [ ] **Step 4: CSS, vlag bij de update, uninstall**

Voeg onderaan `assets/css/admin-layout.css` toe:

```css
/* Overzicht: kaartenraster en statuskolom */
.cm-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px; margin: 16px 0 24px; }
.cm-cards .card { margin: 0; max-width: none; }
.cm-checks .cm-check-status { width: 160px; white-space: nowrap; }
```

In `cookiemelding.php`, in de `plugins_loaded`-closure: voeg direct na `if ( version_compare( $stored_version, CM_VERSION, '<' ) ) {` toe:

```php
        // 3.0: eenmalig laten zien dat de admin een nieuwe indeling heeft
        if ( function_exists( 'cm_flag_admin3_notice' ) ) cm_flag_admin3_notice( $stored_version );
```

In `uninstall.php`: voeg na `delete_option( 'cm_auto_scan_last_found' );` toe:

```php
delete_option( 'cm_show_admin3_notice' );
```

- [ ] **Step 5: Draai de tests**

Run: `php tests/test-admin3-overzicht.php && php tests/test-admin3-frame.php && php tests/test-admin3-registry.php`
Expected: alles PASS.

- [ ] **Step 6: Handmatige check (Ruud)**

- Overzicht toont vier kaarten in een raster.
- De compliance-check heeft iconen, en elke "Oplossen"-link komt op de juiste tab uit.
- De melding over de nieuwe indeling is pas na Taak 10 te zien (bij de overgang naar 3.0.0).

- [ ] **Step 7: README, volledige suite, commit**

Voeg in `tests/README.md` na de regel van `test-admin3-beheer.php` toe:

```
| `test-admin3-overzicht.php` | Overzicht: vier statusblokken, alle vijftien compliance-controles uit 2.4 met dezelfde logica, elke "Oplossen"-link naar een bestaande pagina en tab, eenmalige melding alleen na een update vanaf 2.x. |
```

Run: `php tests/run.php`
Expected: alles groen.

```bash
git add includes/admin/page-overzicht.php assets/css/admin-layout.css cookiemelding.php uninstall.php tests/test-admin3-overzicht.php tests/README.md
git commit -m "feat(admin3): Overzicht met statusblokken en compliance-check, eenmalige melding na de update

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 8: Oude admin verwijderen

**Files:**
- Create: `includes/admin/scan.php` (verhuisd uit `includes/admin.php`)
- Create: `includes/consent.php` (verhuisd uit `includes/admin.php`)
- Delete: `includes/admin.php`, `assets/js/admin.js`, `assets/css/admin.css`
- Modify: `cookiemelding.php`, `includes/license.php`, `includes/privacy.php`, `includes/admin/ajax.php`, `includes/admin/settings.php`, `assets/css/frontend.css`
- Modify tests: `tests/test-admin-fixes.php` (volledig), `tests/test-admin3-cookies.php`, `tests/test-cookie-scan.php`, `tests/run.php`, `tests/README.md`
- Test: `tests/test-admin3-overstap.php` (nieuw)

**Interfaces:**
- Consumes: niets nieuws.
- Produces:
  - `includes/admin/scan.php`: `cm_ajax_import_cookie_db`, `cm_parse_csv_line`, `cm_ajax_scan_urls`, `cm_ajax_scan_batch`, `cm_cookie_prefix_match`, `cm_fallback_cookies`, `cm_server_env_cookies`, `cm_script_signatures`, `cm_secs_to_human`, met hun `wp_ajax_`-registraties. Alles ongewijzigd.
  - `includes/consent.php`: `cm_ajax_geo_check`, `cm_ajax_log_consent`, met de `wp_ajax_` en `wp_ajax_nopriv_`-registraties. Alles ongewijzigd.
  - `cm_admin_verify_ajax()` accepteert alleen nog de nonce per actie.

- [ ] **Step 1: Schrijf de test `tests/test-admin3-overstap.php`**

```php
<?php
/**
 * Overstap naar 3.0 — de oude admin is weg, wat nog nodig is, is verhuisd.
 *
 * Borgt: de oude bestanden, de gedeelde nonce en de oude AJAX-handlers
 * zijn weg; scan, cookiedatabase en de frontend-AJAX (consent loggen,
 * geo-check) bestaan nog, met dezelfde actienamen.
 */

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';

cm_test_group( 'Oude admin is weg' );
foreach ( array( 'includes/admin.php', 'assets/js/admin.js', 'assets/css/admin.css' ) as $f ) {
    cm_assert( "$f verwijderd", ! file_exists( CM_PLUGIN_ROOT . '/' . $f ) );
}
$src = '';
foreach ( array_merge( array( CM_PLUGIN_ROOT . '/cookiemelding.php' ), glob( CM_PLUGIN_ROOT . '/includes/*.php' ), glob( CM_PLUGIN_ROOT . '/includes/admin/*.php' ), glob( CM_PLUGIN_ROOT . '/assets/js/*.js' ) ) as $f ) {
    $src .= file_get_contents( $f );
}
cm_assert( 'geen gedeelde nonce cm_save_settings meer', strpos( $src, 'cm_save_settings' ) === false );
cm_assert( 'geen links naar de oude slugs', strpos( $src, 'page=cookiemelding' ) === false );
cm_assert( 'geen oude AJAX-handlers', ! preg_match( '/wp_ajax_cm_(save_settings|save_privacy|save_cookie_list|get_cookie_list|get_log|export_|import_settings|reset_|bump_consent|license_|save_scan_settings|clear_log|delete_log_row|save_license_url)/', $src ) );

cm_test_group( 'Verhuisd, niet verdwenen' );
require CM_PLUGIN_ROOT . '/includes/admin/scan.php';
require CM_PLUGIN_ROOT . '/includes/consent.php';
foreach ( array( 'cm_ajax_import_cookie_db', 'cm_parse_csv_line', 'cm_ajax_scan_urls', 'cm_ajax_scan_batch', 'cm_cookie_prefix_match', 'cm_fallback_cookies', 'cm_server_env_cookies', 'cm_script_signatures', 'cm_secs_to_human', 'cm_ajax_geo_check', 'cm_ajax_log_consent' ) as $fn ) {
    cm_assert( "$fn bestaat", function_exists( $fn ) );
}
foreach ( array( 'wp_ajax_cm_scan_urls', 'wp_ajax_cm_scan_batch', 'wp_ajax_cm_import_cookie_db', 'wp_ajax_cm_log_consent', 'wp_ajax_nopriv_cm_log_consent', 'wp_ajax_cm_geo_check', 'wp_ajax_nopriv_cm_geo_check' ) as $hook ) {
    cm_assert( "$hook geregistreerd", has_action( $hook ) );
}
$main = file_get_contents( CM_PLUGIN_ROOT . '/cookiemelding.php' );
cm_assert( 'de scanmail linkt naar de nieuwe cookielijst (Review Focus 3)', strpos( $main, "page=cookiebaas-cookies&tab=lijst" ) !== false );
cm_assert( 'cookiemelding.php laadt consent.php en scan.php', strpos( $main, "includes/consent.php" ) !== false && strpos( $main, "includes/admin/scan.php" ) !== false );

exit( cm_test_summary() );
```

Vervang `tests/test-admin-fixes.php` volledig door:

```php
<?php
/**
 * Admin-bugfixes v2.4.5 die buiten de nieuwe admin vallen.
 *
 * Borgt: regeleinden in de privacyverklaring, categorie/provider bij de
 * automatische scan, "none" bij de embed-diensten en het legen van de
 * paginacache bij elke inhoudswijziging. Reset, import, logfilter en
 * kleurwaarden worden sinds 3.0 getest bij hun nieuwe plek
 * (test-admin3-beheer.php, -log.php, -fields.php).
 */

// Realistischere stubs dan de bootstrap: de fixes draaien juist om wat
// sanitize_text_field wél en sanitize_textarea_field níet weghaalt.
function sanitize_text_field( $s ) {
    if ( is_array( $s ) || is_object( $s ) ) return '';
    return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) );
}
function sanitize_textarea_field( $s ) {
    if ( is_array( $s ) || is_object( $s ) ) return '';
    return trim( strip_tags( (string) $s ) );
}
$GLOBALS['cm_test_purges'] = 0;
function cm_purge_page_caches() { $GLOBALS['cm_test_purges']++; }

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
require CM_PLUGIN_ROOT . '/includes/privacy.php';
require CM_PLUGIN_ROOT . '/includes/admin/fields.php';
require CM_PLUGIN_ROOT . '/includes/admin/settings.php';

cm_test_group( 'Privacyverklaring bewaart regeleinden' );
$pv = cm_sanitize_privacy( array( 'pv_doorgifte' => "Regel een\nRegel twee", 'pv_bedrijfsnaam' => "Bedrijf\nBV" ) );
cm_assert( 'textarea pv_doorgifte houdt regeleinde', $pv['pv_doorgifte'] === "Regel een\nRegel twee" );
cm_assert( 'tekstveld pv_bedrijfsnaam blijft één regel', $pv['pv_bedrijfsnaam'] === 'Bedrijf BV' );

cm_test_group( 'Elke inhoudswijziging leegt de paginacache' );
foreach ( array( 'cm_settings', 'cm_cookie_list', 'cm_privacy', 'cm_consent_version' ) as $opt ) {
    $before = $GLOBALS['cm_test_purges'];
    update_option( $opt, array( 'x' => 1 ) );
    cm_assert( "$opt opslaan → paginacache geleegd", $GLOBALS['cm_test_purges'] === $before + 1 );
}

cm_test_group( 'Automatische scan: categorie en provider uit de cookie-DB' );
$row = array( 'platform' => 'Google Analytics', 'controller' => 'Google', 'category' => 'analytics', 'description' => 'Meet bezoek', 'retention' => '2 jaar' );
$e = cm_autoscan_entry( '_ga', $row );
cm_assert( 'DB-categorie analytics blijft analytics', $e['category'] === 'analytics' );
cm_assert( 'provider komt uit platform', $e['provider'] === 'Google Analytics' );
cm_assert( 'looptijd en omschrijving overgenomen', $e['duration'] === '2 jaar' && $e['purpose'] === 'Meet bezoek' );
$e = cm_autoscan_entry( 'x_ad', array( 'platform' => '', 'controller' => 'AdCo', 'category' => 'marketing', 'description' => '', 'retention' => '' ) );
cm_assert( 'marketing blijft marketing, provider valt terug op controller', $e['category'] === 'marketing' && $e['provider'] === 'AdCo' );
$e = cm_autoscan_entry( 'onbekend_ding', false );
cm_assert( 'zonder DB-rij: functional / Onbekend', $e['category'] === 'functional' && $e['provider'] === 'Onbekend' );

cm_test_group( 'Embeds: "none" blokkeert niets, leeg blokkeert alles' );
cm_test_set_settings( array( 'embed_blocked_services' => 'none' ) );
cm_assert( '"none" → YouTube niet geblokkeerd', cm_match_embed_domain( 'https://www.youtube.com/embed/abc' ) === null );
cm_test_set_settings( array( 'embed_blocked_services' => '' ) );
cm_assert( 'leeg → YouTube geblokkeerd', cm_match_embed_domain( 'https://www.youtube.com/embed/abc' ) !== null );

exit( cm_test_summary() );
```

Vervang in `tests/test-admin3-cookies.php` en `tests/test-cookie-scan.php` de regel `require CM_PLUGIN_ROOT . '/includes/admin.php';` door:

```php
require CM_PLUGIN_ROOT . '/includes/admin/scan.php';
```

Vervang in `tests/test-admin3-cookies.php` de assertie `'gedeelde nonce van de oude admin wordt nog geaccepteerd'` door:

```php
cm_assert( 'gedeelde nonce van de oude admin wordt niet meer geaccepteerd', verify_result( 'nonce-cm_save_settings' ) === 'fout' );
```

- [ ] **Step 2: Draai de tests en zie ze falen**

Run: `php tests/test-admin3-overstap.php; php tests/test-cookie-scan.php`
Expected: de overstaptest faalt op "includes/admin.php verwijderd" en op het ontbreken van `scan.php`; de scantest faalt omdat `includes/admin/scan.php` niet bestaat.

- [ ] **Step 3: Verhuis de scan naar `includes/admin/scan.php`**

Maak het bestand met deze kop:

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   COOKIESCAN — AJAX voor de handmatige scan en de cookiedatabase, en de
   kennisbank die de scan gebruikt. In 3.0 ongewijzigd verhuisd uit de
   oude includes/admin.php.
================================================================ */
```

Plak eronder, **ongewijzigd**, het blok uit `includes/admin.php` vanaf de regel `/* ====…` direct boven `   OPEN COOKIE DATABASE — IMPORT` tot en met de regel vóór `add_action( 'wp_ajax_cm_export_register', 'cm_ajax_export_register' );`. Dat kan zo:

```bash
s=$(grep -n 'OPEN COOKIE DATABASE — IMPORT' includes/admin.php | cut -d: -f1)
e=$(grep -n "add_action( 'wp_ajax_cm_export_register'" includes/admin.php | cut -d: -f1)
sed -n "$((s-1)),$((e-1))p" includes/admin.php >> includes/admin/scan.php
```

Controleer de broncheck. Het blok is vooraf nagelopen: geen `style="`, geen hex-kleuren, geen `&#`-entities en geen emoji.

- [ ] **Step 4: Verhuis de frontend-AJAX naar `includes/consent.php`**

Maak het bestand met deze kop:

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   FRONTEND-AJAX — consent loggen en de geo-check. Via admin-ajax, dus
   nooit in de paginacache. In 3.0 ongewijzigd verhuisd uit de oude
   includes/admin.php.
================================================================ */
```

Plak eronder, **ongewijzigd**, het blok uit `includes/admin.php` vanaf de regel `/* ====…` direct boven `   AJAX — GEO-CHECK` tot en met de regel vóór `add_action( 'wp_ajax_cm_get_log', 'cm_ajax_get_log' );`:

```bash
s=$(grep -n 'AJAX — GEO-CHECK' includes/admin.php | cut -d: -f1)
e=$(grep -n "add_action( 'wp_ajax_cm_get_log'" includes/admin.php | cut -d: -f1)
sed -n "$((s-1)),$((e-1))p" includes/admin.php >> includes/consent.php
```

`cm_log_where()` zit daar niet meer tussen; die is in Taak 1 al verhuisd.

- [ ] **Step 5: Verwijder de oude admin**

```bash
git rm includes/admin.php assets/js/admin.js assets/css/admin.css
```

In `cookiemelding.php`:
1. Vervang `require_once CM_PLUGIN_DIR . 'includes/admin.php';` door `require_once CM_PLUGIN_DIR . 'includes/consent.php';`.
2. Voeg na `require_once CM_PLUGIN_DIR . 'includes/admin/ajax.php';` toe: `require_once CM_PLUGIN_DIR . 'includes/admin/scan.php';`.
3. Verwijder het blok vanaf de regel `// AJAX handler om scan-instellingen op te slaan vanaf de cookielijst-pagina` tot en met de sluitende `}` van `cm_ajax_reset_scan_timer()`. Dat zijn beide oude AJAX-handlers met hun `add_action`. `cm_maybe_schedule_auto_scan_cron()` en `cm_force_reset_auto_scan_cron()` blijven; de nieuwe admin gebruikt ze.
4. Vervang in de scanmail `admin_url('admin.php?page=cookiemelding-cookies')` door `admin_url( 'admin.php?page=cookiebaas-cookies&tab=lijst' )`.

In `includes/license.php`: verwijder het blok vanaf de kop `/* ===… AJAX HANDLERS ===… */` tot en met de sluitende `}` van `cm_ajax_license_check()`. Beheer › Licentie gebruikt sinds Taak 4 `admin-post.php`.

In `includes/privacy.php`: verwijder het blok vanaf de kop `/* ===… AJAX — Privacy instellingen opslaan ===… */` tot en met de sluitende `}` van `cm_ajax_save_privacy()`.

In `includes/admin/ajax.php`: verwijder in `cm_admin_verify_ajax()` deze twee regels:

```php
    // ponytail: de oude admin (tot plan 3) stuurt nog de gedeelde nonce; weg met de oude admin
    if ( wp_verify_nonce( $nonce, 'cm_save_settings' ) ) return;
```

In `includes/admin/settings.php`: vervang in de kop de regels
`   Gedeeld door de nieuwe admin (options.php) en de oude admin (AJAX),`
`   tot plan 3 de oude admin verwijdert.`
door:
`   Ook migraties, resets, import en de automatische scan schrijven via`
`   update_option() en lopen dus door dezelfde callbacks.`

In `assets/css/frontend.css`: vervang de kopregel `   COOKIEMELDING — Frontend CSS  |  WAKKR  |  v1.0.0` door `   COOKIEBAAS — Frontend CSS`. Het is alleen een commentaar, dus de frontend verandert niet.

- [ ] **Step 6: Lint ook `includes/admin/`, README**

In `tests/run.php`: vervang

```php
$lint_targets = array_merge(
    array( $root . '/cookiemelding.php' ),
    glob( $root . '/includes/*.php' )
);
```

door

```php
$lint_targets = array_merge(
    array( $root . '/cookiemelding.php', $root . '/uninstall.php' ),
    glob( $root . '/includes/*.php' ),
    glob( $root . '/includes/admin/*.php' )
);
```

Voeg in `tests/README.md` na de regel van `test-admin3-overzicht.php` toe:

```
| `test-admin3-overstap.php` | Overstap naar 3.0: oude admin-bestanden, gedeelde nonce, oude AJAX-handlers en links naar oude slugs zijn weg; scan, cookiedatabase en frontend-AJAX (consent loggen, geo-check) zijn verhuisd met dezelfde actienamen; de scanmail linkt naar de nieuwe cookielijst. |
```

- [ ] **Step 7: Draai de tests**

Run: `php tests/test-admin3-overstap.php && php tests/test-admin-fixes.php && php tests/test-admin3-cookies.php && php tests/test-cookie-scan.php && php tests/test-admin3-frame.php && php tests/run.php`
Expected: alles groen. `grep -rn "cm_save_settings\|includes/admin.php\|admin\.js'\|admin\.css'" cookiemelding.php includes assets/js` geeft niets meer.

- [ ] **Step 8: Handmatige check (Ruud)**

- Het oude menu is weg; Brinckers laadt zonder PHP-fouten.
- Een bezoeker die op de site akkoord geeft, verschijnt in Consent log › Registraties.
- De handmatige scan en "Database laden" werken nog.

- [ ] **Step 9: Commit**

```bash
git add -A includes/admin/scan.php includes/consent.php cookiemelding.php includes/license.php includes/privacy.php includes/admin/ajax.php includes/admin/settings.php assets/css/frontend.css tests/
git commit -m "refactor(admin3): oude admin verwijderd; scan en frontend-AJAX ongewijzigd verhuisd

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

(`git rm` uit Step 5 staat al klaar in de index.)

---

### Task 9: Menu "Cookiebaas" en oude adressen

**Files:**
- Modify: `includes/admin/menu.php`
- Test: `tests/test-admin3-overstap.php`

**Interfaces:**
- Consumes: `cm_admin_pages()`, `cm_admin_page_url()`, `cm_admin_current_tab()`, `cm_admin_page_tabs()`, `cm_log_handle_bulk()`.
- Produces:
  - `cm_admin_old_slug_target( $page ): string`
  - `cm_admin_redirect_old_slug(): void` op `admin_page_access_denied`
  - topmenu "Cookiebaas" op positie 81
  - de preview-assets alleen op Banner › Vormgeving en Teksten

- [ ] **Step 1: Breid de test uit**

Voeg in `tests/test-admin3-overstap.php` bovenaan (vóór `require __DIR__ . '/bootstrap.php';`) toe:

```php
function add_menu_page( $page_title, $menu_title, $cap, $slug, $cb, $icon, $pos ) { $GLOBALS['cm_test_menu'] = array( $menu_title, $slug, $icon, $pos ); return 'toplevel_page_' . $slug; }
function add_submenu_page( $parent, $page_title, $menu_title, $cap, $slug, $cb ) { $GLOBALS['cm_test_sub'][] = $slug; return 'cookiebaas_page_' . $slug; }
```

Voeg vóór `exit( cm_test_summary() );` toe:

```php
cm_test_group( 'Menu' );
require CM_PLUGIN_ROOT . '/includes/admin/menu.php';
require CM_PLUGIN_ROOT . '/includes/admin/actions.php';
require CM_PLUGIN_ROOT . '/includes/admin/page-log.php';
$GLOBALS['cm_test_sub'] = array();
cm_admin3_register_menu();
cm_assert( 'topmenu Cookiebaas op 81 met dashicons-privacy', $GLOBALS['cm_test_menu'] === array( 'Cookiebaas', 'cookiebaas', 'dashicons-privacy', 81 ) );
cm_assert( 'zeven pagina’s in de volgorde van de spec', $GLOBALS['cm_test_sub'] === array( 'cookiebaas', 'cookiebaas-banner', 'cookiebaas-blokkering', 'cookiebaas-cookies', 'cookiebaas-privacy', 'cookiebaas-log', 'cookiebaas-beheer' ) );
cm_assert( 'bulk verwijderen hangt aan de load-hook van Consent log', has_action( 'load-cookiebaas_page_cookiebaas-log' ) );

cm_test_group( 'Oude adressen (Review Focus 3)' );
$map = array(
    'cookiemelding'         => 'cookiebaas',
    'cookiemelding-cookies' => 'cookiebaas-cookies',
    'cookiemelding-privacy' => 'cookiebaas-privacy',
    'cookiemelding-log'     => 'cookiebaas-log',
    'cookiemelding-beheer'  => 'cookiebaas-beheer',
);
foreach ( $map as $old => $new ) cm_assert( "$old → $new", cm_admin_old_slug_target( $old ) === $new );
cm_assert( 'andere slug → niets', cm_admin_old_slug_target( 'cookiebaas-banner' ) === '' && cm_admin_old_slug_target( 'iets' ) === '' );
cm_assert( 'doorverwijzing vóór de "geen toestemming"-melding van WordPress', has_action( 'admin_page_access_denied' ) );
```

- [ ] **Step 2: Draai de test en zie hem falen**

Run: `php tests/test-admin3-overstap.php`
Expected: FAIL bij "topmenu Cookiebaas op 81" (nu "Cookiebaas 3" op 82), fatal bij `cm_admin_old_slug_target()`.

- [ ] **Step 3: Menu en doorverwijzing (`includes/admin/menu.php`)**

Vervang de kop van het bestand door:

```php
/* ================================================================
   ADMIN — menu en paginaframe. Topmenu "Cookiebaas" met zeven pagina's;
   oude adressen (cookiemelding…) verwijzen door.
================================================================ */
```

Vervang de aanroep van `add_menu_page()` in `cm_admin3_register_menu()` door:

```php
    $GLOBALS['cm_admin_hooks'][] = add_menu_page(
        'Cookiebaas', 'Cookiebaas', 'manage_options', 'cookiebaas',
        'cm_admin_render_page', 'dashicons-privacy', 81
    );
```

Vervang in `cm_admin_pages()` het docblock `/** Menupagina's van de nieuwe admin: slug → titel. Plan 2 en 3 vullen aan. */` door `/** Menupagina's: slug → titel, in de volgorde van het menu. */`.

Voeg onder `cm_admin3_register_menu()` toe:

```php
/** Oude slug (2.x) → nieuwe slug, of '' als het geen oude slug is. */
function cm_admin_old_slug_target( $page ) {
    $map = array(
        'cookiemelding'         => 'cookiebaas',
        'cookiemelding-cookies' => 'cookiebaas-cookies',
        'cookiemelding-privacy' => 'cookiebaas-privacy',
        'cookiemelding-log'     => 'cookiebaas-log',
        'cookiemelding-beheer'  => 'cookiebaas-beheer',
    );
    return isset( $map[ $page ] ) ? $map[ $page ] : '';
}

/*
 * WordPress controleert de toegang tot ?page= al in wp-admin/menu.php, vóór
 * admin_init; een onbekende slug eindigt daar in "Je hebt geen toestemming".
 * Deze hook vuurt vlak daarvoor, dus hier doorverwijzen (bladwijzers, scanmails).
 */
add_action( 'admin_page_access_denied', 'cm_admin_redirect_old_slug' );
function cm_admin_redirect_old_slug() {
    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    $to   = cm_admin_old_slug_target( $page );
    if ( $to === '' || ! current_user_can( 'manage_options' ) ) return;
    wp_safe_redirect( cm_admin_page_url( $to ) );
    exit;
}
```

Vervang in `cm_admin3_assets()` het blok `if ( $page === 'cookiebaas-banner' ) { … }` door:

```php
    if ( $page === 'cookiebaas-banner' ) {
        wp_enqueue_media();
        // De preview staat alleen op Vormgeving en Teksten
        $tab = cm_admin_current_tab( cm_admin_page_tabs( $page ), isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '' );
        if ( in_array( $tab, array( 'vormgeving', 'teksten' ), true ) && function_exists( 'cm_admin_preview_assets' ) ) cm_admin_preview_assets();
    }
```

- [ ] **Step 4: Draai de tests**

Run: `php tests/test-admin3-overstap.php && php tests/test-admin3-frame.php && php tests/run.php`
Expected: alles groen. `grep -rn "Cookiebaas 3" includes tests/README.md` geeft niets meer; pas anders de tekst aan naar "Cookiebaas".

- [ ] **Step 5: Handmatige check (Ruud)**

- Het menu heet "Cookiebaas" en staat op de plek van het oude menu.
- `wp-admin/admin.php?page=cookiemelding-log` en de andere oude slugs komen op de nieuwe pagina uit.
- De preview staat alleen op Vormgeving en Teksten, en het uploaden van een afbeelding onder Weergave werkt nog.

- [ ] **Step 6: README, commit**

Vervang in `tests/README.md` de omschrijving van `test-admin3-overstap.php` door:

```
| `test-admin3-overstap.php` | Overstap naar 3.0: oude admin-bestanden, gedeelde nonce, oude AJAX-handlers en links naar oude slugs zijn weg; scan, cookiedatabase en frontend-AJAX zijn verhuisd met dezelfde actienamen; de scanmail linkt naar de nieuwe cookielijst; topmenu "Cookiebaas" (positie 81) met zeven pagina's; elke oude slug verwijst door vóór de "geen toestemming"-melding. |
```

```bash
git add includes/admin/menu.php tests/test-admin3-overstap.php tests/README.md
git commit -m "feat(admin3): menu heet Cookiebaas, oude adressen verwijzen door

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 10: Release 3.0.0 voorbereiden (niet uitbrengen)

**Files:**
- Modify: `CHANGELOG.md`
- Modify: `cookiemelding.php` (plugin-header en `CM_VERSION`)

**Interfaces:**
- Consumes: de anker `#waar-staat-wat` uit `cm_render_admin3_welcome_notice()` (Taak 7). GitHub maakt dat anker van de kop `### Waar staat wat?`.
- Produces: versie 3.0.0 op `main`. Taggen, pushen en de GitHub-release gebeuren pas na akkoord van Ruud, buiten dit plan.

- [ ] **Step 1: Changelog**

Voeg in `CHANGELOG.md` direct onder `# Changelog — Cookiebaas` (met een lege regel ertussen) toe. Vervang `JJJJ-MM-DD` door de datum van vandaag (`date +%Y-%m-%d`):

```markdown
## [3.0.0] - JJJJ-MM-DD

De admin is opnieuw gebouwd: WordPress-native, zonder eigen kleuren of zelfgebouwde onderdelen, en per onderwerp ingedeeld. Uw instellingen, cookielijst, privacyverklaring en consent log blijven precies zoals ze waren; de banner en de blokkering op uw website veranderen niet.

### Waar staat wat?

| In 2.4 | In 3.0 |
|---|---|
| Instellingen › Vormgeving | Banner › Vormgeving |
| Instellingen › Teksten (ook de teksten van de video-placeholder) | Banner › Teksten |
| Instellingen › Layout (positie, breedte, voorkeurenvenster, zweefknop) | Banner › Weergave |
| Instellingen › Algemeen (standaardkeuzes, geldigheid, geo, uitzonderingen, subdomeinen) | Banner › Gedrag |
| Instellingen › Algemeen › Log retentie | Consent log › Bewaren en opnieuw vragen |
| Instellingen › Algemeen › REST API | Beheer › Geavanceerd |
| Instellingen › Google (ook de blokkeerpatronen) | Blokkering › Google en Blokkering › Scripts |
| Instellingen › Embeds | Blokkering › Embeds |
| Cookies & scan | Cookies › Cookielijst en Cookies › Scannen |
| Privacyverklaring | Privacyverklaring |
| Consent log | Consent log › Registraties |
| Beheer › Compliance | Overzicht |
| Beheer › Export / Import: backup | Beheer › Backup |
| Beheer › Export / Import: verwerkingsregister | Privacyverklaring (knop naast de titel) |
| Beheer › Export / Import: cookielijst (CSV) | Cookies › Cookielijst |
| Beheer › Reset: kleuren, cookielijst, privacyverklaring, consent log, consent data | Bij het onderdeel zelf: Banner › Vormgeving, Cookies › Cookielijst, Privacyverklaring, Consent log › Bewaren en opnieuw vragen |
| Beheer › Reset: alles resetten, licentie | Beheer › Reset |
| Beheer › Licentie | Beheer › Licentie |
| Beheer › Info | Beheer › Info |

Oude links en bladwijzers (`?page=cookiemelding…`) verwijzen automatisch door.

### Nieuw
- **Overzicht** met de status van licentie, cookies, toestemmingen en consent-versie, en de compliance-check met een directe link naar de plek waar u iets oplost.
- **Consent log** als WordPress-lijst: filters met aantallen, zoeken, bulk verwijderen, een bewijs per registratie (af te drukken of op te slaan als pdf) en een CSV-export met datumbereik.
- **De versiegeschiedenis** van "iedereen opnieuw laten kiezen" is zichtbaar, met de reden.
- **Engelse teksten** voor de placeholder van geblokkeerde video's zijn in te vullen.
- **Kleuren** kiest u met het kleurvlak of plakt u als hexcode; beide blijven zichtbaar.

### Veranderd
- **Opslaan** gaat via de standaardformulieren van WordPress; acties (exports, resets, licentie, API-sleutel) via gewone knoppen met een duidelijke melding. Een ongeldige waarde houdt de oude waarde en geeft een melding per veld.
- **De API-sleutel** wordt op de server gemaakt en ingetrokken, en staat niet meer in de JavaScript van de admin.
- **De licentiemelding** staat alleen nog op de pagina's van Cookiebaas.
- **De backup** heet `cookiebaas-backup-JJJJ-MM-DD.json`. Terugzetten behoudt uw API-sleutel.

### Verwijderd
- Het veld "knoptekst" bij de video-placeholder had geen effect en is uit de admin gehaald.
- "Google IDs wissen" (maak de velden onder Blokkering › Google leeg) en de selectieve reset (elk onderdeel heeft nu een eigen knop).
- De oude admin-code (`includes/admin.php`, `assets/js/admin.js`, `assets/css/admin.css`).
```

- [ ] **Step 2: Versie**

In `cookiemelding.php`: zet ` * Version:     2.4.6` op ` * Version:     3.0.0` en `define( 'CM_VERSION',     '2.4.6' );` op `define( 'CM_VERSION',     '3.0.0' );`.

- [ ] **Step 3: Draai de volledige suite**

Run: `php tests/run.php`
Expected: alles groen.

- [ ] **Step 4: Commit (niet pushen, niet taggen)**

```bash
git add CHANGELOG.md cookiemelding.php
git commit -m "chore: versie 3.0.0 en changelog met \"Waar staat wat?\"

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

- [ ] **Step 5: Handmatige check (Ruud), daarna pas uitbrengen**

- Zet `main` op Brinckers. Na het laden verschijnt op Overzicht één keer de melding "nieuwe indeling".
- Loop alle zeven pagina's door.
- Pas na akkoord van Ruud: push, tag `v3.0.0`, zip bouwen en GitHub-release. Daarna krijgen klanten de update.

---

## Na plan 3

- Cookiebaas 3.0.0 is klaar voor release. De uitgebrachte versie blijft 2.4.6 tot Ruud 3.0.0 uitbrengt.
- 2.4.x-hotfixes: via `release/2.4.x`, daarna overnemen in `main`.
