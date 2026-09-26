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
