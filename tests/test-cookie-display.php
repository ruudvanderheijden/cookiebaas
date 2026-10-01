<?php
/**
 * Cookienamen voor bezoekers (3.1.3).
 *
 * Borgt: een ID of hash in de naam wordt * in het voorkeurenvenster en de
 * cookietabel (_ga_V41VJXRM2G → _ga_*), gewone namen blijven zoals ze zijn,
 * en elke weergavenaam staat er één keer. De opgeslagen naam blijft exact,
 * want die gebruikt het opruimen bij weigeren.
 */

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';

cm_test_group( 'ID of hash wordt *' );
$cases = array(
    '_ga_V41VJXRM2G'                                     => '_ga_*',
    '_ga_RS7218SW3E'                                     => '_ga_*',
    '_ga_'                                               => '_ga_*',
    '_hjSessionUser_3512357'                             => '_hjSessionUser_*',
    '_hjSession_'                                        => '_hjSession_*',
    'wp_woocommerce_session_d41d8cd98f00b204e9800998ecf8427e' => 'wp_woocommerce_session_*',
    'comment_author_d41d8cd98f00b204e9800998ecf8427e'    => 'comment_author_*',
);
foreach ( $cases as $in => $want ) cm_assert( "$in → $want", cm_cookie_display_name( $in ) === $want );

cm_test_group( 'Gewone namen blijven staan' );
foreach ( array( '_ga', '_gid', '_gcl_au', '_fbp', 'cc_cm_consent', 'PHPSESSID', '__Secure-3PAPISID', '__Secure-3PSIDCC', 'IDE', 'test_cookie', 'AMP_TOKEN', '_pk_id.1.a1b2', 'wp-settings-1', 'li_sugr' ) as $n ) {
    cm_assert( "$n ongewijzigd", cm_cookie_display_name( $n ) === $n );
}

cm_test_group( 'Weergavelijst' );
$list = cm_cookies_for_display( array(
    array( 'name' => '_ga', 'purpose' => 'a' ),
    array( 'name' => '_ga_V41VJXRM2G', 'purpose' => 'b' ),
    array( 'name' => '_ga_RS7218SW3E', 'purpose' => 'c' ),
) );
cm_assert( 'elke weergavenaam één keer (twee GA4-ID\'s → één _ga_*)', array_column( $list, 'display' ) === array( '_ga', '_ga_*' ) );
cm_assert( 'de opgeslagen naam blijft exact', $list[1]['name'] === '_ga_V41VJXRM2G' );

cm_test_group( 'Gebruikt waar bezoekers namen zien' );
$fe = file_get_contents( CM_PLUGIN_ROOT . '/includes/frontend.php' );
$pv = file_get_contents( CM_PLUGIN_ROOT . '/includes/privacy.php' );
cm_assert( 'voorkeurenvenster: alle drie de lijsten', substr_count( $fe, 'cm_cookies_for_display(' ) === 3 && substr_count( $fe, "esc_html(\$ck['display'])" ) === 3 && strpos( $fe, "esc_html(\$ck['name'])" ) === false );
cm_assert( 'cookietabel van de privacyverklaring', strpos( $pv, 'cm_cookies_for_display( $cat_data' ) !== false && strpos( $pv, "esc_html( \$ck['display'] )" ) !== false );
cm_assert( 'het opruimen gebruikt de volledige namen', strpos( $fe, "data-cookies=\"<?php echo esc_attr(implode(',', \$cookie_names)); ?>\"" ) !== false );

exit( cm_test_summary() );
