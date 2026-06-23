<?php
/**
 * Webhook endpoint controller.
 *
 * @package LiveOnCrypto_WC
 */

defined( 'ABSPATH' ) || exit;

class LiveOnCrypto_WC_Webhook_Controller {
	private const REST_NAMESPACE      = 'liveoncrypto/v1';
	private const REST_ROUTE          = '/webhook';
	private const REPLAY_WINDOW       = 300;
	private const KNOWN_EVENTS        = array(
		'payment.created',
		'payment.detected',
		'payment.confirming',
		'payment.paid',
		'payment.expired',
		'payment.underpaid',
		'payment.review',
		'payment.overpaid',
		'payment.pending',
		'payment.processing',
		'payment.confirmed',
		'payment.completed',
		'payment.succeeded',
		'payment.failed',
		'payment.cancelled',
		'payment.canceled',
	);

	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'woocommerce_api_liveoncrypto_webhook', array( $this, 'handle_legacy_webhook' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function handle_legacy_webhook(): void {
		status_header( 410 );
		wp_send_json_error( array( 'message' => 'Use the LiveOnCrypto REST webhook endpoint.' ), 410 );
	}

	public function handle( WP_REST_Request $request ): WP_REST_Response {
		$raw_body = $request->get_body();
		$headers  = $this->get_webhook_headers( $request );
		update_option( 'liveoncrypto_wc_last_webhook_request_received', current_time( 'mysql' ) );
		LiveOnCrypto_WC_Logger::info( 'Webhook received.', array( 'event' => $headers['event'] ?? '', 'timestamp' => $headers['timestamp'] ?? '' ) );

		if ( empty( $headers['event'] ) || empty( $headers['timestamp'] ) || empty( $headers['signature'] ) ) {
			$this->log_failure( 'Payload validation failed.', array( 'reason' => 'missing_headers' ) );
			return $this->error_response( __( 'Unauthorized.', 'liveoncrypto-woocommerce' ), 401 );
		}

		if ( ! $this->timestamp_is_valid( $headers['timestamp'] ) ) {
			$this->log_failure( 'Timestamp replay rejected.', array( 'reason' => 'invalid_timestamp', 'event' => $headers['event'] ) );
			return $this->error_response( __( 'Unauthorized.', 'liveoncrypto-woocommerce' ), 401 );
		}

		if ( ! $this->signature_is_valid( $headers['timestamp'], $raw_body, $headers['signature'] ) ) {
			$this->log_failure( 'Signature verification failed.', array( 'reason' => 'invalid_signature', 'event' => $headers['event'] ) );
			return $this->error_response( __( 'Unauthorized.', 'liveoncrypto-woocommerce' ), 401 );
		}
		update_option( 'liveoncrypto_wc_last_webhook_received', current_time( 'mysql' ) );

		$payload = json_decode( $raw_body, true );
		if ( ! is_array( $payload ) ) {
			$this->log_failure( 'Payload validation failed.', array( 'reason' => 'invalid_json' ) );
			return $this->error_response( __( 'Invalid payload.', 'liveoncrypto-woocommerce' ), 422 );
		}

		$validated = $this->validate_payload( $payload );
		if ( is_wp_error( $validated ) ) {
			$this->log_failure(
				'Payload validation failed.',
				array(
					'reason'     => 'invalid_payload',
					'error_code' => $validated->get_error_code(),
					'payment_id' => isset( $payload['paymentId'] ) && is_scalar( $payload['paymentId'] ) ? sanitize_text_field( (string) $payload['paymentId'] ) : '',
				)
		);
			return $this->error_response( __( 'Invalid payload.', 'liveoncrypto-woocommerce' ), 422 );
		}

		$order_sync = new LiveOnCrypto_WC_Order_Sync();
		$order      = $order_sync->find_order( $validated['paymentId'], $validated['merchantOrderRef'] );
		if ( ! $order ) {
			LiveOnCrypto_WC_Logger::error(
				'Order not found.',
				array(
					'payment_id' => $validated['paymentId'],
					'order_ref'  => $validated['merchantOrderRef'],
				)
		);
			return $this->error_response( __( 'Order not found.', 'liveoncrypto-woocommerce' ), 404 );
		}

		$result = $order_sync->process_webhook_event( $order, $validated );
		if ( ! empty( $result['duplicate'] ) ) {
			LiveOnCrypto_WC_Logger::info( 'Duplicate webhook ignored.', array( 'payment_id' => $validated['paymentId'], 'event' => $validated['event'] ) );
			return rest_ensure_response( array( 'success' => true, 'duplicate' => true ) );
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	/** @return array{event:string,timestamp:string,signature:string} */
	private function get_webhook_headers( WP_REST_Request $request ): array {
		return array(
			'event'     => trim( (string) $request->get_header( 'x-liveoncrypto-event' ) ),
			'timestamp' => trim( (string) $request->get_header( 'x-liveoncrypto-timestamp' ) ),
			'signature' => trim( (string) $request->get_header( 'x-liveoncrypto-signature' ) ),
		);
	}

	private function timestamp_is_valid( string $timestamp ): bool {
		if ( ! preg_match( '/^-?\d+$/', $timestamp ) ) {
		return false;
		}

		return abs( time() - (int) $timestamp ) <= self::REPLAY_WINDOW;
	}

	private function signature_is_valid( string $timestamp, string $raw_body, string $signature ): bool {
		$secret = (string) liveoncrypto_wc_get_option( 'webhook_secret', '' );
		if ( '' === $secret ) {
		return false;
		}

		$expected = hash_hmac( 'sha256', $timestamp . '.' . $raw_body, $secret );
		return hash_equals( $expected, $signature );
	}

	/** @param array<string,mixed> $payload */
	private function validate_payload( array $payload ) {
		$required = array( 'event', 'paymentId', 'merchantOrderRef', 'status', 'fiatAmount', 'fiatCurrency' );
		foreach ( $required as $field ) {
			if ( ! array_key_exists( $field, $payload ) || ! is_scalar( $payload[ $field ] ) || '' === trim( (string) $payload[ $field ] ) ) {
				return new WP_Error( 'missing_' . $field );
		}
		}

		$event = strtolower( sanitize_text_field( str_replace( '_', '.', (string) $payload['event'] ) ) );
		if ( ! in_array( $event, self::KNOWN_EVENTS, true ) ) {
			return new WP_Error( 'unknown_event' );
		}

		$payment_id = sanitize_text_field( (string) $payload['paymentId'] );
		if ( ! preg_match( '/^pay_[A-Za-z0-9_-]{10,80}$/', $payment_id ) ) {
			return new WP_Error( 'invalid_payment_id' );
		}

		$fiat_amount = (string) $payload['fiatAmount'];
		if ( ! is_numeric( $fiat_amount ) || (float) $fiat_amount <= 0 ) {
			return new WP_Error( 'invalid_fiat_amount' );
		}

		$fiat_currency = sanitize_text_field( (string) $payload['fiatCurrency'] );
		if ( ! preg_match( '/^[A-Z]{3}$/', $fiat_currency ) ) {
			return new WP_Error( 'invalid_fiat_currency' );
		}

		$tx_hash = $this->optional_payload_value( $payload, array( 'txHash', 'tx_hash', 'transactionHash' ) );
		if ( '' !== $tx_hash && ! $this->tx_hash_is_valid( $tx_hash ) ) {
			return new WP_Error( 'invalid_tx_hash' );
		}

		return array(
			'event'            => $event,
			'paymentId'        => $payment_id,
			'merchantOrderRef' => sanitize_text_field( (string) $payload['merchantOrderRef'] ),
			'orderNumber'      => $this->optional_payload_value( $payload, array( 'orderNumber', 'order_number' ) ),
			'status'           => sanitize_key( (string) $payload['status'] ),
			'network'          => $this->optional_payload_value( $payload, array( 'network', 'chain' ) ),
			'asset'            => $this->optional_payload_value( $payload, array( 'asset', 'currency', 'cryptoCurrency' ) ),
			'amountPaid'       => $this->optional_payload_value( $payload, array( 'amountPaid', 'amount_paid', 'cryptoAmount' ) ),
			'fiatAmount'       => wc_format_decimal( $fiat_amount, 2 ),
			'fiatCurrency'     => $fiat_currency,
			'txHash'           => $tx_hash,
			'paidAt'           => $this->optional_payload_value( $payload, array( 'paidAt', 'paid_at' ) ),
		);
	}

	private function tx_hash_is_valid( string $tx_hash ): bool {
		if ( preg_match( '/^0x[a-fA-F0-9]{64}$/', $tx_hash ) ) {
			return true;
		}

		if ( preg_match( '/^[A-Za-z0-9]{32,120}$/', $tx_hash ) ) {
			return true;
		}

		return false;
	}

	/**
	 * @param array<string,mixed> $payload
	 * @param array<int,string>   $keys
	 */
	private function optional_payload_value( array $payload, array $keys ): string {
		foreach ( $keys as $key ) {
			if ( isset( $payload[ $key ] ) && is_scalar( $payload[ $key ] ) ) {
				return sanitize_text_field( (string) $payload[ $key ] );
		}
		}

		return '';
	}

	private function error_response( string $message, int $status ): WP_REST_Response {
		return new WP_REST_Response( array( 'success' => false, 'message' => $message ), $status );
	}

	/** @param array<string,mixed> $context */
	private function log_failure( string $message, array $context = array() ): void {
		LiveOnCrypto_WC_Logger::error( $message, $context );
	}
}
