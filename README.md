# Geidea Checkout Patch

## Overview
A lightweight WordPress/WooCommerce utility plugin designed to patch redirection routing and webhook fatal errors in the official Geidea Payment Gateway integration.

## The Problem
During checkout, the default Geidea plugin exhibits two critical routing and processing flaws:
1. **Redirection Loop:** After a successful payment (such as passing a 3D Secure authentication check), the gateway redirects the customer back to the main `/checkout/` page instead of the expected `/checkout/order-received/` confirmation page.
2. **Webhook Fatal Error:** The background Server-to-Server (S2S) webhook crashes with a PHP Fatal Error (HTTP 500). This occurs because the plugin attempts to clear the cart (`WC()->cart->empty_cart()`) during a background request where the frontend WooCommerce cart object does not exist.

## How the Problem Was Found
The issues were identified during payment gateway integration testing. While running test transactions, the payment successfully captured and WooCommerce updated the order status correctly in the backend. However, the frontend failed to load the default order confirmation page, trapping the session on the checkout URL. Subsequent debugging of the gateway's session payload and server error logs revealed the misconfigured `returnUrl` and the webhook fatal error crashing the background handshake.

## The Solution
Instead of modifying the core Geidea plugin directly (which would be overwritten during standard plugin updates), this patch safely alters the gateway's behavior on the fly using WordPress core hooks:
* **Dynamic URL Routing:** Uses the `http_request_args` filter to intercept the outgoing `create_session` payload and dynamically replaces the hardcoded checkout URL with WooCommerce's native `get_checkout_order_received_url()`.
* **Cart Object Injection:** Hooks into `woocommerce_api_geidea` at priority `1` to safely instantiate a temporary `WC_Cart` object before the gateway's webhook handler fires. This allows the `empty_cart()` function to execute cleanly without crashing the server.

## Deployment Process
This plugin is deployed directly from GitHub to the live production environment using [Git Deploy Manager](https://github.com/fareed-rifaideen-ecom/git-deploy-manager).
1. Commit the `geidea-checkout-patch.php` file to the `main` branch.
2. Navigate to the WordPress admin dashboard.
3. Open the Git Deploy Manager interface.
4. Sync the repository to install or update the patch on the live site.

## Maintainer
**Author:** Fareed M. Rifaideen  
**Website:** https://fareed-rifaideen.netlify.app/
