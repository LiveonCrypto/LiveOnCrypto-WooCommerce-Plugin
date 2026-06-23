<?php
require __DIR__ . '/bootstrap.php';

$gateway = new LiveOnCrypto_WC_Gateway();
$gateway->settings['public_key'] = 'pk_test_existing_1234567890123456';
$gateway->settings['secret_key'] = 'sk_test_existing_1234567890123456';
$gateway->settings['webhook_secret'] = 'whsec_existing';

loc_assert_same( 'pk_test_new_1234567890123456', $gateway->validate_public_key_field( 'public_key', ' pk_test_new_1234567890123456 ' ), 'valid public key is accepted' );
loc_assert_same( 'pk_test_existing_1234567890123456', $gateway->validate_public_key_field( 'public_key', 'bad_public' ), 'invalid public key is rejected' );
loc_assert_same( 'sk_live_new_1234567890123456', $gateway->validate_secret_key_field( 'secret_key', 'sk_live_new_1234567890123456' ), 'valid secret key is accepted' );
loc_assert_same( 'sk_test_existing_1234567890123456', $gateway->validate_secret_key_field( 'secret_key', 'not_secret' ), 'invalid secret key is rejected' );
loc_assert_same( 'whsec_new', $gateway->validate_webhook_secret_field( 'webhook_secret', 'whsec_new' ), 'valid webhook secret is accepted' );
loc_assert_same( 'whsec_existing', $gateway->validate_webhook_secret_field( 'webhook_secret', 'bad_whsec' ), 'invalid webhook secret is rejected' );

$controller = new LiveOnCrypto_WC_Webhook_Controller();
$payload = loc_valid_payload();
$body = wp_json_encode( $payload );
$timestamp = (string) time();
$signature = hash_hmac( 'sha256', $timestamp . '.' . $body, 'whsec_test_secret' );
loc_assert_true( loc_reflect_call( $controller, 'signature_is_valid', array( $timestamp, $body, $signature ) ), 'valid HMAC signature verifies' );
loc_assert_false( loc_reflect_call( $controller, 'signature_is_valid', array( $timestamp, $body, 'bad_signature' ) ), 'invalid HMAC signature is rejected' );
loc_assert_false( loc_reflect_call( $controller, 'timestamp_is_valid', array( (string) ( time() - 301 ) ) ), 'timestamp replay outside window is rejected' );

$order = loc_make_order();
$order_ref = loc_reflect_call( $gateway, 'build_order_reference', array( $order ) );
$payload = loc_valid_payload( $order_ref );
$order->update_meta_data( '_liveoncrypto_order_ref', $order_ref );
$response = $controller->handle( loc_signed_request( $payload ) );
loc_assert_same( 200, $response->get_status(), 'signed webhook request succeeds' );
loc_assert_true( $order->is_paid(), 'valid paid webhook marks order paid' );

$missing = new WP_REST_Request( wp_json_encode( $payload ), array() );
loc_assert_same( 401, $controller->handle( $missing )->get_status(), 'missing signature headers return 401' );
loc_assert_same( 401, $controller->handle( loc_signed_request( $payload, 'wrong_secret' ) )->get_status(), 'invalid request signature returns 401' );
loc_assert_same( 401, $controller->handle( loc_signed_request( $payload, 'whsec_test_secret', time() - 999 ) )->get_status(), 'stale request timestamp returns 401' );

$invalid_payload = $payload;
unset( $invalid_payload['paymentId'] );
loc_assert_same( 422, $controller->handle( loc_signed_request( $invalid_payload ) )->get_status(), 'invalid payload schema returns 422' );

loc_assert_same( '1.23', liveoncrypto_wc_normalize_decimal( '1.23000000' ), 'amount normalization trims insignificant zeros' );
loc_assert_same( '10.00', wc_format_decimal( '10.004', 2 ), 'fiat amount normalizes to two decimals' );

$mismatch_order = loc_make_order( 1002, '10.00', 'USD' );
$mismatch = loc_valid_payload( 'wc_1002_ref', 'pay_ABCDEFGHIJ' );
$mismatch_order->update_meta_data( '_liveoncrypto_order_ref', 'wc_1002_ref' );
$mismatch['fiatAmount'] = '9.00';
$sync = new LiveOnCrypto_WC_Order_Sync();
$sync->process_webhook_event( $mismatch_order, array_merge( $mismatch, array( 'orderNumber'=>'', 'network'=>'', 'asset'=>'', 'amountPaid'=>'', 'txHash'=>'', 'paidAt'=>'' ) ) );
loc_assert_same( 'on-hold', $mismatch_order->get_status(), 'amount mismatch places order on hold' );

$currency_order = loc_make_order( 1003, '10.00', 'USD' );
$currency_payload = array_merge( loc_valid_payload( 'wc_1003_ref', 'pay_KLMNOPQRST' ), array( 'fiatCurrency' => 'EUR', 'orderNumber'=>'', 'network'=>'', 'asset'=>'', 'amountPaid'=>'', 'paidAt'=>'' ) );
$sync->process_webhook_event( $currency_order, $currency_payload );
loc_assert_same( 'on-hold', $currency_order->get_status(), 'currency mismatch places order on hold' );

$key_one = liveoncrypto_wc_safe_event_key( 'payment.paid', 'pay_1234567890', '0x' . str_repeat( 'a', 64 ) );
$key_two = liveoncrypto_wc_safe_event_key( 'payment.paid', 'pay_1234567890', '0x' . str_repeat( 'a', 64 ) );
loc_assert_same( $key_one, $key_two, 'duplicate event key generation is deterministic' );
loc_assert_same( $order_ref, loc_reflect_call( $gateway, 'build_order_reference', array( $order ) ), 'order reference generation is deterministic' );

echo "Unit tests passed.\n";
