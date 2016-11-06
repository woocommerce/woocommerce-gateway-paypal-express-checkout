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
	 * Total cost of the transaction to the buyer.
	 *
	 * The value includes shipping cost and tax.
	 *
	 * @var float
	 */
	protected $order_total;

	/**
	 * Sum of tax for all items in this order.
	 *
	 * The value is set for PAYMENTREQUEST_0_TAXAMT param in SetExpressCheckout
	 * call.
	 *
	 * @var float
	 */
	protected $order_tax;

	/**
	 * Total shipping cost for this order.
	 *
	 * @var float
	 */
	protected $shipping;

	/**
	 * Total shipping insurance for this order.
	 *
	 * @var float
	 */
	protected $insurance;

	/**
	 * Total handling costs for this order.
	 *
	 * @var float
	 */
	protected $handling;

	/**
	 * Payment details item type fields.
	 *
	 * @var array
	 */
	protected $items;

	/**
	 * Sum of cost of all items from cart contents.
	 *
	 * @var float
	 */
	protected $total_item_amount;

	/**
	 * A 3-chars currency code.
	 *
	 * @var string
	 */
	protected $currency;

	/**
	 * A free-form field for custom thing.
	 *
	 * Currently just empty string.
	 *
	 * @var string
	 */
	protected $custom;

	/**
	 * Ivoice number.
	 *
	 * @var string
	 */
	protected $invoice_number;

	/**
	 * Shipping discount for this order, specified as negative number.
	 *
	 * @var float
	 */
	protected $ship_discount_amount;

	/**
	 * Currencies that support 0 decimal places -- "zero decimal place" currencies.
	 *
	 * @var array
	 */
	protected $zdp_currencies = array( 'HUF', 'JPY', 'TWD' );

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( ! wc_gateway_ppec()->settings->is_enabled() ) {
			return;
		}

		add_action( 'woocommerce_before_cart_totals', array( $this, 'before_cart_totals' ) );
		add_action( 'woocommerce_widget_shopping_cart_buttons', array( $this, 'display_mini_paypal_button' ), 20 );
		add_action( 'woocommerce_proceed_to_checkout', array( $this, 'display_paypal_button' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
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
	 * Display paypal button on the cart page.
	 */
	public function display_paypal_button() {

		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		$settings = wc_gateway_ppec()->settings;

		// billing details on checkout page to calculate shipping costs
		if ( ! isset( $gateways['ppec_paypal'] ) ) {
			return;
		}
		?>
		<div class="wcppec-checkout-buttons woo_pp_cart_buttons_div">

			<?php if ( has_action( 'woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout' ) ) : ?>
				<div class="wcppec-checkout-buttons__separator">
					<?php _e( '&mdash; or &mdash;', 'woocommerce-gateway-paypal-express-checkout' ); ?>
				</div>
			<?php endif; ?>

			<a href="<?php echo esc_url( add_query_arg( array( 'startcheckout' => 'true' ), wc_get_page_permalink( 'cart' ) ) ); ?>" id="woo_pp_ec_button" class="wcppec-checkout-buttons__button">
				<img src="<?php echo esc_url( 'https://www.paypalobjects.com/webstatic/en_US/i/buttons/checkout-logo-' . $settings->button_size . '.png' ); ?>" alt="<?php _e( 'Check out with PayPal', 'woocommerce-gateway-paypal-express-checkout' ); ?>" style="width: auto; height: auto;">
			</a>

			<?php if ( $settings->is_credit_enabled() ) : ?>
				<a href="<?php echo esc_url( add_query_arg( array( 'startcheckout' => 'true', 'use-ppc' => 'true' ), wc_get_page_permalink( 'cart' ) ) ); ?>" id="woo_pp_ppc_button" class="wcppec-checkout-buttons__button">
				<img src="<?php echo esc_url( 'https://www.paypalobjects.com/webstatic/en_US/i/buttons/ppcredit-logo-' . $settings->button_size . '.png' ); ?>" alt="<?php _e( 'Pay with PayPal Credit', 'woocommerce-gateway-paypal-express-checkout' ); ?>" style="width: auto; height: auto;">
				</a>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Display paypal button on the cart widget
	 */
	public function display_mini_paypal_button() {

		$gateways = WC()->payment_gateways->get_available_payment_gateways();

		// billing details on checkout page to calculate shipping costs
		if ( ! isset( $gateways['ppec_paypal'] ) ) {
			return;
		}
		?>
		<a href="<?php echo esc_url( add_query_arg( array( 'startcheckout' => 'true' ), wc_get_page_permalink( 'cart' ) ) ); ?>" id="woo_pp_ec_button" class="wcppec-cart-widget-button">
			<img src="<?php echo esc_url( 'https://www.paypalobjects.com/webstatic/en_US/i/btn/png/gold-rect-paypalcheckout-26px.png' ); ?>" alt="<?php _e( 'Check out with PayPal', 'woocommerce-gateway-paypal-express-checkout' ); ?>" style="width: auto; height: auto;">
		</a>
		<?php
	}

	/**
	 * Frontend scripts
	 */
	public function enqueue_scripts() {
		$settings = wc_gateway_ppec()->settings;
		$client   = wc_gateway_ppec()->client;

		if ( ! $client->get_payer_id() ) {
			return;
		}

		wp_enqueue_style( 'wc-gateway-ppec-frontend-cart', wc_gateway_ppec()->plugin_url . 'assets/css/wc-gateway-ppec-frontend-cart.css' );

		if ( is_cart() ) {
			wp_enqueue_script( 'paypal-checkout-js', 'https://www.paypalobjects.com/api/checkout.js', array(), '1.0', true );
			wp_enqueue_script( 'wc-gateway-ppec-frontend-in-context-checkout', wc_gateway_ppec()->plugin_url . 'assets/js/wc-gateway-ppec-frontend-in-context-checkout.js', array( 'jquery' ), wc_gateway_ppec()->version, true );
			wp_localize_script( 'wc-gateway-ppec-frontend-in-context-checkout', 'wc_ppec_context',
				array(
					'payer_id'    => $client->get_payer_id(),
					'environment' => $settings->get_environment(),
					'locale'      => $settings->get_paypal_locale(),
					'start_flow'  => esc_url( add_query_arg( array( 'startcheckout' => 'true' ), wc_get_page_permalink( 'cart' ) ) ),
					'show_modal'  => apply_filters( 'woocommerce_paypal_express_checkout_show_cart_modal', true ),
				)
			);
		}
	}

	/**
	 * @deprecated
	 */
	public function loadCartDetails() {
		_deprecated_function( __METHOD__, '1.2.0', 'WC_Gateway_PPEC_Cart_Handler::load_cart_details' );
		return $this->laod_cart_details();
	}

	/**
	 * Load cart details.
	 */
	public function load_cart_details() {

		$this->total_item_amount = 0;
		$this->items = array();

		// load all cart items into an array
		$roundedPayPalTotal = 0;

		$is_zdp_currency = in_array( get_woocommerce_currency(), $this->zdp_currencies );
		if ( $is_zdp_currency ) {
			$decimals = 0;
		} else {
			$decimals = 2;
		}

		$discounts = round( WC()->cart->get_cart_discount_total(), $decimals );
		foreach ( WC()->cart->cart_contents as $cart_item_key => $values ) {
			$amount = round( $values['line_subtotal'] / $values['quantity'] , $decimals );
			$item   = array(
				'name'        => $values['data']->post->post_title,
				'description' => $values['data']->post->post_content,
				'quantity'    => $values['quantity'],
				'amount'      => $amount,
			);

			$this->items[] = $item;

			$roundedPayPalTotal += round( $amount * $values['quantity'], $decimals );
		}

		$this->order_tax = round( WC()->cart->tax_total + WC()->cart->shipping_tax_total, $decimals );
		$this->shipping = round( WC()->cart->shipping_total, $decimals );
		$this->total_item_amount = round( WC()->cart->cart_contents_total, $decimals ) + $discounts;
		$this->order_total = round( $this->total_item_amount + $this->order_tax + $this->shipping, $decimals );

		// need to compare WC totals with what PayPal will calculate to see if they match
		// if they do not match, check to see what the merchant would like to do
		// options are to remove line items or add a line item to adjust for the difference
		if ( $this->total_item_amount != $roundedPayPalTotal ) {
			if ( 'add' === wc_gateway_ppec()->settings->get_subtotal_mismatch_behavior() ) {
				// ...
				// Add line item to make up different between WooCommerce calculations and PayPal calculations
				$cartItemAmountDifference = $this->total_item_amount - $roundedPayPalTotal;

				$modifyLineItem = array(
					'name'			=> 'Line Item Amount Offset',
					'description'	=> 'Adjust cart calculation discrepancy',
					'quantity'		=> 1,
					'amount'		=> round( $cartItemAmountDifference, $decimals )
					);

				$this->items[] = $modifyLineItem;
				$this->total_item_amount += $modifyLineItem[ 'amount' ];
				$this->order_total += $modifyLineItem[ 'amount' ];

			} else {
				// ...
				// Omit line items altogether
				unset($this->items);
			}

		}

		// enter discount shenanigans. item total cannot be 0 so make modifications accordingly
		if ( $this->total_item_amount == $discounts ) {
			// ...
			// Omit line items altogether
			unset($this->items);
			$this->ship_discount_amount = 0;
			$this->total_item_amount -= $discounts;
			$this->order_total -= $discounts;
		} else {
			// Build PayPal_Cart object as normal
			if ( $discounts > 0 ) {
				$discLineItem = array(
					'name'        => 'Discount',
					'description' => 'Discount Amount',
					'quantity'    => 1,
					'amount'      => '-' . $discounts
					);

				$this->items[] = $discLineItem;
			}

			$this->ship_discount_amount = 0;
			$this->total_item_amount -= $discounts;
			$this->order_total -= $discounts;
		}

		// If the totals don't line up, adjust the tax to make it work (cause it's probably a tax mismatch).
		$wooOrderTotal = round( WC()->cart->total, $decimals );
		if( $wooOrderTotal != $this->order_total ) {
			$this->order_tax += $wooOrderTotal - $this->order_total;
			$this->order_total = $wooOrderTotal;
		}

		$this->order_tax = round( $this->order_tax, $decimals );

		// after all of the discount shenanigans, load up the other standard variables
		$this->insurance = 0;
		$this->handling = 0;
		$this->currency = get_woocommerce_currency();
		$this->custom = '';
		$this->invoice_number = '';

		if ( ! is_numeric( $this->shipping ) ) {
			$this->shipping = 0;
		}
	}

	/**
	 * @deprecated
	 */
	public function loadOrderDetails( $order_id ) {
		_deprecated_function( __METHOD__, '1.2.0', 'WC_Gateway_PPEC_Cart_Handler::load_order_details' );
		return $this->load_order_details( $order_id );
	}

	/**
	 * Load order details from given order_id.
	 *
	 * @param int $order_id Order ID
	 */
	public function load_order_details( $order_id ) {

		$order = wc_get_order( $order_id );
		$this->total_item_amount = 0;
		$this->items = array();

		// load all cart items into an array
		$roundedPayPalTotal = 0;

		$is_zdp_currency = in_array( get_woocommerce_currency(), $this->zdp_currencies );
		if ( $is_zdp_currency ) {
			$decimals = 0;
		} else {
			$decimals = 2;
		}

		$discounts = round( $order->get_total_discount(), $decimals );
		foreach ( $order->get_items() as $cart_item_key => $values ) {
			$amount = round( $values['line_subtotal'] / $values['qty'] , $decimals );
			$item   = array(
				'name'     => $values['name'],
				'quantity' => $values['qty'],
				'amount'   => $amount,
			);

			$this->items[] = $item;

			$roundedPayPalTotal += round( $amount * $values['qty'], $decimals );
		}

		$this->order_tax = round( $order->get_total_tax(), $decimals );
		$this->shipping = round( $order->get_total_shipping(), $decimals );
		// if ( $order->get_shipping_tax() != 0 ) {
		// 	$this->shipping += round( $order->get_shipping_tax(), $decimals );
		// }
		$this->total_item_amount = round( $order->get_subtotal(), $decimals );
		$this->order_total = round( $this->total_item_amount + $this->order_tax + $this->shipping, $decimals );

		// need to compare WC totals with what PayPal will calculate to see if they match
		// if they do not match, check to see what the merchant would like to do
		// options are to remove line items or add a line item to adjust for the difference
		if ( $this->total_item_amount != $roundedPayPalTotal ) {
			if ( 'add' === wc_gateway_ppec()->settings->get_subtotal_mismatch_behavior() ) {
				// ...
				// Add line item to make up different between WooCommerce calculations and PayPal calculations
				$cartItemAmountDifference = $this->total_item_amount - $roundedPayPalTotal;

				$modifyLineItem = array(
					'name'			=> 'Line Item Amount Offset',
					'description'	=> 'Adjust cart calculation discrepancy',
					'quantity'		=> 1,
					'amount'		=> round( $cartItemAmountDifference, $decimals )
					);

				$this->items[] = $modifyLineItem;

			} else {
				// ...
				// Omit line items altogether
				unset($this->items);
			}

		}

		// enter discount shenanigans. item total cannot be 0 so make modifications accordingly
		if ( $this->total_item_amount == $discounts ) {
			// Omit line items altogether
			unset($this->items);
			$this->ship_discount_amount = 0;
			$this->total_item_amount -= $discounts;
			$this->order_total -= $discounts;
		} else {
			// Build PayPal_Cart object as normal
			if ( $discounts > 0 ) {
				$discLineItem = array(
					'name'        => 'Discount',
					'description' => 'Discount Amount',
					'quantity'    => 1,
					'amount'      => '-' . $discounts
					);

				$this->items[] = $discLineItem;
				$this->total_item_amount -= $discounts;
				$this->order_total -= $discounts;
			}

			$this->ship_discount_amount = 0;
		}

		// If the totals don't line up, adjust the tax to make it work (cause it's probably a tax mismatch).
		$wooOrderTotal = round( $order->get_total(), $decimals );
		if( $wooOrderTotal != $this->order_total ) {
			$this->order_tax += $wooOrderTotal - $this->order_total;
			$this->order_total = $wooOrderTotal;
		}

		$this->order_tax = round( $this->order_tax, $decimals );

		// after all of the discount shenanigans, load up the other standard variables
		$this->insurance = 0;
		$this->handling = 0;
		$this->currency = get_woocommerce_currency();
		$this->custom = '';
		$this->invoice_number = '';

		if ( ! is_numeric( $this->shipping ) ) {
			$this->shipping = 0;
		}
	}

	/**
	 * @deprecated
	 */
	public function setECParams() {
		_deprecated_function( __METHOD__, '1.2.0', 'WC_Gateway_PPEC_Cart_Handler::get_set_express_checkout_params' );
		return $this->get_set_express_checkout_params();
	}

	/**
	 * Get parameters for SetExpressCheckout call.
	 *
	 * @todo Merge this method with getter in client wrapper.
	 *
	 * @return array Params for SetExpressCheckout call
	 */
	public function get_set_express_checkout_params() {
		$stdParams = array (
			'PAYMENTREQUEST_0_AMT'          => $this->order_total,
			'PAYMENTREQUEST_0_CURRENCYCODE' => $this->currency,
			'PAYMENTREQUEST_0_ITEMAMT'      => $this->total_item_amount,
			'PAYMENTREQUEST_0_SHIPPINGAMT'  => $this->shipping,
			'PAYMENTREQUEST_0_INSURANCEAMT' => $this->insurance,
			'PAYMENTREQUEST_0_HANDLINGAMT'  => $this->handling,
			'PAYMENTREQUEST_0_TAXAMT'       => $this->order_tax,
			'PAYMENTREQUEST_0_CUSTOM'       => $this->custom,
			'PAYMENTREQUEST_0_INVNUM'       => $this->invoice_number,
			'PAYMENTREQUEST_0_SHIPDISCAMT'  => $this->ship_discount_amount,
			'NOSHIPPING'                    => WC()->cart->needs_shipping() ? 0 : 1,
		);

		if ( ! empty( $this->items ) ) {
			$count = 0;
			foreach ( $this->items as $line_item_key => $values ) {
				$lineItemParams = array(
					'L_PAYMENTREQUEST_0_NAME' . $count => $values['name'],
					'L_PAYMENTREQUEST_0_DESC' . $count => ! empty( $values['description'] ) ? strip_tags( $values['description'] ) : '',
					'L_PAYMENTREQUEST_0_QTY' . $count  => $values['quantity'],
					'L_PAYMENTREQUEST_0_AMT' . $count  => $values['amount']
				);

				$stdParams = array_merge( $stdParams, $lineItemParams );
				$count++;
			}
		}
		return $stdParams;
	}
}
