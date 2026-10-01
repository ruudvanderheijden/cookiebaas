<?php
/**
 * Voorkeurenvenster als kaarten (3.2).
 *
 * Borgt: de uitklaplijst blijft de standaard; de kaartweergave gebruikt
 * dezelfde id's (dus hetzelfde script), heeft per categorie een kaart met
 * selectievakje en punten, geen dienstschakelaars of cookielijst, de knoppen
 * afwijzen → opslaan → akkoord, en een introregel met links naar de gekozen
 * pagina's. Zonder pagina die de cookielijst toont, waarschuwen de admin en
 * het Overzicht.
 */

$GLOBALS['cm_test_posts'] = array(
    12 => (object) array( 'ID' => 12, 'post_title' => 'Cookieverklaring', 'post_status' => 'publish', 'post_content' => 'Tekst [cookiebaas_cookies] meer' ),
    13 => (object) array( 'ID' => 13, 'post_title' => 'Over ons', 'post_status' => 'publish', 'post_content' => 'Geen lijst' ),
    14 => (object) array( 'ID' => 14, 'post_title' => 'Privacy', 'post_status' => 'publish', 'post_content' => '[cookiebaas_privacy]' ),
);
function get_post( $id ) { return isset( $GLOBALS['cm_test_posts'][ $id ] ) ? $GLOBALS['cm_test_posts'][ $id ] : null; }
function get_pages( $a = array() ) { return array_values( $GLOBALS['cm_test_posts'] ); }
function get_permalink( $id ) { return 'https://example.test/pagina-' . (int) $id . '/'; }
$GLOBALS['cm_test_wp_privacy'] = 'https://example.test/privacy/';
function get_privacy_policy_url() { return $GLOBALS['cm_test_wp_privacy']; }
function wp_date( $f, $t = null ) { return gmdate( $f, $t === null ? time() : $t ); }
function add_query_arg( $a, $b = null, $c = null ) {
    if ( is_array( $a ) ) { $args = $a; $url = $b; } else { $args = array( $a => $b ); $url = $c; }
    foreach ( $args as $k => $v ) $url .= ( strpos( $url, '?' ) === false ? '?' : '&' ) . rawurlencode( $k ) . '=' . rawurlencode( $v );
    return $url;
}

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
require CM_PLUGIN_ROOT . '/includes/admin/menu.php';
require CM_PLUGIN_ROOT . '/includes/admin/fields.php';
require CM_PLUGIN_ROOT . '/includes/admin/settings.php';
require CM_PLUGIN_ROOT . '/includes/admin/actions.php';
require CM_PLUGIN_ROOT . '/includes/admin/page-banner.php';
require CM_PLUGIN_ROOT . '/includes/admin/page-overzicht.php';
require CM_PLUGIN_ROOT . '/includes/frontend.php';

function set_settings( array $s ) { update_option( 'cm_settings', array_merge( cm_default_settings(), $s ) ); }
function prefs_markup() { ob_start(); cm_banner_markup(); return ob_get_clean(); }

cm_test_group( 'Uitklaplijst blijft de standaard' );
set_settings( array() );
$h = prefs_markup();
cm_assert( 'standaard: uitklaplijst, geen kaarten', strpos( $h, 'cm-cat-header' ) !== false && strpos( $h, 'cm-card' ) === false && strpos( $h, 'cm-prefs-cards' ) === false );
cm_assert( 'knop heet nu "Alles akkoord"', strpos( $h, '>Alles akkoord<' ) !== false );

cm_test_group( 'Kaartweergave' );
set_settings( array( 'prefs_layout' => 'cards', 'cookie_page_id' => '12' ) );
$h = prefs_markup();
cm_assert( 'kaarten: drie kaarten, geen uitklapkoppen', strpos( $h, 'cm-prefs-cards' ) !== false && substr_count( $h, 'class="cm-card"' ) === 3 && strpos( $h, 'cm-cat-header' ) === false );
cm_assert( 'zelfde id\'s als de uitklaplijst (zelfde script)', substr_count( $h, 'id="cm-toggle-analytics"' ) === 1 && substr_count( $h, 'id="cm-toggle-marketing"' ) === 1 && substr_count( $h, 'id="cm-save-btn"' ) === 1 && substr_count( $h, 'id="cm-allowall-btn"' ) === 1 && substr_count( $h, 'id="cm-prefs-close"' ) === 1 );
cm_assert( 'functioneel: vast aangevinkt met "Altijd actief"', strpos( $h, '<input type="checkbox" checked disabled>' ) !== false && strpos( $h, 'Altijd actief' ) !== false );
cm_assert( 'geen dienstschakelaars en geen cookielijst', strpos( $h, 'cm-service-toggle' ) === false && strpos( $h, 'cm-cookie-item' ) === false );
cm_assert( 'punten met vinkje (2 + 2 + 1)', substr_count( $h, '<li>' ) >= 5 && strpos( $h, '<li>De website werkt goed en veilig.</li>' ) !== false && strpos( $h, '<li>Advertenties die beter bij u passen.</li>' ) !== false );
$sav = strpos( $h, 'id="cm-save-btn"' ); $all = strpos( $h, 'id="cm-allowall-btn"' );
cm_assert( 'alleen opslaan en akkoord, geen "Alles afwijzen"', $sav < $all && strpos( $h, 'cm-rejectall-btn' ) === false );
cm_assert( 'introregel met beide links', strpos( $h, 'Meer weten? Bekijk ons <a href="https://example.test/privacy/">privacybeleid</a> of de <a href="https://example.test/pagina-12/">cookieverklaring</a>.' ) !== false );
cm_assert( 'vermelding Cookiebaas blijft', strpos( $h, 'class="cm-credit"' ) !== false || cm_license_is_valid() );

set_settings( array( 'prefs_layout' => 'cards', 'privacy_page_id' => '14', 'txt_cat3_points' => "  \n  ", 'txt_cat2_points' => "<b>vet</b>\n\n  tweede  " ) );
$h = prefs_markup();
cm_assert( 'gekozen privacypagina gaat voor; zonder cookiepagina alleen die link', strpos( $h, 'Meer weten? Bekijk ons <a href="https://example.test/pagina-14/">privacybeleid</a>.' ) !== false );
cm_assert( 'alleen witruimte als punten → uitgebreide omschrijving', strpos( $h, '<p class="cm-card-text">' . esc_html( cm_get( 'txt_cat3_long' ) ) . '</p>' ) !== false );
cm_assert( 'punten ge-escaped, lege regels weg', strpos( $h, '<li>&lt;b&gt;vet&lt;/b&gt;</li><li>tweede</li>' ) !== false );
$GLOBALS['cm_test_wp_privacy'] = '';
set_settings( array( 'prefs_layout' => 'cards' ) );
cm_assert( 'geen pagina\'s → geen introregel', strpos( prefs_markup(), '<p class="cm-prefs-text" id="cm-prefs-desc"></p>' ) !== false );

cm_test_group( 'Admin' );
$fields = array_column( cm_admin_field_list( 'cm_settings', array( 'banner' => cm_tabs_banner() ) ), null, 'key' );
cm_assert( 'keuze uitklaplijst/kaarten', isset( $fields['prefs_layout'] ) && array_keys( cm_admin_field_options( $fields['prefs_layout'] ) ) === array( 'accordion', 'cards' ) );
cm_assert( 'paginakeuzes alleen bij kaarten', $fields['privacy_page_id']['show_if'] === array( 'prefs_layout' => 'cards' ) && $fields['cookie_page_id']['show_if'] === array( 'prefs_layout' => 'cards' ) );
cm_assert( 'detailniveau alleen bij de uitklaplijst', $fields['prefs_cookie_detail']['show_if'] === array( 'prefs_layout' => 'accordion' ) );
cm_assert( 'punten per categorie (NL en EN)', isset( $fields['txt_cat1_points'], $fields['txt_cat3_points_en'] ) );
cm_assert( 'pagina-opties: geen + gepubliceerde pagina\'s', array_keys( cm_admin_field_options( $fields['cookie_page_id'] ) ) === array( 0, 12, 13, 14 ) );
cm_assert( 'pagina met [cookiebaas_cookies] of [cookiebaas_privacy] toont de lijst', cm_page_shows_cookie_list( 12 ) && cm_page_shows_cookie_list( 14 ) && ! cm_page_shows_cookie_list( 13 ) && ! cm_page_shows_cookie_list( 0 ) );
set_settings( array( 'prefs_layout' => 'cards', 'cookie_page_id' => '13' ) );
ob_start(); cm_render_prefs_cards_notice(); $n = ob_get_clean();
cm_assert( 'melding: waarschuwing als de pagina de lijst niet toont', strpos( $n, 'notice-warning' ) !== false && strpos( $n, 'geen van beide shortcodes' ) !== false );
set_settings( array( 'prefs_layout' => 'cards', 'cookie_page_id' => '12' ) );
ob_start(); cm_render_prefs_cards_notice(); $n = ob_get_clean();
cm_assert( 'melding: info als de pagina de lijst toont', strpos( $n, 'notice-info' ) !== false && strpos( $n, 'toont de cookielijst' ) !== false );

cm_test_group( 'Overzicht' );
$list = array( array( 'name' => 'x', 'category' => 'analytics', 'purpose' => 'doel', 'duration' => '1 jaar' ) );
$by = function ( array $s ) use ( $list ) { return array_column( cm_compliance_checks( array_merge( cm_default_settings(), $s ), cm_default_privacy(), $list ), null, 'title' )['Elke cookie heeft een doel en looptijd']; };
cm_assert( 'uitklaplijst met complete lijst: in orde', $by( array() )['status'] === 'ok' );
$c = $by( array( 'prefs_layout' => 'cards', 'cookie_page_id' => '13' ) );
cm_assert( 'kaarten zonder cookieverklaring: aandacht nodig, met link naar Weergave', $c['status'] === 'warn' && strpos( $c['detail'], 'kaarten' ) !== false && strpos( $c['url'], 'weergave' ) !== false );
cm_assert( 'kaarten met cookieverklaring: in orde', $by( array( 'prefs_layout' => 'cards', 'cookie_page_id' => '12' ) )['status'] === 'ok' );

cm_test_group( 'CSS volgt de kleurinstellingen' );
$css = file_get_contents( CM_PLUGIN_ROOT . '/assets/css/frontend.css' );
cm_assert( 'vakjes en vinkjes gebruiken de schakelaar- en labelkleuren', strpos( $css, 'var(--cm-toggle-on, #0091ff)' ) !== false && strpos( $css, '#cm-prefs .cm-card input[type="checkbox"]:disabled { background: var(--cm-always-bg' ) !== false && strpos( $css, '#cm-prefs .cm-card-points li::before' ) !== false );
cm_assert( 'akkoordknop gebruikt de kleuren van "Alles akkoord"', strpos( $css, '#cm-prefs .cm-btn-allowall,' ) !== false && strpos( $css, 'var(--cm-allowall-bg, #0091ff)' ) !== false );

cm_test_group( 'Knoppen, vinkkleur en kop (na eerste test)' );
cm_assert( 'knoppen op één rij: opslaan links, akkoord rechts; tekst breekt binnen de knop', strpos( $css, '#cm-prefs .cm-prefs-cards .cm-prefs-footer { flex-wrap: nowrap;' ) !== false && strpos( $css, '#cm-prefs .cm-prefs-cards #cm-save-btn { order: 1; margin-right: auto; }' ) !== false && strpos( $css, '#cm-prefs .cm-prefs-cards #cm-allowall-btn { order: 2; }' ) !== false && strpos( $css, 'white-space: normal !important' ) !== false );
cm_assert( 'compactere kop', strpos( $css, '#cm-prefs .cm-prefs-cards .cm-prefs-header { padding: 24px 36px 14px; border-bottom: 0; }' ) !== false );
cm_assert( 'vinkjes bij de punten: eigen kleur, anders "Schakelaar aan"', strpos( $css, 'border: solid var(--cm-card-check, var(--cm-toggle-on, #0091ff))' ) !== false );
$vg = array_column( cm_admin_field_list( 'cm_settings', array( 'banner' => cm_tabs_banner() ) ), null, 'key' );
cm_assert( 'kleurveld voor de vinkjes (licht en donker, optioneel)', isset( $vg['color_card_check'], $vg['dm_card_check'] ) && $vg['color_card_check']['type'] === 'color_optional' );
set_settings( array( 'color_toggle_on' => '#123456' ) );
ob_start(); cm_output_inline_css(); $inline = ob_get_clean();
cm_assert( 'zonder eigen kleur: vinkjes in de kleur van "Schakelaar aan"', strpos( $inline, '--cm-card-check:var(--cm-toggle-on);' ) !== false && strpos( $inline, '--cm-toggle-on:#123456;' ) !== false );
set_settings( array( 'color_card_check' => '#00a32a' ) );
ob_start(); cm_output_inline_css(); $inline = ob_get_clean();
cm_assert( 'met eigen kleur: die kleur', strpos( $inline, '--cm-card-check:#00a32a;' ) !== false );

cm_test_group( 'Alles standaard uit in de kaartweergave' );
$fe_src = file_get_contents( CM_PLUGIN_ROOT . '/includes/frontend.php' );
cm_assert( '"Analytische cookies standaard aangevinkt" geldt niet voor kaarten (opslaan zonder vinkjes = weigeren)', strpos( $fe_src, "var ANALYTICS_DEFAULT = <?php echo ( cm_get('analytics_default') && cm_get('prefs_layout') !== 'cards' ) ? 'true' : 'false'; ?>;" ) !== false );
set_settings( array( 'prefs_layout' => 'cards' ) );
$h = prefs_markup();
cm_assert( 'vakjes analytisch en marketing staan in de HTML uit', strpos( $h, '<input type="checkbox" id="cm-toggle-analytics">' ) !== false && strpos( $h, '<input type="checkbox" id="cm-toggle-marketing">' ) !== false );

cm_test_group( 'Eigen kleuren voor "Mijn keuzes opslaan" en de vakjes' );
set_settings( array() );
$h = prefs_markup();
cm_assert( 'opslaan heeft een eigen klasse (uitklaplijst)', strpos( $h, 'class="cm-btn cm-btn-save" id="cm-save-btn"' ) !== false && strpos( $h, 'cm-btn-accept" id="cm-save-btn"' ) === false );
set_settings( array( 'prefs_layout' => 'cards' ) );
cm_assert( 'opslaan heeft een eigen klasse (kaarten)', strpos( prefs_markup(), 'class="cm-btn cm-btn-save" id="cm-save-btn"' ) !== false );
$d = cm_default_settings();
cm_assert( 'standaard zoals "Cookievoorkeuren": geen achtergrond, rand en tekst als die knop', $d['color_save_bg'] === '' && $d['color_save_border'] === $d['color_prefs_border'] && $d['color_save_text'] === $d['color_prefs_text'] && $d['color_save_hover_border'] === $d['color_prefs_hover_border'] && $d['dm_save_border'] === $d['dm_prefs_border'] );
$css = file_get_contents( CM_PLUGIN_ROOT . '/assets/css/frontend.css' );
cm_assert( 'CSS van opslaan gebruikt de eigen variabelen', strpos( $css, 'body #cm-prefs .cm-btn-save { background: var(--cm-save-bg, transparent)' ) !== false && strpos( $css, 'border-color: var(--cm-save-hover-border' ) !== false );
cm_assert( 'vakje: eigen achtergrond en vinkje', strpos( $css, 'background: var(--cm-card-box-on, var(--cm-toggle-on' ) !== false && strpos( $css, 'border: solid var(--cm-card-box-tick, #fff)' ) !== false );
$vg = array_column( cm_admin_field_list( 'cm_settings', array( 'banner' => cm_tabs_banner() ) ), null, 'key' );
$keys = array();
foreach ( array( 'color_', 'dm_' ) as $px ) foreach ( array( 'save_bg', 'save_text', 'save_border', 'save_hover_bg', 'save_hover_text', 'save_hover_border', 'card_box_on', 'card_box_tick', 'card_check' ) as $k ) $keys[] = $px . $k;
cm_assert( 'kleurvelden in de admin (licht en donker)', ! array_diff( $keys, array_keys( $vg ) ) );
set_settings( array() );
ob_start(); cm_output_inline_css(); $inline = ob_get_clean();
cm_assert( 'inline: lege achtergrond = transparant, vakje en vinkje met terugval', strpos( $inline, '--cm-save-bg:transparent;' ) !== false && strpos( $inline, '--cm-card-box-on:var(--cm-toggle-on);' ) !== false && strpos( $inline, '--cm-card-box-tick:#fff;' ) !== false );

cm_test_group( 'Update naar 3.2: eigen Akkoord-kleuren blijven op opslaan' );
$src = file_get_contents( CM_PLUGIN_ROOT . '/cookiemelding.php' );
$existing = array( 'color_accept_bg' => '#ff6600', 'color_accept_text' => '#ffffff', 'color_accept_hover_bg' => '#111111', 'color_accept_hover_text' => '#ffffff', 'color_accept_border' => '' );
$merged = array_merge( cm_default_settings(), $existing );
cm_migrate_save_button_colors( $existing, $merged );
cm_assert( 'eigen Akkoord-kleuren → opslaan ziet er hetzelfde uit', $merged['color_save_bg'] === '#ff6600' && $merged['color_save_border'] === '#ff6600' && $merged['color_save_hover_bg'] === '#111111' && $merged['color_save_text'] === '#ffffff' );
cm_assert( 'donker thema ongewijzigd (geen eigen kleuren) → nieuwe standaard', $merged['dm_save_bg'] === '' );
$existing = array( 'color_accept_bg' => '#111111' );
$merged = array_merge( cm_default_settings(), $existing );
cm_migrate_save_button_colors( $existing, $merged );
cm_assert( 'standaard Akkoord-kleuren → nieuwe standaard (zoals Cookievoorkeuren)', $merged['color_save_bg'] === '' && $merged['color_save_border'] === '#d1d1d1' );
$existing = array( 'color_accept_bg' => '#ff6600', 'color_save_text' => '#123456' );
$merged = array_merge( cm_default_settings(), $existing );
cm_migrate_save_button_colors( $existing, $merged );
cm_assert( 'al eigen opslaankleuren → niets overschreven', $merged['color_save_bg'] === '' && $merged['color_save_text'] === '#123456' );
cm_assert( 'migratie draait bij de update naar 3.2.0', strpos( $src, "version_compare( \$stored_version, '3.2.0', '<' )" ) !== false && strpos( $src, 'cm_migrate_save_button_colors( $existing, $merged );' ) !== false );

exit( cm_test_summary() );
