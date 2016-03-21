<?php
/**
 * Plugin bootstrapper.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Gateway_PPEC_Admin_Handler {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_update_options_general', array( $this, 'force_zero_decimal' ) );
		add_action( 'admin_notices', array( $this, 'show_decimal_warning' ) );

		add_filter( 'woocommerce_get_sections_checkout', array( $this, 'filter_checkout_sections' ) );
	}

	/**
	 * Force zero decimal on specific currencies.
	 */
	public function force_zero_decimal() {
		$settings = wc_gateway_ppec()->settings;
		if ( $settings->currency_has_decimal_restriction() ) {
			update_option( 'woocommerce_price_num_decimals', 0 );
			update_option( 'wc_gateway_ppce_display_decimal_msg', true );
		}
	}

	/**
	 * Show decimal warning.
	 */
	public function show_decimal_warning() {
		if ( get_option( 'wc_gateway_ppce_display_decimal_msg', false ) ) {
			?>
			<div class="updated fade">
				<p>
					<strong><?php _e( 'NOTE: PayPal does not accept decimal places for the currency in which you are transacting.  The "Number of Decimals" option in WooCommerce has automatically been set to 0 for you.', 'woocommerce-gateway-paypal-express-checkout' ); ?></strong>
				</p>
			</div>
			<?php
			delete_option( 'wc_gateway_ppce_display_decimal_msg' );
		}
	}

	/**
	 * Prevent PPEC Credit showing up in the admin, because it shares its settings
	 * with the PayPal Express Checkout class.
	 *
	 * @param array $sections List of sections in checkout
	 *
	 * @return array Sections in checkout
	 */
	public function filter_checkout_sections( $sections ) {

		$paypal_sections = array(
			'wc_gateway_ppec_with_paypal',
		);

		$card_sections = array(
			'wc_gateway_ppec_with_card',
		);

		$current_section = isset( $_GET['section'] ) ? $_GET['section'] : '';

		// If the current section is a paypal section, remove the card section,
		// otherwise, remove the paypal section
		$sections_to_remove = in_array( $current_section, $paypal_sections ) ? $card_sections : $paypal_sections;

		// And, let's also remove simplify commerce from the sections if it is not enabled and it is not the
		// current section. (Note: The option will be empty if it has never been enabled)

		$simplify_commerce_options = get_option( 'woocommerce_simplify_commerce_settings', array() );
		if ( empty( $simplify_commerce_options ) || ( "no" === $simplify_commerce_options['enabled'] ) ) {
			if ( 'wc_gateway_simplify_commerce' !== $current_section ) {
				$sections_to_remove[] = 'wc_gateway_simplify_commerce';
			}
			if ( 'wc_addons_gateway_simplify_commerce' !== $current_section ) {
				$sections_to_remove[] = 'wc_addons_gateway_simplify_commerce';
			}
		}

		foreach( $sections_to_remove as $section_to_remove ) {
			unset( $sections[$section_to_remove] );
		}

		return $sections;

	}
}
