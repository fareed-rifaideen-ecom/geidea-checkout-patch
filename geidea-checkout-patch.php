<?php
/**
 * Plugin Name: Geidea Checkout Patch
 * Plugin URI: https://github.com/fareed-rifaideen-ecom/geidea-checkout-patch
 * Description: Resolves redirection routing and webhook fatal errors in the Geidea Payment Gateway.
 * Version: 1.0.0
 * Author: Fareed M. Rifaideen
 * Author URI: https://fareed-rifaideen.netlify.app/
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * FIX 1: Intercept Geidea Session Payload
 * Overrides the hardcoded 'wc_get_checkout_url()' sent to Geidea with the native Order Received URL.
 */
add_filter( 'http_request_args', 'geidea_patch_fix_redirect_payload', 10, 2 );
function geidea_patch_fix_redirect_payload( $args, $url ) {
    // Check if this is the Geidea Create Session API endpoint
    if ( strpos( $url, '/payment-intent/api/v2/direct/session' ) !== false ) {
        if ( isset( $args['body'] ) && is_string( $args['body'] ) ) {
            $payload = json_decode( $args['body'], true );
            
            // If we have an order ID (merchantReferenceId), fetch the proper Order Received URL
            if ( isset( $payload['merchantReferenceId'] ) && isset( $payload['returnUrl'] ) ) {
                $order = wc_get_order( $payload['merchantReferenceId'] );
                if ( $order ) {
                    $payload['returnUrl'] = $order->get_checkout_order_received_url();
                    $args['body'] = wp_json_encode( $payload );
                }
            }
        }
    }
    return $args;
}

/**
 * FIX 2: Prevent Fatal Error in Server-to-Server Webhook
 * Instantiates the WC_Cart object before Geidea's return_handler attempts to empty it.
 */
add_action( 'woocommerce_api_geidea', 'geidea_patch_prevent_webhook_crash', 1 );
function geidea_patch_prevent_webhook_crash() {
    // Ensure WooCommerce is loaded and the cart object doesn't exist yet
    if ( function_exists( 'WC' ) && is_null( WC()->cart ) ) {
        // Initialize an empty cart object safely to prevent the S2S script from crashing
        WC()->cart = new \WC_Cart();
    }
}
