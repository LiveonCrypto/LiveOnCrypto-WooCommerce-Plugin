<?php
/**
 * Admin notices.
 *
 * @package LiveOnCrypto_WC
 */

defined( 'ABSPATH' ) || exit;

class LiveOnCrypto_WC_Admin_Notices {
	public static function init_missing_woocommerce_notice(): void {
		add_action( 'admin_notices', array( __CLASS__, 'render_missing_woocommerce_notice' ) );
	}

	public static function render_missing_woocommerce_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'LiveOnCrypto for WooCommerce requires WooCommerce to be active. The plugin is currently idle.', 'liveoncrypto-woocommerce' ); ?></p>
		</div>
		<?php
	}
}
