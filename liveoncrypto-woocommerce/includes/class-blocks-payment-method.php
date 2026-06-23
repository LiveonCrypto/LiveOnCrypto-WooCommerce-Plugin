<?php
/**
 * WooCommerce Blocks payment method integration.
 *
 * @package LiveOnCrypto_WC
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

if ( class_exists( AbstractPaymentMethodType::class ) ) {
	/**
	 * Registers LiveOnCrypto in the WooCommerce Blocks checkout UI.
	 *
	 * The classic WC_Payment_Gateway remains the source of truth for settings,
	 * order preparation, payment intent creation, and redirect handling. Blocks
	 * only presents the payment method and lets WooCommerce call the gateway's
	 * existing process_payment() method after the order is created.
	 */
	class LiveOnCrypto_WC_Blocks_Payment_Method extends AbstractPaymentMethodType {
		/**
		 * Payment method name matching the classic gateway ID.
		 *
		 * @var string
		 */
		protected $name = 'liveoncrypto';

		/**
		 * Classic gateway instance.
		 *
		 * @var LiveOnCrypto_WC_Gateway|null
		 */
		private ?LiveOnCrypto_WC_Gateway $gateway = null;

		/**
		 * Initialize the integration from the classic gateway settings.
		 */
		public function initialize(): void {
			$gateways = WC()->payment_gateways()->payment_gateways();
			$gateway  = $gateways[ $this->name ] ?? null;

			if ( $gateway instanceof LiveOnCrypto_WC_Gateway ) {
				$this->gateway = $gateway;
			}
		}

		/**
		 * Whether this payment method can be shown in Blocks checkout.
		 */
		public function is_active(): bool {
			return $this->gateway instanceof LiveOnCrypto_WC_Gateway && 'yes' === $this->gateway->enabled;
		}

		/**
		 * Register the minimal Blocks frontend script.
		 *
		 * @return array<int,string>
		 */
		public function get_payment_method_script_handles(): array {
			$handle = 'liveoncrypto_wc_blocks_checkout';

			wp_register_script(
				$handle,
				LIVEONCRYPTO_WC_URL . 'assets/js/blocks-checkout.js',
				array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n' ),
				LIVEONCRYPTO_WC_VERSION,
				true
			);

			return array( $handle );
		}

		/**
		 * Expose classic gateway settings needed to render the Blocks payment row.
		 *
		 * @return array<string,mixed>
		 */
		public function get_payment_method_data(): array {
			$title       = __( 'Pay with Crypto', 'liveoncrypto-woocommerce' );
			$description = __( 'Pay securely with cryptocurrency through LiveOnCrypto.', 'liveoncrypto-woocommerce' );

			if ( $this->gateway instanceof LiveOnCrypto_WC_Gateway ) {
				$title       = (string) $this->gateway->title;
				$description = (string) $this->gateway->description;
			}

			return array(
				'title'       => $title,
				'description' => $description,
				'supports'    => $this->get_supported_features(),
			);
		}

		/**
		 * Mirror the classic gateway supported features.
		 *
		 * @return array<int,string>
		 */
		public function get_supported_features(): array {
			if ( $this->gateway instanceof LiveOnCrypto_WC_Gateway ) {
				return $this->gateway->supports;
			}

			return array( 'products' );
		}
	}
}
