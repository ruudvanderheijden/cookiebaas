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
