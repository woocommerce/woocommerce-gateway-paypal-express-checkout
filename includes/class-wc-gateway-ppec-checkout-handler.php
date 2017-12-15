<?php
/**
 * Cart handler.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$includes_path = wc_gateway_ppec()->includes_path;

// TODO: Use spl autoload to require on-demand maybe?

require_once( $includes_path . 'class-wc-gateway-ppec-settings.php' );
require_once( $includes_path . 'class-wc-gateway-ppec-session-data.php' );
require_once( $includes_path . 'class-wc-gateway-ppec-checkout-details.php' );

require_once( $includes_path . 'class-wc-gateway-ppec-api-error.php' );
require_once( $includes_path . 'exceptions/class-wc-gateway-ppec-api-exception.php' );
require_once( $includes_path . 'exceptions/class-wc-gateway-ppec-missing-session-exception.php' );

require_once( $includes_path . 'class-wc-gateway-ppec-payment-details.php' );
require_once( $includes_path . 'class-wc-gateway-ppec-address.php' );

class WC_Gateway_PPEC_Checkout_Handler {

	/**
	 * Cached result from self::get_checkout_defails.
	 *
	 * @since 1.2.0
	 *
	 * @var PayPal_Checkout_Details
	 */
	protected $_checkout_details;

	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		add_filter( 'the_title', array( $this, 'endpoint_page_titles' ) );
		add_action( 'woocommerce_checkout_init', array( $this, 'checkout_init' ) );
		add_filter( 'woocommerce_default_address_fields', array( $this, 'filter_default_address_fields' ) );
		add_filter( 'woocommerce_billing_fields', array( $this, 'filter_billing_fields' ) );
		add_action( 'woocommerce_checkout_process', array( $this, 'copy_checkout_details_to_post' ) );

		add_action( 'wp', array( $this, 'maybe_return_from_paypal' ) );
		add_action( 'wp', array( $this, 'maybe_cancel_checkout_with_paypal' ) );
		add_action( 'woocommerce_cart_emptied', array( $this, 'maybe_clear_session_data' ) );

		add_action( 'woocommerce_available_payment_gateways', array( $this, 'maybe_disable_other_gateways' ) );
		add_action( 'woocommerce_review_order_after_submit', array( $this, 'maybe_render_cancel_link' ) );

		add_action( 'woocommerce_cart_shipping_packages', array( $this, 'maybe_add_shipping_information' ) );
	}

	/**
	 * If the buyer clicked on the "Check Out with PayPal" button, we need to wait for the cart
	 * totals to be available.  Unfortunately that doesn't happen until
	 * woocommerce_before_cart_totals executes, and there is already output sent to the browser by
	 * this point.  So, to get around this issue, we'll enable output buffering to prevent WP from
	 * sending anything back to the browser.
	 */
	public function init() {
		if ( version_compare( WC_VERSION, '3.3', '<' ) ) {
			add_filter( 'wc_checkout_params', array( $this, 'filter_wc_checkout_params' ), 10, 1 );
		} else {
			add_filter( 'woocommerce_get_script_data', array( $this, 'filter_wc_checkout_params' ), 10, 2 );
		}
		if ( isset( $_GET['startcheckout'] ) && 'true' === $_GET['startcheckout'] ) {
			ob_start();
		}
	}

	/**
	 * Handle endpoint page title
	 * @param  string $title
	 * @return string
	 */
	public function endpoint_page_titles( $title ) {
		if ( ! is_admin() && is_main_query() && in_the_loop() && is_page() && is_checkout() && $this->has_active_session() ) {
			$title = __( 'Confirm your PayPal order', 'woocommerce-gateway-paypal-express-checkout' );
			remove_filter( 'the_title', array( $this, 'endpoint_page_titles' ) );
		}
		return $title;
	}

	/**
	 * If there's an active PayPal session during checkout (e.g. if the customer started checkout
	 * with PayPal from the cart), import billing and shipping details from PayPal using the
	 * token we have for the customer.
	 *
	 * Hooked to the woocommerce_checkout_init action
	 *
	 * @param WC_Checkout $checkout
	 */
	function checkout_init( $checkout ) {
		if ( ! $this->has_active_session() ) {
			return;
		}

		// Since we've removed the billing and shipping checkout fields, we should also remove the
		// billing and shipping portion of the checkout form
		remove_action( 'woocommerce_checkout_billing', array( $checkout, 'checkout_form_billing' ) );
		remove_action( 'woocommerce_checkout_shipping', array( $checkout, 'checkout_form_shipping' ) );

		// Lastly, let's add back in 1) displaying customer details from PayPal, 2) allow for
		// account registration and 3) shipping details from PayPal
		add_action( 'woocommerce_checkout_billing', array( $this, 'paypal_billing_details' ) );
		add_action( 'woocommerce_checkout_billing', array( $this, 'account_registration' ) );
		add_action( 'woocommerce_checkout_shipping', array( $this, 'paypal_shipping_details' ) );
	}

	/**
	 * If the cart doesn't need shipping at all, don't require the address fields
	 * (this is unique to PPEC). This is one of two places we need to filter fields.
	 * See also filter_billing_fields below.
	 *
	 * @since 1.2.1
	 * @param $fields array
	 *
	 * @return array
	 */
	public function filter_default_address_fields( $fields ) {
		if ( method_exists( WC()->cart, 'needs_shipping' ) && ! WC()->cart->needs_shipping() ) {
			$not_required_fields = array( 'address_1', 'city', 'postcode', 'country' );
			foreach ( $not_required_fields as $not_required_field ) {
				if ( array_key_exists( $not_required_field, $fields ) ) {
					$fields[ $not_required_field ]['required'] = false;
				}
			}
		}

		// Regardless of shipping, PP doesn't have the county required (e.g. using Ireland without a county is acceptable)
		if ( array_key_exists( 'state', $fields ) ) {
			$fields['state']['required'] = false;
		}

		return $fields;

	}

	/**
	 * Since PayPal doesn't always give us the phone number for the buyer, we need to make
	 * that field not required. Note that core WooCommerce adds the phone field after calling
	 * get_default_address_fields, so the woocommerce_default_address_fields cannot
	 * be used to make the phone field not required.
	 *
	 * This is one of two places we need to filter fields. See also filter_default_address_fields above.
	 *
	 * @since 1.2.0
	 * @version 1.2.1
	 * @param $billing_fields array
	 *
	 * @return array
	 */
	public function filter_billing_fields( $billing_fields ) {
		$require_phone_number = wc_gateway_ppec()->settings->require_phone_number;

		if ( array_key_exists( 'billing_phone', $billing_fields ) ) {
			$billing_fields['billing_phone']['required'] = 'yes' === $require_phone_number;
		}

		return $billing_fields;
	}

	/**
	 * When an active session is present, gets (from PayPal) the buyer details
	 * and replaces the appropriate checkout fields in $_POST
	 *
	 * Hooked to woocommerce_checkout_process
	 *
	 * @since 1.2.0
	 */
	public function copy_checkout_details_to_post() {
		if ( ! $this->has_active_session() ) {
			return;
		}

		// Make sure the selected payment method is ppec_paypal
		if ( ! isset( $_POST['payment_method'] ) || ( 'ppec_paypal' !== $_POST['payment_method'] ) ) {
			return;
		}

		// Get the buyer details from PayPal
		try {
			$session          = WC()->session->get( 'paypal' );
			$token            = isset( $_GET['token'] ) ? $_GET['token'] : $session->token;
			$checkout_details = $this->get_checkout_details( $token );
		} catch ( PayPal_API_Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
			return;
		}

		$shipping_details = $this->get_mapped_shipping_address( $checkout_details );
		$billing_details  = $this->get_mapped_billing_address( $checkout_details );

		// If the billing address is empty, copy address from shipping
		if ( empty( $billing_details['address_1'] ) ) {
			// Set flag so that WC copies billing to shipping
			$_POST['ship_to_different_address'] = 0;

			$copyable_keys = array( 'address_1', 'address_2', 'city', 'state', 'postcode', 'country' );
			foreach ( $copyable_keys as $copyable_key ) {
				if ( array_key_exists( $copyable_key, $shipping_details ) ) {
					$billing_details[ $copyable_key ] = $shipping_details[ $copyable_key ];
				}
			}
		} else {
			// Shipping may be different from billing, so set flag to not copy address from billing
			$_POST['ship_to_different_address'] = 1;
		}

		foreach ( $shipping_details as $key => $value ) {
			$_POST[ 'shipping_' . $key ] = $value;
		}

		foreach ( $billing_details as $key => $value ) {
			$_POST[ 'billing_' . $key ] = $value;
		}
	}

	/**
	 * Show billing information obtained from PayPal. This replaces the billing fields
	 * that the customer would ordinarily fill in. Should only happen if we have an active
	 * session (e.g. if the customer started checkout with PayPal from their cart.)
	 *
	 * Is hooked to woocommerce_checkout_billing action by checkout_init
	 */
	public function paypal_billing_details() {
		$session          = WC()->session->get( 'paypal' );
		$token            = isset( $_GET['token'] ) ? $_GET['token'] : $session->token;
		try {
			$checkout_details = $this->get_checkout_details( $token );
		} catch ( PayPal_API_Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
			return;
		}
		?>
		<h3><?php _e( 'Billing details', 'woocommerce-gateway-paypal-express-checkout' ); ?></h3>
		<ul>
			<?php if ( $checkout_details->payer_details->billing_address ) : ?>
				<li><strong><?php _e( 'Address:', 'woocommerce-gateway-paypal-express-checkout' ) ?></strong></br><?php echo WC()->countries->get_formatted_address( $this->get_mapped_billing_address( $checkout_details ) ); ?></li>
			<?php else : ?>
				<li><strong><?php _e( 'Name:', 'woocommerce-gateway-paypal-express-checkout' ) ?></strong> <?php echo esc_html( $checkout_details->payer_details->first_name . ' ' . $checkout_details->payer_details->last_name ); ?></li>
			<?php endif; ?>

			<?php if ( ! empty( $checkout_details->payer_details->email ) ) : ?>
				<li><strong><?php _e( 'Email:', 'woocommerce-gateway-paypal-express-checkout' ) ?></strong> <?php echo esc_html( $checkout_details->payer_details->email ); ?></li>
			<?php endif; ?>

			<?php if ( ! empty( $checkout_details->payer_details->phone_number ) ) : ?>
				<li><strong><?php _e( 'Phone:', 'woocommerce-gateway-paypal-express-checkout' ) ?></strong> <?php echo esc_html( $checkout_details->payer_details->phone_number ); ?></li>
			<?php elseif ( 'yes' === wc_gateway_ppec()->settings->require_phone_number ) : ?>
				<li>
				<?php
				if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
					$fields = WC()->checkout->checkout_fields['billing'];
				} else {
					$fields = WC()->checkout->get_checkout_fields( 'billing' );
				}
				woocommerce_form_field( 'billing_phone', $fields['billing_phone'], WC()->checkout->get_value( 'billing_phone' ) );
				?>
				</li>
			<?php endif; ?>
		</ul>
		<?php
	}

	/**
	 * If there is an active session (e.g. the customer initiated checkout from the cart), since we
	 * removed the checkout_form_billing action, we need to put a registration form back in to
	 * allow the customer to create an account.
	 *
	 *  Is hooked to woocommerce_checkout_billing action by checkout_init
	 * @since 1.2.0
	 */
	public function account_registration() {
		$checkout = WC()->checkout();

		if ( ! is_user_logged_in() && $checkout->enable_signup ) {

			if ( $checkout->enable_guest_checkout ) {
				?>
				<p class="form-row form-row-wide create-account">
					<input class="input-checkbox" id="createaccount" <?php checked( ( true === $checkout->get_value( 'createaccount' ) || ( true === apply_filters( 'woocommerce_create_account_default_checked', false ) ) ), true) ?> type="checkbox" name="createaccount" value="1" /> <label for="createaccount" class="checkbox"><?php _e( 'Create an account?', '' ); ?></label>
				</p>
				<?php
			}

			if ( ! empty( $checkout->checkout_fields['account'] ) ) {
				?>
				<div class="create-account">

					<p><?php _e( 'Create an account by entering the information below. If you are a returning customer please login at the top of the page.', 'woocommerce' ); ?></p>

					<?php foreach ( $checkout->checkout_fields['account'] as $key => $field ) : ?>

						<?php woocommerce_form_field( $key, $field, $checkout->get_value( $key ) ); ?>

					<?php endforeach; ?>

					<div class="clear"></div>

				</div>
				<?php
			}

		}
	}

	/**
	 * Show shipping information obtained from PayPal. This replaces the shipping fields
	 * that the customer would ordinarily fill in. Should only happen if we have an active
	 * session (e.g. if the customer started checkout with PayPal from their cart.)
	 *
	 * Is hooked to woocommerce_checkout_shipping action by checkout_init
	 */
	public function paypal_shipping_details() {
		$session          = WC()->session->get( 'paypal' );
		$token            = isset( $_GET['token'] ) ? $_GET['token'] : $session->token;

		try {
			$checkout_details = $this->get_checkout_details( $token );
		} catch ( PayPal_API_Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
			return;
		}

		if ( ! WC_Gateway_PPEC_Plugin::needs_shipping() ) {
			return;
		}

		?>
		<h3><?php _e( 'Shipping details', 'woocommerce-gateway-paypal-express-checkout' ); ?></h3>
		<?php
		echo WC()->countries->get_formatted_address( $this->get_mapped_shipping_address( $checkout_details ) );
	}

	/**
	 * @deprecated 1.2.0
	 */
	public function after_checkout_validation( $posted_checkout ) {
		_deprecated_function( 'after_checkout_validation', '1.2.0', '' );
	}

	/**
	 * Map PayPal billing address to WC shipping address
	 * NOTE: Not all PayPal_Checkout_Payer_Details objects include a billing address
	 * @param  object $checkout_details
	 * @return array
	 */
	public function get_mapped_billing_address( $checkout_details ) {
		if ( empty( $checkout_details->payer_details ) ) {
			return array();
		}

		$phone = '';

		if ( ! empty( $checkout_details->payer_details->phone_number ) ) {
			$phone = $checkout_details->payer_details->phone_number;
		} elseif ( 'yes' === wc_gateway_ppec()->settings->require_phone_number && ! empty( $_POST['billing_phone'] ) ) {
			$phone = wc_clean( $_POST['billing_phone'] );
		}

		return array(
			'first_name' => $checkout_details->payer_details->first_name,
			'last_name'  => $checkout_details->payer_details->last_name,
			'company'    => $checkout_details->payer_details->business_name,
			'address_1'  => $checkout_details->payer_details->billing_address ? $checkout_details->payer_details->billing_address->getStreet1() : '',
			'address_2'  => $checkout_details->payer_details->billing_address ? $checkout_details->payer_details->billing_address->getStreet2() : '',
			'city'       => $checkout_details->payer_details->billing_address ? $checkout_details->payer_details->billing_address->getCity() : '',
			'state'      => $checkout_details->payer_details->billing_address ? $checkout_details->payer_details->billing_address->getState() : '',
			'postcode'   => $checkout_details->payer_details->billing_address ? $checkout_details->payer_details->billing_address->getZip() : '',
			'country'    => $checkout_details->payer_details->billing_address ? $checkout_details->payer_details->billing_address->getCountry() : $checkout_details->payer_details->country,
			'phone'      => $phone,
			'email'      => $checkout_details->payer_details->email,
		);
	}

	/**
	 * Map PayPal shipping address to WC shipping address.
	 *
	 * @param  object $checkout_details Checkout details
	 * @return array
	 */
	public function get_mapped_shipping_address( $checkout_details ) {
		if ( empty( $checkout_details->payments[0] ) || empty( $checkout_details->payments[0]->shipping_address ) ) {
			return array();
		}

		$name       = explode( ' ', $checkout_details->payments[0]->shipping_address->getName() );
		$first_name = array_shift( $name );
		$last_name  = implode( ' ', $name );
		return array(
			'first_name'    => $first_name,
			'last_name'     => $last_name,
			'company'       => $checkout_details->payer_details->business_name,
			'address_1'     => $checkout_details->payments[0]->shipping_address->getStreet1(),
			'address_2'     => $checkout_details->payments[0]->shipping_address->getStreet2(),
			'city'          => $checkout_details->payments[0]->shipping_address->getCity(),
			'state'         => $checkout_details->payments[0]->shipping_address->getState(),
			'postcode'      => $checkout_details->payments[0]->shipping_address->getZip(),
			'country'       => $checkout_details->payments[0]->shipping_address->getCountry(),
		);
	}

	/**
	 * Checks data is correctly set when returning from PayPal Express Checkout
	 */
	public function maybe_return_from_paypal() {
		if ( empty( $_GET['woo-paypal-return'] ) || empty( $_GET['token'] ) || empty( $_GET['PayerID'] ) ) {
			return;
		}

		$token                    = $_GET['token'];
		$payer_id                 = $_GET['PayerID'];
		$create_billing_agreement = ! empty( $_GET['create-billing-agreement'] );
		$session                  = WC()->session->get( 'paypal' );

		if ( empty( $session ) || $this->session_has_expired( $token ) ) {
			wc_add_notice( __( 'Your PayPal checkout session has expired. Please check out again.', 'woocommerce-gateway-paypal-express-checkout' ), 'error' );
			return;
		}

		// Store values in session.
		$session->checkout_completed = true;
		$session->payer_id           = $payer_id;
		$session->token              = $token;

		WC()->session->set( 'paypal', $session );

		try {
			// If commit was true, take payment right now
			if ( 'order' === $session->source && $session->order_id ) {
				$checkout_details = $this->get_checkout_details( $token );

				// Get order
				$order = wc_get_order( $session->order_id );

				// Maybe create billing agreement.
				if ( $create_billing_agreement ) {
					$this->create_billing_agreement( $order, $checkout_details );
				}

				// Complete the payment now.
				$this->do_payment( $order, $session->token, $session->payer_id );

				// Clear Cart
				WC()->cart->empty_cart();

				// Redirect
				wp_redirect( $order->get_checkout_order_received_url() );
				exit;
			}
		} catch ( PayPal_API_Exception $e ) {
			wc_add_notice( __( 'Sorry, an error occurred while trying to retrieve your information from PayPal. Please try again.', 'woocommerce-gateway-paypal-express-checkout' ), 'error' );
			$this->maybe_clear_session_data();
			wp_safe_redirect( wc_get_page_permalink( 'cart' ) );
			exit;
		} catch ( PayPal_Missing_Session_Exception $e ) {
			wc_add_notice( __( 'Your PayPal checkout session has expired. Please check out again.', 'woocommerce-gateway-paypal-express-checkout' ), 'error' );
			$this->maybe_clear_session_data();
			wp_safe_redirect( wc_get_page_permalink( 'cart' ) );
			exit;
		}
	}

	/**
	 * Maybe disable this or other gateways.
	 *
	 * @since 1.0.0
	 * @version 1.2.1
	 *
	 * @param array $gateways Available gateways
	 *
	 * @return array Available gateways
	 */
	public function maybe_disable_other_gateways( $gateways ) {
		// Unset all other gateways after checking out from cart.
		if ( $this->has_active_session() ) {
			foreach ( $gateways as $id => $gateway ) {
				if ( 'ppec_paypal' !== $id ) {
					unset( $gateways[ $id ] );
				}
			}

		// If using PayPal standard (this is admin choice) we don't need to also show PayPal EC on checkout.
		} elseif ( is_checkout() && ( isset( $gateways['paypal'] ) || 'no' === wc_gateway_ppec()->settings->mark_enabled ) ) {
			unset( $gateways['ppec_paypal'] );
		}

		// If the cart total is zero (e.g. because of a coupon), don't allow this gateway.
		// We do this only if we're on the checkout page (is_checkout), but not on the order-pay page (is_checkout_pay_page)
		if ( is_cart() || ( is_checkout() && ! is_checkout_pay_page() ) ) {
			if ( isset( $gateways['ppec_paypal'] ) && ( 0 >= WC()->cart->total ) ) {
				unset( $gateways['ppec_paypal'] );
			}
		}

		return $gateways;
	}

	/**
	 * When cart based Checkout with PPEC is in effect, we need to include
	 * a Cancel button on the checkout form to give the user a means to throw
	 * away the session provided and possibly select a different payment
	 * gateway.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function maybe_render_cancel_link() {
		if ( $this->has_active_session() ) {
			printf(
				'<a href="%s" class="wc-gateway-ppec-cancel">%s</a>',
				esc_url( add_query_arg( 'wc-gateway-ppec-clear-session', true, wc_get_cart_url() ) ),
				esc_html__( 'Cancel', 'woocommerce-gateway-paypal-express-checkout' )
			);
		}
	}

	/**
	 * Buyer cancels checkout with PayPal.
	 *
	 * Clears the session data and display notice.
	 */
	public function maybe_cancel_checkout_with_paypal() {
		if ( is_cart() && ! empty( $_GET['wc-gateway-ppec-clear-session'] ) ) {
			$this->maybe_clear_session_data();

			$notice =  __( 'You have cancelled Checkout with PayPal. Please try to process your order again.', 'woocommerce-gateway-paypal-express-checkout' );
			if ( ! wc_has_notice( $notice, 'notice' ) ) {
				wc_add_notice( $notice, 'notice' );
			}
		}
	}

	/**
	 * Used when cart based Checkout with PayPal is in effect. Hooked to woocommerce_cart_emptied
	 * Also called by WC_PayPal_Braintree_Loader::possibly_cancel_checkout_with_paypal
	 *
	 * @since 1.0.0
	 */
	public function maybe_clear_session_data() {
		if ( $this->has_active_session() ) {
			unset( WC()->session->paypal );
		}
	}

	/**
	 * Checks whether session with passed token has expired.
	 *
	 * @since 1.0.0
	 *
	 * @param string $token Token
	 *
	 * @return bool
	 */
	public function session_has_expired( $token ) {
		$session = WC()->session->paypal;
		return ( ! $session || ! is_a( $session, 'WC_Gateway_PPEC_Session_Data' ) || $session->expiry_time < time() || $token !== $session->token );
	}

	/**
	 * Checks whether there's active session from cart-based checkout with PPEC.
	 *
	 * @since 1.0.0
	 *
	 * @return bool Returns true if PPEC session exists and still valid
	 */
	public function has_active_session() {
		if ( ! WC()->session ) {
			return false;
		}

		$session = WC()->session->paypal;
		return ( is_a( $session, 'WC_Gateway_PPEC_Session_Data' ) && $session->payer_id && $session->expiry_time > time() );
	}

	/**
	 * @deprecated
	 */
	public function get_token_from_session() {
		_deprecated_function( __METHOD__, '1.2.0', '' );
	}

	/**
	 * @deprecated
	 */
	public function setShippingAddress( $address ) {
		_deprecated_function( __METHOD__, '1.2.0', '' );
	}

	/**
	 * @deprecated
	 */
	public function getSetExpressCheckoutParameters() {
		// No replacement because WC_Gateway_PPEC_Client::get_set_express_checkout_params
		// needs context from where the buyer starts checking out.
		_deprecated_function( __METHOD__, '1.2.0', '' );
	}

	/**
	 * @deprecated
	 */
	public function getDoExpressCheckoutParameters( $token, $payer_id ) {
		// No replacement because WC_Gateway_PPEC_Client::get_do_express_checkout_params
		// needs order_id to return properly.
		_deprecated_function( __METHOD__, '1.2.0', '' );
	}

	/**
	 * @deprecated
	 */
	protected function is_success( $response ) {
		_deprecated_function( __METHOD__, '1.2.0', 'WC_Gateway_PPEC_Client::response_has_success_status' );

		$client = wc_gateway_ppec()->client;
		return $client->response_has_success_status( $response );
	}

	/**
	 * Generic checkout handler.
	 *
	 * @param array $context_args Context parameters for checkout.
	 * @param array $session_data_args Session parameters (token pre-populated).
	 *
	 * @throws PayPal_API_Exception
	 * @return string Redirect URL.
	 */
	protected function start_checkout( $context_args, $session_data_args ) {
		$settings     = wc_gateway_ppec()->settings;
		$client       = wc_gateway_ppec()->client;
		$context_args['create_billing_agreement'] = $this->needs_billing_agreement_creation( $context_args );

		$params   = $client->get_set_express_checkout_params( $context_args );
		$response = $client->set_express_checkout( $params );

		if ( $client->response_has_success_status( $response ) ) {
			$session_data_args['token'] = $response['TOKEN'];

			WC()->session->paypal = new WC_Gateway_PPEC_Session_Data( $session_data_args );

			return $settings->get_paypal_redirect_url( $response['TOKEN'], false, $session_data_args['use_paypal_credit'] );
		} else {
			throw new PayPal_API_Exception( $response );
		}
	}

	/**
	 * Handler when buyer is checking out from cart page.
	 *
	 * @return string Redirect URL.
	 */
	public function start_checkout_from_cart() {
		$settings     = wc_gateway_ppec()->settings;

		$context_args = array(
			'start_from' => 'cart',
		);

		$session_data_args = array(
			'source'            => 'cart',
			'expires_in'        => $settings->get_token_session_length(),
			'use_paypal_credit' => wc_gateway_ppec_is_using_credit(),
		);

		return $this->start_checkout( $context_args, $session_data_args );
	}

	/**
	 * Handler when buyer is checking out from checkout page.
	 *
	 * @param int  $order_id Order ID.
	 * @param bool $use_ppc  Whether to use PayPal credit.
	 *
	 * @return string Redirect URL.
	 */
	public function start_checkout_from_checkout( $order_id, $use_ppc ) {
		$settings     = wc_gateway_ppec()->settings;

		$context_args = array(
			'start_from' => 'checkout',
			'order_id'   => $order_id,
		);

		$session_data_args = array(
			'source'            => 'order',
			'order_id'          => $order_id,
			'expires_in'        => $settings->get_token_session_length(),
			'use_paypal_credit' => $use_ppc,
		);

		return $this->start_checkout( $context_args, $session_data_args );
	}

	/**
	 * Checks whether buyer checkout from checkout page.
	 *
	 * @since 1.2.0
	 *
	 * @return bool Returns true if buyer checkout from checkout page
	 */
	public function is_started_from_checkout_page() {
		$session = WC()->session->get( 'paypal' );

		return (
			! $this->has_active_session()
			||
			! $session->checkout_completed
		);
	}

	/**
	 * @deprecated
	 */
	public function getCheckoutDetails( $token = false ) {
		_deprecated_function( __METHOD__, '1.2.0', 'WC_Gateway_PPEC_Checkout_Handler::get_checkout_details' );
		return $this->get_checkout_details( $token );
	}

	/**
	 * Get checkout details from token.
	 *
	 * @since 1.2.0
	 *
	 * @throws \Exception
	 *
	 * @param bool|string $token Express Checkout token
	 */
	public function get_checkout_details( $token = false ) {
		if ( is_a( $this->_checkout_details, 'PayPal_Checkout_Details' ) ) {
			return $this->_checkout_details;
		}

		if ( false === $token && ! empty( $_GET['token'] ) ) {
			$token = $_GET['token'];
		}

		$client   = wc_gateway_ppec()->client;
		$response = $client->get_express_checkout_details( $token );

		if ( $client->response_has_success_status( $response ) ) {
			$checkout_details = new PayPal_Checkout_Details();
			$checkout_details->loadFromGetECResponse( $response );

			$this->_checkout_details = $checkout_details;
			return $checkout_details;
		} else {
			throw new PayPal_API_Exception( $response );
		}
	}

	/**
	 * Creates billing agreement and stores the billing agreement ID in order's
	 * meta and subscriptions meta.
	 *
	 * @since 1.2.0
	 *
	 * @throws \Exception
	 *
	 * @param WC_Order                $order            Order object
	 * @param PayPal_Checkout_Details $checkout_details Checkout details
	 */
	public function create_billing_agreement( $order, $checkout_details ) {
		if ( 1 !== intval( $checkout_details->billing_agreement_accepted ) ) {
			throw new PayPal_API_Exception( $checkout_details->raw_response );
		}

		$client = wc_gateway_ppec()->client;
		$resp   = $client->create_billing_agreement( $checkout_details->token );

		if ( ! $client->response_has_success_status( $resp ) || empty( $resp['BILLINGAGREEMENTID'] ) ) {
			throw new PayPal_API_Exception( $resp );
		}

		$old_wc = version_compare( WC_VERSION, '3.0', '<' );
		$order_id = $old_wc ? $order->id : $order->get_id();
		if ( $old_wc ) {
			update_post_meta( $order_id, '_ppec_billing_agreement_id', $resp['BILLINGAGREEMENTID'] );
		} else {
			$order->update_meta_data( '_ppec_billing_agreement_id', $resp['BILLINGAGREEMENTID'] );
		}

		$subscriptions = array();
		if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order_id ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order_id );
		} elseif ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order_id ) ) {
			$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
		}

		$billing_agreement_id = $old_wc ? get_post_meta( $order_id, '_ppec_billing_agreement_id', true ) : $order->get_meta( '_ppec_billing_agreement_id', true );

		foreach ( $subscriptions as $subscription ) {
			update_post_meta( is_callable( array( $subscription, 'get_id' ) ) ? $subscription->get_id() : $subscription->id, '_ppec_billing_agreement_id', $billing_agreement_id );
		}
	}

	/**
	 * Complete a payment that has been authorized via PPEC.
	 */
	public function do_payment( $order, $token, $payer_id ) {
		$settings     = wc_gateway_ppec()->settings;
		$session_data = WC()->session->get( 'paypal', null );

		if ( ! $order || null === $session_data || $this->session_has_expired( $token ) || empty( $payer_id ) ) {
			throw new PayPal_Missing_Session_Exception();
		}

		$client = wc_gateway_ppec()->client;
		$old_wc = version_compare( WC_VERSION, '3.0', '<' );
		$order_id = $old_wc ? $order->id : $order->get_id();
		$params = $client->get_do_express_checkout_params( array(
			'order_id' => $order_id,
			'token'    => $token,
			'payer_id' => $payer_id,
		) );

		$response = $client->do_express_checkout_payment( $params );

		if ( $client->response_has_success_status( $response ) ) {
			$payment_details = new PayPal_Payment_Details();
			$payment_details->loadFromDoECResponse( $response );

			$meta = $old_wc ? get_post_meta( $order_id, '_woo_pp_txnData', true ) : $order->get_meta( '_woo_pp_txnData', true );
			if ( ! empty( $meta ) ) {
				$txnData = $meta;
			} else {
				$txnData = array( 'refundable_txns' => array() );
			}

			$paymentAction = $settings->get_paymentaction();

			$txn = array(
				'txnID'           => $payment_details->payments[0]->transaction_id,
				'amount'          => $order->get_total(),
				'refunded_amount' => 0
			);
			if ( 'Completed' == $payment_details->payments[0]->payment_status ) {
				$txn['status'] = 'Completed';
			} else {
				$txn['status'] = $payment_details->payments[0]->payment_status . '_' . $payment_details->payments[0]->pending_reason;
			}
			$txnData['refundable_txns'][] = $txn;

			if ( 'authorization' == $paymentAction ) {
				$txnData['auth_status'] = 'NotCompleted';
			}

			$txnData['txn_type'] = $paymentAction;

			if ( $old_wc ) {
				update_post_meta( $order_id, '_woo_pp_txnData', $txnData );
			} else {
				$order->update_meta_data( '_woo_pp_txnData', $txnData );
			}

			// Payment was taken so clear session
			$this->maybe_clear_session_data();

			// Handle order
			$this->handle_payment_response( $order, $payment_details->payments[0] );
		} else {
			throw new PayPal_API_Exception( $response );
		}
	}

	/**
	 * Handle result of do_payment
	 */
	public function handle_payment_response( $order, $payment ) {
		// Store meta data to order
		$old_wc = version_compare( WC_VERSION, '3.0', '<' );

		update_post_meta( $old_wc ? $order->id : $order->get_id(), '_paypal_status', strtolower( $payment->payment_status ) );
		update_post_meta( $old_wc ? $order->id : $order->get_id(), '_transaction_id', $payment->transaction_id );

		// Handle $payment response
		if ( 'completed' === strtolower( $payment->payment_status ) ) {
			$order->payment_complete( $payment->transaction_id );
		} else {
			if ( 'authorization' === $payment->pending_reason ) {
				$order->update_status( 'on-hold', __( 'Payment authorized. Change payment status to processing or complete to capture funds.', 'woocommerce-gateway-paypal-express-checkout' ) );
			} else {
				$order->update_status( 'on-hold', sprintf( __( 'Payment pending (%s).', 'woocommerce-gateway-paypal-express-checkout' ), $payment->pending_reason ) );
			}
			if ( $old_wc ) {
				if ( ! get_post_meta( $order->id, '_order_stock_reduced', true ) ) {
					$order->reduce_order_stock();
				}
			} else {
				wc_maybe_reduce_stock_levels( $order->get_id() );
			}
		}
	}

	/**
	 * This function filter the packages adding shipping information from PayPal on the checkout page
	 * after the user is authenticated by PayPal.
	 *
	 * @since 1.9.13 Introduced
	 * @param array $packages
	 *
	 * @return mixed
	 */
	public function maybe_add_shipping_information( $packages ) {
		if ( empty( $_GET['woo-paypal-return'] ) || empty( $_GET['token'] ) || empty( $_GET['PayerID'] ) ) {
			return $packages;
		}

		// Shipping details from PayPal
		try {
			$checkout_details = $this->get_checkout_details( wc_clean( $_GET['token'] ) );
		} catch ( PayPal_API_Exception $e ) {
			return $packages;
		}

		$destination = $this->get_mapped_shipping_address( $checkout_details );

		$packages[0]['destination']['country']   = $destination['country'];
		$packages[0]['destination']['state']     = $destination['state'];
		$packages[0]['destination']['postcode']  = $destination['postcode'];
		$packages[0]['destination']['city']      = $destination['city'];
		$packages[0]['destination']['address']   = $destination['address_1'];
		$packages[0]['destination']['address_2'] = $destination['address_2'];

		return $packages;
	}

	/**
	 * Checks whether checkout needs billing agreement creation.
	 *
	 * @since 1.2.0
	 *
	 * @param array $args {
	 *     Context args to retrieve SetExpressCheckout parameters.
	 *
	 *     @type string $start_from Start from 'cart' or 'checkout'.
	 *     @type int    $order_id   Order ID if $start_from is 'checkout'.
	 * }
	 *
	 * @return bool Returns true if billing agreement is needed in the purchase
	 */
	public function needs_billing_agreement_creation( $args ) {
		$needs_billing_agreement = false;
		switch ( $args['start_from'] ) {
			case 'cart':
				if ( class_exists( 'WC_Subscriptions_Cart' ) ) {
					$needs_billing_agreement = WC_Subscriptions_Cart::cart_contains_subscription();
				}
				break;
			case 'checkout':
				if ( function_exists( 'wcs_order_contains_subscription' ) ) {
					$needs_billing_agreement = wcs_order_contains_subscription( $args['order_id'] );
				}
				if ( function_exists( 'wcs_order_contains_renewal' ) ) {
					$needs_billing_agreement = ( $needs_billing_agreement || wcs_order_contains_renewal( $args['order_id'] ) );
				}
				break;
		}

		return $needs_billing_agreement;
	}

	/**
	 * Filter checkout AJAX endpoint so it carries the query string after buyer is
	 * redirected from PayPal.
	 *
	 * To explain the reason why we need to store this in the session, we
	 * first need to take a look at how things flow:
	 *
	 * For guest checkout with Geolocation enabled:
	 *
	 * 1. On the checkout screen, WooCommerce gets shipping information and
	 * this hook is called. We have `$_GET` context, so we will replace
	 * `$packages[0]['destination']` using the PP account (Country 1).
	 *
	 * 2. Package hash gets stored by `WC_Shipping::calculate_shipping_for_package`
	 * for destination "Country 1".
	 *
	 * 3. The AJAX `update_order_review` will be called from core. At this
	 * point, we do not have `$_GET` context, so this method will return
	 * the original packages. Note that the original packages will now
	 * contain shipping information based on Geolocation (Country 2, may be
	 * distinct from Country 1).
	 *
	 * 4. At this point, the package hash will be different, and thus the
	 * call to `get_rates_for_package` within `WC_Shipping::calculate_shipping_for_package`
	 * will re-trigger shipping extensions, such as FedEx, USPS, etc.
	 *
	 * To avoid this behaviour, make sure we store the packages and their
	 * correct destination based on PP account info for re-usage in any
	 * AJAX calls where we don't have PP token context.
	 *
	 * Related core commits: 75cc4f9, 2ff1ee1
	 *
	 * @since 1.4.7
	 *
	 * @param array  $params
	 * @param string $handle
	 *
	 * @return string URL.
	 */
	public function filter_wc_checkout_params( $params, $handle = '' ) {
		if ( 'wc-checkout' !== $handle && ! doing_action( 'wc_checkout_params' ) ) {
			return $params;
		}

		$fields = array( 'woo-paypal-return', 'token', 'PayerID' );

		$params['wc_ajax_url'] = remove_query_arg( 'wc-ajax', $params['wc_ajax_url'] );

		foreach ( $fields as $field ) {
			if ( ! empty( $_GET[ $field ] ) ) {
				$params['wc_ajax_url'] = add_query_arg( $field, $_GET[ $field ], $params['wc_ajax_url'] );
			}
		}

		$params['wc_ajax_url'] = add_query_arg( 'wc-ajax', '%%endpoint%%', $params['wc_ajax_url'] );

		return $params;
	}
}
