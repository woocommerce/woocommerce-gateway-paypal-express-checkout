<?php
/**
 * Cart handler.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_PPEC_Cart_Handler handles button display in the cart.
 */
class WC_Gateway_PPEC_Cart_Handler {

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( ! wc_gateway_ppec()->settings->is_enabled() ) {
			return;
		}

		add_action( 'woocommerce_before_cart_totals', array( $this, 'before_cart_totals' ) );
		add_action( 'woocommerce_proceed_to_checkout', array( $this, 'display_paypal_button' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		if ( 'yes' === wc_gateway_ppec()->settings->use_spb ) {
			add_action( 'woocommerce_after_mini_cart', array( $this, 'display_mini_paypal_button' ), 20 );
		} else {
			add_action( 'woocommerce_widget_shopping_cart_buttons', array( $this, 'display_mini_paypal_button' ), 20 );
		}

		if ( 'yes' === wc_gateway_ppec()->settings->checkout_on_single_product_enabled ) {
			add_action( 'woocommerce_after_add_to_cart_form', array( $this, 'display_paypal_button_product' ), 1 );
			add_action( 'wc_ajax_wc_ppec_generate_cart', array( $this, 'wc_ajax_generate_cart' ) );
			add_action( 'wp', array( $this, 'ensure_session' ) ); // Ensure there is a customer session so that nonce is not invalidated by new session created on AJAX POST request.
		}

		add_action( 'wc_ajax_wc_ppec_update_shipping_costs', array( $this, 'wc_ajax_update_shipping_costs' ) );
		add_action( 'wc_ajax_wc_ppec_start_checkout', array( $this, 'wc_ajax_start_checkout' ) );
	}

	/**
	 * Start checkout handler when cart is loaded.
	 */
	public function before_cart_totals() {
		// If there then call start_checkout() else do nothing so page loads as normal.
		if ( ! empty( $_GET['startcheckout'] ) && 'true' === $_GET['startcheckout'] ) {
			// Trying to prevent auto running checkout when back button is pressed from PayPal page.
			$_GET['startcheckout'] = 'false';
			woo_pp_start_checkout();
		}
	}

	/**
	 * Generates the cart for PayPal Checkout on a product level.
	 *
	 * @since 1.4.0
	 */
	public function wc_ajax_generate_cart() {
		global $post;

		if ( ! wp_verify_nonce( $_POST['nonce'], '_wc_ppec_generate_cart_nonce' ) ) {
			wp_die( __( 'Cheatin&#8217; huh?', 'woocommerce-gateway-paypal-express-checkout' ) );
		}

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		WC()->shipping->reset_shipping();
		$product = wc_get_product( $post->ID );

		if ( ! empty( $_POST['add-to-cart'] ) ) {
			$product = wc_get_product( absint( $_POST['add-to-cart'] ) );
		}

		/**
		 * If this page is single product page, we need to simulate
		 * adding the product to the cart taken account if it is a
		 * simple or variable product.
		 */
		if ( $product ) {
			$qty     = ! isset( $_POST['qty'] ) ? 1 : absint( $_POST['qty'] );
			wc_empty_cart();

			if ( $product->is_type( 'variable' ) ) {
				$attributes = array_map( 'wc_clean', $_POST['attributes'] );

				if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
					$variation_id = $product->get_matching_variation( $attributes );
				} else {
					$data_store = WC_Data_Store::load( 'product' );
					$variation_id = $data_store->find_matching_product_variation( $product, $attributes );
				}

				WC()->cart->add_to_cart( $product->get_id(), $qty, $variation_id, $attributes );
			} else {
				WC()->cart->add_to_cart( $product->get_id(), $qty );
			}

			WC()->cart->calculate_totals();
		}

		wp_send_json( new stdClass() );
	}

	/**
	 * Update shipping costs. Trigger this update before checking out to have total costs up to date.
	 *
	 * @since 1.4.0
	 */
	public function wc_ajax_update_shipping_costs() {
		if ( ! wp_verify_nonce( $_POST['nonce'], '_wc_ppec_update_shipping_costs_nonce' ) ) {
			wp_die( __( 'Cheatin&#8217; huh?', 'woocommerce-gateway-paypal-express-checkout' ) );
		}

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		WC()->shipping->reset_shipping();

		WC()->cart->calculate_totals();

		wp_send_json( new stdClass() );
	}

	/**
	 * Set Express Checkout and return token in response.
	 *
	 * @since 1.6.0
	 */
	public function wc_ajax_start_checkout() {
		if ( ! wp_verify_nonce( $_POST['nonce'], '_wc_ppec_start_checkout_nonce' ) ) {
			wp_die( __( 'Cheatin&#8217; huh?', 'woocommerce-gateway-paypal-express-checkout' ) );
		}

		if ( ! empty( $_POST['terms-field'] ) && empty( $_POST['terms'] ) ) {
			$message = __( 'Please read and accept the terms and conditions to proceed with your order.', 'woocommerce-gateway-paypal-express-checkout' );
			wp_send_json_error( array( 'message' => $message ) );
			return;
		}

		if ( isset( $_POST['from_checkout'] ) && 'yes' === $_POST['from_checkout'] ) {
			add_filter( 'woocommerce_cart_needs_shipping', '__return_false' );
		}

		try {
			wc_gateway_ppec()->checkout->start_checkout_from_cart();
			wp_send_json_success( array( 'token' => WC()->session->paypal->token ) );
		} catch( PayPal_API_Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Display paypal button on the product page.
	 *
	 * @since 1.4.0
	 */
	public function display_paypal_button_product() {
		$gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ( ! is_product() || ! isset( $gateways['ppec_paypal'] ) ) {
			return;
		}

		$settings = wc_gateway_ppec()->settings;

		$express_checkout_img_url = apply_filters( 'woocommerce_paypal_express_checkout_button_img_url', sprintf( 'https://www.paypalobjects.com/webstatic/en_US/i/buttons/checkout-logo-%s.png', $settings->button_size ) );

		?>
		<div class="wcppec-checkout-buttons woo_pp_cart_buttons_div">
			<?php if ( 'yes' === $settings->use_spb ) : ?>
			<div id="woo_pp_ec_button_product"></div>
			<?php else : ?>

			<a href="<?php echo esc_url( add_query_arg( array( 'startcheckout' => 'true' ), wc_get_page_permalink( 'cart' ) ) ); ?>" id="woo_pp_ec_button_product" class="wcppec-checkout-buttons__button">
				<img src="<?php echo esc_url( $express_checkout_img_url ); ?>" alt="<?php _e( 'Check out with PayPal', 'woocommerce-gateway-paypal-express-checkout' ); ?>" style="width: auto; height: auto;">
			</a>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Display paypal button on the cart page.
	 */
	public function display_paypal_button() {

		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		$settings = wc_gateway_ppec()->settings;

		// billing details on checkout page to calculate shipping costs
		if ( ! isset( $gateways['ppec_paypal'] ) || 'no' === $settings->cart_checkout_enabled ) {
			return;
		}

		$express_checkout_img_url = apply_filters( 'woocommerce_paypal_express_checkout_button_img_url', sprintf( 'https://www.paypalobjects.com/webstatic/en_US/i/buttons/checkout-logo-%s.png', $settings->button_size ) );
		$paypal_credit_img_url    = apply_filters( 'woocommerce_paypal_express_checkout_credit_button_img_url', sprintf( 'https://www.paypalobjects.com/webstatic/en_US/i/buttons/ppcredit-logo-%s.png', $settings->button_size ) );
		?>
		<div class="wcppec-checkout-buttons woo_pp_cart_buttons_div">

			<?php if ( has_action( 'woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout' ) ) : ?>
				<div class="wcppec-checkout-buttons__separator">
					<?php _e( '&mdash; or &mdash;', 'woocommerce-gateway-paypal-express-checkout' ); ?>
				</div>
			<?php endif; ?>

			<?php if ( 'yes' === $settings->use_spb ) : ?>
			<div id="woo_pp_ec_button_cart"></div>
			<?php else : ?>

			<a href="<?php echo esc_url( add_query_arg( array( 'startcheckout' => 'true' ), wc_get_page_permalink( 'cart' ) ) ); ?>" id="woo_pp_ec_button" class="wcppec-checkout-buttons__button">
				<img src="<?php echo esc_url( $express_checkout_img_url ); ?>" alt="<?php _e( 'Check out with PayPal', 'woocommerce-gateway-paypal-express-checkout' ); ?>" style="width: auto; height: auto;">
			</a>

			<?php if ( $settings->is_credit_enabled() ) : ?>
				<a href="<?php echo esc_url( add_query_arg( array( 'startcheckout' => 'true', 'use-ppc' => 'true' ), wc_get_page_permalink( 'cart' ) ) ); ?>" id="woo_pp_ppc_button" class="wcppec-checkout-buttons__button">
				<img src="<?php echo esc_url( $paypal_credit_img_url ); ?>" alt="<?php _e( 'Pay with PayPal Credit', 'woocommerce-gateway-paypal-express-checkout' ); ?>" style="width: auto; height: auto;">
				</a>
			<?php endif; ?>

			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Display paypal button on the cart widget
	 */
	public function display_mini_paypal_button() {

		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		$settings = wc_gateway_ppec()->settings;

		// billing details on checkout page to calculate shipping costs
		if ( ! isset( $gateways['ppec_paypal'] ) || 'no' === $settings->cart_checkout_enabled ) {
			return;
		}
		?>

		<?php if ( 'yes' === $settings->use_spb ) : ?>
		<p class="woocommerce-mini-cart__buttons buttons wcppec-cart-widget-spb">
			<span id="woo_pp_ec_button_mini_cart"></span>
		</p>
		<?php else : ?>

		<a href="<?php echo esc_url( add_query_arg( array( 'startcheckout' => 'true' ), wc_get_page_permalink( 'cart' ) ) ); ?>" id="woo_pp_ec_button" class="wcppec-cart-widget-button">
			<img src="<?php echo esc_url( 'https://www.paypalobjects.com/webstatic/en_US/i/btn/png/gold-rect-paypalcheckout-26px.png' ); ?>" alt="<?php _e( 'Check out with PayPal', 'woocommerce-gateway-paypal-express-checkout' ); ?>" style="width: auto; height: auto;">
		</a>
		<?php endif; ?>
		<?php
	}

	/**
	 * Convert from settings to values expected by PayPal Button API:
	 *   - 'small' button size only allowed if layout is 'vertical'.
	 *   - 'label' only allowed if layout is 'vertical'.
	 *   - 'disallowed' funding methods if layout is 'vertical'.
	 *   - 'allowed' funding methods if layout is 'horizontal'.
	 *   - Only allow PayPal Credit if supported.
	 *
	 * @param array Raw settings.
	 *
	 * @return array Same array adapted to include data suitable for client-side rendering.
	 *
	 * @since 1.6.0
	 */
	protected function get_button_settings( $settings, $context = '' ) {
		$prefix = $context ? $context . '_' : $context;
		$data = array(
			'button_layout'        => $settings->{ $prefix . 'button_layout' },
			'button_size'          => $settings->{ $prefix . 'button_size' },
			'hide_funding_methods' => $settings->{ $prefix . 'hide_funding_methods' },
			'credit_enabled'       => $settings->{ $prefix . 'credit_enabled' },
		);

		$button_layout        = $data['button_layout'];
		$data['button_label'] = 'horizontal' === $button_layout ? 'buynow' : null;
		$data['button_size']  = 'vertical' === $button_layout && 'small' === $data['button_size']
			? 'medium'
			: $data['button_size'];

		if ( ! wc_gateway_ppec_is_credit_supported() ) {
			$data['credit_enabled'] = 'no';
			if ( ! is_array( $data['hide_funding_methods'] ) ) {
				$data['hide_funding_methods'] = array( 'CREDIT' );
			} elseif ( ! in_array( 'CREDIT', $data['hide_funding_methods'] ) ) {
				$data['hide_funding_methods'][] = 'CREDIT';
			}
		}

		if ( 'vertical' === $button_layout ) {
			$data['disallowed_methods'] = $data['hide_funding_methods'];
		} else {
			$data['allowed_methods'] = 'yes' === $data['credit_enabled'] ? array( 'CREDIT' ) : array();
		}
		unset( $data['hide_funding_methods'], $data['credit_enabled'] );

		return $data;
	}

	/**
	 * Frontend scripts
	 */
	public function enqueue_scripts() {
		$settings = wc_gateway_ppec()->settings;
		$client   = wc_gateway_ppec()->client;

		wp_enqueue_style( 'wc-gateway-ppec-frontend-cart', wc_gateway_ppec()->plugin_url . 'assets/css/wc-gateway-ppec-frontend-cart.css' );

		$is_cart     = is_cart() && ! WC()->cart->is_empty() && 'yes' === $settings->cart_checkout_enabled;
		$is_product  = is_product() && 'yes' === $settings->checkout_on_single_product_enabled;
		$is_checkout = is_checkout() && 'yes' === $settings->mark_enabled && ! wc_gateway_ppec()->checkout->has_active_session();
		$page        = $is_cart ? 'cart' : ( $is_product ? 'product' : ( $is_checkout ? 'checkout' : null ) );

		if ( 'yes' !== $settings->use_spb && $is_cart ) {
			wp_enqueue_script( 'paypal-checkout-js', 'https://www.paypalobjects.com/api/checkout.js', array(), null, true );
			wp_enqueue_script( 'wc-gateway-ppec-frontend-in-context-checkout', wc_gateway_ppec()->plugin_url . 'assets/js/wc-gateway-ppec-frontend-in-context-checkout.js', array( 'jquery' ), wc_gateway_ppec()->version, true );
			wp_localize_script( 'wc-gateway-ppec-frontend-in-context-checkout', 'wc_ppec_context',
				array(
					'payer_id'    => $client->get_payer_id(),
					'environment' => $settings->get_environment(),
					'locale'      => $settings->get_paypal_locale(),
					'start_flow'  => esc_url( add_query_arg( array( 'startcheckout' => 'true' ), wc_get_page_permalink( 'cart' ) ) ),
					'show_modal'  => apply_filters( 'woocommerce_paypal_express_checkout_show_cart_modal', true ),
					'update_shipping_costs_nonce' => wp_create_nonce( '_wc_ppec_update_shipping_costs_nonce' ),
					'ajaxurl'     => WC_AJAX::get_endpoint( 'wc_ppec_update_shipping_costs' ),
				)
			);

		} elseif ( 'yes' === $settings->use_spb ) {
			wp_enqueue_script( 'paypal-checkout-js', 'https://www.paypalobjects.com/api/checkout.js', array(), null, true );
			wp_enqueue_script( 'wc-gateway-ppec-smart-payment-buttons', wc_gateway_ppec()->plugin_url . 'assets/js/wc-gateway-ppec-smart-payment-buttons.js', array( 'jquery', 'paypal-checkout-js' ), wc_gateway_ppec()->version, true );

			$data = array(
				'environment'          => 'sandbox' === $settings->get_environment() ? 'sandbox' : 'production',
				'locale'               => $settings->get_paypal_locale(),
				'page'                 => $page,
				'button_color'         => $settings->button_color,
				'button_shape'         => $settings->button_shape,
				'start_checkout_nonce' => wp_create_nonce( '_wc_ppec_start_checkout_nonce' ),
				'start_checkout_url'   => WC_AJAX::get_endpoint( 'wc_ppec_start_checkout' ),
			);

			if ( ! is_null(  $page ) ) {
				if ( 'product' === $page && 'yes' === $settings->single_product_settings_toggle ) {
					$button_settings = $this->get_button_settings( $settings, 'single_product' );
				} elseif ( 'checkout' === $page && 'yes' === $settings->mark_settings_toggle ) {
					$button_settings = $this->get_button_settings( $settings, 'mark' );
				} else {
					$button_settings = $this->get_button_settings( $settings );
				}

				$data = array_merge( $data, $button_settings );
			}

			$settings_toggle = 'yes' === $settings->mini_cart_settings_toggle;
			$mini_cart_data  = $this->get_button_settings( $settings, $settings_toggle ? 'mini_cart' : '' );
			foreach( $mini_cart_data as $key => $value ) {
				unset( $mini_cart_data[ $key ] );
				$mini_cart_data[ 'mini_cart_' . $key ] = $value;
			}
			$data = array_merge( $data, $mini_cart_data );

			wp_localize_script( 'wc-gateway-ppec-smart-payment-buttons', 'wc_ppec_context', $data );
		}

		if ( $is_product ) {
			wp_enqueue_script( 'wc-gateway-ppec-generate-cart', wc_gateway_ppec()->plugin_url . 'assets/js/wc-gateway-ppec-generate-cart.js', array( 'jquery' ), wc_gateway_ppec()->version, true );
			wp_localize_script( 'wc-gateway-ppec-generate-cart', 'wc_ppec_generate_cart_context',
				array(
					'generate_cart_nonce' => wp_create_nonce( '_wc_ppec_generate_cart_nonce' ),
					'ajaxurl'             => WC_AJAX::get_endpoint( 'wc_ppec_generate_cart' ),
				)
			);
		}
	}

	/**
	 * Creates a customer session if one is not already active.
	 */
	public function ensure_session() {
		$frontend = ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' ) && ! defined( 'REST_REQUEST' );

		if ( ! $frontend ) {
			return;
		}

		if ( ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}
	}

	/**
	 * @deprecated
	 */
	public function loadCartDetails() {
		_deprecated_function( __METHOD__, '1.2.0', '' );
	}

	/**
	 * @deprecated
	 */
	public function loadOrderDetails( $order_id ) {
		_deprecated_function( __METHOD__, '1.2.0', '' );
	}

	/**
	 * @deprecated
	 */
	public function setECParams() {
		_deprecated_function( __METHOD__, '1.2.0', '' );
	}
}
