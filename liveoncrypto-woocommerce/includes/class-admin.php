<?php
/**
 * Admin diagnostics and order metadata panel.
 *
 * @package LiveOnCrypto_WC
 */

defined( 'ABSPATH' ) || exit;

class LiveOnCrypto_WC_Admin {
	private const BASE_API_URL = 'https://app.liveoncrypto.online';

	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_order_meta_box' ) );
		add_action( 'admin_post_liveoncrypto_connection_test', array( $this, 'handle_connection_test' ) );
	}

	public function add_menu(): void {
		add_submenu_page(
			'woocommerce',
			esc_html__( 'LiveOnCrypto Diagnostics', 'liveoncrypto-woocommerce' ),
			esc_html__( 'LiveOnCrypto', 'liveoncrypto-woocommerce' ),
			'manage_woocommerce',
			'liveoncrypto-diagnostics',
			array( $this, 'render_diagnostics_page' )
		);
	}

	public function render_diagnostics_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access LiveOnCrypto diagnostics.', 'liveoncrypto-woocommerce' ) );
		}
		$settings    = get_option( 'woocommerce_liveoncrypto_settings', array() );
		$settings    = is_array( $settings ) ? $settings : array();
		$diagnostics = $this->get_diagnostics( $settings );
		include LIVEONCRYPTO_WC_PATH . 'templates/admin-diagnostics.php';
	}

	public function handle_connection_test(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to test the LiveOnCrypto connection.', 'liveoncrypto-woocommerce' ) );
		}
		check_admin_referer( 'liveoncrypto_connection_test' );
		$settings = get_option( 'woocommerce_liveoncrypto_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();
		$status   = 'missing';
		$message  = __( 'LiveOnCrypto secret key is missing.', 'liveoncrypto-woocommerce' );
		if ( ! empty( $settings['secret_key'] ) ) {
			try {
				$client = new LiveOnCrypto_WC_API_Client( self::BASE_API_URL, (string) $settings['secret_key'] );
				$client->get_currencies();
				$status  = 'success';
				$message = __( 'Connection to LiveOnCrypto succeeded.', 'liveoncrypto-woocommerce' );
			} catch ( LiveOnCrypto_WC_API_Exception $exception ) {
				$status  = 'failed';
				$message = __( 'Connection to LiveOnCrypto failed. Check credentials.', 'liveoncrypto-woocommerce' );
				update_option( 'liveoncrypto_wc_last_api_error', sanitize_text_field( $exception->getMessage() ) );
			}
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'liveoncrypto-diagnostics', 'loc_test' => $status, 'loc_msg' => rawurlencode( $message ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/** @param array<string,mixed> $settings */
	private function get_diagnostics( array $settings ): array {
		return array(
			'wordpress_version' => get_bloginfo( 'version' ),
			'woocommerce_version' => defined( 'WC_VERSION' ) ? WC_VERSION : __( 'Unavailable', 'liveoncrypto-woocommerce' ),
			'php_version' => PHP_VERSION,
			'hpos_compatibility' => class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ? __( 'Declared compatible', 'liveoncrypto-woocommerce' ) : __( 'Unknown', 'liveoncrypto-woocommerce' ),
			'rest_route_available' => rest_url( 'liveoncrypto/v1/webhook' ) ? __( 'Available', 'liveoncrypto-woocommerce' ) : __( 'Unavailable', 'liveoncrypto-woocommerce' ),
			'last_api_error' => (string) get_option( 'liveoncrypto_wc_last_api_error', '' ),
			'last_webhook_received' => (string) get_option( 'liveoncrypto_wc_last_webhook_received', '' ),
			'last_webhook_request_received' => (string) get_option( 'liveoncrypto_wc_last_webhook_request_received', '' ),
			'has_public_key' => ! empty( $settings['public_key'] ),
			'has_secret_key' => ! empty( $settings['secret_key'] ),
			'has_webhook_secret' => ! empty( $settings['webhook_secret'] ),
		);
	}

	public function add_order_meta_box(): void {
		foreach ( array( 'shop_order', 'woocommerce_page_wc-orders' ) as $screen ) {
			add_meta_box( 'liveoncrypto_order_details', __( 'LiveOnCrypto Payment', 'liveoncrypto-woocommerce' ), array( $this, 'render_order_meta_box' ), $screen, 'side', 'default' );
		}
	}

	public function render_order_meta_box( mixed $post_or_order ): void {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( is_object( $post_or_order ) ? $post_or_order->ID : 0 );
		if ( ! $order || ! current_user_can( 'edit_shop_order', $order->get_id() ) ) {
			echo '<p>' . esc_html__( 'You do not have permission to view LiveOnCrypto payment details for this order.', 'liveoncrypto-woocommerce' ) . '</p>';
			return;
		}

		if ( 'liveoncrypto' !== $order->get_payment_method() ) {
			echo '<p>' . esc_html__( 'This order was not paid with LiveOnCrypto.', 'liveoncrypto-woocommerce' ) . '</p>';
			return;
		}
		$fields = array(
			__( 'Payment ID', 'liveoncrypto-woocommerce' ) => '_liveoncrypto_payment_id',
			__( 'LiveOnCrypto order number', 'liveoncrypto-woocommerce' ) => '_liveoncrypto_order_number',
			__( 'Network', 'liveoncrypto-woocommerce' ) => '_liveoncrypto_network',
			__( 'Asset', 'liveoncrypto-woocommerce' ) => '_liveoncrypto_asset',
			__( 'Fiat amount/currency', 'liveoncrypto-woocommerce' ) => array( '_liveoncrypto_fiat_amount', '_liveoncrypto_fiat_currency' ),
			__( 'Crypto amount paid', 'liveoncrypto-woocommerce' ) => '_liveoncrypto_amount_paid',
			__( 'Transaction hash', 'liveoncrypto-woocommerce' ) => '_liveoncrypto_tx_hash',
			__( 'Paid timestamp', 'liveoncrypto-woocommerce' ) => '_liveoncrypto_paid_at',
			__( 'Last webhook event', 'liveoncrypto-woocommerce' ) => '_liveoncrypto_last_webhook_event',
		);
		echo '<dl class="liveoncrypto-order-meta">';
		foreach ( $fields as $label => $meta_key ) {
			$value = is_array( $meta_key ) ? trim( (string) $order->get_meta( $meta_key[0], true ) . ' ' . (string) $order->get_meta( $meta_key[1], true ) ) : (string) $order->get_meta( $meta_key, true );
			echo '<dt><strong>' . esc_html( (string) $label ) . '</strong></dt><dd>';
			if ( '_liveoncrypto_tx_hash' === $meta_key ) {
				$url = liveoncrypto_wc_explorer_url( (string) $order->get_meta( '_liveoncrypto_network', true ), $value );
				echo '' !== $url ? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $value ) . '</a>' : esc_html( '' !== $value ? $value : '—' );
			} else {
				echo esc_html( '' !== $value ? $value : '—' );
			}
			echo '</dd>';
		}
		echo '</dl>';
	}
}
