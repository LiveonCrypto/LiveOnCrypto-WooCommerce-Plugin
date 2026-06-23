<?php
/**
 * Settings validation helpers.
 *
 * @package LiveOnCrypto_WC
 */

defined( 'ABSPATH' ) || exit;

class LiveOnCrypto_WC_Settings_Validator {
	public static function sanitize_text_setting( mixed $value ): string {
		return sanitize_text_field( wp_unslash( (string) $value ) );
	}
}
