<?php
/**
 * Main plugin coordinator.
 *
 * @package LiveOnCrypto_WC
 */

defined( 'ABSPATH' ) || exit;

class LiveOnCrypto_WC_Plugin {
	private static ?LiveOnCrypto_WC_Plugin $instance = null;

	public static function instance(): LiveOnCrypto_WC_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init(): void {
		add_filter( 'woocommerce_payment_gateways', array( $this, 'register_gateway' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'woocommerce_blocks_payment_method_type_registration', array( $this, 'register_blocks_payment_method' ) );

		( new LiveOnCrypto_WC_Webhook_Controller() )->init();
		( new LiveOnCrypto_WC_Order_Sync() )->init();
		( new LiveOnCrypto_WC_Admin() )->init();

		LiveOnCrypto_WC_Logger::info( 'Gateway initialized.' );
	}

	public function register_gateway( array $gateways ): array {
		$gateways[] = 'LiveOnCrypto_WC_Gateway';
		return $gateways;
	}

	public function register_blocks_payment_method( $payment_method_registry ): void {
		if ( class_exists( 'LiveOnCrypto_WC_Blocks_Payment_Method' ) && is_object( $payment_method_registry ) && method_exists( $payment_method_registry, 'register' ) ) {
			$payment_method_registry->register( new LiveOnCrypto_WC_Blocks_Payment_Method() );
			LiveOnCrypto_WC_Logger::info( 'Blocks payment method registered.' );
		}
	}

	public function enqueue_checkout_assets(): void {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}

		wp_enqueue_style( 'liveoncrypto_wc_checkout', LIVEONCRYPTO_WC_URL . 'assets/css/checkout.css', array(), LIVEONCRYPTO_WC_VERSION );
	}

	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( false === strpos( $hook_suffix, 'woocommerce' ) ) {
			return;
		}

		wp_enqueue_style( 'liveoncrypto_wc_admin', LIVEONCRYPTO_WC_URL . 'assets/css/admin.css', array(), LIVEONCRYPTO_WC_VERSION );
	}
}
