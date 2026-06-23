<?php
/**
 * LiveOnCrypto order-pay widget template.
 *
 * @package LiveOnCrypto_WC
 *
 * @var WC_Order             $order         WooCommerce order.
 * @var array<string,string> $widget_config Public LiveOnCrypto widget config.
 * @var string               $return_url    Customer return URL.
 */

defined( 'ABSPATH' ) || exit;

$order_number = $order instanceof WC_Order ? $order->get_order_number() : '';
$total        = $order instanceof WC_Order ? $order->get_formatted_order_total() : '';
$currency     = $order instanceof WC_Order ? $order->get_currency() : '';
$config_json  = wp_json_encode( $widget_config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT );
?>
<div class="liveoncrypto-wc-checkout-widget" data-liveoncrypto-wc-widget>
	<div class="liveoncrypto-wc-checkout-widget__header">
		<p class="liveoncrypto-wc-checkout-widget__eyebrow"><?php esc_html_e( 'Crypto payment', 'liveoncrypto-woocommerce' ); ?></p>
		<h2 class="liveoncrypto-wc-checkout-widget__title">
			<?php
			printf(
				/* translators: %s: WooCommerce order number. */
				esc_html__( 'Pay for order #%s', 'liveoncrypto-woocommerce' ),
				esc_html( $order_number )
			);
			?>
		</h2>
	</div>

	<div class="liveoncrypto-wc-checkout-widget__summary" aria-label="<?php esc_attr_e( 'Order payment summary', 'liveoncrypto-woocommerce' ); ?>">
		<span class="liveoncrypto-wc-checkout-widget__summary-label"><?php esc_html_e( 'Final total', 'liveoncrypto-woocommerce' ); ?></span>
		<strong class="liveoncrypto-wc-checkout-widget__summary-total"><?php echo wp_kses_post( $total ); ?></strong>
		<span class="liveoncrypto-wc-checkout-widget__summary-currency"><?php echo esc_html( $currency ); ?></span>
	</div>

	<p class="liveoncrypto-wc-checkout-widget__description">
		<?php esc_html_e( 'Your cryptocurrency payment is securely handled through LiveOnCrypto. After you pay, this page will show progress updates while the order remains pending until the LiveOnCrypto webhook confirms payment.', 'liveoncrypto-woocommerce' ); ?>
	</p>

	<div class="liveoncrypto-wc-checkout-widget__notice" data-liveoncrypto-wc-status role="status" aria-live="polite">
		<?php esc_html_e( 'When you are ready, click the button below to open the LiveOnCrypto payment widget.', 'liveoncrypto-woocommerce' ); ?>
	</div>

	<button type="button" class="button alt liveoncrypto-wc-checkout-widget__button" data-liveoncrypto-wc-pay disabled>
		<?php esc_html_e( 'Pay with Crypto', 'liveoncrypto-woocommerce' ); ?>
	</button>

	<p class="liveoncrypto-wc-checkout-widget__fallback" data-liveoncrypto-wc-fallback>
		<?php esc_html_e( 'If the LiveOnCrypto payment widget does not load, refresh this page or contact the store for assistance. Your order will not be marked paid until payment is confirmed by LiveOnCrypto.', 'liveoncrypto-woocommerce' ); ?>
	</p>

	<p class="liveoncrypto-wc-checkout-widget__return">
		<a href="<?php echo esc_url( $return_url ); ?>"><?php esc_html_e( 'Return to order details', 'liveoncrypto-woocommerce' ); ?></a>
	</p>

	<script type="application/json" data-liveoncrypto-wc-config><?php echo $config_json ? $config_json : '{}'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
</div>
