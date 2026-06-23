<?php
/**
 * Logging wrapper.
 *
 * @package LiveOnCrypto_WC
 */

defined( 'ABSPATH' ) || exit;

class LiveOnCrypto_WC_Logger {
	private const SOURCE = 'liveoncrypto';

	public static function info( string $message, array $context = array() ): void {
		self::log( 'info', $message, $context );
	}

	public static function error( string $message, array $context = array() ): void {
		self::log( 'error', $message, $context );
	}

	private static function log( string $level, string $message, array $context = array() ): void {
		if ( 'yes' !== liveoncrypto_wc_get_option( 'debug_logging', 'no' ) && 'error' !== $level ) {
			return;
		}

		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		$context           = self::redact_context( $context );
		$context['source'] = self::SOURCE;
		wc_get_logger()->log( $level, $message, $context );
	}

	/**
	 * Redact credential-like values before writing WooCommerce logs.
	 *
	 * @param array<string,mixed> $context Log context.
	 * @return array<string,mixed>
	 */
	private static function redact_context( array $context ): array {
		$redacted = array();
		foreach ( $context as $key => $value ) {
			$normalized_key = strtolower( (string) $key );
			if ( in_array( $normalized_key, array( 'authorization', 'secret_key', 'api_key', 'token', 'webhook_secret', 'raw_body' ), true ) ) {
				$redacted[ $key ] = '[redacted]';
			} elseif ( 'customeremail' === str_replace( '_', '', $normalized_key ) || 'email' === $normalized_key ) {
				$redacted[ $key ] = is_string( $value ) ? liveoncrypto_wc_mask_email( $value ) : '[redacted]';
			} elseif ( is_array( $value ) ) {
				$redacted[ $key ] = self::redact_context( $value );
			} elseif ( is_string( $value ) ) {
				$redacted[ $key ] = preg_replace( '/\b(sk_(?:live|test)_[A-Za-z0-9_\-]+|whsec_[A-Za-z0-9_\-]+)\b/', '[redacted]', $value );
			} else {
				$redacted[ $key ] = $value;
			}
		}

		return $redacted;
	}
}
