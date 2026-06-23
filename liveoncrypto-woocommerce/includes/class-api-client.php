<?php
/**
 * LiveOnCrypto API client.
 *
 * @package LiveOnCrypto_WC
 */

defined( 'ABSPATH' ) || exit;

/**
 * Exception raised for LiveOnCrypto API failures.
 */
class LiveOnCrypto_WC_API_Exception extends Exception {
	private string $error_type;
	private int $status_code;
	private array $details;

	public function __construct( string $message, string $error_type = 'api_error', int $status_code = 0, array $details = array() ) {
		parent::__construct( $message, $status_code );

		$this->error_type  = $error_type;
		$this->status_code = $status_code;
		$this->details     = $details;
	}

	public function get_error_type(): string {
		return $this->error_type;
	}

	public function get_status_code(): int {
		return $this->status_code;
	}

	public function get_details(): array {
		return $this->details;
	}
}

/**
 * Minimal WordPress HTTP API backed client for LiveOnCrypto.
 */
class LiveOnCrypto_WC_API_Client {
	private const CONNECT_TIMEOUT = 5;
	private const REQUEST_TIMEOUT = 15;

	private string $base_url;
	private string $secret_key;

	public function __construct( string $base_url = '', string $secret_key = '' ) {
		$this->base_url   = untrailingslashit( trim( $base_url ) );
		$this->secret_key = trim( $secret_key );
	}

	public function has_credentials(): bool {
		return '' !== $this->base_url && '' !== $this->secret_key;
	}

	/**
	 * Get payments.
	 *
	 * @return array<string,mixed>
	 * @throws LiveOnCrypto_WC_API_Exception When the request fails.
	 */
	public function get_payments(): array {
		return $this->request( 'GET', '/api/v1/payments' );
	}

	/**
	 * Get a payment by LiveOnCrypto payment UID.
	 *
	 * @param string $payment_uid Payment UID.
	 * @return array<string,mixed>
	 * @throws LiveOnCrypto_WC_API_Exception When the request fails.
	 */
	public function get_payment( string $payment_uid ): array {
		$payment_uid = trim( $payment_uid );
		if ( '' === $payment_uid ) {
			throw new LiveOnCrypto_WC_API_Exception( 'Payment UID is required.', 'validation_error', 0 );
		}

		return $this->request( 'GET', '/api/v1/payments/' . rawurlencode( $payment_uid ) );
	}

	/**
	 * Create a server-side payment intent.
	 *
	 * The optional idempotency key is sent using a conventional header so it can be
	 * supported by the API without changing gateway call sites later.
	 *
	 * @param array<string,mixed> $data Payload.
	 * @param string             $idempotency_key Optional idempotency key.
	 * @return array<string,mixed>
	 * @throws LiveOnCrypto_WC_API_Exception When the request fails.
	 */
	public function create_payment_intent( array $data, string $idempotency_key = '' ): array {
		$headers = array();
		if ( '' !== trim( $idempotency_key ) ) {
			$headers['Idempotency-Key'] = trim( $idempotency_key );
		}

		return $this->request( 'POST', '/api/v1/payment-intents', $data, $headers );
	}

	/**
	 * Get supported currencies.
	 *
	 * @return array<string,mixed>
	 * @throws LiveOnCrypto_WC_API_Exception When the request fails.
	 */
	public function get_currencies(): array {
		return $this->request( 'GET', '/api/v1/currencies' );
	}

	/**
	 * @param array<string,mixed> $body Request body.
	 * @param array<string,string> $extra_headers Additional headers.
	 * @return array<string,mixed>
	 * @throws LiveOnCrypto_WC_API_Exception When the request fails.
	 */
	private function request( string $method, string $path, array $body = array(), array $extra_headers = array() ): array {
		if ( ! $this->has_credentials() ) {
			throw new LiveOnCrypto_WC_API_Exception( 'LiveOnCrypto API credentials are incomplete.', 'authentication_error', 0 );
		}

		$url     = $this->base_url . '/' . ltrim( $path, '/' );
		$method  = strtoupper( $method );
		$headers = array_merge(
			array(
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $this->secret_key,
			),
			$extra_headers
		);

		$args = array(
			'method'      => $method,
			'timeout'     => self::REQUEST_TIMEOUT,
			'redirection' => 0,
			'headers'     => $headers,
		);

		if ( 'GET' !== $method ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}

		$args = apply_filters( 'liveoncrypto_wc_api_request_args', $args, $method, $path, $body );
		$args['timeout']         = self::REQUEST_TIMEOUT;
		$args['connect_timeout'] = self::CONNECT_TIMEOUT;

		LiveOnCrypto_WC_Logger::info(
			'LiveOnCrypto API request started.',
			$this->redact_context(
				array(
					'method'  => $method,
					'url'     => $url,
					'headers' => $args['headers'],
					'body'    => $body,
				)
			)
		);

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			LiveOnCrypto_WC_Logger::error( 'LiveOnCrypto API network error.', $this->redact_context( array( 'error' => $response->get_error_message(), 'code' => $response->get_error_code(), 'url' => $url ) ) );
			throw new LiveOnCrypto_WC_API_Exception( 'Unable to connect to LiveOnCrypto.', 'network_error', 0, array( 'code' => $response->get_error_code() ) );
		}

		$status_code   = (int) wp_remote_retrieve_response_code( $response );
		$response_body = (string) wp_remote_retrieve_body( $response );
		$decoded       = array();
		if ( '' !== $response_body ) {
			$decoded = json_decode( $response_body, true );
			if ( ! is_array( $decoded ) ) {
				$decoded = array( 'raw_body' => $response_body );
			}
		}

		if ( $status_code < 200 || $status_code >= 300 ) {
			$error_type = $this->classify_error( $status_code );
			LiveOnCrypto_WC_Logger::error( 'LiveOnCrypto API error response.', $this->redact_context( array( 'status_code' => $status_code, 'error_type' => $error_type, 'response' => $decoded, 'url' => $url ) ) );
			throw new LiveOnCrypto_WC_API_Exception( 'LiveOnCrypto API request failed.', $error_type, $status_code, $decoded );
		}

		LiveOnCrypto_WC_Logger::info( 'LiveOnCrypto API request completed.', $this->redact_context( array( 'status_code' => $status_code, 'url' => $url ) ) );

		return $decoded;
	}

	private function classify_error( int $status_code ): string {
		if ( 401 === $status_code || 403 === $status_code ) {
			return 'authentication_error';
		}
		if ( 400 === $status_code || 422 === $status_code ) {
			return 'validation_error';
		}
		if ( $status_code >= 500 ) {
			return 'server_error';
		}
		return 'api_error';
	}

	/**
	 * @param array<string,mixed> $context Context to redact.
	 * @return array<string,mixed>
	 */
	private function redact_context( array $context ): array {
		$redacted = array();
		foreach ( $context as $key => $value ) {
			$normalized_key = strtolower( (string) $key );
			if ( in_array( $normalized_key, array( 'authorization', 'secret_key', 'api_key', 'token' ), true ) ) {
				$redacted[ $key ] = '[redacted]';
			} elseif ( is_array( $value ) ) {
				$redacted[ $key ] = $this->redact_context( $value );
			} elseif ( is_string( $value ) ) {
				$redacted[ $key ] = preg_replace( '/\b(sk_(?:live|test)_[A-Za-z0-9_\-]+|whsec_[A-Za-z0-9_\-]+)\b/', '[redacted]', $value );
			} else {
				$redacted[ $key ] = $value;
			}
		}

		return $redacted;
	}
}
