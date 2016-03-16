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

		add_filter( 'woocommerce_get_sections_checkout', array( $this, 'filter_sections_checkout' ) );
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
	 * Prevent PayPal Credit showing up in the admin, because it shares its settings
	 * with the PayPal Express Checkout class.
	 *
	 * @param array $sections List of sections in checkout
	 *
	 * @return array Sections in checkout
	 */
	public function filter_sections_checkout( $sections ) {
		unset( $sections['paypal_credit'] );
		return $sections;
	}
}
