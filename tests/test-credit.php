<?php
/**
 * Vermelding "Cookiebaas" in de gratis versie (3.0).
 *
 * Borgt: zonder geldige licentie staat rechtsonder in de banner én in het
 * voorkeurenvenster een vaste, grijze link naar cookiebaas.nl (nofollow);
 * met een geldige licentie niet. De paginacache wordt alleen geleegd als de
 * geldigheid van de licentie echt verandert.
 */

define( 'CM_TEST_REAL_LICENSE', true );
$GLOBALS['cm_test_purges'] = 0;
function cm_purge_page_caches() { $GLOBALS['cm_test_purges']++; }

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';
require CM_PLUGIN_ROOT . '/includes/license.php';
require CM_PLUGIN_ROOT . '/includes/frontend.php';

$active  = array( 'key' => 'CB-TEST', 'status' => 'active', 'expires_at' => gmdate( 'Y-m-d', time() + 86400 * 30 ) );
$expired = array( 'key' => 'CB-TEST', 'status' => 'expired', 'expires_at' => gmdate( 'Y-m-d', time() - 86400 ) );

function markup() { ob_start(); cm_banner_markup(); return ob_get_clean(); }

cm_test_group( 'Vermelding zonder licentie' );
update_option( 'cm_license_data', array( 'key' => '', 'status' => '' ) );
$h = markup();
cm_assert( 'in banner én voorkeurenvenster', substr_count( $h, 'class="cm-credit"' ) === 2 );
cm_assert( 'link naar cookiebaas.nl, nieuw tabblad, nofollow', strpos( $h, 'href="https://www.cookiebaas.nl"' ) !== false && strpos( $h, 'rel="nofollow noopener"' ) !== false && strpos( $h, 'target="_blank"' ) !== false );
cm_assert( 'banner: na de knoppen, binnen het venster', strpos( $h, 'class="cm-credit"' ) > strpos( $h, 'id="cm-btn-accept"' ) && strpos( $h, 'class="cm-credit"' ) < strpos( $h, 'id="cm-prefs"' ) );
cm_assert( 'voorkeurenvenster: na de knoppen', strrpos( $h, 'class="cm-credit"' ) > strpos( $h, 'id="cm-rejectall-btn"' ) );
update_option( 'cm_license_data', $expired );
cm_assert( 'ook bij een verlopen licentie', substr_count( markup(), 'class="cm-credit"' ) === 2 );

cm_test_group( 'Geen vermelding met licentie' );
update_option( 'cm_license_data', $active );
cm_assert( 'geldige licentie → geen vermelding', strpos( markup(), 'cm-credit' ) === false );

cm_test_group( 'Vaste stijl, niet instelbaar' );
$css = file_get_contents( CM_PLUGIN_ROOT . '/assets/css/frontend.css' );
cm_assert( 'eigen grijze kleur met !important (thema en kleurinstellingen veranderen hem niet)', (bool) preg_match( '/\.cm-credit[^{]*\{[^}]*color:\s*#[0-9a-f]{6}\s*!important/i', $css ) );
cm_assert( 'geen CSS-variabele voor de vermelding', ! preg_match( '/\.cm-credit[^{]*\{[^}]*var\(/i', $css ) );
cm_assert( 'geen instelling in de defaults', ! preg_grep( '/credit/i', array_keys( cm_default_settings() ) ) );

cm_test_group( 'nofollow blijft ook in de browser' );
$js = file_get_contents( CM_PLUGIN_ROOT . '/includes/frontend.php' );
cm_assert( 'makeLinksExternal overschrijft geen bestaande rel (anders verdwijnt nofollow)', strpos( $js, "if (!a.getAttribute('rel')) a.setAttribute('rel', 'noopener noreferrer');" ) !== false );

cm_test_group( 'Paginacache alleen bij een andere geldigheid' );
update_option( 'cm_license_data', $active );
$p = $GLOBALS['cm_test_purges'];
update_option( 'cm_license_data', array_merge( $active, array( 'last_check' => 123 ) ) );
cm_assert( 'dagelijkse controle zonder wijziging → geen purge', $GLOBALS['cm_test_purges'] === $p );
update_option( 'cm_license_data', $expired );
cm_assert( 'licentie verloopt → purge', $GLOBALS['cm_test_purges'] === $p + 1 );
update_option( 'cm_license_data', $active );
// De vervaldatum verstrijkt zonder dat er iets wordt opgeslagen: de opgeslagen waarde is nu al ongeldig
$GLOBALS['cm_test_options']['cm_license_data'] = array_merge( $active, array( 'expires_at' => gmdate( 'Y-m-d', time() - 86400 ) ) );
$p = $GLOBALS['cm_test_purges'];
update_option( 'cm_license_data', array_merge( $GLOBALS['cm_test_options']['cm_license_data'], array( 'status' => 'expired' ) ) ); // dagelijkse controle
cm_assert( 'verlopen op de vervaldatum (oude waarde al ongeldig) → toch purge', $GLOBALS['cm_test_purges'] === $p + 1 );
update_option( 'cm_license_data', $active );
$p = $GLOBALS['cm_test_purges'];
delete_option( 'cm_license_data' );
cm_assert( 'licentie lokaal gewist → purge', $GLOBALS['cm_test_purges'] === $p + 1 );

exit( cm_test_summary() );
