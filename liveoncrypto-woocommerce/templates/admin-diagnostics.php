<?php
/**
 * Admin diagnostics template.
 *
 * @package LiveOnCrypto_WC
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap liveoncrypto-wc-diagnostics">
	<h1><?php esc_html_e( 'LiveOnCrypto Diagnostics', 'liveoncrypto-woocommerce' ); ?></h1>
	<?php if ( isset( $_GET['loc_msg'] ) ) : ?>
		<?php
		$loc_test     = isset( $_GET['loc_test'] ) ? sanitize_key( wp_unslash( $_GET['loc_test'] ) ) : '';
		$notice_class = 'success' === $loc_test ? 'notice-success' : 'notice-warning';
		$loc_message  = sanitize_text_field( wp_unslash( $_GET['loc_msg'] ) );
		?>
		<div class="notice <?php echo esc_attr( $notice_class ); ?> is-dismissible"><p><?php echo esc_html( $loc_message ); ?></p></div>
	<?php endif; ?>
	<h2><?php esc_html_e( 'Connection', 'liveoncrypto-woocommerce' ); ?></h2>
	<table class="widefat striped"><tbody>
		<tr><th><?php esc_html_e( 'Public key', 'liveoncrypto-woocommerce' ); ?></th><td><?php echo esc_html( ! empty( $settings['public_key'] ) ? liveoncrypto_wc_redact_secret( (string) $settings['public_key'] ) : __( 'Missing', 'liveoncrypto-woocommerce' ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Secret key', 'liveoncrypto-woocommerce' ); ?></th><td><?php echo esc_html( ! empty( $settings['secret_key'] ) ? liveoncrypto_wc_redact_secret( (string) $settings['secret_key'] ) : __( 'Missing', 'liveoncrypto-woocommerce' ) ); ?></td></tr>
	</tbody></table>
	<p><a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=liveoncrypto_connection_test' ), 'liveoncrypto_connection_test' ) ); ?>"><?php esc_html_e( 'Test LiveOnCrypto connection', 'liveoncrypto-woocommerce' ); ?></a></p>

	<h2><?php esc_html_e( 'Webhook', 'liveoncrypto-woocommerce' ); ?></h2>
	<p><?php esc_html_e( 'Copy this endpoint URL into the LiveOnCrypto dashboard webhook settings, then save the dashboard signing secret in WooCommerce > Settings > Payments > LiveOnCrypto.', 'liveoncrypto-woocommerce' ); ?></p>
	<p><input type="url" class="regular-text code" readonly value="<?php echo esc_url( liveoncrypto_wc_webhook_endpoint_url() ); ?>" onclick="this.select();document.execCommand('copy');" /></p>
	<table class="widefat striped"><tbody>
		<tr><th><?php esc_html_e( 'Webhook signing secret', 'liveoncrypto-woocommerce' ); ?></th><td><?php echo esc_html( ! empty( $settings['webhook_secret'] ) ? liveoncrypto_wc_redact_secret( (string) $settings['webhook_secret'] ) : __( 'Missing', 'liveoncrypto-woocommerce' ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Last verified webhook timestamp', 'liveoncrypto-woocommerce' ); ?></th><td><?php echo esc_html( $diagnostics['last_webhook_received'] ?: __( 'No verified webhooks received yet.', 'liveoncrypto-woocommerce' ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Last webhook request timestamp', 'liveoncrypto-woocommerce' ); ?></th><td><?php echo esc_html( $diagnostics['last_webhook_request_received'] ?: __( 'No webhook requests received yet.', 'liveoncrypto-woocommerce' ) ); ?></td></tr>
	</tbody></table>

	<h2><?php esc_html_e( 'Diagnostics', 'liveoncrypto-woocommerce' ); ?></h2>
	<table class="widefat striped"><tbody>
		<tr><th><?php esc_html_e( 'WordPress version', 'liveoncrypto-woocommerce' ); ?></th><td><?php echo esc_html( $diagnostics['wordpress_version'] ); ?></td></tr>
		<tr><th><?php esc_html_e( 'WooCommerce version', 'liveoncrypto-woocommerce' ); ?></th><td><?php echo esc_html( $diagnostics['woocommerce_version'] ); ?></td></tr>
		<tr><th><?php esc_html_e( 'PHP version', 'liveoncrypto-woocommerce' ); ?></th><td><?php echo esc_html( $diagnostics['php_version'] ); ?></td></tr>
		<tr><th><?php esc_html_e( 'HPOS compatibility status', 'liveoncrypto-woocommerce' ); ?></th><td><?php echo esc_html( $diagnostics['hpos_compatibility'] ); ?></td></tr>
		<tr><th><?php esc_html_e( 'REST route availability', 'liveoncrypto-woocommerce' ); ?></th><td><?php echo esc_html( $diagnostics['rest_route_available'] ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Last API error', 'liveoncrypto-woocommerce' ); ?></th><td><?php echo esc_html( $diagnostics['last_api_error'] ?: __( 'None recorded.', 'liveoncrypto-woocommerce' ) ); ?></td></tr>
	</tbody></table>
</div>
