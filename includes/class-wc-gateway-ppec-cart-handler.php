<?php
/**
 * Cart handler.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Gateway_PPEC_Cart_Handler {

	/**
	 *
	 */
	public function __construct() {
		add_action( 'woocommerce_before_cart_totals', array( $this, 'before_cart_totals' ) );

		if ( version_compare( WC()->version, '2.3', '>=' ) ) {
			add_action( 'woocommerce_after_cart_totals', array( $this, 'display_paypal_button' ) );
		} else {
			add_action( 'woocommerce_proceed_to_checkout', array( $this, 'display_paypal_button' ) );
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	public function before_cart_totals() {
		// If there then call start_checkout() else do nothing so page loads as normal.
		if ( ! empty( $_GET['startcheckout'] ) && 'true' === $_GET['startcheckout'] ) {
			// Trying to prevent auto running checkout when back button is pressed from PayPal page.
			$_GET['startcheckout'] = 'false';
			woo_pp_start_checkout();
		}
	}

	public function display_paypal_button() {
		$settings = wc_gateway_ppec()->settings->loadSettings();
		if( ! $settings->enabled ) {
			return;
		}

		if ( version_compare( WC()->version, '2.3', '>' ) ) {
			$class = 'woo_pp_cart_buttons_div';
		} else {
			$class = 'woo_pp_checkout_buttons_div';
		}

		if ( $settings->enableInContextCheckout && $settings->getActiveApiCredentials()->payerID ) {
			$class .= ' paypal-button-hidden';
		}

		$redirect_arg = array( 'startcheckout' => 'true' );
		$redirect     = add_query_arg( $redirect_arg );

		$checkout_logo = 'https://www.paypalobjects.com/webstatic/en_US/i/buttons/checkout-logo-' . $settings->buttonSize . '.png';
		$credit_logo   = 'https://www.paypalobjects.com/webstatic/en_US/i/buttons/ppcredit-logo-' . $settings->buttonSize . '.png';
		?>
		<div class="<?php echo esc_attr( $class ); ?>">
			<span style="float: right;">
				<a href="<?php echo esc_url( $redirect ); ?>" id="woo_pp_ec_button">
					<img src="<?php echo esc_url( $checkout_logo ); ?>" alt="<?php _e( 'Check out with PayPal', 'woocommerce-gateway-paypal-express-checkout' ); ?>" style="width: auto; height: auto;">
				</a>
			</span>

			<?php if ( $settings->ppcEnabled ) : ?>
				<?php
				$redirect = add_query_arg( array( 'use-ppc' => 'true' ), $redirect );
				?>
				<span style="float: right; padding-right: 5px;">

					<a href="<?php echo esc_url( $redirect ); ?>" id="woo_pp_ppc_button">
						<img src="<?php echo esc_url( $credit_logo ); ?>" alt="<?php _e( 'Pay with PayPal Credit', 'woocommerce-gateway-paypal-express-checkout' ); ?>" style="width: auto; height: auto;">
					</a>
				</span>
			<?php endif; ?>
		</div>

		<?php
		if ( $settings->enableInContextCheckout && $settings->getActiveApiCredentials()->payerID ) {
			$payer_id = $settings->getActiveApiCredentials()->payerID;
			?>
			<script type="text/javascript">
				window.paypalCheckoutReady = function() {
					paypal.checkout.setup( '<?php echo $payer_id; ?>', {
						button: [ 'woo_pp_ec_button', 'woo_pp_ppc_button' ]
					});
				}
			</script>
			<?php
		}
	}

	public function enqueue_scripts() {
		if ( ! is_cart() ) {
			return;
		}

		wp_enqueue_style( 'wc-gateway-ppec-frontend-cart', wc_gateway_ppec()->plugin_url . 'assets/css/wc-gateway-ppec-frontend-cart.css' );

		$settings = wc_gateway_ppec()->settings->loadSettings();
		if ( $settings->enabled && $settings->enableInContextCheckout && $settings->getActiveApiCredentials()->payerID ) {
			wp_enqueue_script( 'paypal-checkout-js', 'https://www.paypalobjects.com/api/checkout.js', array(), null, true );
		}
	}
}
