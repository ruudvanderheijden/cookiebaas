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
cm_assert( 'zelfde id\'s als de uitklaplijst (zelfde script)', substr_count( $h, 'id="cm-toggle-analytics"' ) === 1 && substr_count( $h, 'id="cm-toggle-marketing"' ) === 1 && substr_count( $h, 'id="cm-save-btn"' ) === 1 && substr_count( $h, 'id="cm-allowall-btn"' ) === 1 && substr_count( $h, 'id="cm-rejectall-btn"' ) === 1 && substr_count( $h, 'id="cm-prefs-close"' ) === 1 );
cm_assert( 'functioneel: vast aangevinkt met "Altijd actief"', strpos( $h, '<input type="checkbox" checked disabled>' ) !== false && strpos( $h, 'Altijd actief' ) !== false );
cm_assert( 'geen dienstschakelaars en geen cookielijst', strpos( $h, 'cm-service-toggle' ) === false && strpos( $h, 'cm-cookie-item' ) === false );
cm_assert( 'punten met vinkje (2 + 2 + 1)', substr_count( $h, '<li>' ) >= 5 && strpos( $h, '<li>De website werkt goed en veilig.</li>' ) !== false && strpos( $h, '<li>Advertenties die beter bij u passen.</li>' ) !== false );
$rej = strpos( $h, 'id="cm-rejectall-btn"' ); $sav = strpos( $h, 'id="cm-save-btn"' ); $all = strpos( $h, 'id="cm-allowall-btn"' );
cm_assert( 'knoppen: afwijzen, opslaan, akkoord', $rej < $sav && $sav < $all );
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
cm_assert( 'knoppen op één rij: afwijzen links, opslaan en akkoord rechts; tekst breekt binnen de knop', strpos( $css, '#cm-prefs .cm-prefs-cards .cm-prefs-footer { flex-wrap: nowrap;' ) !== false && strpos( $css, '#cm-prefs .cm-prefs-cards #cm-rejectall-btn { margin-right: auto; }' ) !== false && strpos( $css, 'white-space: normal !important' ) !== false );
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

exit( cm_test_summary() );
