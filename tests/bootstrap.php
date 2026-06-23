<?php
/**
 * Lightweight WordPress/WooCommerce test bootstrap for standalone plugin tests.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'LIVEONCRYPTO_WC_PATH', dirname( __DIR__ ) . '/liveoncrypto-woocommerce/' );
define( 'LIVEONCRYPTO_WC_URL', 'https://example.test/wp-content/plugins/liveoncrypto-woocommerce/' );
define( 'LIVEONCRYPTO_WC_VERSION', '0.1.0-test' );

$GLOBALS['loc_options'] = array(
	'woocommerce_liveoncrypto_settings' => array(
		'enabled'           => 'yes',
		'public_key'        => 'pk_test_public_1234567890123456',
		'secret_key'        => 'sk_test_secret_1234567890123456',
		'webhook_secret'    => 'whsec_test_secret',
		'pending_status'    => 'on-hold',
	),
);
$GLOBALS['loc_orders'] = array();
$GLOBALS['loc_notices'] = array();
$GLOBALS['loc_admin_errors'] = array();
$GLOBALS['loc_scripts'] = array();
$GLOBALS['loc_styles'] = array();
$GLOBALS['loc_hpos_enabled'] = false;
$GLOBALS['loc_actions'] = array();
$GLOBALS['loc_filters'] = array();

function __( $text, $domain = null ) { return $text; }
function esc_html__( $text, $domain = null ) { return $text; }
function esc_attr__( $text, $domain = null ) { return $text; }
function esc_html_e( $text, $domain = null ) { echo htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr_e( $text, $domain = null ) { echo htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $url ) { return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' ); }
function esc_url_raw( $url ) { return trim( (string) $url ); }
function wp_kses_post( $html ) { return (string) $html; }
function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data, $flags ); }
function wp_unslash( $value ) { return $value; }
function wc_clean( $value ) { return is_array( $value ) ? array_map( 'wc_clean', $value ) : sanitize_text_field( $value ); }
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
function sanitize_text_field( $value ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $value ) ) ); }
function sanitize_email( $email ) { return filter_var( trim( (string) $email ), FILTER_SANITIZE_EMAIL ); }
function is_email( $email ) { return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ); }
function absint( $value ) { return abs( (int) $value ); }
function str_starts_with_polyfill() {}
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function untrailingslashit( $string ) { return rtrim( (string) $string, '/\\' ); }
function rest_url( $path = '' ) { return 'https://example.test/wp-json/' . ltrim( (string) $path, '/' ); }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' ); }
function wp_nonce_url( $url, $action = -1 ) { return $url . '&_wpnonce=test'; }
function current_time( $type ) { return '2026-06-17 00:00:00'; }
function status_header( $code ) {}
function wp_send_json_error( $data, $status = null ) { echo json_encode( array( 'success' => false, 'data' => $data ) ); }
function update_option( $key, $value ) { $GLOBALS['loc_options'][ $key ] = $value; return true; }
function get_option( $key, $default = false ) { return $GLOBALS['loc_options'][ $key ] ?? $default; }
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['loc_actions'][ $hook ][] = $callback; }
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['loc_filters'][ $hook ][] = $callback; }
function apply_filters( $hook, $value, ...$args ) { return $value; }
function register_rest_route( $namespace, $route, $args ) { $GLOBALS['loc_rest_route'] = compact( 'namespace', 'route', 'args' ); }
function rest_ensure_response( $data ) { return $data instanceof WP_REST_Response ? $data : new WP_REST_Response( $data, 200 ); }
function wc_format_decimal( $number, $dp = false ) { return number_format( (float) $number, (int) $dp, '.', '' ); }
function wc_add_notice( $message, $type = 'success' ) { $GLOBALS['loc_notices'][] = compact( 'message', 'type' ); }
function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) { $GLOBALS['loc_scripts'][ $handle ] = compact( 'src', 'deps', 'ver', 'in_footer' ); }
function wp_localize_script( $handle, $object_name, $l10n ) { $GLOBALS['loc_script_data'][ $handle ][ $object_name ] = $l10n; return true; }
function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false ) { $GLOBALS['loc_styles'][ $handle ] = compact( 'src', 'deps', 'ver' ); }
function wp_remote_request( $url, $args ) { return array( 'response' => array( 'code' => 200 ), 'body' => '{}' ); }
function wp_remote_retrieve_response_code( $response ) { return (int) ( $response['response']['code'] ?? 0 ); }
function wp_remote_retrieve_body( $response ) { return (string) ( $response['body'] ?? '' ); }
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
function wc_get_order( $order_id ) { return $GLOBALS['loc_orders'][ (int) $order_id ] ?? null; }
function wc_get_orders( $args ) {
	$matches = array();
	foreach ( $GLOBALS['loc_orders'] as $order ) {
		if ( isset( $args['meta_key'], $args['meta_value'] ) && $order->get_meta( $args['meta_key'], true ) === $args['meta_value'] ) {
			$matches[] = $order;
		}
	}
	return array_slice( $matches, 0, $args['limit'] ?? 1 );
}

class WP_Error { private string $code; public function __construct( $code = '' ) { $this->code = (string) $code; } public function get_error_code() { return $this->code; } }
class WP_REST_Response { public array $data; public int $status; public function __construct( $data = null, $status = 200 ) { $this->data = (array) $data; $this->status = (int) $status; } public function get_status() { return $this->status; } public function get_data() { return $this->data; } }
class WP_REST_Request { private string $body; private array $headers; public function __construct( $body = '', $headers = array() ) { $this->body = $body; $this->headers = array_change_key_case( $headers, CASE_LOWER ); } public function get_body() { return $this->body; } public function get_header( $key ) { return $this->headers[ strtolower( $key ) ] ?? ''; } }
class WC_Admin_Settings { public static function add_error( $message ) { $GLOBALS['loc_admin_errors'][] = $message; } }
class WC_Payment_Gateway { public string $id = ''; public array $settings = array(); public array $form_fields = array(); public function init_settings() { $this->settings = get_option( 'woocommerce_' . $this->id . '_settings', array() ); } public function get_option( $key, $empty_value = null ) { return $this->settings[ $key ] ?? $empty_value; } public function get_field_key( $key ) { return 'woocommerce_' . $this->id . '_' . $key; } public function get_tooltip_html( $data ) { return ''; } public function get_description_html( $data ) { return isset( $data['description'] ) ? '<p>' . esc_html( $data['description'] ) . '</p>' : ''; } public function generate_password_html( $key, $data ) { return '<input type="password" />'; } }
class WC_Order { private int $id; private string $status = 'pending'; private array $meta = array(); public array $notes = array(); private int $complete_count = 0; public function __construct( $id, private string $total = '10.00', private string $currency = 'USD', private string $email = 'buyer@example.test', private string $key = 'order_key' ) { $this->id = (int) $id; } public function get_id(){return $this->id;} public function get_total(){return $this->total;} public function get_currency(){return $this->currency;} public function get_billing_email(){return $this->email;} public function get_order_key(){return $this->key;} public function get_order_number(){return (string) $this->id;} public function get_payment_method(){return 'liveoncrypto';} public function update_meta_data($k,$v){$this->meta[$k]=$v;} public function get_meta($k,$single=true){return $this->meta[$k] ?? '';} public function update_status($s,$note=''){ $this->status=$s; if($note) $this->notes[]=$note; } public function has_status($s){return $this->status===$s;} public function get_status(){return $this->status;} public function add_order_note($note,$is_customer_note=false,$added_by_user=false){$this->notes[]=$note;} public function save(){} public function is_paid(){return $this->complete_count > 0;} public function payment_complete($tx=''){ $this->complete_count++; $this->status='processing'; $this->meta['_transaction_id']=$tx; } public function get_completion_count(){return $this->complete_count;} public function get_checkout_payment_url($on_checkout=false){return 'https://example.test/checkout/order-pay/' . $this->id;} public function get_view_order_url(){return 'https://example.test/my-account/view-order/' . $this->id;} public function get_formatted_order_total(){return '$' . $this->total;} }

require_once LIVEONCRYPTO_WC_PATH . 'includes/functions.php';
require_once LIVEONCRYPTO_WC_PATH . 'includes/class-logger.php';
require_once LIVEONCRYPTO_WC_PATH . 'includes/class-api-client.php';
require_once LIVEONCRYPTO_WC_PATH . 'includes/class-gateway.php';
require_once LIVEONCRYPTO_WC_PATH . 'includes/class-order-sync.php';
require_once LIVEONCRYPTO_WC_PATH . 'includes/class-webhook-controller.php';

function loc_assert_same( $expected, $actual, $message ) { if ( $expected !== $actual ) { fwrite( STDERR, "FAIL: $message\nExpected: " . var_export($expected,true) . "\nActual: " . var_export($actual,true) . "\n" ); exit(1); } }
function loc_assert_true( $actual, $message ) { loc_assert_same( true, (bool) $actual, $message ); }
function loc_assert_false( $actual, $message ) { loc_assert_same( false, (bool) $actual, $message ); }
function loc_reflect_call( $object, $method, array $args = array() ) { $r = new ReflectionMethod( $object, $method ); $r->setAccessible( true ); return $r->invokeArgs( $object, $args ); }
function loc_make_order( $id = 1001, $total = '10.00', $currency = 'USD' ) { $order = new WC_Order( $id, $total, $currency, 'buyer@example.test', 'key_' . $id ); $GLOBALS['loc_orders'][ $id ] = $order; return $order; }
function loc_signed_request( array $payload, $secret = 'whsec_test_secret', $timestamp = null, array $headers = array() ) { $body = wp_json_encode( $payload ); $timestamp = $timestamp ?? time(); $headers = array_merge( array( 'x-liveoncrypto-event' => $payload['event'] ?? 'payment.paid', 'x-liveoncrypto-timestamp' => (string) $timestamp, 'x-liveoncrypto-signature' => hash_hmac( 'sha256', $timestamp . '.' . $body, $secret ) ), $headers ); return new WP_REST_Request( $body, $headers ); }
function loc_valid_payload( $order_ref = 'wc_1001_ref', $payment_id = 'pay_1234567890' ) { return array( 'event'=>'payment.paid','paymentId'=>$payment_id,'merchantOrderRef'=>$order_ref,'status'=>'paid','fiatAmount'=>'10.00','fiatCurrency'=>'USD','txHash'=>'0x' . str_repeat( 'a', 64 ),'network'=>'ethereum','asset'=>'ETH','paidAt'=>'2026-06-17T00:00:00Z' ); }
