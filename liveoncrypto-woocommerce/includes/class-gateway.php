<?php
/**
 * WooCommerce payment gateway.
 *
 * @package LiveOnCrypto_WC
 */

defined( 'ABSPATH' ) || exit;

class LiveOnCrypto_WC_Gateway extends WC_Payment_Gateway {
	private const BASE_API_URL = 'https://app.liveoncrypto.online';
	private const WIDGET_SCRIPT_URL = 'https://app.liveoncrypto.online/widget.js';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'liveoncrypto';
		$this->icon               = '';
		$this->has_fields         = false;
		$this->method_title       = __( 'LiveOnCrypto', 'liveoncrypto-woocommerce' );
		$this->method_description = __( 'Accept cryptocurrency payments with LiveOnCrypto. Configure credentials before enabling.', 'liveoncrypto-woocommerce' );
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title             = $this->get_option( 'title', __( 'Pay with Crypto', 'liveoncrypto-woocommerce' ) );
		$this->description       = $this->get_option( 'description', __( 'Pay securely with cryptocurrency through LiveOnCrypto.', 'liveoncrypto-woocommerce' ) );
		$this->enabled           = $this->get_option( 'enabled', 'no' );
		$this->order_button_text = $this->get_option( 'button_label', __( 'Pay with Crypto', 'liveoncrypto-woocommerce' ) );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'render_payment_widget' ) );
		add_filter( 'woocommerce_valid_order_statuses_for_payment', array( $this, 'allow_on_hold_order_payment' ), 10, 2 );
	}

	/**
	 * Define gateway settings fields.
	 */
	public function init_form_fields(): void {
		$this->form_fields = array(
			'connection_section' => array(
				'title' => __( 'Connection', 'liveoncrypto-woocommerce' ),
				'type'  => 'title',
			),
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'liveoncrypto-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable LiveOnCrypto payments', 'liveoncrypto-woocommerce' ),
				'default' => 'no',
			),
			'title' => array(
				'title'       => __( 'Checkout title', 'liveoncrypto-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Payment method title shown during checkout.', 'liveoncrypto-woocommerce' ),
				'default'     => __( 'Pay with Crypto', 'liveoncrypto-woocommerce' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Checkout description', 'liveoncrypto-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description shown during checkout.', 'liveoncrypto-woocommerce' ),
				'default'     => __( 'Pay securely with cryptocurrency through LiveOnCrypto.', 'liveoncrypto-woocommerce' ),
				'desc_tip'    => true,
			),
			'public_key' => array(
				'title'       => __( 'Public client key', 'liveoncrypto-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Your LiveOnCrypto publishable key. It must begin with pk_live_ or pk_test_.', 'liveoncrypto-woocommerce' ),
				'default'     => '',
			),
			'secret_key' => array(
				'title'       => __( 'Secret client key', 'liveoncrypto-woocommerce' ),
				'type'        => 'password',
				'description' => __( 'Your LiveOnCrypto secret key. It must begin with sk_live_ or sk_test_. Never expose this key to customers.', 'liveoncrypto-woocommerce' ),
				'default'     => '',
			),
			'webhook_section' => array(
				'title'       => __( 'Webhook', 'liveoncrypto-woocommerce' ),
				'type'        => 'title',
				'description' => __( 'Copy the webhook endpoint URL into the LiveOnCrypto dashboard webhook settings, then paste the generated signing secret below.', 'liveoncrypto-woocommerce' ),
			),
			'webhook_secret' => array(
				'title'       => __( 'Webhook signing secret', 'liveoncrypto-woocommerce' ),
				'type'        => 'password',
				'description' => __( 'Used to verify LiveOnCrypto webhook requests. It must begin with whsec_.', 'liveoncrypto-woocommerce' ),
				'default'     => '',
			),
			'webhook_url' => array(
				'title'       => __( 'Webhook endpoint URL', 'liveoncrypto-woocommerce' ),
				'type'        => 'webhook_url',
				'description' => __( 'Configure this URL in LiveOnCrypto so signed payment events reach your store.', 'liveoncrypto-woocommerce' ),
			),
			'last_webhook_received' => array(
				'title' => __( 'Last received webhook', 'liveoncrypto-woocommerce' ),
				'type'  => 'last_webhook',
			),
			'appearance_section' => array(
				'title' => __( 'Checkout appearance', 'liveoncrypto-woocommerce' ),
				'type'  => 'title',
			),
			'order_behavior_section' => array(
				'title' => __( 'Order behavior', 'liveoncrypto-woocommerce' ),
				'type'  => 'title',
			),
			'debug_logging' => array(
				'title'   => __( 'Debug logging', 'liveoncrypto-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable debug logging', 'liveoncrypto-woocommerce' ),
				'default' => 'no',
			),
			'pending_status' => array(
				'title'       => __( 'Pending status behavior', 'liveoncrypto-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Order status to use while waiting for LiveOnCrypto payment confirmation.', 'liveoncrypto-woocommerce' ),
				'default'     => 'on-hold',
				'options'     => array(
					'pending' => __( 'Pending payment', 'liveoncrypto-woocommerce' ),
					'on-hold' => __( 'On hold', 'liveoncrypto-woocommerce' ),
				),
			),
			'button_label' => array(
				'title'       => __( 'Button label', 'liveoncrypto-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Text shown on the checkout place-order button when LiveOnCrypto is selected.', 'liveoncrypto-woocommerce' ),
				'default'     => __( 'Pay with Crypto', 'liveoncrypto-woocommerce' ),
			),
			'connection_test' => array(
				'title' => __( 'Connection test', 'liveoncrypto-woocommerce' ),
				'type'  => 'connection_test',
			),
			'uninstall_section' => array(
				'title'       => __( 'Uninstall behavior', 'liveoncrypto-woocommerce' ),
				'type'        => 'title',
				'description' => __( 'Order payment records are always preserved. Enable the cleanup option only if plugin settings should be deleted when the plugin is uninstalled.', 'liveoncrypto-woocommerce' ),
			),
			'delete_settings_on_uninstall' => array(
				'title'   => __( 'Settings cleanup', 'liveoncrypto-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Delete LiveOnCrypto plugin settings on uninstall', 'liveoncrypto-woocommerce' ),
				'default' => 'no',
			),
			'expiration_behavior' => array(
				'title'       => __( 'Expired payment behavior', 'liveoncrypto-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Order status behavior when LiveOnCrypto reports an expired payment.', 'liveoncrypto-woocommerce' ),
				'default'     => 'on-hold',
				'options'     => array(
					'on-hold' => __( 'Put order on hold', 'liveoncrypto-woocommerce' ),
					'failed'  => __( 'Mark order failed', 'liveoncrypto-woocommerce' ),
					'cancel'  => __( 'Cancel order', 'liveoncrypto-woocommerce' ),
				),
			),
		);
	}


	/**
	 * Allow LiveOnCrypto on-hold orders to render the order-pay page.
	 *
	 * WooCommerce does not consider on-hold orders payable by default, but this
	 * gateway can intentionally place newly-created orders on hold while the
	 * shopper completes the offsite crypto payment flow.
	 *
	 * @param array<int,string> $statuses Valid order statuses for payment.
	 * @param WC_Order|null     $order    WooCommerce order being checked.
	 * @return array<int,string>
	 */
	public function allow_on_hold_order_payment( array $statuses, $order = null ): array {
		if ( $order instanceof WC_Order && 'liveoncrypto' === $order->get_payment_method() && ! in_array( 'on-hold', $statuses, true ) ) {
			$statuses[] = 'on-hold';
		}

		return $statuses;
	}

	/**
	 * Render the public REST webhook URL in gateway settings.
	 *
	 * @param string $key Field key.
	 * @param array<string,mixed> $data Field configuration.
	 */
	public function generate_webhook_url_html( string $key, array $data ): string {
		$field_key   = $this->get_field_key( $key );
		$defaults    = array(
			'title'       => '',
			'description' => '',
			'desc_tip'    => false,
		);
		$data        = wp_parse_args( $data, $defaults );
		$webhook_url = liveoncrypto_wc_webhook_endpoint_url();

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo esc_html( $data['title'] ); ?></label>
				<?php echo wp_kses_post( $this->get_tooltip_html( $data ) ); ?>
			</th>
			<td class="forminp">
				<input class="input-text regular-input" type="url" id="<?php echo esc_attr( $field_key ); ?>" value="<?php echo esc_url( $webhook_url ); ?>" readonly="readonly" onclick="this.select();" />
				<?php echo wp_kses_post( $this->get_description_html( $data ) ); ?>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	public function generate_last_webhook_html( string $key, array $data ): string {
		$value = (string) get_option( 'liveoncrypto_wc_last_webhook_received', '' );
		return '<tr valign="top"><th scope="row" class="titledesc">' . esc_html( $data['title'] ?? '' ) . '</th><td class="forminp">' . esc_html( '' !== $value ? $value : __( 'No webhooks received yet.', 'liveoncrypto-woocommerce' ) ) . '</td></tr>';
	}

	public function generate_connection_test_html( string $key, array $data ): string {
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=liveoncrypto_connection_test' ), 'liveoncrypto_connection_test' );
		return '<tr valign="top"><th scope="row" class="titledesc">' . esc_html( $data['title'] ?? '' ) . '</th><td class="forminp"><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Test LiveOnCrypto connection', 'liveoncrypto-woocommerce' ) . '</a></td></tr>';
	}

	public function generate_password_html( $key, $data ): string {
		$saved = (string) $this->get_option( $key, '' );
		if ( '' !== $saved ) {
			$data['description'] = sprintf( '%s %s', $data['description'] ?? '', sprintf( __( 'Saved value: %s. Enter a new value to replace it.', 'liveoncrypto-woocommerce' ), liveoncrypto_wc_redact_secret( $saved ) ) );
		}
		return parent::generate_password_html( $key, $data );
	}

	/**
	 * Validate customer-facing text fields.
	 */
	public function validate_title_field( string $key, $value ): string {
		$value = sanitize_text_field( wp_unslash( $value ) );
		return '' !== $value ? $value : __( 'Pay with Crypto', 'liveoncrypto-woocommerce' );
	}

	/**
	 * Validate checkout description.
	 */
	public function validate_description_field( string $key, $value ): string {
		return sanitize_textarea_field( wp_unslash( $value ) );
	}

	/**
	 * Validate button label.
	 */
	public function validate_button_label_field( string $key, $value ): string {
		$value = sanitize_text_field( wp_unslash( $value ) );
		return '' !== $value ? $value : __( 'Pay with Crypto', 'liveoncrypto-woocommerce' );
	}

	/**
	 * Validate publishable keys.
	 */
	public function validate_public_key_field( string $key, $value ): string {
		$value = trim( wc_clean( wp_unslash( $value ) ) );
		if ( '' !== $value && ! preg_match( '/^pk_(live|test)_[A-Za-z0-9_\-]{16,120}$/', $value ) ) {
			WC_Admin_Settings::add_error( __( 'LiveOnCrypto public client key must begin with pk_live_ or pk_test_ and include a valid key body.', 'liveoncrypto-woocommerce' ) );
			return $this->get_option( $key, '' );
		}
		return $value;
	}

	/**
	 * Validate secret keys.
	 */
	public function validate_secret_key_field( string $key, $value ): string {
		$value = trim( wc_clean( wp_unslash( $value ) ) );
		if ( '' === $value ) {
			return $this->get_option( $key, '' );
		}
		if ( '' !== $value && ! preg_match( '/^sk_(live|test)_[A-Za-z0-9_\-]{16,120}$/', $value ) ) {
			WC_Admin_Settings::add_error( __( 'LiveOnCrypto secret client key must begin with sk_live_ or sk_test_ and include a valid key body.', 'liveoncrypto-woocommerce' ) );
			return $this->get_option( $key, '' );
		}
		return $value;
	}

	/**
	 * Validate webhook signing secret.
	 */
	public function validate_webhook_secret_field( string $key, $value ): string {
		$value = trim( wc_clean( wp_unslash( $value ) ) );
		if ( '' === $value ) {
			return $this->get_option( $key, '' );
		}
		if ( '' !== $value && ! str_starts_with( $value, 'whsec_' ) ) {
			WC_Admin_Settings::add_error( __( 'LiveOnCrypto webhook signing secret must begin with whsec_.', 'liveoncrypto-woocommerce' ) );
			return $this->get_option( $key, '' );
		}
		return $value;
	}

	/**
	 * Validate pending status option.
	 */
	public function validate_pending_status_field( string $key, $value ): string {
		$value = sanitize_key( wp_unslash( $value ) );
		return in_array( $value, array( 'pending', 'on-hold' ), true ) ? $value : 'on-hold';
	}

	/**
	 * Validate expired payment behavior option.
	 */
	public function validate_expiration_behavior_field( string $key, $value ): string {
		$value = sanitize_key( wp_unslash( $value ) );
		return in_array( $value, array( 'on-hold', 'failed', 'cancel' ), true ) ? $value : 'on-hold';
	}

	/**
	 * Process checkout payment submission.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array<string,string>
	 */
	public function process_payment( $order_id ): array {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wc_add_notice( __( 'Unable to create LiveOnCrypto payment for this order.', 'liveoncrypto-woocommerce' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$total = (float) $order->get_total();
		if ( $total <= 0 ) {
			wc_add_notice( __( 'LiveOnCrypto payments require an order total greater than zero.', 'liveoncrypto-woocommerce' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$billing_email = $order->get_billing_email();
		if ( empty( $billing_email ) || ! is_email( $billing_email ) ) {
			wc_add_notice( __( 'A valid billing email address is required for LiveOnCrypto payments.', 'liveoncrypto-woocommerce' ), 'error' );
			return array( 'result' => 'failure' );
		}

		LiveOnCrypto_WC_Logger::info( 'Payment method selected.', array( 'order_id' => $order->get_id(), 'customer_email' => liveoncrypto_wc_mask_email( $billing_email ) ) );

		$order_ref = $this->build_order_reference( $order );
		LiveOnCrypto_WC_Logger::info( 'Order reference created.', array( 'order_id' => $order->get_id(), 'order_ref' => $order_ref ) );

		$order->update_meta_data( '_liveoncrypto_order_ref', $order_ref );
		$order->update_meta_data( '_liveoncrypto_status', 'pending' );
		$order->update_meta_data( '_liveoncrypto_fiat_amount', wc_format_decimal( $order->get_total(), 2 ) );
		$order->update_meta_data( '_liveoncrypto_fiat_currency', $order->get_currency() );

		$order->update_status( $this->get_pending_order_status() );
		$order->add_order_note( __( 'Awaiting LiveOnCrypto payment.', 'liveoncrypto-woocommerce' ), false, false );
		$order->save();

		$this->create_server_side_payment_intent( $order, $order_ref );

		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true ),
		);
	}

	/**
	 * Render the LiveOnCrypto payment widget on the WooCommerce order-pay page.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function render_payment_widget( $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			echo '<p>' . esc_html__( 'Unable to load LiveOnCrypto payment details for this order.', 'liveoncrypto-woocommerce' ) . '</p>';
			return;
		}

		$widget_script_url = self::WIDGET_SCRIPT_URL;
		$dependencies      = array();
		if ( '' !== $widget_script_url ) {
			wp_enqueue_script( 'liveoncrypto_widget', $widget_script_url, array(), null, true );
			$dependencies[] = 'liveoncrypto_widget';
		}

		wp_enqueue_style( 'liveoncrypto_wc_checkout', LIVEONCRYPTO_WC_URL . 'assets/css/checkout.css', array(), LIVEONCRYPTO_WC_VERSION );
		wp_enqueue_script( 'liveoncrypto_wc_checkout_widget', LIVEONCRYPTO_WC_URL . 'assets/js/checkout-widget.js', $dependencies, LIVEONCRYPTO_WC_VERSION, true );
		wp_localize_script(
			'liveoncrypto_wc_checkout_widget',
			'LiveOnCryptoWCStrings',
			array(
				'paymentCreated'     => __( 'Payment session created. Follow the LiveOnCrypto instructions to complete payment.', 'liveoncrypto-woocommerce' ),
				'paymentDetected'    => __( 'Payment detected. Waiting for network confirmation.', 'liveoncrypto-woocommerce' ),
				'paymentConfirming'  => __( 'Payment is confirming on the network.', 'liveoncrypto-woocommerce' ),
				'paymentPaid'        => __( 'Payment received. We are finalizing your order.', 'liveoncrypto-woocommerce' ),
				'paymentExpired'     => __( 'This payment session has expired. Refresh the page to start again.', 'liveoncrypto-woocommerce' ),
				'paymentError'       => __( 'LiveOnCrypto reported a payment error. Please try again or contact the store.', 'liveoncrypto-woocommerce' ),
				'initFailed'         => __( 'The LiveOnCrypto payment widget could not be initialized. Please refresh this page.', 'liveoncrypto-woocommerce' ),
				'detailsUnavailable' => __( 'LiveOnCrypto payment details are unavailable. Please contact the store.', 'liveoncrypto-woocommerce' ),
				'widgetNotLoaded'    => __( 'The LiveOnCrypto payment widget did not load. Refresh this page or contact the store for assistance.', 'liveoncrypto-woocommerce' ),
				'widgetReady'        => __( 'LiveOnCrypto is ready. Click Pay with Crypto to continue.', 'liveoncrypto-woocommerce' ),
				'openingWidget'      => __( 'Opening the LiveOnCrypto payment widget…', 'liveoncrypto-woocommerce' ),
			)
		);

		$payment_id = (string) $order->get_meta( '_liveoncrypto_payment_id', true );
		$widget_config = array(
			'clientKey'     => (string) $this->get_option( 'public_key', '' ),
			'amountFiat'    => wc_format_decimal( $order->get_total(), 2 ),
			'fiatCurrency'  => $order->get_currency(),
			'itemName'      => sprintf( /* translators: %s: WooCommerce order number. */ __( 'WooCommerce order #%s', 'liveoncrypto-woocommerce' ), $order->get_order_number() ),
			'orderRef'      => (string) $order->get_meta( '_liveoncrypto_order_ref', true ),
			'customerEmail' => $order->get_billing_email(),
		);
		if ( '' !== $payment_id ) {
			$widget_config['paymentId'] = $payment_id;
		}

		$return_url = $order->get_view_order_url();

		LiveOnCrypto_WC_Logger::info( 'Payment page rendered.', array( 'order_id' => $order->get_id() ) );

		include LIVEONCRYPTO_WC_PATH . 'templates/checkout-widget.php';
	}


	/**
	 * Create a LiveOnCrypto payment intent after the WooCommerce order exists.
	 *
	 * Failures are intentionally recoverable: the order remains pending/on-hold and
	 * the public checkout widget can still render on the order-pay page.
	 */
	private function create_server_side_payment_intent( WC_Order $order, string $order_ref ): void {
		$client = new LiveOnCrypto_WC_API_Client(
			self::BASE_API_URL,
			(string) $this->get_option( 'secret_key', '' )
		);

		if ( ! $client->has_credentials() ) {
			LiveOnCrypto_WC_Logger::info(
				'LiveOnCrypto payment intent not created because API settings are incomplete.',
				array(
					'order_id' => $order->get_id(),
					'order_ref' => $order_ref,
				)
			);
			return;
		}

		$payload = array(
			'amountFiat'    => wc_format_decimal( $order->get_total(), 2 ),
			'fiatCurrency'  => $order->get_currency(),
			'itemName'      => sprintf( /* translators: %s: WooCommerce order number. */ __( 'WooCommerce order #%s', 'liveoncrypto-woocommerce' ), $order->get_order_number() ),
			'orderRef'      => $order_ref,
			'customerEmail' => $order->get_billing_email(),
		);

		try {
			LiveOnCrypto_WC_Logger::info( 'Payment intent creation attempted.', array( 'order_id' => $order->get_id(), 'order_ref' => $order_ref, 'customer_email' => liveoncrypto_wc_mask_email( $order->get_billing_email() ) ) );
			$response = $client->create_payment_intent( $payload, $this->build_idempotency_key( $order ) );
			$this->store_payment_intent_response( $order, $response );
			LiveOnCrypto_WC_Logger::info( 'Payment intent created.', array( 'order_id' => $order->get_id(), 'order_ref' => $order_ref ) );
			$order->add_order_note( __( 'LiveOnCrypto payment intent created.', 'liveoncrypto-woocommerce' ), false, false );
			$order->save();
		} catch ( LiveOnCrypto_WC_API_Exception $exception ) {
			$order->add_order_note( __( 'LiveOnCrypto payment intent could not be created automatically. Customer can retry from the payment page while the order remains unpaid.', 'liveoncrypto-woocommerce' ), false, false );
			$order->save();

			wc_add_notice( __( 'We could not prepare your crypto payment automatically. Please continue to the payment page and try again, or contact the store if the problem persists.', 'liveoncrypto-woocommerce' ), 'notice' );

			LiveOnCrypto_WC_Logger::error(
				'Payment intent failed.',
				array(
					'order_id'    => $order->get_id(),
					'order_ref'   => $order_ref,
					'error_type'  => $exception->get_error_type(),
					'status_code' => $exception->get_status_code(),
					'details'     => $exception->get_details(),
				)
			);
		}
	}

	/**
	 * Store known payment intent response fields on the order when present.
	 *
	 * @param array<string,mixed> $response API response.
	 */
	private function store_payment_intent_response( WC_Order $order, array $response ): void {
		$payment_id = $this->first_response_value( $response, array( 'paymentId', 'payment_id', 'id', 'uid' ) );
		if ( '' !== $payment_id ) {
			$order->update_meta_data( '_liveoncrypto_payment_id', $payment_id );
		}

		$order_number = $this->first_response_value( $response, array( 'orderNumber', 'order_number' ) );
		if ( '' !== $order_number ) {
			$order->update_meta_data( '_liveoncrypto_order_number', $order_number );
		}

		$status = $this->first_response_value( $response, array( 'status', 'paymentStatus', 'payment_status' ) );
		if ( '' !== $status ) {
			$order->update_meta_data( '_liveoncrypto_status', sanitize_key( $status ) );
		}
	}

	/**
	 * @param array<string,mixed> $response API response.
	 * @param array<int,string>   $keys Candidate keys.
	 */
	private function first_response_value( array $response, array $keys ): string {
		foreach ( $keys as $key ) {
			if ( isset( $response[ $key ] ) && is_scalar( $response[ $key ] ) ) {
				return wc_clean( (string) $response[ $key ] );
			}
		}

		foreach ( array( 'data', 'payment', 'paymentIntent', 'payment_intent' ) as $container_key ) {
			if ( isset( $response[ $container_key ] ) && is_array( $response[ $container_key ] ) ) {
				$value = $this->first_response_value( $response[ $container_key ], $keys );
				if ( '' !== $value ) {
					return $value;
			}
			}
		}

		return '';
	}

	/**
	 * Build a stable idempotency key from WooCommerce order identifiers.
	 */
	private function build_idempotency_key( WC_Order $order ): string {
		return 'wc_' . $order->get_id() . '_' . $order->get_order_key();
	}

	/**
	 * Build deterministic LiveOnCrypto order reference.
	 */
	private function build_order_reference( WC_Order $order ): string {
		$hash = substr( hash_hmac( 'sha256', $order->get_order_key(), (string) $order->get_id() ), 0, 12 );
		return sprintf( 'wc_%d_%s', $order->get_id(), $hash );
	}

	/**
	 * Get the configured WooCommerce order status for unpaid payments.
	 */
	private function get_pending_order_status(): string {
		$status = $this->get_option( 'pending_status', 'on-hold' );
		return in_array( $status, array( 'pending', 'on-hold' ), true ) ? $status : 'on-hold';
	}

}
