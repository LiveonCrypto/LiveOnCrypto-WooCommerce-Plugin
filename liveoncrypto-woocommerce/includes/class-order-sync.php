<?php
/**
 * Order lookup and webhook state synchronization.
 *
 * @package LiveOnCrypto_WC
 */

defined( 'ABSPATH' ) || exit;

class LiveOnCrypto_WC_Order_Sync {
	private const WEBHOOK_EVENTS_META_KEY = '_liveoncrypto_webhook_events';

	public function init(): void {
		add_action( 'woocommerce_order_status_changed', array( $this, 'record_status_change' ), 10, 4 );
	}

	public function record_status_change( int $order_id, string $old_status, string $new_status, WC_Order $order ): void {
		if ( 'liveoncrypto' !== $order->get_payment_method() ) {
			return;
		}

		$order->update_meta_data( liveoncrypto_wc_order_meta_key( 'last_status_sync' ), time() );
		$order->save();
	}

	public function find_order_by_payment_id( string $payment_id ): ?WC_Order {
		return $this->find_order_by_meta( '_liveoncrypto_payment_id', $payment_id );
	}

	public function find_order_by_order_ref( string $order_ref ): ?WC_Order {
		$order = $this->find_order_by_meta( '_liveoncrypto_order_ref', $order_ref );
		if ( $order ) {
			return $order;
		}

		if ( preg_match( '/^wc_(\d+)_/', $order_ref, $matches ) ) {
			$order = wc_get_order( absint( $matches[1] ) );
			return $order instanceof WC_Order ? $order : null;
		}

		return null;
	}

	public function find_order( string $payment_id, string $order_ref ): ?WC_Order {
		if ( '' !== $payment_id ) {
			$order = $this->find_order_by_payment_id( $payment_id );
			if ( $order ) {
				return $order;
			}
		}

		return '' !== $order_ref ? $this->find_order_by_order_ref( $order_ref ) : null;
	}

	/** @param array<string,string> $payload */
	public function process_webhook_event( WC_Order $order, array $payload ): array {
		$event_key = liveoncrypto_wc_safe_event_key( $payload['event'], $payload['paymentId'], $payload['txHash'] );
		if ( $this->event_was_processed( $order, $event_key ) ) {
			return array( 'duplicate' => true );
		}

		$order->update_meta_data( '_liveoncrypto_last_webhook_event', $payload['event'] );
		$this->store_payment_meta( $order, $payload );

		if ( 'liveoncrypto' !== $order->get_payment_method() ) {
			$order->add_order_note( __( 'LiveOnCrypto webhook ignored because the order payment method is not LiveOnCrypto.', 'liveoncrypto-woocommerce' ), false, false );
			$this->mark_event_processed( $order, $event_key );
			$order->save();
			return array( 'processed' => true, 'paid' => false );
		}

		switch ( $payload['event'] ) {
			case 'payment.paid':
				$this->handle_paid_event( $order, $payload );
				break;
			case 'payment.detected':
				$this->hold_order( $order, __( 'LiveOnCrypto payment detected. Waiting for network confirmation.', 'liveoncrypto-woocommerce' ) );
				break;
			case 'payment.confirming':
				$this->hold_order( $order, __( 'LiveOnCrypto payment is confirming on the network.', 'liveoncrypto-woocommerce' ) );
				break;
			case 'payment.expired':
				$this->handle_expired_event( $order );
				break;
			case 'payment.underpaid':
			case 'payment.review':
				$this->hold_order( $order, __( 'LiveOnCrypto payment requires manual review before fulfillment.', 'liveoncrypto-woocommerce' ) );
				break;
			case 'payment.overpaid':
				$this->handle_overpaid_event( $order, $payload );
				break;
			default:
				$order->add_order_note( __( 'LiveOnCrypto payment status updated by webhook.', 'liveoncrypto-woocommerce' ), false, false );
				break;
		}

		$this->mark_event_processed( $order, $event_key );
		$order->save();

		return array( 'processed' => true, 'paid' => $order->is_paid() );
	}

	private function find_order_by_meta( string $meta_key, string $meta_value ): ?WC_Order {
		$meta_value = trim( $meta_value );
		if ( '' === $meta_value ) {
			return null;
		}

		$orders = wc_get_orders(
			array(
				'limit'      => 1,
				'return'     => 'objects',
				'meta_key'   => $meta_key,
				'meta_value' => $meta_value,
			)
		);

		return isset( $orders[0] ) && $orders[0] instanceof WC_Order ? $orders[0] : null;
	}

	/** @param array<string,string> $payload */
	private function store_payment_meta( WC_Order $order, array $payload ): void {
		$meta_map = array(
			'_liveoncrypto_payment_id'    => 'paymentId',
			'_liveoncrypto_order_number'  => 'orderNumber',
			'_liveoncrypto_status'        => 'status',
			'_liveoncrypto_network'       => 'network',
			'_liveoncrypto_asset'         => 'asset',
			'_liveoncrypto_amount_paid'   => 'amountPaid',
			'_liveoncrypto_fiat_amount'   => 'fiatAmount',
			'_liveoncrypto_fiat_currency' => 'fiatCurrency',
			'_liveoncrypto_tx_hash'       => 'txHash',
			'_liveoncrypto_paid_at'       => 'paidAt',
		);

		foreach ( $meta_map as $meta_key => $payload_key ) {
			if ( isset( $payload[ $payload_key ] ) && '' !== $payload[ $payload_key ] ) {
				$order->update_meta_data( $meta_key, $payload[ $payload_key ] );
			}
		}
	}

	/** @param array<string,string> $payload */
	private function handle_paid_event( WC_Order $order, array $payload ): void {
		if ( 'paid' !== $payload['status'] ) {
			$this->hold_order( $order, __( 'LiveOnCrypto payment.paid webhook did not include paid status. Manual review required.', 'liveoncrypto-woocommerce' ) );
			return;
		}

		if ( $order->is_paid() ) {
			LiveOnCrypto_WC_Logger::info( 'Paid webhook ignored for an already paid order.', array( 'order_id' => $order->get_id(), 'payment_id' => $payload['paymentId'] ) );
			return;
		}

		if ( ! $this->amount_matches( $payload['fiatAmount'], $order->get_total() ) ) {
			LiveOnCrypto_WC_Logger::error( 'Amount mismatch.', array( 'order_id' => $order->get_id(), 'payment_id' => $payload['paymentId'], 'expected' => $order->get_total(), 'received' => $payload['fiatAmount'] ) );
			$this->hold_order( $order, __( 'LiveOnCrypto fiat amount mismatch. Manual review required before marking paid.', 'liveoncrypto-woocommerce' ) );
			return;
		}

		if ( strtoupper( $payload['fiatCurrency'] ) !== strtoupper( $order->get_currency() ) ) {
			LiveOnCrypto_WC_Logger::error( 'Currency mismatch.', array( 'order_id' => $order->get_id(), 'payment_id' => $payload['paymentId'], 'expected' => $order->get_currency(), 'received' => $payload['fiatCurrency'] ) );
			$this->hold_order( $order, __( 'LiveOnCrypto fiat currency mismatch. Manual review required before marking paid.', 'liveoncrypto-woocommerce' ) );
			return;
		}

		$tx_hash = '' !== $payload['txHash'] ? $payload['txHash'] : $payload['paymentId'];
		$order->payment_complete( $tx_hash );
		LiveOnCrypto_WC_Logger::info( 'Order marked paid.', array( 'order_id' => $order->get_id(), 'payment_id' => $payload['paymentId'] ) );
		$order->add_order_note( $this->paid_note( $payload ), false, false );
	}

	/** @param array<string,string> $payload */
	private function handle_overpaid_event( WC_Order $order, array $payload ): void {
		if ( $this->amount_due_is_satisfied( $payload['fiatAmount'], $order->get_total() ) && strtoupper( $payload['fiatCurrency'] ) === strtoupper( $order->get_currency() ) ) {
			$order->add_order_note( __( 'LiveOnCrypto reported an overpayment; fiat amount due is satisfied.', 'liveoncrypto-woocommerce' ), false, false );
			if ( ! $order->is_paid() ) {
				$order->payment_complete( '' !== $payload['txHash'] ? $payload['txHash'] : $payload['paymentId'] );
			}
			return;
		}

		$this->hold_order( $order, __( 'LiveOnCrypto overpayment requires manual review because the expected fiat amount or currency was not satisfied.', 'liveoncrypto-woocommerce' ) );
	}

	private function handle_expired_event( WC_Order $order ): void {
		$behavior = sanitize_key( (string) liveoncrypto_wc_get_option( 'expiration_behavior', 'on-hold' ) );
		if ( 'cancel' === $behavior || 'cancelled' === $behavior || 'canceled' === $behavior ) {
			$order->update_status( 'cancelled', __( 'LiveOnCrypto payment expired.', 'liveoncrypto-woocommerce' ) );
			return;
		}

		if ( 'failed' === $behavior || 'fail' === $behavior ) {
			$order->update_status( 'failed', __( 'LiveOnCrypto payment expired.', 'liveoncrypto-woocommerce' ) );
			return;
		}

		$this->hold_order( $order, __( 'LiveOnCrypto payment expired. Manual review may be required.', 'liveoncrypto-woocommerce' ) );
	}

	private function hold_order( WC_Order $order, string $note ): void {
		if ( ! $order->has_status( 'on-hold' ) ) {
			LiveOnCrypto_WC_Logger::info( 'Order placed on hold for review.', array( 'order_id' => $order->get_id() ) );
			$order->update_status( 'on-hold', $note );
			return;
		}

		$order->add_order_note( $note, false, false );
	}

	private function amount_matches( string $webhook_amount, string $order_total ): bool {
		return wc_format_decimal( $webhook_amount, 2 ) === wc_format_decimal( $order_total, 2 );
	}

	private function amount_due_is_satisfied( string $webhook_amount, string $order_total ): bool {
		return (float) wc_format_decimal( $webhook_amount, 2 ) >= (float) wc_format_decimal( $order_total, 2 );
	}

	/** @param array<string,string> $payload */
	private function paid_note( array $payload ): string {
		return sprintf(
			/* translators: 1: payment ID, 2: transaction hash, 3: network, 4: asset, 5: paid timestamp */
			__( "LiveOnCrypto payment received.\nPayment ID: %1\$s\nTransaction hash: %2\$s\nNetwork: %3\$s\nAsset: %4\$s\nPaid timestamp: %5\$s", 'liveoncrypto-woocommerce' ),
			$payload['paymentId'],
			'' !== $payload['txHash'] ? $payload['txHash'] : __( 'Unavailable', 'liveoncrypto-woocommerce' ),
			'' !== $payload['network'] ? $payload['network'] : __( 'Unavailable', 'liveoncrypto-woocommerce' ),
			'' !== $payload['asset'] ? $payload['asset'] : __( 'Unavailable', 'liveoncrypto-woocommerce' ),
			'' !== $payload['paidAt'] ? $payload['paidAt'] : current_time( 'mysql' )
		);
	}

	private function event_key( string $event, string $payment_id, string $tx_hash ): string {
		return $event . ':' . $payment_id . ':' . $tx_hash;
	}

	private function event_was_processed( WC_Order $order, string $event_key ): bool {
		$processed = $order->get_meta( self::WEBHOOK_EVENTS_META_KEY, true );
		return is_array( $processed ) && in_array( $event_key, $processed, true );
	}

	private function mark_event_processed( WC_Order $order, string $event_key ): void {
		$processed = $order->get_meta( self::WEBHOOK_EVENTS_META_KEY, true );
		$processed = is_array( $processed ) ? array_values( array_filter( $processed, 'is_string' ) ) : array();
		$processed[] = $event_key;
		$order->update_meta_data( self::WEBHOOK_EVENTS_META_KEY, array_slice( array_values( array_unique( $processed ) ), -100 ) );
	}
}
