<?php
/**
 * Standalone coverage for reusable support helpers.
 *
 * Run with: php tests/test-support-helpers.php
 */

define( 'ABSPATH', __DIR__ . '/' );

function sanitize_key( $key ) {
	$key = strtolower( (string) $key );
	return preg_replace( '/[^a-z0-9_\-]/', '', $key );
}

function sanitize_text_field( $value ) {
	$value = strip_tags( (string) $value );
	$value = preg_replace( '/[\r\n\t ]+/', ' ', $value );
	return trim( $value );
}

function sanitize_email( $email ) {
	return filter_var( trim( (string) $email ), FILTER_SANITIZE_EMAIL );
}

function get_option( $key, $default = false ) {
	$options = array(
		'woocommerce_liveoncrypto_settings' => array(
			'debug_logging'  => 'yes',
			'webhook_secret' => 'whsec_test_secret',
		),
	);

	return $options[ $key ] ?? $default;
}

function rest_url( $path = '' ) {
	return 'https://example.test/wp-json/' . ltrim( (string) $path, '/' );
}

require_once __DIR__ . '/../liveoncrypto-woocommerce/includes/functions.php';

function assert_same_value( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		fwrite( STDERR, $message . PHP_EOL . 'Expected: ' . var_export( $expected, true ) . PHP_EOL . 'Actual: ' . var_export( $actual, true ) . PHP_EOL );
		exit( 1 );
	}
}

function assert_matches_pattern( $pattern, $actual, $message ) {
	if ( ! preg_match( $pattern, $actual ) ) {
		fwrite( STDERR, $message . PHP_EOL . 'Pattern: ' . $pattern . PHP_EOL . 'Actual: ' . var_export( $actual, true ) . PHP_EOL );
		exit( 1 );
	}
}

assert_same_value( 'sk_t•••••••••••••3456', liveoncrypto_wc_redact_secret( 'sk_test_secret_123456' ), 'Secrets should keep only a short prefix/suffix.' );
assert_same_value( '••••••••', liveoncrypto_wc_redact_secret( '12345678' ), 'Short secrets should be fully redacted.' );
assert_same_value( 'c*******@example.com', liveoncrypto_wc_mask_email( 'customer@example.com' ), 'Customer emails should be masked.' );
assert_same_value( '1.23', liveoncrypto_wc_normalize_decimal( '1.23000000' ), 'Decimals should trim trailing zeroes.' );
assert_same_value( '0.00000001', liveoncrypto_wc_normalize_decimal( '0.000000009', 8 ), 'Decimals should normalize to configured precision.' );
assert_matches_pattern( '/^[a-f0-9]{64}$/', liveoncrypto_wc_safe_event_key( 'payment.paid', 'pay_1234567890', '0xabc' ), 'Event keys should be safe hashes.' );
assert_same_value( liveoncrypto_wc_safe_event_key( 'payment.paid', 'pay_1234567890', '0xabc' ), liveoncrypto_wc_safe_event_key( 'payment.paid', 'pay_1234567890', '0xabc' ), 'Event keys should be deterministic.' );
assert_same_value( 'https://example.test/wp-json/liveoncrypto/v1/webhook', liveoncrypto_wc_webhook_endpoint_url(), 'Webhook URLs should use the REST route.' );
assert_same_value( 'https://etherscan.io/tx/0xabc123', liveoncrypto_wc_explorer_url( 'ethereum', '0xabc123' ), 'Known network explorer URLs should be generated.' );
assert_same_value( '', liveoncrypto_wc_explorer_url( 'unknown', '0xabc123' ), 'Unknown networks should not generate explorer URLs.' );
assert_same_value( '', liveoncrypto_wc_explorer_url( 'ethereum', 'bad/hash' ), 'Unsafe transaction hashes should not generate explorer URLs.' );

echo "Support helper tests passed.\n";
