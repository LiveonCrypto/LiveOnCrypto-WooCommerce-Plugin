<?php
require __DIR__ . '/bootstrap.php';

function loc_run_hpos_scenario( $enabled ) {
	$GLOBALS['loc_hpos_enabled'] = $enabled;
	$id = $enabled ? 3001 : 3002;
	$order = loc_make_order( $id, '10.00', 'USD' );
	$gateway = new LiveOnCrypto_WC_Gateway();
	$gateway->settings = array_merge( $gateway->settings, array( 'public_key'=>'pk_test_public_1234567890123456', 'secret_key'=>'', 'webhook_secret'=>'whsec_test_secret', 'pending_status'=>'on-hold', 'widget_script_url'=>'https://malicious.example/widget.js' ) );
	$result = $gateway->process_payment( $id );
	loc_assert_same( 'success', $result['result'], 'checkout can create LiveOnCrypto order' );
	loc_assert_same( 'on-hold', $order->get_status(), 'checkout order is pending/on-hold' );
	loc_assert_true( (string) $order->get_meta( '_liveoncrypto_order_ref', true ) !== '', 'checkout stores order reference' );

	ob_start();
	$gateway->render_payment_widget( $id );
	$html = ob_get_clean();
	loc_assert_true( str_contains( $html, 'data-liveoncrypto-wc-config' ), 'order-pay page renders fallback widget config before server intent exists' );
	loc_assert_same( 'https://app.liveoncrypto.online/widget.js', $GLOBALS['loc_scripts']['liveoncrypto_widget']['src'] ?? '', 'widget script URL is hard-coded to production' );
	loc_assert_false( str_contains( $html, 'sk_test_secret_1234567890123456' ), 'secret key is not present in rendered HTML' );

	$order->update_meta_data( '_liveoncrypto_payment_id', 'pay_RENDERTEST' . $id );
	ob_start();
	$gateway->render_payment_widget( $id );
	$html = ob_get_clean();
	loc_assert_true( str_contains( $html, 'data-liveoncrypto-wc-config' ), 'order-pay page renders widget config after server intent exists' );
	loc_assert_true( str_contains( $html, 'pk_test_public_1234567890123456' ), 'rendered widget includes public key' );
	loc_assert_true( str_contains( $html, 'pay_RENDERTEST' . $id ), 'rendered widget includes server-created payment ID' );
	loc_assert_true( str_contains( $html, 'buyer@example.test' ), 'customer email is present in public order-scoped widget config' );
	loc_assert_true( str_contains( $html, 'amountFiat' ), 'fiat amount is present in public order-scoped widget config' );
	loc_assert_false( str_contains( $html, 'sk_test_secret_1234567890123456' ), 'secret key is not present in rendered widget HTML' );

	$payload = loc_valid_payload( $order->get_meta( '_liveoncrypto_order_ref', true ), 'pay_' . ( $enabled ? 'HPOSENABLED1' : 'HPOSDISABL1' ) );
	$controller = new LiveOnCrypto_WC_Webhook_Controller();
	$response = $controller->handle( loc_signed_request( $payload ) );
	loc_assert_same( 200, $response->get_status(), 'webhook marks order paid' );
	loc_assert_true( $order->is_paid(), 'order paid after webhook' );
	$count = $order->get_completion_count();
	$duplicate = $controller->handle( loc_signed_request( $payload ) );
	loc_assert_same( 200, $duplicate->get_status(), 'duplicate webhook returns 200' );
	loc_assert_same( $count, $order->get_completion_count(), 'duplicate webhook does not complete order twice' );
	loc_assert_same( 401, $controller->handle( loc_signed_request( $payload, 'wrong_secret' ) )->get_status(), 'invalid signature returns 401' );

	$hold_order = loc_make_order( $id + 10, '10.00', 'USD' );
	$hold_order->update_meta_data( '_liveoncrypto_order_ref', 'wc_hold_' . $id );
	$mismatch = loc_valid_payload( 'wc_hold_' . $id, 'pay_MISMATCH' . $id );
	$mismatch['fiatAmount'] = '5.00';
	loc_assert_same( 200, $controller->handle( loc_signed_request( $mismatch ) )->get_status(), 'amount mismatch webhook is accepted for review' );
	loc_assert_same( 'on-hold', $hold_order->get_status(), 'amount mismatch places order on hold' );
}

$gateway = new LiveOnCrypto_WC_Gateway();
loc_assert_true( array_key_exists( 'enabled', $gateway->form_fields ) && 'LiveOnCrypto' === $gateway->method_title, 'gateway appears in WooCommerce settings' );
loc_assert_false( array_key_exists( 'environment', $gateway->form_fields ), 'environment setting is hidden because it is hard-coded' );
loc_assert_false( array_key_exists( 'base_api_url', $gateway->form_fields ), 'base API URL setting is hidden because it is hard-coded' );
loc_assert_false( array_key_exists( 'widget_script_url', $gateway->form_fields ), 'widget script URL setting is hidden because it is hard-coded' );
loc_assert_same( array( 'pending', 'failed', 'on-hold' ), $gateway->allow_on_hold_order_payment( array( 'pending', 'failed' ), loc_make_order( 2999 ) ), 'on-hold LiveOnCrypto orders remain payable on the order-pay page' );
loc_run_hpos_scenario( true );
loc_run_hpos_scenario( false );

echo "Integration tests passed.\n";
