<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ================================================================
   INLINE CSS — CSS custom properties vanuit instellingen
================================================================ */
add_action( 'wp_head', 'cm_output_inline_css', 1 );
function cm_output_inline_css() {
    if ( is_admin() ) return;
    $s  = array_merge( cm_default_settings(), (array) get_option( 'cm_settings', array() ) );
    $op = intval( cm_get('overlay_opacity') ) / 100;
    $rp = intval( cm_get('radius_popup') )   . 'px';
    $rb = intval( cm_get('radius_btn') )     . 'px';

    // Optionele borders (leeg = transparant zodat beide knoppen altijd even groot zijn)
    $ab        = cm_get('color_accept_border')  ? cm_get('color_accept_border')  : 'transparent';
    $rb_border = cm_get('color_reject_border')  ? cm_get('color_reject_border')  : 'transparent';
    $alb       = cm_get('color_allowall_border') ? cm_get('color_allowall_border') : 'transparent';

    echo '<style id="cm-vars">:root{';
    echo '--cm-overlay-alpha:'  . esc_attr($op) . ';';
    echo '--cm-popup-bg:'       . esc_attr( cm_get('color_popup_bg') )          . ';';
    echo '--cm-popup-radius:'   . esc_attr($rp) . ';';
    echo '--cm-btn-radius:'     . esc_attr($rb) . ';';
    echo '--cm-title-color:'    . esc_attr( cm_get('color_title') )              . ';';
    echo '--cm-body-color:'     . esc_attr( cm_get('color_body') )               . ';';
    echo '--cm-accept-bg:'      . esc_attr( cm_get('color_accept_bg') )          . ';';
    echo '--cm-accept-text:'    . esc_attr( cm_get('color_accept_text') )        . ';';
    echo '--cm-accept-hover-bg:'. esc_attr( cm_get('color_accept_hover_bg') )    . ';';
    echo '--cm-accept-hover-text:' . esc_attr( cm_get('color_accept_hover_text') ) . ';';
    echo '--cm-accept-border:'  . $ab . ';';
    echo '--cm-reject-text:'    . esc_attr( cm_get('color_reject_text') )        . ';';
    echo '--cm-reject-hover-text:' . esc_attr( cm_get('color_reject_hover_text') ) . ';';
    echo '--cm-reject-bg:'      . esc_attr( cm_get('color_reject_bg') ?: '#f5f2ee' )   . ';';
    echo '--cm-reject-hover-bg:'. esc_attr( cm_get('color_reject_hover_bg') ?: '#e8e4de' ) . ';';
    echo '--cm-reject-border:'  . $rb_border . ';';
    echo '--cm-prefs-border:'   . esc_attr( cm_get('color_prefs_border') )       . ';';
    echo '--cm-prefs-text:'     . esc_attr( cm_get('color_prefs_text') )         . ';';
    echo '--cm-prefs-hover-border:' . esc_attr( cm_get('color_prefs_hover_border') ) . ';';
    echo '--cm-prefs-hover-text:'   . esc_attr( cm_get('color_prefs_hover_text') )   . ';';
    echo '--cm-allowall-bg:'    . esc_attr( cm_get('color_allowall_bg') )        . ';';
    echo '--cm-allowall-text:'  . esc_attr( cm_get('color_allowall_text') )      . ';';
    echo '--cm-allowall-hover-bg:'  . esc_attr( cm_get('color_allowall_hover_bg') )  . ';';
    echo '--cm-allowall-hover-text:'. esc_attr( cm_get('color_allowall_hover_text') ) . ';';
    echo '--cm-allowall-border:'. $alb . ';';
    echo '--cm-close-bg:'       . esc_attr( cm_get('color_close_bg') )           . ';';
    echo '--cm-close-hover-bg:' . esc_attr( cm_get('color_close_hover_bg') )     . ';';
    echo '--cm-close-icon:'     . esc_attr( cm_get('color_close_icon') )         . ';';
    echo '--cm-toggle-on:'      . esc_attr( cm_get('color_toggle_on') )          . ';';
    echo '--cm-always-bg:'      . esc_attr( cm_get('color_always_bg') )          . ';';
    // Zweefknop icoontje kleuren
    echo '--cm-float-icon-bg:'          . esc_attr( cm_get('color_float_icon_bg') ?: '#111111' )       . ';';
    echo '--cm-float-icon-color:'       . esc_attr( cm_get('color_float_icon_color') ?: '#ffffff' )    . ';';
    echo '--cm-float-icon-hover-bg:'    . esc_attr( cm_get('color_float_icon_hover_bg') ?: '#0091ff' ) . ';';
    echo '--cm-float-icon-hover-color:' . esc_attr( cm_get('color_float_icon_hover_color') ?: '#ffffff' ) . ';';
    // Zweefknop tekstknop kleuren
    echo '--cm-float-text-bg:'          . esc_attr( cm_get('color_float_text_bg') ?: '#ffffff' )       . ';';
    echo '--cm-float-text-color:'       . esc_attr( cm_get('color_float_text_color') ?: '#444444' )    . ';';
    echo '--cm-float-text-border:'      . esc_attr( cm_get('color_float_text_border') ?: '#e0dbd3' )   . ';';
    echo '--cm-float-text-hover-bg:'    . esc_attr( cm_get('color_float_text_hover_bg') ?: '#f6f7f7' ) . ';';
    echo '--cm-float-text-hover-color:' . esc_attr( cm_get('color_float_text_hover_color') ?: '#111111' ) . ';';
    echo '--cm-cat-border:'      . esc_attr( cm_get('color_cat_border') ?: '#e8e4de' )      . ';';
    echo '--cm-service-bg:'      . esc_attr( cm_get('color_service_bg') ?: '#f6f4f1' )      . ';';
    echo '--cm-cookie-item-bg:'  . esc_attr( cm_get('color_cookie_item_bg') ?: '#ffffff' )  . ';';
    echo '--cm-expand-bg:'         . esc_attr( cm_get('color_expand_bg') ?: '#f0ede8' )       . ';';
    echo '--cm-expand-icon:'       . esc_attr( cm_get('color_expand_icon') ?: '#666666' )     . ';';
    echo '--cm-expand-open-bg:'    . esc_attr( cm_get('color_expand_open_bg') ?: '#111111' )  . ';';
    echo '--cm-expand-open-icon:'  . esc_attr( cm_get('color_expand_open_icon') ?: '#ffffff' ). ';';
    // Embed placeholder kleuren
    echo '--cm-embed-bg:'              . esc_attr( cm_get('color_embed_bg') ?: '#000000' )             . ';';
    echo '--cm-embed-title:'           . esc_attr( cm_get('color_embed_title') ?: '#ffffff' )          . ';';
    echo '--cm-embed-body:'            . esc_attr( cm_get('color_embed_body') ?: '#ffffff' )           . ';';
    echo '--cm-embed-btn-bg:'          . esc_attr( cm_get('color_embed_btn_bg') ?: '#ffffff' )         . ';';
    echo '--cm-embed-btn-text:'        . esc_attr( cm_get('color_embed_btn_text') ?: '#000000' )       . ';';
    echo '--cm-embed-btn-hover-bg:'    . esc_attr( cm_get('color_embed_btn_hover_bg') ?: '#0091ff' )   . ';';
    echo '--cm-embed-btn-hover-text:'  . esc_attr( cm_get('color_embed_btn_hover_text') ?: '#ffffff' ) . ';';
    echo '}</style>' . "\n";

    // Dark mode CSS variabelen
    if ( cm_get('dark_mode_enabled') ) {
        $dm = function( $key, $fallback = '' ) {
            $v = cm_get( $key );
            return $v ?: $fallback;
        };
        $dm_ab  = cm_get('dm_accept_bg')    ? '' : '';
        $dm_alb = cm_get('dm_allowall_bg')  ? '' : '';

        echo '<style id="cm-vars-dark">@media (prefers-color-scheme: dark) { :root {';
        echo '--cm-popup-bg:'              . esc_attr( $dm('dm_popup_bg','#1a1a1a') )             . ';';
        echo '--cm-title-color:'           . esc_attr( $dm('dm_title','#f0f0f0') )                . ';';
        echo '--cm-body-color:'            . esc_attr( $dm('dm_body','#b0b0b0') )                 . ';';
        echo '--cm-accept-bg:'             . esc_attr( $dm('dm_accept_bg','#ffffff') )            . ';';
        echo '--cm-accept-text:'           . esc_attr( $dm('dm_accept_text','#111111') )          . ';';
        echo '--cm-accept-hover-bg:'       . esc_attr( $dm('dm_accept_hover_bg','#0091ff') )      . ';';
        echo '--cm-accept-hover-text:'     . esc_attr( $dm('dm_accept_hover_text','#ffffff') )    . ';';
        echo '--cm-accept-border:transparent;';
        echo '--cm-reject-text:'           . esc_attr( $dm('dm_reject_text','#111111') )          . ';';
        echo '--cm-reject-hover-text:'     . esc_attr( $dm('dm_reject_hover_text','#ffffff') )    . ';';
        echo '--cm-reject-bg:'             . esc_attr( $dm('dm_reject_bg','#f2f2f2') )            . ';';
        echo '--cm-reject-hover-bg:'       . esc_attr( $dm('dm_reject_hover_bg','#0091ff') )      . ';';
        echo '--cm-reject-border:transparent;';
        echo '--cm-prefs-border:'          . esc_attr( $dm('dm_prefs_border','#444444') )         . ';';
        echo '--cm-prefs-text:'            . esc_attr( $dm('dm_prefs_text','#aaaaaa') )           . ';';
        echo '--cm-prefs-hover-border:'    . esc_attr( $dm('dm_prefs_hover_border','#888888') )   . ';';
        echo '--cm-prefs-hover-text:'      . esc_attr( $dm('dm_prefs_hover_text','#ffffff') )     . ';';
        echo '--cm-allowall-bg:'           . esc_attr( $dm('dm_allowall_bg','#ffffff') )          . ';';
        echo '--cm-allowall-text:'         . esc_attr( $dm('dm_allowall_text','#111111') )        . ';';
        echo '--cm-allowall-hover-bg:'     . esc_attr( $dm('dm_allowall_hover_bg','#0091ff') )    . ';';
        echo '--cm-allowall-hover-text:'   . esc_attr( $dm('dm_allowall_hover_text','#ffffff') )  . ';';
        echo '--cm-allowall-border:transparent;';
        echo '--cm-close-bg:'              . esc_attr( $dm('dm_close_bg','#2a2a2a') )             . ';';
        echo '--cm-close-hover-bg:'        . esc_attr( $dm('dm_close_hover_bg','#ffffff') )       . ';';
        echo '--cm-close-icon:'            . esc_attr( $dm('dm_close_icon','#aaaaaa') )           . ';';
        echo '--cm-toggle-on:'             . esc_attr( $dm('dm_toggle_on','#0091ff') )            . ';';
        echo '--cm-always-bg:'             . esc_attr( $dm('dm_always_bg','#1a3a5c') )            . ';';
        echo '--cm-float-icon-bg:'         . esc_attr( $dm('dm_float_icon_bg','#f2f2f2') )        . ';';
        echo '--cm-float-icon-color:'      . esc_attr( $dm('dm_float_icon_color','#111111') )     . ';';
        echo '--cm-float-icon-hover-bg:'   . esc_attr( $dm('dm_float_icon_hover_bg','#0091ff') )  . ';';
        echo '--cm-float-icon-hover-color:'. esc_attr( $dm('dm_float_icon_hover_color','#ffffff') ) . ';';
        echo '--cm-cat-border:'            . esc_attr( $dm('dm_cat_border','#2e2e2e') )           . ';';
        echo '--cm-service-bg:'            . esc_attr( $dm('dm_service_bg','#252525') )           . ';';
        echo '--cm-cookie-item-bg:'        . esc_attr( $dm('dm_cookie_item_bg','#1e1e1e') )       . ';';
        echo '--cm-overlay-alpha:0.75;';
        echo '} }</style>' . "\n";
    }

    // Externe CSS — inline geladen en geminificeerd voor snelheid
    $css_file = CM_PLUGIN_DIR . 'assets/css/frontend.css';
    if ( file_exists( $css_file ) ) {
        $css = file_get_contents( $css_file );
        // Minificeer: verwijder comments, dubbele spaties, regeleindes
        $css = preg_replace( '!/\*.*?\*/!s', '', $css );       // comments
        $css = preg_replace( '/\s+/', ' ', $css );              // whitespace
        $css = str_replace( array(' { ', ' } ', '{ ', ' }', '; ', ': ', ', '), array('{', '}', '{', '}', ';', ':', ','), $css );
        $css = trim( $css );
        echo '<style id="cm-frontend-css">' . $css . '</style>' . "\n";
    }
}

/* ================================================================
   MEERTALIGHEID — vertaalhelpers
================================================================ */

/**
 * Vaste UI-teksten per taal — voor hardcoded strings in de banner HTML
 * die niet via admin-instellingen beheerd worden.
 */
function cm_ui( $key ) {
    static $strings = null;
    if ( $strings === null ) {
        $strings = array(
            'nl' => array(
                'always_active'   => 'Altijd actief',
                'duration'        => 'Looptijd',
                'outside_eu'      => 'Buiten EU',
                'third_party'     => 'Derde partij',
                'data_outside_eu' => 'Gegevens worden doorgegeven buiten de EU',
                'no_cookies'      => 'Er worden momenteel geen cookies gebruikt in deze categorie.',
            ),
            'en' => array(
                'always_active'   => 'Always active',
                'duration'        => 'Duration',
                'outside_eu'      => 'Outside EU',
                'third_party'     => 'Third party',
                'data_outside_eu' => 'Data is transferred outside the EU',
                'no_cookies'      => 'No cookies are currently used in this category.',
            ),
        );
    }
    $lang = cm_detect_lang();
    return isset( $strings[ $lang ][ $key ] ) ? $strings[ $lang ][ $key ] : ( $strings['nl'][ $key ] ?? $key );
}


function cm_t( $key ) {
    $lang = cm_detect_lang();
    if ( $lang !== 'nl' ) {
        $lang_key = $key . '_' . $lang; // bijv. 'txt_banner_title_en'
        $val = cm_get( $lang_key );
        if ( $val !== '' && $val !== null ) return $val;
    }
    return cm_get( $key );
}

add_action( 'wp_footer',                 'cm_render_frontend', 999 );
add_action( 'wp_body_open',              'cm_render_frontend', 1   );
add_action( 'nectar_after_main_content', 'cm_render_frontend'      );

/* ================================================================
   GEO-TARGETING
================================================================ */
function cm_requires_consent_banner() {
    // Landen waar een cookiebanner / opt-in vereist is
    $consent_countries = array(
        // EU-27
        'AT','BE','BG','CY','CZ','DE','DK','EE','ES','FI','FR','GR','HR','HU',
        'IE','IT','LT','LU','LV','MT','NL','PL','PT','RO','SE','SI','SK',
        // EEA
        'IS','LI','NO',
        // Vergelijkbare wetgeving
        'GB', // UK GDPR
        'CH', // nDSG (Zwitserland)
        'BR', // LGPD (Brazilië)
        'CA', // PIPEDA / Law 25 Quebec
        'IN', // DPDP Act 2023 (India)
        'TH', // PDPA (Thailand)
        'ID', // PDP Law (Indonesië)
        'ZA', // POPIA (Zuid-Afrika)
        'JP', // APPI (Japan)
        'KR', // PIPA (Zuid-Korea)
        'AU', // Privacy Act (Australië)
        'NZ', // Privacy Act 2020 (Nieuw-Zeeland)
        'SG', // PDPA (Singapore)
        'AR', // Ley 25.326 (Argentinië)
        'MX', // LFPDPPP (Mexico)
    );

    // Probeer land via diverse server-variabelen (CloudFlare, hosting headers)
    $country = '';
    $headers = array(
        'HTTP_CF_IPCOUNTRY',        // Cloudflare
        'HTTP_X_COUNTRY_CODE',      // diverse CDNs
        'HTTP_GEOIP_COUNTRY_CODE',  // mod_geoip
        'HTTP_X_FORWARDED_COUNTRY',
    );
    foreach ( $headers as $h ) {
        if ( ! empty( $_SERVER[ $h ] ) ) {
            $country = strtoupper( sanitize_text_field( wp_unslash( $_SERVER[ $h ] ) ) );
            break;
        }
    }

    // Geen land-header gevonden: veiligheidshalve banner tonen
    if ( ! $country ) return true;

    return in_array( $country, $consent_countries, true );
}

/* ================================================================
   SPOOR 1 — GOOGLE CONSENT MODE V2 + GA4/GTM SELF-LOADER
   De plugin beheert GA4 en GTM zelf — volledig correct en AVG-compliant.
   Verwijder de GA snippet uit uw Header/Footer plugin na het invullen
   van het Measurement ID in Instellingen → Gedrag → Google integratie.
================================================================ */
add_action( 'wp_head', 'cm_inject_google_consent_mode', -1000 );
function cm_inject_google_consent_mode() {
    if ( is_admin() ) return;

    $ga4_id = trim( cm_get('ga4_measurement_id') );
    $gtm_id = trim( cm_get('gtm_container_id') );
    $ua_id  = trim( cm_get('ua_tracking_id') );

    // Lees huidig consent
    $consent     = null;
    $cookie_name = 'cc_cm_consent';
    if ( isset( $_COOKIE[$cookie_name] ) ) {
        $consent = json_decode( stripslashes( urldecode( $_COOKIE[$cookie_name] ) ), true );
    }
    $allow_analytics = ! empty( $consent['analytics'] );
    $allow_marketing = ! empty( $consent['marketing'] );

    $analytics_update = $allow_analytics ? 'granted' : 'denied';
    $marketing_update = $allow_marketing ? 'granted' : 'denied';
    ?>
<!-- Cookiebaas Consent Mode v2 -->
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('consent', 'default', {
    'analytics_storage':  'denied',
    'ad_storage':         'denied',
    'ad_user_data':       'denied',
    'ad_personalization': 'denied',
    'functionality_storage': 'granted',
    'security_storage':      'granted',
    'wait_for_update':    500
});
gtag('set', 'ads_data_redaction', true);
gtag('set', 'url_passthrough', true);
<?php if ( $consent ) : ?>
gtag('consent', 'update', {
    'analytics_storage':  '<?php echo esc_js( $analytics_update ); ?>',
    'ad_storage':         '<?php echo esc_js( $marketing_update ); ?>',
    'ad_user_data':       '<?php echo esc_js( $marketing_update ); ?>',
    'ad_personalization': '<?php echo esc_js( $marketing_update ); ?>'
});
<?php endif; ?>
</script>
<?php
    // GA4 self-loader — beide scripts krijgen server-side het juiste type
    // Bij geen consent: type="text/plain" + data-cm-type="analytics"
    // Bij consent:      normale script tags
    if ( $ga4_id && preg_match('/^G-[A-Z0-9]+$/i', $ga4_id) ) :
        if ( $allow_analytics ) : ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr($ga4_id); ?>"></script>
<script>gtag('js', new Date()); gtag('config', '<?php echo esc_js($ga4_id); ?>');</script>
        <?php else : ?>
<script type="text/plain" data-cm-type="analytics" data-cm-blocked-src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr($ga4_id); ?>" async></script>
<script type="text/plain" data-cm-type="analytics">gtag('js', new Date()); gtag('config', '<?php echo esc_js($ga4_id); ?>');</script>
        <?php endif;
    endif;

    // GTM self-loader
    if ( $gtm_id && preg_match('/^GTM-[A-Z0-9]+$/i', $gtm_id) ) :
        if ( $allow_analytics ) : ?>
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','<?php echo esc_js($gtm_id); ?>');</script>
        <?php else : ?>
<script type="text/plain" data-cm-type="analytics">(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','<?php echo esc_js($gtm_id); ?>');</script>
        <?php endif;
    endif;

    // UA (Universal Analytics) self-loader — verouderd maar nog ondersteund
    if ( $ua_id && preg_match('/^UA-[0-9]+-[0-9]+$/i', $ua_id) ) :
        if ( $allow_analytics ) : ?>
<script async src="https://www.google-analytics.com/analytics.js"></script>
<script>window.ga=window.ga||function(){(ga.q=ga.q||[]).push(arguments)};ga.l=+new Date;ga('create','<?php echo esc_js($ua_id); ?>','auto');ga('send','pageview');</script>
        <?php else : ?>
<script type="text/plain" data-cm-type="analytics" data-cm-blocked-src="https://www.google-analytics.com/analytics.js" async></script>
<script type="text/plain" data-cm-type="analytics">window.ga=window.ga||function(){(ga.q=ga.q||[]).push(arguments)};ga.l=+new Date;ga('create','<?php echo esc_js($ua_id); ?>','auto');ga('send','pageview');</script>
        <?php endif;
    endif; ?>
    <?php
}

/* ================================================================
   SPOOR 2 — OUTPUT BUFFER (voor niet-Google scripts)
   Werkt server-side: scripts krijgen type="text/plain" in de HTML.
   Vereist dat de server output buffering ondersteunt.
   Detecteert automatisch bekende tracking scripts op basis van
   een ingebouwde kennisbank + instelbare patronen.
================================================================ */

// Ingebouwde kennisbank — automatisch herkende scripts zonder configuratie
function cm_get_known_patterns() {
    return array(
        'analytics' => array(
            'googletagmanager.com',
            'google-analytics.com',
            'analytics.google.com',
            'hotjar.com',
            'clarity.ms',
            'mouseflow.com',
            'fullstory.com',
            'heap.io',
            'mixpanel.com',
            'segment.com',
            'amplitude.com',
            'matomo',
            'piwik',
            'plausible.io',
            'fathom.com',
            'simpleanalytics',
        ),
        'marketing' => array(
            'facebook.net',
            'connect.facebook.net',
            'doubleclick.net',
            'googlesyndication.com',
            'googleadservices.com',
            'google.com/pagead',
            'tiktok.com',
            'snap.licdn.com',
            'linkedin.com/px',
            'twitter.com/i/adsct',
            'ads.twitter.com',
            'pinterest.com/v3',
            'bing.com/bat.js',
            'bat.bing.com',
            'adroll.com',
            'criteo',
            'outbrain',
            'taboola',
            'quantserve.com',
            'scorecardresearch.com',
        ),
    );
}

// Combineer ingebouwde kennisbank met aangepaste patronen
function cm_get_all_patterns( $type ) {
    $known   = cm_get_known_patterns();
    $builtin = isset( $known[$type] ) ? $known[$type] : array();
    $custom  = array_filter( array_map( 'trim', explode( ',', cm_get('block_' . $type . '_patterns') ) ) );
    return array_values( array_unique( array_merge( $builtin, $custom ) ) );
}

add_action( 'wp', 'cm_init_cookie_blocker' );
function cm_init_cookie_blocker() {
    if ( is_admin() ) return;
    if ( defined('DOING_AJAX') && DOING_AJAX ) return;
    if ( defined('REST_REQUEST') && REST_REQUEST ) return;

    $consent     = null;
    $cookie_name = 'cc_cm_consent';
    if ( isset( $_COOKIE[$cookie_name] ) ) {
        $consent = json_decode( stripslashes( urldecode( $_COOKIE[$cookie_name] ) ), true );
    }
    $allow_analytics = ! empty( $consent['analytics'] );
    $allow_marketing = ! empty( $consent['marketing'] );
    if ( $allow_analytics && $allow_marketing ) return;

    ob_start( 'cm_filter_buffer' );
    add_action( 'wp_footer', 'cm_end_buffer', 9999 );
}

function cm_end_buffer() {
    if ( ob_get_level() > 0 && ob_get_length() !== false ) {
        ob_end_flush();
    }
}

function cm_script_tag_blocked( $tag, $patterns_a, $patterns_m, $allow_analytics, $allow_marketing ) {
    // Externe scripts — match op src
    if ( preg_match( '/\bsrc\s*=\s*["\']([^"\']*)["\']/', $tag, $m ) ) {
        $src = $m[1];
        if ( ! $allow_analytics ) {
            foreach ( $patterns_a as $p ) {
                if ( $p && stripos( $src, $p ) !== false ) return 'analytics';
            }
        }
        if ( ! $allow_marketing ) {
            foreach ( $patterns_m as $p ) {
                if ( $p && stripos( $src, $p ) !== false ) return 'marketing';
            }
        }
    }
    return false;
}

function cm_inline_script_blocked( $block, $patterns_a, $patterns_m, $allow_analytics, $allow_marketing ) {
    // Sla plugin-eigen scripts over
    foreach ( array( 'cc_cm_consent', 'cm-blocker', 'cm_render_frontend', 'WAKKR', 'dataLayer.push(arguments)', 'wait_for_update' ) as $skip ) {
        if ( stripos( $block, $skip ) !== false ) return false;
    }
    if ( ! $allow_analytics ) {
        foreach ( $patterns_a as $p ) {
            if ( $p && stripos( $block, $p ) !== false ) return 'analytics';
        }
        // Automatische GTM/GA inline herkenning
        if ( stripos($block,'dataLayer')!==false && ( stripos($block,'gtag(')!==false || stripos($block,'GTM-')!==false ) ) {
            return 'analytics';
        }
    }
    if ( ! $allow_marketing ) {
        foreach ( $patterns_m as $p ) {
            if ( $p && stripos( $block, $p ) !== false ) return 'marketing';
        }
        // Facebook Pixel inline herkenning
        if ( stripos($block,'fbq(')!==false || stripos($block,'fbevents')!==false ) return 'marketing';
    }
    return false;
}

function cm_filter_buffer( $html ) {
    if ( ! $html ) return $html;

    $consent     = null;
    $cookie_name = 'cc_cm_consent';
    if ( isset( $_COOKIE[$cookie_name] ) ) {
        $consent = json_decode( stripslashes( urldecode( $_COOKIE[$cookie_name] ) ), true );
    }
    $allow_analytics = ! empty( $consent['analytics'] );
    $allow_marketing = ! empty( $consent['marketing'] );
    if ( $allow_analytics && $allow_marketing ) return $html;

    $pA = cm_get_all_patterns('analytics');
    $pM = cm_get_all_patterns('marketing');

    // Blokkeer externe scripts
    $html = preg_replace_callback(
        '/<script(\s[^>]*)?>/i',
        function( $matches ) use ( $pA, $pM, $allow_analytics, $allow_marketing ) {
            $tag = $matches[0];
            if ( stripos( $tag, 'data-cm-type=' ) !== false ) return $tag;
            $type = cm_script_tag_blocked( $tag, $pA, $pM, $allow_analytics, $allow_marketing );
            if ( ! $type ) return $tag;
            $tag = preg_replace( '/\btype\s*=\s*["\'][^"\']*["\']/i', '', $tag );
            return str_replace( '<script', '<script type="text/plain" data-cm-type="' . $type . '"', $tag );
        },
        $html
    );

    // Blokkeer inline scripts
    $html = preg_replace_callback(
        '/<script(?:\s[^>]*)?>[\s\S]*?<\/script>/i',
        function( $matches ) use ( $pA, $pM, $allow_analytics, $allow_marketing ) {
            $b = $matches[0];
            if ( stripos( $b, 'data-cm-type=' ) !== false ) return $b;
            if ( preg_match( '/\bsrc\s*=/i', $b ) ) return $b; // externe scripts al afgehandeld
            $type = cm_inline_script_blocked( $b, $pA, $pM, $allow_analytics, $allow_marketing );
            if ( ! $type ) return $b;
            return preg_replace( '/<script(\s[^>]*)?>/i', '<script type="text/plain" data-cm-type="' . $type . '">', $b, 1 );
        },
        $html
    );

    // Blokkeer iframes van bekende embed-domeinen (YouTube, Vimeo, etc.)
    if ( cm_get('embed_blocker_enabled') ) {
        $html = preg_replace_callback(
            '/<iframe\s[^>]*(?:\/>|>[\s\S]*?<\/iframe>)/i',
            function( $matches ) use ( $allow_analytics, $allow_marketing ) {
                $tag = $matches[0];
                // Al geblokkeerd? Skip.
                if ( stripos( $tag, 'data-cm-embed' ) !== false ) return $tag;
                // Haal src eruit
                if ( ! preg_match( '/\bsrc\s*=\s*["\']([^"\']*)["\']/', $tag, $sm ) ) return $tag;
                $src = $sm[1];
                // Match tegen bekende embed-domeinen
                $info = cm_match_embed_domain( $src );
                if ( ! $info ) return $tag;
                $cat = $info['category'];
                // Check of consent al gegeven is voor deze categorie
                if ( $cat === 'analytics' && $allow_analytics ) return $tag;
                if ( $cat === 'marketing' && $allow_marketing ) return $tag;
                // Bouw placeholder HTML (vervangt volledige <iframe>...</iframe>)
                return cm_build_embed_placeholder( $tag, $src, $info );
            },
            $html
        );
    }

    return $html;
}

/**
 * Bouwt een placeholder div die een geblokkeerd iframe vervangt.
 * De hele <iframe ...> tag wordt vervangen door een <div> met de originele data.
 */
function cm_build_embed_placeholder( $original_tag, $src, $info ) {
    $service  = esc_attr( $info['service'] );
    $cat      = esc_attr( $info['category'] );
    $icon     = isset( $info['icon'] ) ? $info['icon'] : '▶';

    // Haal breedte/hoogte uit originele iframe voor aspect ratio
    $width  = '';
    $height = '';
    if ( preg_match( '/\bwidth\s*=\s*["\']?(\d+)/i', $original_tag, $wm ) ) $width = $wm[1];
    if ( preg_match( '/\bheight\s*=\s*["\']?(\d+)/i', $original_tag, $hm ) ) $height = $hm[1];

    // Bereken aspect ratio padding (standaard 16:9)
    $ratio_pct = '56.25%';
    if ( $width && $height && (int)$width > 0 ) {
        $ratio_pct = number_format( ( (int)$height / (int)$width ) * 100, 4, '.', '' ) . '%';
    }

    // Teksten ophalen (taalafhankelijk)
    $title_txt  = cm_t('txt_embed_title');
    $body_txt   = str_replace( '{service}', '<strong>' . esc_html( $info['service'] ) . '</strong>', cm_t('txt_embed_body') );
    // Dubbele <strong> voorkomen (body template bevat al <strong> tags)
    $body_txt   = preg_replace( '/<strong>\s*<strong>/', '<strong>', $body_txt );
    $body_txt   = preg_replace( '/<\/strong>\s*<\/strong>/', '</strong>', $body_txt );
    $btn_txt    = cm_t('txt_embed_btn');
    $prefs_txt  = cm_t('txt_embed_prefs');

    // Thumbnail voor YouTube
    $thumb_style = '';
    if ( stripos( $src, 'youtube' ) !== false || stripos( $src, 'youtu.be' ) !== false ) {
        // Haal video ID uit YouTube URL
        if ( preg_match( '/(?:embed\/|v\/|v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $src, $vid ) ) {
            $thumb_url = 'https://img.youtube.com/vi/' . $vid[1] . '/hqdefault.jpg';
            $thumb_style = ' style="background-image:url(\'' . esc_url( $thumb_url ) . '\')"';
        }
    }

    // Bewaar het volledige originele iframe als data-attribuut (base64 encoded)
    $encoded_tag = base64_encode( $original_tag );

    $html  = '<!--cm-embed-placeholder-->';
    $html .= '<div class="cm-embed-placeholder" data-cm-embed-cat="' . $cat . '" data-cm-embed-src="' . esc_attr( $src ) . '" data-cm-embed-service="' . $service . '" data-cm-embed-tag="' . esc_attr( $encoded_tag ) . '">';
    $html .= '<div class="cm-embed-ratio" style="padding-bottom:' . $ratio_pct . '"' . $thumb_style . '>';
    $html .= '<div class="cm-embed-inner">';
    $html .= '<div class="cm-embed-icon">' . $icon . '</div>';
    $html .= '<div class="cm-embed-title">' . esc_html( $title_txt ) . '</div>';
    $html .= '<div class="cm-embed-body">' . wp_kses( $body_txt, array( 'strong' => array(), 'em' => array() ) ) . '</div>';
    $html .= '<button type="button" class="cm-embed-accept-btn" data-cm-embed-cat="' . $cat . '">' . esc_html( $btn_txt ) . '</button>';
    $html .= '<div class="cm-embed-prefs-link">' . wp_kses( $prefs_txt, array( 'a' => array( 'href' => array(), 'class' => array() ) ) ) . '</div>';
    $html .= '</div></div></div>';
    $html .= '<!--/cm-embed-placeholder-->';

    return $html;
}

/* ================================================================
   SPOOR 3 — JS CONSENT MODE UPDATE
   Na akkoord: stuur gtag consent update + laad geblokkeerde scripts.
   Geen page reload nodig voor Google — Consent Mode regelt dit zelf.
   Overige scripts (type=text/plain) worden dynamisch ingeladen.
================================================================ */
add_action( 'wp_head', 'cm_output_script_blocker', -999 );
function cm_output_script_blocker() {
    if ( is_admin() ) return;
    // Altijd renderen — ook zonder patronen (voor Consent Mode update)
    $pA = cm_get_all_patterns('analytics');
    $pM = cm_get_all_patterns('marketing');
    ?>
<script id="cm-blocker">(function(){
    /* --- Consent lezen --- */
    function getConsent(){var n='cc_cm_consent=',c=document.cookie.split(';');for(var i=0;i<c.length;i++){var x=c[i].trim();if(x.indexOf(n)===0){try{return JSON.parse(decodeURIComponent(x.slice(n.length)));}catch(e){}}}return null;}
    var consent=getConsent(),allowA=!!(consent&&consent.analytics),allowM=!!(consent&&consent.marketing);
    if(allowA&&allowM)return;

    var pA=<?php echo wp_json_encode($pA); ?>;
    var pM=<?php echo wp_json_encode($pM); ?>;

    /* --- Patroon matching --- */
    function matches(s,p){if(!s)return false;s=s.toLowerCase();for(var i=0;i<p.length;i++){if(p[i]&&s.indexOf(p[i].toLowerCase())!==-1)return true;}return false;}
    function getType(src,txt){
        if(!allowA){
            if(matches(src,pA)||matches(txt,pA))return'analytics';
            if(txt&&txt.indexOf('dataLayer')!==-1&&(txt.indexOf('gtag(')!==-1||txt.indexOf('GTM-')!==-1))return'analytics';
        }
        if(!allowM){
            if(matches(src,pM)||matches(txt,pM))return'marketing';
            if(txt&&(txt.indexOf('fbq(')!==-1||txt.indexOf('fbevents')!==-1))return'marketing';
        }
        return false;
    }

    /* --- Script blokkeren --- */
    var skipWords=['cc_cm_consent','cm-blocker','cm_render_frontend','WAKKR','wait_for_update'];
    function shouldSkip(txt){for(var i=0;i<skipWords.length;i++){if(txt.indexOf(skipWords[i])!==-1)return true;}return false;}
    function blockNode(s){
        if(s.getAttribute('data-cm-type'))return;
        var src=s.getAttribute('src')||'',txt=s.textContent||'';
        if(shouldSkip(txt))return;
        var t=getType(src,txt);
        if(!t)return;
        s.setAttribute('data-cm-type',t);
        if(src){s.setAttribute('data-cm-blocked-src',src);s.removeAttribute('src');}
        s.type='text/plain';
    }

    /* --- Iframe blokkeren (embed blocker) --- */
    <?php if ( cm_get('embed_blocker_enabled') ) :
        // Filter domeinen op basis van geblokkeerde diensten
        $blocked_raw = cm_get('embed_blocked_services');
        $blocked_list = $blocked_raw ? array_map('trim', explode(',', $blocked_raw)) : array();
        $block_all = empty($blocked_raw);
        $filtered_domains = array_filter( cm_get_embed_domains(), function($v) use ($block_all, $blocked_list) {
            if ( $v['category'] === 'functional' ) return false;
            if ( $block_all ) return true;
            return in_array( $v['service'], $blocked_list, true );
        });
    ?>
    var embedDomains=<?php echo wp_json_encode( array_values( array_keys( $filtered_domains ) ) ); ?>;
    var embedMap=<?php echo wp_json_encode( $filtered_domains ); ?>;
    function matchEmbed(src){
        if(!src)return false;
        var sl=src.toLowerCase();
        for(var i=0;i<embedDomains.length;i++){
            if(sl.indexOf(embedDomains[i])!==-1){
                var info=embedMap[embedDomains[i]];
                if(info.category==='analytics'&&allowA)return false;
                if(info.category==='marketing'&&allowM)return false;
                return info;
            }
        }
        return false;
    }
    function blockIframe(iframe){
        if(iframe.getAttribute('data-cm-embed-src'))return;
        var src=iframe.getAttribute('src')||iframe.getAttribute('data-src')||'';
        if(!src)return;
        var info=matchEmbed(src);
        if(!info)return;
        /* Verwijder src zodat het iframe niet laadt */
        iframe.setAttribute('data-cm-embed-src',src);
        iframe.setAttribute('data-cm-embed-cat',info.category);
        iframe.setAttribute('data-cm-embed-service',info.service);
        iframe.removeAttribute('src');
        /* PHP output buffer vangt de meeste op, maar dit is voor dynamisch geladen iframes */
    }
    <?php endif; ?>

    /* --- MutationObserver voor dynamisch geladen scripts en iframes --- */
    var obs=new MutationObserver(function(ms){
        ms.forEach(function(m){
            m.addedNodes.forEach(function(n){
                if(n.nodeType!==1)return;
                if(n.tagName==='SCRIPT')blockNode(n);
                <?php if ( cm_get('embed_blocker_enabled') ) : ?>
                else if(n.tagName==='IFRAME')blockIframe(n);
                <?php endif; ?>
                else if(n.querySelectorAll){
                    n.querySelectorAll('script').forEach(blockNode);
                    <?php if ( cm_get('embed_blocker_enabled') ) : ?>
                    n.querySelectorAll('iframe').forEach(blockIframe);
                    <?php endif; ?>
                }
            });
        });
    });
    obs.observe(document.documentElement,{childList:true,subtree:true});
    document.addEventListener('DOMContentLoaded',function(){obs.disconnect();});
})();</script>
    <?php
}

/* ================================================================
   PAGINA-UITZONDERINGEN
================================================================ */
function cm_is_excluded_page() {
    // 1. WordPress login/registratie pagina
    if ( cm_get('exclude_login_page') ) {
        $uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        if ( strpos( $uri, 'wp-login.php' ) !== false ) return true;
        if ( strpos( $uri, 'wp-register.php' ) !== false ) return true;
    }

    // 2. WooCommerce checkout, betaling, bestellingsbevestiging
    if ( cm_get('exclude_woocommerce_checkout') ) {
        if ( function_exists('is_checkout') && is_checkout() ) return true;
        if ( function_exists('is_order_received_page') && is_order_received_page() ) return true;
        if ( function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-pay') ) return true;
    }

    // 3. Specifieke pagina-ID's
    $ids_raw = cm_get('exclude_page_ids');
    if ( $ids_raw ) {
        $ids = array_filter( array_map( 'intval', explode( ',', $ids_raw ) ) );
        if ( ! empty( $ids ) && is_singular() ) {
            global $post;
            if ( $post && in_array( (int) $post->ID, $ids, true ) ) return true;
        }
    }

    // 4. URL-patronen
    $patterns_raw = cm_get('exclude_url_patterns');
    if ( $patterns_raw ) {
        $uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        $patterns = array_filter( array_map( 'trim', explode( ',', $patterns_raw ) ) );
        foreach ( $patterns as $pattern ) {
            if ( $pattern && strpos( $uri, $pattern ) !== false ) return true;
        }
    }

    return false;
}

function cm_render_frontend() {
    if ( ! empty( $GLOBALS['cm_rendered'] ) ) return;
    if ( is_admin() ) return;

    // Pagina-uitzonderingen — geen banner op uitgesloten pagina's
    if ( cm_is_excluded_page() ) {
        $GLOBALS['cm_rendered'] = true;
        return;
    }

    $GLOBALS['cm_rendered'] = true;

    // Geo-targeting check
    // Optie 1 (standaard): altijd banner tonen — geo_enabled = 0
    // Optie 2: alleen tonen aan landen met privacywetgeving — geo_enabled = 1
    if ( cm_get('geo_enabled') ) {
        if ( ! cm_requires_consent_banner() ) {
            // Bezoeker komt uit een land zonder vergelijkbare privacywetgeving
            $outside = cm_get('geo_outside_eu') ?: 'hide';
            if ( $outside === 'accept' ) {
                // Automatisch consent geven via JS
                echo '<script>(function(){if(!document.cookie.includes("cc_cm_consent=")){var exp=new Date(Date.now()+365*24*3600*1000).toUTCString();var c="cc_cm_consent="+encodeURIComponent(JSON.stringify({v:"2.0",sv:"1",analytics:true,marketing:true,method:"geo-auto",exp:exp}))+"; expires="+exp+"; path=/; SameSite=Lax";if(location.protocol==="https:")c+="; Secure";document.cookie=c;}})();</script>' . "\n";
            }
            // Beide opties: geen banner renderen
            return;
        }
    }

    $show_float      = cm_get('show_float_btn');
    $float_btn_style = cm_get('float_btn_style'); // 'text' of 'icon'

    // Helper: geeft derde-partij badge HTML terug op basis van service mapping
    $third_party_badge = function( $provider ) {
        static $provider_map = null;
        if ( $provider_map === null ) {
            // Bouw eenmalig een map van service-naam → info
            // door de bekende eerste cookie per dienst op te zoeken
            $samples = array('_ga','_fbp','bcookie','_hj','_cl','_tt_','_pinterest_ct_','twid','YSC','intercom-x','__hs','__stripe_a','__cf_a','_gcl_au','li_gc','_gcl_aw','MUID','ttwid');
            $provider_map = array();
            foreach ( $samples as $sample ) {
                $info = cm_service_for_cookie( $sample );
                if ( $info && ! empty($info['third_party']) && ! isset($provider_map[$info['service']]) ) {
                    $provider_map[$info['service']] = $info;
                }
            }
        }
        $info = isset($provider_map[$provider]) ? $provider_map[$provider] : null;
        if ( ! $info || empty($info['third_party']) ) return '';
        $country = isset($info['country']) ? $info['country'] : '';
        $label   = $country ? cm_ui('outside_eu') . ' &middot; ' . esc_html($country) : cm_ui('third_party');
        return '<span class="cm-badge-thirdparty" aria-label="' . esc_attr( cm_ui('data_outside_eu') ) . '">' . esc_html($label) . '</span>';
    };

    // Bouw cookielijst per categorie op vanuit database
    $all_cookies = cm_get_cookie_list();

    // Bij EN-modus: haal live Engelse beschrijvingen op uit de Open Cookie Database
    // en vertaal opgeslagen NL-looptijden terug naar Engels.
    // De opgeslagen 'purpose' is NL (gezet bij scannen) — bij EN vervangen door originele DB-tekst.
    if ( cm_detect_lang() === 'en' ) {
        $nl_to_en_duration = array(
            'sessie'    => 'Session',    'session'   => 'Session',
            'permanent' => 'Persistent', 'nooit'     => 'Never',
            'direct'    => 'Immediately',
        );
        $nl_to_en_units = array(
            'jaar'    => 'year',   'jaren'   => 'years',
            'maand'   => 'month',  'maanden' => 'months',
            'week'    => 'week',   'weken'   => 'weeks',
            'dag'     => 'day',    'dagen'   => 'days',
            'uur'     => 'hour',   'uren'    => 'hours',
            'minuut'  => 'minute', 'minuten' => 'minutes',
        );
        foreach ( $all_cookies as &$ck ) {
            // -- Purpose: live opzoeken in DB voor niet-builtin cookies --
            if ( empty( $ck['builtin'] ) && ! empty( $ck['name'] ) ) {
                $db_row = cm_lookup_cookie( $ck['name'] );
                if ( $db_row && ! empty( $db_row['description'] ) ) {
                    $ck['purpose'] = $db_row['description'];
                }
                // Gebruik ook DB-retention als die beschikbaar is (al in EN door cm_lookup_cookie)
                if ( $db_row && ! empty( $db_row['retention'] ) ) {
                    $ck['duration'] = $db_row['retention'];
                    continue; // duration al goed, skip NL→EN vertaling hieronder
                }
            }

            // -- Duration: NL→EN vertalen voor opgeslagen teksten --
            if ( empty( $ck['duration'] ) ) continue;
            $d     = trim( $ck['duration'] );
            $lower = strtolower( $d );
            if ( isset( $nl_to_en_duration[ $lower ] ) ) {
                $ck['duration'] = $nl_to_en_duration[ $lower ];
                continue;
            }
            if ( preg_match( '/^(\d+[\.,]?\d*)\s+(.+)$/i', $d, $m ) ) {
                $num  = $m[1];
                $unit = strtolower( trim( $m[2] ) );
                if ( isset( $nl_to_en_units[ $unit ] ) ) {
                    $en_unit = $nl_to_en_units[ $unit ];
                    if ( (float) str_replace( ',', '.', $num ) != 1.0 && substr( $en_unit, -1 ) !== 's' ) {
                        $en_unit .= 's';
                    }
                    $ck['duration'] = $num . ' ' . $en_unit;
                }
            }
        }
        unset( $ck );
    }

    $cats = array( 'functional' => array(), 'analytics' => array(), 'marketing' => array() );
    foreach ( $all_cookies as $ck ) {
        $cat = isset($ck['category']) ? $ck['category'] : 'functional';
        if ( isset($cats[$cat]) ) $cats[$cat][] = $ck;
    }

    // Groepeer analytics en marketing cookies per dienst
    // Gebruikt cm_service_for_cookie() voor automatische herkenning
    // Valt terug op de provider-veld als cookie niet herkend wordt
    $group_cats = array( 'analytics', 'marketing' );
    $services   = array( 'analytics' => array(), 'marketing' => array() );
    foreach ( $group_cats as $cat ) {
        foreach ( $cats[$cat] as $ck ) {
            $mapped = cm_service_for_cookie( $ck['name'] );
            if ( $mapped ) {
                $provider = $mapped['service'];
            } else {
                $provider = isset($ck['provider']) && $ck['provider'] ? $ck['provider'] : 'Overig';
            }
            if ( ! isset($services[$cat][$provider]) ) {
                $services[$cat][$provider] = array();
            }
            $services[$cat][$provider][] = $ck;
        }
    }

    // Script blocking patronen naar JS doorgeven
    $block_analytics = array_filter( array_map( 'trim', explode(',', cm_get('block_analytics_patterns') ) ) );
    $block_marketing = array_filter( array_map( 'trim', explode(',', cm_get('block_marketing_patterns') ) ) );
    ?>

    <!-- ===== COOKIEMELDING PLUGIN ===== -->
    <div id="cm-overlay" aria-hidden="true" style="display:none"></div>

    <div id="cm-banner" role="dialog" aria-modal="true"
         aria-labelledby="cm-banner-title"
         aria-describedby="cm-banner-desc"
         data-position="<?php echo esc_attr( cm_get('banner_position') ?: 'bottom-center' ); ?>"
         style="display:none">
        <div class="cm-box">
            <h2 class="cm-title" id="cm-banner-title"><?php echo esc_html( cm_t('txt_banner_title') ); ?></h2>
            <div class="cm-text" id="cm-banner-desc"><?php echo wp_kses( cm_t('txt_banner_body'), array('a'=>array('href'=>array(),'target'=>array()),'strong'=>array(),'em'=>array()) ); ?></div>
            <div class="cm-footer">
                <div class="cm-footer-left">
                    <button type="button" class="cm-btn cm-btn-ghost" id="cm-btn-prefs"><?php echo esc_html( cm_t('txt_btn_prefs') ); ?></button>
                </div>
                <div class="cm-footer-right">
                    <button type="button" class="cm-btn cm-btn-reject" id="cm-btn-reject"><?php echo esc_html( cm_t('txt_btn_reject') ); ?></button>
                    <button type="button" class="cm-btn cm-btn-accept" id="cm-btn-accept"><?php echo esc_html( cm_t('txt_btn_accept') ); ?></button>
                </div>
            </div>
        </div>
    </div>

    <div id="cm-prefs" role="dialog" aria-modal="true"
         aria-labelledby="cm-prefs-title-h2"
         aria-describedby="cm-prefs-desc"
         style="display:none">
        <div class="cm-prefs-box">
            <div class="cm-prefs-header">
                <button type="button" class="cm-prefs-close" id="cm-prefs-close" aria-label="Sluiten">&#x2715;</button>
                <h2 class="cm-prefs-title" id="cm-prefs-title-h2"><?php echo esc_html( cm_t('txt_prefs_title') ); ?></h2>
                <p class="cm-prefs-text" id="cm-prefs-desc"><?php echo wp_kses( cm_t('txt_prefs_body'), array('a'=>array('href'=>array(),'target'=>array()),'strong'=>array(),'em'=>array()) ); ?></p>
                <button type="button" class="cm-allow-all" id="cm-allowall-btn"><?php echo esc_html( cm_t('txt_btn_allowall') ); ?></button>
            </div>
            <div class="cm-prefs-body">
                <div class="cm-section-label">Cookievoorkeuren beheren</div>
                <div class="cm-categories">

                    <!-- Categorie 1: Functioneel (altijd actief) -->
                    <div class="cm-category" id="cm-cat-functional">
                        <div class="cm-cat-header" data-cat="functional"
                             role="button" tabindex="0" aria-expanded="false" aria-controls="cm-cat-detail-functional">
                            <span class="cm-expand-icon" aria-hidden="true">+</span>
                            <div class="cm-cat-info">
                                <div class="cm-cat-name"><?php echo esc_html( cm_t('txt_cat1_name') ); ?></div>
                                <div class="cm-cat-desc"><?php echo esc_html( cm_t('txt_cat1_short') ); ?></div>
                            </div>
                            <span class="cm-always-on"><?php echo esc_html( cm_ui('always_active') ); ?></span>
                        </div>
                        <div class="cm-cat-detail" id="cm-cat-detail-functional">
                            <p><?php echo esc_html( cm_t('txt_cat1_long') ); ?></p>
                            <?php if ( ! empty($cats['functional']) ) : ?>
                            <div class="cm-cookie-list">
                                <?php foreach ( $cats['functional'] as $ck ) : ?>
                                <div class="cm-cookie-item">
                                    <div class="cm-cookie-name"><?php echo esc_html($ck['name']); ?></div>
                                    <div class="cm-cookie-meta">
                                        <span><?php echo esc_html($ck['purpose']); ?></span>
                                        <span><?php echo esc_html( cm_ui('duration') ); ?>: <?php echo esc_html($ck['duration']); ?></span>
                                        <span class="cm-cookie-provider"><?php echo esc_html($ck['provider']); ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else : ?>
                            <p class="cm-no-cookies"><?php echo esc_html( cm_ui('no_cookies') ); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Categorie 2: Analytisch -->
                    <div class="cm-category" id="cm-cat-analytics">
                        <div class="cm-cat-header" data-cat="analytics"
                             role="button" tabindex="0" aria-expanded="false" aria-controls="cm-cat-detail-analytics">
                            <span class="cm-expand-icon" aria-hidden="true">+</span>
                            <div class="cm-cat-info">
                                <div class="cm-cat-name"><?php echo esc_html( cm_t('txt_cat2_name') ); ?></div>
                                <div class="cm-cat-desc"><?php echo esc_html( cm_t('txt_cat2_short') ); ?></div>
                            </div>
                            <label class="cm-toggle" onclick="event.stopPropagation()">
                                <input type="checkbox" id="cm-toggle-analytics"
                                    aria-label="<?php echo esc_attr( cm_t('txt_cat2_name') ); ?>"
                                    onchange="cmCatToggleAll(this,'analytics')"
                                    aria-controls="cm-cat-detail-analytics">
                                <span class="cm-toggle-track"></span>
                            </label>
                        </div>
                        <div class="cm-cat-detail" id="cm-cat-detail-analytics">
                            <p><?php echo esc_html( cm_t('txt_cat2_long') ); ?></p>
                            <?php if ( ! empty($services['analytics']) ) : ?>
                            <div class="cm-service-list">
                                <?php foreach ( $services['analytics'] as $provider => $cookies ) :
                                    $service_id = 'svc-analytics-' . sanitize_title($provider);
                                    $cookie_names = array_column($cookies, 'name');
                                ?>
                                <div class="cm-service" data-service="<?php echo esc_attr($service_id); ?>">
                                    <div class="cm-service-header">
                                        <span class="cm-service-name"><?php echo esc_html($provider); ?><?php echo $third_party_badge($provider); ?></span>
                                        <label class="cm-toggle cm-toggle--sm" onclick="event.stopPropagation()">
                                            <input type="checkbox"
                                                class="cm-service-toggle"
                                                data-cat="analytics"
                                                data-service="<?php echo esc_attr($service_id); ?>"
                                                data-cookies="<?php echo esc_attr(implode(',', $cookie_names)); ?>"
                                                aria-label="<?php echo esc_attr($provider); ?>"
                                                onchange="cmServiceToggle(this)">
                                            <span class="cm-toggle-track"></span>
                                        </label>
                                    </div>
                                    <div class="cm-cookie-list">
                                        <?php foreach ( $cookies as $ck ) :
                                            $svc = cm_service_for_cookie( $ck['name'] );
                                            $org = $svc ? explode( ' / ', $svc['service'] )[0] : ( isset($ck['provider']) ? $ck['provider'] : '' );
                                        ?>
                                        <div class="cm-cookie-item">
                                            <div class="cm-cookie-name"><?php echo esc_html($ck['name']); ?></div>
                                            <div class="cm-cookie-meta">
                                                <div class="cm-cookie-meta-row"><span><?php echo esc_html($ck['purpose']); ?></span></div>
                                                <div class="cm-cookie-meta-row">
                                                    <?php if ( $org ) : ?><span class="cm-cookie-meta-provider"><?php echo esc_html($org); ?></span><span>&middot;</span><?php endif; ?>
                                                    <span><?php echo esc_html( cm_ui('duration') ); ?>: <?php echo esc_html($ck['duration']); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else : ?>
                            <p class="cm-no-cookies"><?php echo esc_html( cm_ui('no_cookies') ); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Categorie 3: Marketing -->
                    <div class="cm-category" id="cm-cat-marketing">
                        <div class="cm-cat-header" data-cat="marketing"
                             role="button" tabindex="0" aria-expanded="false" aria-controls="cm-cat-detail-marketing">
                            <span class="cm-expand-icon" aria-hidden="true">+</span>
                            <div class="cm-cat-info">
                                <div class="cm-cat-name"><?php echo esc_html( cm_t('txt_cat3_name') ); ?></div>
                                <div class="cm-cat-desc"><?php echo esc_html( cm_t('txt_cat3_short') ); ?></div>
                            </div>
                            <label class="cm-toggle" onclick="event.stopPropagation()">
                                <input type="checkbox" id="cm-toggle-marketing"
                                    aria-label="<?php echo esc_attr( cm_t('txt_cat3_name') ); ?>"
                                    onchange="cmCatToggleAll(this,'marketing')"
                                    aria-controls="cm-cat-detail-marketing">
                                <span class="cm-toggle-track"></span>
                            </label>
                        </div>
                        <div class="cm-cat-detail" id="cm-cat-detail-marketing">
                            <p><?php echo esc_html( cm_t('txt_cat3_long') ); ?></p>
                            <?php if ( ! empty($services['marketing']) ) : ?>
                            <div class="cm-service-list">
                                <?php foreach ( $services['marketing'] as $provider => $cookies ) :
                                    $service_id = 'svc-marketing-' . sanitize_title($provider);
                                    $cookie_names = array_column($cookies, 'name');
                                ?>
                                <div class="cm-service" data-service="<?php echo esc_attr($service_id); ?>">
                                    <div class="cm-service-header">
                                        <span class="cm-service-name"><?php echo esc_html($provider); ?><?php echo $third_party_badge($provider); ?></span>
                                        <label class="cm-toggle cm-toggle--sm" onclick="event.stopPropagation()">
                                            <input type="checkbox"
                                                class="cm-service-toggle"
                                                data-cat="marketing"
                                                data-service="<?php echo esc_attr($service_id); ?>"
                                                data-cookies="<?php echo esc_attr(implode(',', $cookie_names)); ?>"
                                                aria-label="<?php echo esc_attr($provider); ?>"
                                                onchange="cmServiceToggle(this)">
                                            <span class="cm-toggle-track"></span>
                                        </label>
                                    </div>
                                    <div class="cm-cookie-list">
                                        <?php foreach ( $cookies as $ck ) :
                                            $svc = cm_service_for_cookie( $ck['name'] );
                                            $org = $svc ? explode( ' / ', $svc['service'] )[0] : ( isset($ck['provider']) ? $ck['provider'] : '' );
                                        ?>
                                        <div class="cm-cookie-item">
                                            <div class="cm-cookie-name"><?php echo esc_html($ck['name']); ?></div>
                                            <div class="cm-cookie-meta">
                                                <div class="cm-cookie-meta-row"><span><?php echo esc_html($ck['purpose']); ?></span></div>
                                                <div class="cm-cookie-meta-row">
                                                    <?php if ( $org ) : ?><span class="cm-cookie-meta-provider"><?php echo esc_html($org); ?></span><span>&middot;</span><?php endif; ?>
                                                    <span><?php echo esc_html( cm_ui('duration') ); ?>: <?php echo esc_html($ck['duration']); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else : ?>
                            <p class="cm-no-cookies"><?php echo esc_html( cm_ui('no_cookies') ); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>
            <div class="cm-prefs-footer">
                <button type="button" class="cm-btn cm-btn-outline" id="cm-rejectall-btn"><?php echo esc_html( cm_t('txt_btn_rejectall') ); ?></button>
                <button type="button" class="cm-btn cm-btn-accept" id="cm-save-btn"><?php echo esc_html( cm_t('txt_btn_save') ); ?></button>
            </div>
        </div>
    </div>

    <?php if ( $show_float ) : ?>
    <div id="cm-float" data-float-pos="<?php echo esc_attr( cm_get('float_position') ?: 'left' ); ?>">
        <?php if ( $float_btn_style === 'icon' ) :
            $float_size_class = cm_get('float_icon_size') === 'small' ? ' cm-float-small' : '';
            $custom_svg = cm_get('float_icon_custom_svg');
            $default_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="currentColor" width="32" height="32" aria-hidden="true"><path d="M164.49,163.51a12,12,0,1,1-17,0A12,12,0,0,1,164.49,163.51Zm-81-8a12,12,0,1,0,17,0A12,12,0,0,0,83.51,155.51Zm9-39a12,12,0,1,0-17,0A12,12,0,0,0,92.49,116.49Zm48-1a12,12,0,1,0,0,17A12,12,0,0,0,140.49,115.51ZM232,128A104,104,0,1,1,128,24a8,8,0,0,1,8,8,40,40,0,0,0,40,40,8,8,0,0,1,8,8,40,40,0,0,0,40,40A8,8,0,0,1,232,128Zm-16.31,7.39A56.13,56.13,0,0,1,168.5,87.5a56.13,56.13,0,0,1-47.89-47.19,88,88,0,1,0,95.08,95.08Z"></path></svg>';
            // Sanitize custom SVG: alleen <svg> tags toestaan
            $icon_svg = $default_svg;
            if ( $custom_svg && preg_match( '/<svg\b[^>]*>.*<\/svg>/is', $custom_svg, $m ) ) {
                $icon_svg = $m[0];
            }
        ?>
        <button type="button" id="cm-float-btn" class="cm-float-icon<?php echo esc_attr($float_size_class); ?>" aria-label="<?php echo esc_attr( cm_t('txt_float_label') ); ?>">
            <?php echo $icon_svg; ?>
        </button>
        <?php else : ?>
        <button type="button" id="cm-float-btn"><?php echo esc_html( cm_t('txt_float_label') ); ?></button>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <script>
    (function () {
        'use strict';

        var COOKIE_NAME       = 'cc_cm_consent';
        var EXPIRY_MONTHS     = <?php echo intval( cm_get('expiry_months') ); ?>;
        var SHOW_FLOAT        = <?php echo cm_get('show_float_btn') ? 'true' : 'false'; ?>;
        var AJAX_URL          = '<?php echo esc_js( site_url("/wp-admin/admin-ajax.php") ); ?>';
        var LOG_NONCE         = '<?php echo wp_create_nonce("cm_log_consent"); ?>';
        var ANALYTICS_DEFAULT = <?php echo cm_get('analytics_default') ? 'true' : 'false'; ?>;
        var RESPECT_DNT       = <?php echo cm_get('respect_dnt') ? 'true' : 'false'; ?>;
        var IS_DNT            = RESPECT_DNT && (navigator.doNotTrack === '1' || window.doNotTrack === '1' || navigator.msDoNotTrack === '1');
        window.CM_CONFIG      = { expiry_months: EXPIRY_MONTHS, show_float: SHOW_FLOAT, consent_version: '<?php echo esc_js( (string) get_option("cm_consent_version", 1) ); ?>' };

        // Cookienamen per categorie — gebruikt bij intrekking consent
        var COOKIE_NAMES = {
            analytics: <?php
                $names_a = array();
                foreach ( $cats['analytics'] as $ck ) $names_a[] = $ck['name'];
                echo wp_json_encode( $names_a );
            ?>,
            marketing: <?php
                $names_m = array();
                foreach ( $cats['marketing'] as $ck ) $names_m[] = $ck['name'];
                echo wp_json_encode( $names_m );
            ?>
        };

        // Verwijder alle cookies die overeenkomen met bekende tracking-prefixen + expliciete namen
        function deleteCookiesByType(type) {
            var names  = COOKIE_NAMES[type] || [];
            var host   = window.location.hostname;
            var bare   = host.replace(/^www\./, '');
            var domains = ['', host, '.' + host];
            if (bare !== host) { domains.push(bare); domains.push('.' + bare); }
            var paths = ['/', '/wp-content', '/wp-admin', '/wp-includes'];
            var past  = 'Thu, 01 Jan 1970 00:00:00 GMT';

            var prefixes = type === 'analytics'
                ? ['_ga', '_gid', '_gat', '_utm', '__utm', 'mp_', '_hjSession', '_hjid', '_hjFirst', '_hjIncluded', 'ajs_', '__hstc', 'hubspotutk']
                : ['_fbp', '_fbc', 'fr', 'IDE', 'DSID', 'test_cookie', 'NID', 'ANID', 'anj', '__gads', '__gpi'];

            // Verzamel alle te verwijderen namen: expliciet + prefix-scan op document.cookie
            var toDelete = names.slice();
            document.cookie.split(';').forEach(function(c) {
                var n = c.split('=')[0].trim();
                if (!n) return;
                prefixes.forEach(function(p) {
                    if (n.indexOf(p) === 0 && toDelete.indexOf(n) === -1) toDelete.push(n);
                });
            });

            // Verwijder via alle domein/pad-combinaties
            toDelete.forEach(function(name) {
                paths.forEach(function(path) {
                    domains.forEach(function(domain) {
                        var c = name + '=; expires=' + past + '; path=' + path + '; SameSite=Lax';
                        document.cookie = domain ? c + '; domain=' + domain : c;
                    });
                });
            });

            return toDelete; // geef terug voor logging
        }

        // Bij pageload: check of er een revoke-flag staat en verwijder direct, vóór GA-init
        (function checkRevokeFlag() {
            try {
                var flag = sessionStorage.getItem('cm_revoke');
                if (!flag) return;
                sessionStorage.removeItem('cm_revoke');
                var types = JSON.parse(flag);
                if (types.analytics) deleteCookiesByType('analytics');
                if (types.marketing)  deleteCookiesByType('marketing');
            } catch(e) {}
        })();

        // Verwijder cookies en herlaad — flag wordt opgepikt op de verse pagina
        function revokeAndReload(analytics, marketing) {
            try {
                sessionStorage.setItem('cm_revoke', JSON.stringify({ analytics: analytics, marketing: marketing }));
            } catch(e) {}
            // Direct ook verwijderen voor het geval de reload snel gaat
            if (analytics) deleteCookiesByType('analytics');
            if (marketing)  deleteCookiesByType('marketing');
            setTimeout(function() { window.location.reload(); }, 150);
        }



        function releaseScripts(type) {
            document.querySelectorAll('script[data-cm-type="' + type + '"]').forEach(function(s) {
                var ns = document.createElement('script');
                var src = s.getAttribute('data-cm-blocked-src') || '';
                // Kopieer attributen (niet type en data-cm-*)
                Array.from(s.attributes).forEach(function(a) {
                    if (a.name !== 'type' && a.name.indexOf('data-cm-') !== 0 && a.name !== 'src') {
                        ns.setAttribute(a.name, a.value);
                    }
                });
                if (src) {
                    ns.src = src;
                } else {
                    ns.textContent = s.textContent;
                }
                s.remove();
                document.head.appendChild(ns);
            });
        }

        /* ---- Embed placeholders herstellen ---- */
        // Helper: zorg dat herstelde iframes responsive zijn (volle breedte)
        function makeResponsive(iframe) {
            iframe.style.width = '100%';
            iframe.style.border = 'none';
            var w = parseInt(iframe.getAttribute('width')) || 16;
            var h = parseInt(iframe.getAttribute('height')) || 9;
            iframe.style.aspectRatio = w + '/' + h;
            iframe.removeAttribute('width');
            iframe.removeAttribute('height');
        }

        function releaseEmbeds(type) {
            // 1. Placeholders (gemaakt door PHP output buffer)
            document.querySelectorAll('.cm-embed-placeholder[data-cm-embed-cat="' + type + '"]').forEach(function(ph) {
                var encodedTag = ph.getAttribute('data-cm-embed-tag');
                if (encodedTag) {
                    try {
                        var originalHtml = atob(encodedTag);
                        var wrapper = document.createElement('div');
                        wrapper.innerHTML = originalHtml;
                        var iframe = wrapper.firstElementChild;
                        if (iframe) {
                            makeResponsive(iframe);
                            ph.parentNode.replaceChild(iframe, ph);
                        }
                    } catch(e) {
                        // Fallback: maak nieuw iframe van data-cm-embed-src
                        var src = ph.getAttribute('data-cm-embed-src');
                        if (src) {
                            var nf = document.createElement('iframe');
                            nf.src = src;
                            nf.setAttribute('allowfullscreen', '');
                            nf.style.width = '100%';
                            nf.style.border = 'none';
                            nf.style.aspectRatio = '16/9';
                            ph.parentNode.replaceChild(nf, ph);
                        }
                    }
                }
            });
            // 2. Dynamisch geblokkeerde iframes (via JS MutationObserver)
            document.querySelectorAll('iframe[data-cm-embed-cat="' + type + '"]').forEach(function(iframe) {
                var src = iframe.getAttribute('data-cm-embed-src');
                if (src) {
                    iframe.src = src;
                    iframe.removeAttribute('data-cm-embed-src');
                    iframe.removeAttribute('data-cm-embed-cat');
                    iframe.removeAttribute('data-cm-embed-service');
                    makeResponsive(iframe);
                }
            });
        }

        /* ---- Enkele embed accepteren (per placeholder) ---- */
        function acceptSingleEmbed(placeholder) {
            var encodedTag = placeholder.getAttribute('data-cm-embed-tag');
            if (encodedTag) {
                try {
                    var originalHtml = atob(encodedTag);
                    var wrapper = document.createElement('div');
                    wrapper.innerHTML = originalHtml;
                    var iframe = wrapper.firstElementChild;
                    if (iframe) {
                        makeResponsive(iframe);
                        placeholder.parentNode.replaceChild(iframe, placeholder);
                        return;
                    }
                } catch(e) {}
            }
            // Fallback
            var src = placeholder.getAttribute('data-cm-embed-src');
            if (src) {
                var nf = document.createElement('iframe');
                nf.src = src;
                nf.setAttribute('allowfullscreen', '');
                nf.style.width = '100%';
                nf.style.border = 'none';
                nf.style.aspectRatio = '16/9';
                placeholder.parentNode.replaceChild(nf, placeholder);
            }
        }

        /* ---- Consent cookie ---- */
        function getConsent() {
            var cookies = document.cookie.split('; ');
            for (var i = 0; i < cookies.length; i++) {
                if (cookies[i].indexOf(COOKIE_NAME + '=') === 0) {
                    try { return JSON.parse(decodeURIComponent(cookies[i].slice(COOKIE_NAME.length + 1))); }
                    catch(e) { return null; }
                }
            }
            return null;
        }

        var IS_SECURE = <?php echo is_ssl() ? 'true' : 'false'; ?>;

        function setConsent(data) {
            var now     = new Date();
            var expires = new Date(now.getTime() + EXPIRY_MONTHS * 30 * 24 * 60 * 60 * 1000);
            var payload = {
                v: '2.0', sv: '<?php echo esc_js( (string) get_option("cm_consent_version", 1) ); ?>',
                ts: now.toISOString(), exp: expires.toISOString(),
                analytics: data.analytics, marketing: data.marketing, method: data.method
            };
            if (data.services) payload.services = data.services;
            var cookie = COOKIE_NAME + '=' + encodeURIComponent(JSON.stringify(payload))
                + '; expires=' + expires.toUTCString() + '; path=/; SameSite=Lax';
            if (IS_SECURE) cookie += '; Secure';
            document.cookie = cookie;
        }

        function isExpired(c) { return !c || !c.exp || new Date() > new Date(c.exp); }

        /* ---- Links in banner/prefs openen in nieuw tabblad ---- */
        function makeLinksExternal() {
            ['cm-banner', 'cm-prefs'].forEach(function(id) {
                var el = document.getElementById(id);
                if (!el) return;
                el.querySelectorAll('a[href]').forEach(function(a) {
                    if (!a.getAttribute('target')) a.setAttribute('target', '_blank');
                    a.setAttribute('rel', 'noopener noreferrer');
                });
            });
        }

        /* ---- DOM helpers ---- */
        function g(id)  { return document.getElementById(id); }
        function ov()   { return g('cm-overlay'); }
        function bn()   { return g('cm-banner');  }
        function pr()   { return g('cm-prefs');   }
        function fl()   { return g('cm-float');   }

        var prefsOpen = false;

        /* ---- WCAG 2.1 AA: focus trap en focus herstel ---- */
        var _lastFocus = null;

        function getFocusable(container) {
            return Array.prototype.slice.call(
                container.querySelectorAll(
                    'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), ' +
                    'textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
                )
            ).filter(function(el) { return el.offsetParent !== null; });
        }

        function trapFocus(container, e) {
            var focusable = getFocusable(container);
            if (!focusable.length) return;
            var first = focusable[0];
            var last  = focusable[focusable.length - 1];
            if (e.shiftKey) {
                if (document.activeElement === first) { e.preventDefault(); last.focus(); }
            } else {
                if (document.activeElement === last)  { e.preventDefault(); first.focus(); }
            }
        }

        function focusFirst(container) {
            var focusable = getFocusable(container);
            if (focusable.length) focusable[0].focus();
        }

        function saveFocus() { _lastFocus = document.activeElement; }
        function restoreFocus() { if (_lastFocus) { _lastFocus.focus(); _lastFocus = null; } }

        /* ---- aria-expanded sync voor categorie-headers ---- */
        function updateAriaExpanded(cat) {
            var header = document.querySelector('.cm-cat-header[data-cat="' + cat + '"]');
            var detail = document.getElementById('cm-cat-detail-' + cat);
            if (header && detail) {
                var open = document.getElementById('cm-cat-' + cat).classList.contains('cm-open');
                header.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
        }

        function showBanner() {
            if (ov()) ov().classList.add('cm-active');
            if (bn()) {
                var b = bn();
                b.classList.remove('cm-active');
                requestAnimationFrame(function() {
                    requestAnimationFrame(function() {
                        b.classList.add('cm-active');
                        b.removeAttribute('aria-hidden');
                        // Stuur focus naar eerste knop in de banner
                        focusFirst(b);
                    });
                });
            }
            hideFloat();
        }

        function hideBanner() {
            if (ov()) ov().classList.remove('cm-active');
            if (bn()) {
                bn().classList.remove('cm-active');
                bn().setAttribute('aria-hidden', 'true');
            }
            if (SHOW_FLOAT) showFloat();
        }

        function openPrefs() {
            saveFocus();
            prefsOpen = true;
            if (pr()) {
                pr().classList.add('cm-active');
                pr().removeAttribute('aria-hidden');
            }
            if (ov()) ov().classList.add('cm-active');
            if (bn()) bn().classList.remove('cm-active');
            var c = getConsent();
            var analyticsOn = c ? !!c.analytics : ANALYTICS_DEFAULT;
            var marketingOn = c ? !!c.marketing : false;

            // Categorie-toggles
            var tA = g('cm-toggle-analytics'), tM = g('cm-toggle-marketing');
            if (tA) tA.checked = analyticsOn;
            if (tM) tM.checked = marketingOn;

            // Lees welke cookies daadwerkelijk aanwezig zijn in de browser
            var activeCookies = {};
            document.cookie.split(';').forEach(function(ck) {
                var n = ck.split('=')[0].trim();
                if (n) activeCookies[n] = true;
            });

            // Dienst-toggles: aan als de dienst was ingeschakeld
            // Controleer ook of cookies van deze dienst in de browser aanwezig zijn
            document.querySelectorAll('.cm-service-toggle').forEach(function(cb) {
                var cat      = cb.getAttribute('data-cat');
                var catOn    = cat === 'analytics' ? analyticsOn : marketingOn;
                var names    = (cb.getAttribute('data-cookies') || '').split(',').filter(Boolean);
                var svcId    = cb.getAttribute('data-service');

                // Dienst aan als:
                // 1. Per-dienst consent opgeslagen en was aan
                // 2. Een van de cookies aanwezig in browser (incl. prefix-varianten)
                // 3. Geen granulaire data → volg categorie
                var wasOn = catOn;
                if (c && c.services && svcId in c.services) {
                    wasOn = !!c.services[svcId];
                } else {
                    // Check browser cookies
                    var anyActive = false;
                    names.forEach(function(name) {
                        if (activeCookies[name]) { anyActive = true; return; }
                        // Prefix-check
                        Object.keys(activeCookies).forEach(function(bName) {
                            if (bName.indexOf(name + '_') === 0) anyActive = true;
                        });
                    });
                    if (anyActive) wasOn = true;
                }
                cb.checked = wasOn;
            });

            cmUpdateCatToggle('analytics');
            cmUpdateCatToggle('marketing');
            makeLinksExternal();
            // Stuur focus naar sluiten-knop (eerste focusbaar element in prefs)
            if (pr()) focusFirst(pr());
        }

        // Dienst-toggle: één toggle voor alle cookies van dezelfde provider
        window.cmServiceToggle = function(cb) {
            var cat = cb.getAttribute('data-cat');
            window.cmUpdateCatToggle(cat);
        };

        // Master toggle: zet alle dienst-toggles in categorie aan of uit
        window.cmCatToggleAll = function(masterCb, cat) {
            var checked = masterCb.checked;
            document.querySelectorAll('.cm-service-toggle[data-cat="' + cat + '"]').forEach(function(cb) {
                cb.checked = checked;
            });
        };

        // Update master toggle: aan als minstens 1 dienst aan staat
        window.cmUpdateCatToggle = function(cat) {
            var toggles = document.querySelectorAll('.cm-service-toggle[data-cat="' + cat + '"]');
            if (!toggles.length) return;
            var master = g('cm-toggle-' + cat);
            if (!master) return;
            var anyChecked = false;
            toggles.forEach(function(cb) { if (cb.checked) anyChecked = true; });
            master.checked = anyChecked;
            master.indeterminate = false;
        };

        var cmCatToggleAll    = window.cmCatToggleAll;
        var cmUpdateCatToggle = window.cmUpdateCatToggle;
        var cmServiceToggle   = window.cmServiceToggle;

        function closePrefs() {
            prefsOpen = false;
            if (pr()) {
                pr().classList.remove('cm-active');
                pr().setAttribute('aria-hidden', 'true');
            }
            restoreFocus();
            var c = getConsent();
            if (!c || isExpired(c)) {
                if (ov()) ov().classList.add('cm-active');
                showBanner();
            } else {
                if (ov()) ov().classList.remove('cm-active');
                if (SHOW_FLOAT) showFloat();
            }
        }

        function applyConsent(analytics, marketing, method, serviceConsent) {
            var prev = getConsent();
            setConsent({ analytics: analytics, marketing: marketing, method: method, services: serviceConsent || null });
            if (pr()) pr().classList.remove('cm-active');
            hideBanner();
            logConsent(analytics ? 1 : 0, marketing ? 1 : 0, method);

            var hadAnalytics = prev && prev.analytics;
            var hadMarketing = prev && prev.marketing;
            var revokeA = hadAnalytics && !analytics;
            var revokeM = hadMarketing && !marketing;

            if (revokeA || revokeM) {
                revokeAndReload(revokeA, revokeM);
                return;
            }

            // Granulaire intrekking per dienst: verwijder cookies van uitgezette diensten
            if (serviceConsent) {
                var toDelete = [];
                document.querySelectorAll('.cm-service-toggle').forEach(function(cb) {
                    var svcId = cb.getAttribute('data-service');
                    if (!serviceConsent[svcId]) {
                        // Dienst uitgevinkt — verzamel cookienamen
                        var names = (cb.getAttribute('data-cookies') || '').split(',').filter(Boolean);
                        names.forEach(function(n) { if (toDelete.indexOf(n) === -1) toDelete.push(n); });
                    }
                });
                if (toDelete.length) deleteCookiesByName(toDelete);
            }

            // SPOOR 1 — Google Consent Mode v2 update
            if (typeof gtag === 'function') {
                gtag('consent', 'update', {
                    'analytics_storage':  analytics ? 'granted' : 'denied',
                    'ad_storage':         marketing ? 'granted' : 'denied',
                    'ad_user_data':       marketing ? 'granted' : 'denied',
                    'ad_personalization': marketing ? 'granted' : 'denied'
                });
            }

            // SPOOR 2 — Overige geblokkeerde scripts
            var hasBlocked = document.querySelectorAll('script[data-cm-type]').length > 0;
            if (hasBlocked) {
                if (analytics) releaseScripts('analytics');
                if (marketing) releaseScripts('marketing');
            }

            // SPOOR 3 — Geblokkeerde embeds (iframes) vrijgeven
            if (analytics) releaseEmbeds('analytics');
            if (marketing) releaseEmbeds('marketing');
        }

        // Verwijder een specifieke lijst van cookienamen via alle domein/pad-combinaties
        // Scant ook document.cookie op prefix-varianten (bijv. _ga verwijdert ook _ga_XXXXXXXXX)
        // Zet bijbehorende toggles ook uit zodat UI consistent blijft
        function deleteCookiesByName(names) {
            var host    = window.location.hostname;
            var bare    = host.replace(/^www\./, '');
            var domains = ['', host, '.' + host];
            if (bare !== host) { domains.push(bare); domains.push('.' + bare); }
            var paths = ['/', '/wp-content', '/wp-admin', '/wp-includes'];
            var past  = 'Thu, 01 Jan 1970 00:00:00 GMT';

            // Voeg prefix-varianten toe die in document.cookie staan
            var allNames = names.slice();
            document.cookie.split(';').forEach(function(c) {
                var n = c.split('=')[0].trim();
                if (!n) return;
                names.forEach(function(base) {
                    if (n !== base && n.indexOf(base + '_') === 0 && allNames.indexOf(n) === -1) {
                        allNames.push(n);
                    }
                });
            });

            // Verwijder alle cookies
            allNames.forEach(function(name) {
                paths.forEach(function(path) {
                    domains.forEach(function(domain) {
                        var c = name + '=; expires=' + past + '; path=' + path + '; SameSite=Lax';
                        document.cookie = domain ? c + '; domain=' + domain : c;
                    });
                });
            });

            // Zet toggles van verwijderde cookies ook uit zodat UI klopt
            allNames.forEach(function(name) {
                var cb = document.querySelector('.cm-cookie-toggle[data-cookie="' + name + '"]');
                if (cb) {
                    cb.checked = false;
                    var cat = cb.getAttribute('data-cat');
                    if (cat) window.cmUpdateCatToggle(cat);
                }
            });
        }

        /* ---- Consent logging ---- */
        function getSessionId() {
            try {
                var sid = sessionStorage.getItem('cm_sid');
                if (!sid) {
                    sid = 'cm_' + Math.random().toString(36).slice(2) + Date.now().toString(36);
                    sessionStorage.setItem('cm_sid', sid);
                }
                return sid;
            } catch(e) { return 'cm_nostorage_' + Math.random().toString(36).slice(2); }
        }

        function hasLoggedThisSession() {
            try { return !!sessionStorage.getItem('cm_logged'); } catch(e) { return false; }
        }
        function markLoggedThisSession() {
            try { sessionStorage.setItem('cm_logged', '1'); } catch(e) {}
        }

        function logConsent(analytics, marketing, method) {
            if (!AJAX_URL) { console.warn('[CM] logConsent: geen AJAX_URL'); return; }
            markLoggedThisSession();
            try {
                var body = 'action=cm_log_consent'
                    + '&analytics='  + encodeURIComponent(analytics)
                    + '&marketing='  + encodeURIComponent(marketing)
                    + '&method='     + encodeURIComponent(method)
                    + '&session_id=' + encodeURIComponent(getSessionId())
                    + '&url='        + encodeURIComponent(window.location.href);

                var xhr = new XMLHttpRequest();
                xhr.open('POST', AJAX_URL, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    try {
                        var resp = JSON.parse(xhr.responseText);
                        if (resp && resp.success) {
                        } else {
                            console.warn('[CM] Log mislukt:', xhr.responseText);
                        }
                    } catch(e) {
                        console.warn('[CM] Log response onleesbaar:', xhr.responseText);
                    }
                };
                xhr.onerror = function() { console.warn('[CM] Log XHR fout (netwerk?)'); };
                xhr.send(body);
            } catch(e) { console.warn('[CM] logConsent fout:', e); }
        }

        function acceptAll()  { applyConsent(true,  true,  'accept-all', null); }
        function rejectAll()  { applyConsent(false, false, 'reject-all', null); }
        function savePrefs() {
            var serviceConsent = {};
            var analyticsOn = false, marketingOn = false;

            document.querySelectorAll('.cm-service-toggle').forEach(function(cb) {
                var svcId = cb.getAttribute('data-service');
                var cat   = cb.getAttribute('data-cat');
                serviceConsent[svcId] = cb.checked;
                if (cb.checked && cat === 'analytics') analyticsOn = true;
                if (cb.checked && cat === 'marketing')  marketingOn  = true;
            });

            // Fallback als er geen dienst-toggles zijn
            var tA = g('cm-toggle-analytics'), tM = g('cm-toggle-marketing');
            if (!document.querySelectorAll('.cm-service-toggle[data-cat="analytics"]').length && tA) analyticsOn = tA.checked;
            if (!document.querySelectorAll('.cm-service-toggle[data-cat="marketing"]').length && tM) marketingOn = tM.checked;

            applyConsent(analyticsOn, marketingOn, 'custom', serviceConsent);
        }

        function fire(name) { try { document.dispatchEvent(new CustomEvent(name)); } catch(e) {} }
        function toggleCat(id) {
            var c = g('cm-cat-'+id);
            if (c) {
                c.classList.toggle('cm-open');
                updateAriaExpanded(id);
            }
        }
        function on(id, fn) { var n = g(id); if(n) n.addEventListener('click', fn); }

        function moveToBody() {
            ['cm-overlay','cm-banner','cm-prefs','cm-float'].forEach(function(id) {
                var node = document.getElementById(id);
                if (!node) return;
                if (node.parentNode !== document.body) document.body.appendChild(node);
                // cm-float: display wordt uitsluitend door showFloat/hideFloat beheerd
                if (id !== 'cm-float') node.style.display = '';
            });
        }

        function showFloat() {
            var f = fl();
            if (!f) return;
            f.style.cssText = 'display:block !important; visibility:visible !important;';
        }
        function hideFloat() {
            var f = fl();
            if (!f) return;
            f.style.cssText = 'display:none !important;';
        }

        function bindAll() {
            on('cm-btn-prefs',     openPrefs);
            on('cm-btn-reject',    rejectAll);
            on('cm-btn-accept',    acceptAll);
            on('cm-prefs-close',   closePrefs);
            on('cm-allowall-btn',  acceptAll);
            on('cm-rejectall-btn', rejectAll);
            on('cm-save-btn',      savePrefs);
            on('cm-float-btn', function() {
                hideFloat();
                showBanner();
            });
            document.querySelectorAll('.cm-cat-header').forEach(function(h) {
                h.addEventListener('click', function() {
                    var cat = this.getAttribute('data-cat');
                    toggleCat(cat);
                });
            });
            /* Dienst-headers: klik om cookies in/uit te klappen */
            document.querySelectorAll('.cm-service-header').forEach(function(h) {
                h.addEventListener('click', function(e) {
                    /* Niet triggeren als de toggle zelf wordt geklikt */
                    if (e.target.closest('.cm-toggle')) return;
                    var svc = this.closest('.cm-service');
                    if (svc) svc.classList.toggle('cm-svc-open');
                });
            });
            document.addEventListener('keydown', function(e) {
                var key = e.key || e.keyCode;
                var isEscape = key === 'Escape' || key === 27;
                var isTab    = key === 'Tab'    || key === 9;

                // Escape sluit prefs of banner
                if (isEscape) {
                    if (prefsOpen) { closePrefs(); return; }
                    if (bn() && bn().classList.contains('cm-active')) {
                        // Banner sluiten via Escape = weigeren
                        rejectAll();
                        return;
                    }
                }

                // Focus trap in prefs-dialog
                if (isTab && prefsOpen && pr()) {
                    trapFocus(pr(), e);
                    return;
                }

                // Focus trap in banner-dialog
                if (isTab && bn() && bn().classList.contains('cm-active')) {
                    trapFocus(bn(), e);
                }
            });

            // Enter/Space activeren categorie-headers (role=button)
            document.addEventListener('keydown', function(e) {
                var key = e.key || e.keyCode;
                var isActivate = key === 'Enter' || key === ' ' || key === 13 || key === 32;
                if (!isActivate) return;
                var target = document.activeElement;
                if (target && target.classList.contains('cm-cat-header')) {
                    e.preventDefault();
                    var cat = target.getAttribute('data-cat');
                    if (cat) {
                        toggleCat(cat);
                        updateAriaExpanded(cat);
                    }
                }
            });

            // Embed placeholder knoppen — event delegation op document
            document.addEventListener('click', function(e) {
                // "Inhoud laden" knop — laadt enkel deze embed
                var btn = e.target.closest('.cm-embed-accept-btn');
                if (btn) {
                    e.preventDefault();
                    var ph = btn.closest('.cm-embed-placeholder');
                    if (ph) acceptSingleEmbed(ph);
                    return;
                }
                // "Cookievoorkeuren" link in placeholder — opent prefs modal
                var prefsLink = e.target.closest('.cm-embed-open-prefs');
                if (prefsLink) {
                    e.preventDefault();
                    openPrefs();
                    return;
                }
            });
        }

        function init() {
            moveToBody();
            makeLinksExternal();
            bindAll();
            // Float altijd verborgen starten — JS beslist wanneer hij zichtbaar wordt
            hideFloat();

            var SERVER_VERSION = '<?php echo esc_js( (string) get_option("cm_consent_version", 1) ); ?>';
            var c = getConsent();


            // Toon banner als: geen consent, verlopen, of versie mismatch
            var versionMismatch = c && c.sv && c.sv !== SERVER_VERSION;
            var needsBanner = !c || isExpired(c) || versionMismatch;

            // Do Not Track: automatisch weigeren als DNT actief is en geen consent opgeslagen
            if (IS_DNT && needsBanner) {
                applyConsent(false, false, 'dnt');
                return;
            }

            if (needsBanner) {
                showBanner();
            } else {
                // Bestaande geldige consent — scripts vrijgeven
                if (c.analytics) { fire('cm_analytics_enabled'); releaseScripts('analytics'); releaseEmbeds('analytics'); }
                if (c.marketing)  { fire('cm_marketing_enabled');  releaseScripts('marketing');  releaseEmbeds('marketing'); }
                if (SHOW_FLOAT) showFloat();

                // Log eenmalig per sessie (voor terugkerende bezoekers)
                if (!hasLoggedThisSession()) {
                    logConsent(c.analytics ? 1 : 0, c.marketing ? 1 : 0, 'pageload');
                }
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }

        /* Globale API — voor gebruik vanuit footer-links, custom knoppen etc. */
        window.Cookiebaas = {
            showBanner: function() { showBanner(); },
            openPrefs:  function() { openPrefs(); }
        };

    })();
    </script>
    <!-- ===== /COOKIEMELDING PLUGIN ===== -->
    <?php
}
