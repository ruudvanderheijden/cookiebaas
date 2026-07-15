<?php
/**
 * Instellingen-cache — cm_get() / cm_get_flush() (fix uit v1.8.0).
 *
 * Vroeger cachete cm_get() in een PHP-static die cm_get_flush() niet kon legen,
 * dus opslaan-en-lezen binnen één request gaf oude waarden. De cache zit nu in
 * $GLOBALS en wordt automatisch geleegd via de update_option_cm_settings-hook.
 *
 * Deze test leunt op de hook-emulatie in bootstrap.php: update_option() vuurt
 * daadwerkelijk de door defaults.php geregistreerde flush-hook.
 */

require __DIR__ . '/bootstrap.php';
require CM_PLUGIN_ROOT . '/includes/defaults.php';

cm_test_group( 'Instellingen-cache (cm_get / cm_get_flush)' );

// Startwaarde uit defaults
cm_assert( 'gtm_container_id start leeg (default)', cm_get( 'gtm_container_id' ) === '' );

// Opslaan → cm_get moet BINNEN hetzelfde request de nieuwe waarde geven
update_option( 'cm_settings', array( 'gtm_container_id' => 'GTM-NIEUW1' ) );
cm_assert( 'na opslaan geeft cm_get() direct de NIEUWE waarde (hook-flush)', cm_get( 'gtm_container_id' ) === 'GTM-NIEUW1' );

// Tweede wijziging
update_option( 'cm_settings', array( 'gtm_container_id' => 'GTM-NIEUW2' ) );
cm_assert( 'tweede wijziging ook direct zichtbaar', cm_get( 'gtm_container_id' ) === 'GTM-NIEUW2' );

// Directe DB-mutatie zonder hook → cache blijft (gewenst: binnen-request cache)
$GLOBALS['cm_test_options']['cm_settings'] = array( 'gtm_container_id' => 'GTM-STIEKEM' );
cm_assert( 'zonder flush blijft de binnen-request cache staan', cm_get( 'gtm_container_id' ) === 'GTM-NIEUW2' );

// Handmatige flush
cm_get_flush();
cm_assert( 'na cm_get_flush() wordt opnieuw uit de DB gelezen', cm_get( 'gtm_container_id' ) === 'GTM-STIEKEM' );

// De flush-hook is echt geregistreerd door defaults.php
cm_assert( 'update_option_cm_settings hook geregistreerd', has_action( 'update_option_cm_settings' ) );
cm_assert( 'delete_option_cm_settings hook geregistreerd', has_action( 'delete_option_cm_settings' ) );

// Lege DB-waarde valt terug op default
update_option( 'cm_settings', array( 'color_accept_bg' => '' ) );
cm_assert( 'lege DB-waarde valt terug op default', cm_get( 'color_accept_bg' ) === cm_default_settings()['color_accept_bg'] );

exit( cm_test_summary() );
