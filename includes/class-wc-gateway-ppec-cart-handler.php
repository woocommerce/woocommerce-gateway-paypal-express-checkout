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
	 * TODO rename this to underscore var names
	 */
	protected $orderTotal;
	protected $orderTax;
	protected $shipping;
	protected $insurance;
	protected $handling;
	protected $items;
	protected $totalItemAmount;
	protected $currency;
	protected $custom;
	protected $invoiceNumber;
	protected $shipDiscountAmount;

	/**
	 * Currencies that support 0 decimal places -- "zero decimal place" currencies
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

	public function before_cart_totals() {
		// If there then call start_checkout() else do nothing so page loads as normal.
		if ( ! empty( $_GET['startcheckout'] ) && 'true' === $_GET['startcheckout'] ) {
			// Trying to prevent auto running checkout when back button is pressed from PayPal page.
			$_GET['startcheckout'] = 'false';
			woo_pp_start_checkout();
		}
	}

	/**
	 * Display paypal button on the cart page
	 */
	public function display_paypal_button() {
		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		$settings = wc_gateway_ppec()->settings;

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
		</div>
		<?php
	}

	/**
	 * Display paypal button on the cart widget
	 */
	public function display_mini_paypal_button() {
		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		$settings = wc_gateway_ppec()->settings;

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
				)
			);
		}
	}

	/**
	 * Load cart details.
	 */
	public function loadCartDetails() {

		$this->totalItemAmount = 0;
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

		$this->orderTax = round( WC()->cart->tax_total + WC()->cart->shipping_tax_total, $decimals );
		$this->shipping = round( WC()->cart->shipping_total, $decimals );
		$this->totalItemAmount = round( WC()->cart->cart_contents_total, $decimals ) + $discounts;
		$this->orderTotal = round( $this->totalItemAmount + $this->orderTax + $this->shipping, $decimals );

		// need to compare WC totals with what PayPal will calculate to see if they match
		// if they do not match, check to see what the merchant would like to do
		// options are to remove line items or add a line item to adjust for the difference
		if ( $this->totalItemAmount != $roundedPayPalTotal ) {
			if ( 'add' === wc_gateway_ppec()->settings->get_subtotal_mismatch_behavior() ) {
				// ...
				// Add line item to make up different between WooCommerce calculations and PayPal calculations
				$cartItemAmountDifference = $this->totalItemAmount - $roundedPayPalTotal;

				$modifyLineItem = array(
					'name'			=> 'Line Item Amount Offset',
					'description'	=> 'Adjust cart calculation discrepancy',
					'quantity'		=> 1,
					'amount'		=> round( $cartItemAmountDifference, $decimals )
					);

				$this->items[] = $modifyLineItem;
				$this->totalItemAmount += $modifyLineItem[ 'amount' ];
				$this->orderTotal += $modifyLineItem[ 'amount' ];

			} else {
				// ...
				// Omit line items altogether
				unset($this->items);
			}

		}

		// enter discount shenanigans. item total cannot be 0 so make modifications accordingly
		if ( $this->totalItemAmount == $discounts ) {
			// ...
			// Omit line items altogether
			unset($this->items);
			$this->shipDiscountAmount = 0;
			$this->totalItemAmount -= $discounts;
			$this->orderTotal -= $discounts;
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

			$this->shipDiscountAmount = 0;
			$this->totalItemAmount -= $discounts;
			$this->orderTotal -= $discounts;
		}

		// If the totals don't line up, adjust the tax to make it work (cause it's probably a tax mismatch).
		$wooOrderTotal = round( WC()->cart->total, $decimals );
		if( $wooOrderTotal != $this->orderTotal ) {
			$this->orderTax += $wooOrderTotal - $this->orderTotal;
			$this->orderTotal = $wooOrderTotal;
		}

		$this->orderTax = round( $this->orderTax, $decimals );

		// after all of the discount shenanigans, load up the other standard variables
		$this->insurance = 0;
		$this->handling = 0;
		$this->currency = get_woocommerce_currency();
		$this->custom = '';
		$this->invoiceNumber = '';

		if ( ! is_numeric( $this->shipping ) ) {
			$this->shipping = 0;
		}
	}

	public function loadOrderDetails( $order_id ) {

		$order = wc_get_order( $order_id );
		$this->totalItemAmount = 0;
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

		$this->orderTax = round( $order->get_total_tax(), $decimals );
		$this->shipping = round( $order->get_total_shipping(), $decimals );
		// if ( $order->get_shipping_tax() != 0 ) {
		// 	$this->shipping += round( $order->get_shipping_tax(), $decimals );
		// }
		$this->totalItemAmount = round( $order->get_subtotal(), $decimals );
		$this->orderTotal = round( $this->totalItemAmount + $this->orderTax + $this->shipping, $decimals );

		// need to compare WC totals with what PayPal will calculate to see if they match
		// if they do not match, check to see what the merchant would like to do
		// options are to remove line items or add a line item to adjust for the difference
		if ( $this->totalItemAmount != $roundedPayPalTotal ) {
			if ( 'add' === wc_gateway_ppec()->settings->get_subtotal_mismatch_behavior() ) {
				// ...
				// Add line item to make up different between WooCommerce calculations and PayPal calculations
				$cartItemAmountDifference = $this->totalItemAmount - $roundedPayPalTotal;

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
		if ( $this->totalItemAmount == $discounts ) {
			// Omit line items altogether
			unset($this->items);
			$this->shipDiscountAmount = 0;
			$this->totalItemAmount -= $discounts;
			$this->orderTotal -= $discounts;
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
				$this->totalItemAmount -= $discounts;
				$this->orderTotal -= $discounts;
			}

			$this->shipDiscountAmount = 0;
		}

		// If the totals don't line up, adjust the tax to make it work (cause it's probably a tax mismatch).
		$wooOrderTotal = round( $order->get_total(), $decimals );
		if( $wooOrderTotal != $this->orderTotal ) {
			$this->orderTax += $wooOrderTotal - $this->orderTotal;
			$this->orderTotal = $wooOrderTotal;
		}

		$this->orderTax = round( $this->orderTax, $decimals );

		// after all of the discount shenanigans, load up the other standard variables
		$this->insurance = 0;
		$this->handling = 0;
		$this->currency = get_woocommerce_currency();
		$this->custom = '';
		$this->invoiceNumber = '';

		if ( ! is_numeric( $this->shipping ) ) {
			$this->shipping = 0;
		}
	}

	public function setECParams() {
		$stdParams = array (
			'PAYMENTREQUEST_0_AMT'          => $this->orderTotal,
			'PAYMENTREQUEST_0_CURRENCYCODE' => $this->currency,
			'PAYMENTREQUEST_0_ITEMAMT'      => $this->totalItemAmount,
			'PAYMENTREQUEST_0_SHIPPINGAMT'  => $this->shipping,
			'PAYMENTREQUEST_0_INSURANCEAMT' => $this->insurance,
			'PAYMENTREQUEST_0_HANDLINGAMT'  => $this->handling,
			'PAYMENTREQUEST_0_TAXAMT'       => $this->orderTax,
			'PAYMENTREQUEST_0_CUSTOM'       => $this->custom,
			'PAYMENTREQUEST_0_INVNUM'       => $this->invoiceNumber,
			'PAYMENTREQUEST_0_SHIPDISCAMT'  => $this->shipDiscountAmount,
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
