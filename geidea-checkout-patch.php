<?php
/**
 * Plugin Name: Geidea Checkout Patch
 * Plugin URI: https://github.com/fareed-rifaideen-ecom/geidea-checkout-patch
 * Description: Resolves redirection routing, webhook fatal errors, and currency symbol formatting in the Geidea Payment Gateway.
 * Version: 1.0.1
 * Author: E-commerce Web Development Team
 * Author URI: https://thecyclehub.com
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * FIX 1: Intercept Geidea Session Payload (Regex Method)
 * Safely overrides the 'returnUrl' without decoding/re-encoding the JSON, 
 * preventing schema corruption and signature invalidation.
 */
add_filter( 'http_request_args', 'geidea_patch_fix_redirect_payload', 10, 2 );
function geidea_patch_fix_redirect_payload( $args, $url ) {
    // Check if this is the Geidea Create Session API endpoint
    if ( strpos( $url, '/payment-intent/api/v2/direct/session' ) !== false ) {
        if ( isset( $args['body'] ) && is_string( $args['body'] ) ) {
            
            // Extract the order ID using regex
            if ( preg_match( '/"merchantReferenceId":"([^"]+)"/', $args['body'], $matches ) ) {
                $order = wc_get_order( $matches[1] );
                
                if ( $order ) {
                    // Fetch the URL, decode HTML entities, and escape forward slashes to match json_encode standard
                    $raw_url = html_entity_decode( $order->get_checkout_order_received_url() );
                    $json_safe_url = str_replace( '/', '\/', esc_url_raw( $raw_url ) );
                    
                    // Surgically replace the returnUrl
                    $args['body'] = preg_replace( 
                        '/"returnUrl":"[^"]+"/', 
                        '"returnUrl":"' . $json_safe_url . '"', 
                        $args['body'] 
                    );
                }
            }
        }
    }
    return $args;
}

/**
 * FIX 2: Prevent Fatal Error in Server-to-Server Webhook
 * Injects a session-less dummy cart class to safely absorb the empty_cart() call.
 */
add_action( 'woocommerce_api_geidea', 'geidea_patch_prevent_webhook_crash', 1 );
function geidea_patch_prevent_webhook_crash() {
    // Ensure WooCommerce is loaded and the cart object doesn't exist yet
    if ( function_exists( 'WC' ) && is_null( WC()->cart ) ) {
        
        // Define a dummy cart to absorb the method call without crashing
        if ( ! class_exists( 'Geidea_Dummy_Cart' ) ) {
            class Geidea_Dummy_Cart {
                public function empty_cart() { 
                    return true; 
                }
            }
        }
        WC()->cart = new Geidea_Dummy_Cart();
    }
}

/**
 * FIX 3: Currency Symbol Formatting
 * Resolves regional currency display conflicts for the Geidea Gateway.
 */
add_filter( 'woocommerce_currency_symbol', 'geidea_patch_format_currency_symbol', 10, 2 );
function geidea_patch_format_currency_symbol( $currency_symbol, $currency ) {
    if ( $currency === 'AED' ) {
        return 'AED';
    }
    return $currency_symbol;
}
