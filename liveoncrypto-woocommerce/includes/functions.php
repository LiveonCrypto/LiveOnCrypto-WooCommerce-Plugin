<?php
/**
 * Shared helper functions.
 *
 * @package LiveOnCrypto_WC
 */

defined( 'ABSPATH' ) || exit;

function liveoncrypto_wc_get_option( string $key, mixed $default = null ): mixed {
	$options = get_option( 'woocommerce_liveoncrypto_settings', array() );
	return is_array( $options ) ? ( $options[ $key ] ?? $default ) : $default;
}

function liveoncrypto_wc_order_meta_key( string $key ): string {
	return '_liveoncrypto_' . sanitize_key( $key );
}

function liveoncrypto_wc_redact_secret( string $value ): string {
	$value = trim( $value );
	if ( '' === $value ) {
		return '';
	}
	$length = strlen( $value );
	if ( $length <= 8 ) {
		return str_repeat( '•', $length );
	}
	return substr( $value, 0, 4 ) . str_repeat( '•', max( 4, $length - 8 ) ) . substr( $value, -4 );
}

function liveoncrypto_wc_mask_email( string $email ): string {
	$email = sanitize_email( $email );
	if ( '' === $email || ! str_contains( $email, '@' ) ) {
		return '';
	}
	list( $local, $domain ) = explode( '@', $email, 2 );
	$masked_local = substr( $local, 0, 1 ) . str_repeat( '*', max( 1, strlen( $local ) - 1 ) );
	return $masked_local . '@' . $domain;
}

function liveoncrypto_wc_normalize_decimal( mixed $value, int $precision = 8 ): string {
	if ( ! is_numeric( $value ) ) {
		return '';
	}
	$normalized = number_format( (float) $value, $precision, '.', '' );
	return rtrim( rtrim( $normalized, '0' ), '.' );
}

function liveoncrypto_wc_safe_event_key( string $event, string $payment_id, string $tx_hash = '' ): string {
	$source = strtolower( sanitize_key( $event ) ) . '|' . sanitize_text_field( $payment_id ) . '|' . sanitize_text_field( $tx_hash );
	return hash( 'sha256', $source );
}

function liveoncrypto_wc_webhook_endpoint_url(): string {
	return rest_url( 'liveoncrypto/v1/webhook' );
}

function liveoncrypto_wc_explorer_url( string $network, string $tx_hash ): string {
	$network = strtolower( sanitize_key( $network ) );
	$tx_hash = trim( sanitize_text_field( $tx_hash ) );
	if ( '' === $tx_hash || ! preg_match( '/^[A-Za-z0-9]+$/', $tx_hash ) ) {
		return '';
	}
	$bases = array(
		'bitcoin'  => 'https://www.blockchain.com/explorer/transactions/btc/',
		'ethereum' => 'https://etherscan.io/tx/',
		'polygon'  => 'https://polygonscan.com/tx/',
		'bsc'      => 'https://bscscan.com/tx/',
		'arbitrum' => 'https://arbiscan.io/tx/',
		'optimism' => 'https://optimistic.etherscan.io/tx/',
		'base'     => 'https://basescan.org/tx/',
		'solana'   => 'https://solscan.io/tx/',
	);
	return isset( $bases[ $network ] ) ? $bases[ $network ] . rawurlencode( $tx_hash ) : '';
}
