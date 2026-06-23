<?php
/**
 * Uninstall handler.
 *
 * @package LiveOnCrypto_WC
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$settings = get_option( 'woocommerce_liveoncrypto_settings', array() );
$settings = is_array( $settings ) ? $settings : array();

// Preserve WooCommerce order payment records and LiveOnCrypto order metadata by default.
if ( 'yes' === ( $settings['delete_settings_on_uninstall'] ?? 'no' ) ) {
	delete_option( 'woocommerce_liveoncrypto_settings' );
	delete_option( 'liveoncrypto_wc_last_api_error' );
	delete_option( 'liveoncrypto_wc_last_webhook_received' );
	delete_option( 'liveoncrypto_wc_last_webhook_request_received' );
}
