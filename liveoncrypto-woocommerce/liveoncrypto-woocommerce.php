<?php
/**
 * Plugin Name: LiveOnCrypto for WooCommerce
 * Plugin URI: https://www.liveoncrypto.com/
 * Description: Accept cryptocurrency payments in WooCommerce through LiveOnCrypto.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * WC requires at least: 7.1
 * WC tested up to: 9.0
 * Author: LiveOnCrypto
 * Author URI: https://www.liveoncrypto.com/
 * Text Domain: liveoncrypto-woocommerce
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package LiveOnCrypto_WC
 */

defined( 'ABSPATH' ) || exit;

define( 'LIVEONCRYPTO_WC_VERSION', '0.1.0' );
define( 'LIVEONCRYPTO_WC_FILE', __FILE__ );
define( 'LIVEONCRYPTO_WC_PATH', plugin_dir_path( __FILE__ ) );
define( 'LIVEONCRYPTO_WC_URL', plugin_dir_url( __FILE__ ) );

require_once LIVEONCRYPTO_WC_PATH . 'includes/functions.php';
require_once LIVEONCRYPTO_WC_PATH . 'includes/class-logger.php';
require_once LIVEONCRYPTO_WC_PATH . 'includes/class-admin-notices.php';

add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

add_action( 'plugins_loaded', 'liveoncrypto_wc_bootstrap', 20 );

/**
 * Bootstrap the plugin only when WooCommerce is available.
 */
function liveoncrypto_wc_bootstrap(): void {
	if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WC_Payment_Gateway' ) ) {
		LiveOnCrypto_WC_Admin_Notices::init_missing_woocommerce_notice();
		return;
	}

	require_once LIVEONCRYPTO_WC_PATH . 'includes/class-settings-validator.php';
	require_once LIVEONCRYPTO_WC_PATH . 'includes/class-api-client.php';
	require_once LIVEONCRYPTO_WC_PATH . 'includes/class-gateway.php';
	require_once LIVEONCRYPTO_WC_PATH . 'includes/class-blocks-payment-method.php';
	require_once LIVEONCRYPTO_WC_PATH . 'includes/class-webhook-controller.php';
	require_once LIVEONCRYPTO_WC_PATH . 'includes/class-order-sync.php';
	require_once LIVEONCRYPTO_WC_PATH . 'includes/class-admin.php';
	require_once LIVEONCRYPTO_WC_PATH . 'includes/class-plugin.php';

	LiveOnCrypto_WC_Plugin::instance()->init();
}
