<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

require_once( 'lib/class-cart.php' );

class WooCommerce_PayPal_Cart extends PayPal_Cart {

	// Currencies that support 0 decimal places -- "zero decimal place" currencies
	protected $zdp_currencies = array( 'HUF', 'JPY', 'TWD' );

	public function loadCartDetails() {
		global $woocommerce;
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

		$discounts = round( $woocommerce->cart->get_order_discount_total(), $decimals );
		foreach ( $woocommerce->cart->cart_contents as $cart_item_key => $values ) {
			$amount = round( $values['line_total'] / $values['quantity'] , $decimals );
			$item   = array(
				'name'        => $values['data']->post->post_title,
				'description' => $values['data']->post->post_content,
				'quantity'    => $values['quantity'],
				'amount'      => $amount,
			);

			$this->items[] = $item;

			$roundedPayPalTotal += round( $amount * $values['quantity'], $decimals );
		}

		$this->orderTax = round( $woocommerce->cart->tax_total, $decimals );
		$this->shipping = round( $woocommerce->cart->shipping_total, $decimals );
		if ( $woocommerce->cart->shipping_tax_total != 0 ) {
			$this->orderTax += round( $woocommerce->cart->shipping_tax_total, $decimals );
		}
		$this->totalItemAmount = round( $woocommerce->cart->cart_contents_total, $decimals );
		$this->orderTotal = $this->totalItemAmount + $this->orderTax + $this->shipping;

		// need to compare WC totals with what PayPal will calculate to see if they match
		// if they do not match, check to see what the merchant would like to do
		// options are to remove line items or add a line item to adjust for the difference
		if ( $this->totalItemAmount != $roundedPayPalTotal ) {
			$settings = new WooCommerce_PayPal_Settings();
			$settings->loadSettings();
			$subtotalBehavior = $settings->subtotalMismatchBehavior;

			if ( WooCommerce_PayPal_Settings::subtotalMismatchBehaviorAddLineItem == $subtotalBehavior ) {
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

			} elseif ( WooCommerce_PayPal_Settings::subtotalMismatchBehaviorDropLineItems == $subtotalBehavior ) {
				// ...
				// Omit line items altogether
				unset($this->items);
			}

		}

		// enter discount shenanigans. item total cannot be 0 so make modifications accordingly
		if ( $this->totalItemAmount == $discounts ) {
			$settings = new WooCommerce_PayPal_Settings();
			$settings->loadSettings();
			$behavior = $settings->zeroSubtotalBehavior;

			if ( WooCommerce_PayPal_Settings::zeroSubtotalBehaviorModifyItems == $behavior ) {
				// ...
				// Go ahead and pass the discounts with the cart, but then add in a 0.01 line
				// item and add a 0.01 shipping discount.
				$discountLineItem = array(
					'name'        => 'Discount',
					'description' => 'Discount Amount',
					'quantity'    => 1,
					'amount'      => $discounts
				);

				$this->items[] = $discountLineItme;

				if ( $is_zdp_currency ) {
					$discount = 1;
				} else {
					$discount = 0.01;
				}

				$modifyLineItem = array(
					'name'          => 'Discount Offset',
					'description'   => 'Amount Discounted in Shipping',
					'quantity'      => 1,
					'amount'        => $discount
				);

				$this->items[] = $modifyLineItem;
				$this->shipDiscountAmount = $discount;

			} elseif ( WooCommerce_PayPal_Settings::zeroSubtotalBehaviorOmitLineItems == $behavior ) {
				// ...
				// Omit line items altogether
				unset($this->items);
				$this->shipDiscountAmount = 0;

			} else {
				// ...
				// Increase SHIPDISCAMT by the amount of all the coupons in the cart
				$this->shipDiscountAmount = round( $woocommerce->cart->get_order_discount_total(), $decimals );

			}
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
		}

		// after all of the discount shenanigans, load up the other standard variables
		$this->insurance = 0;
		$this->handling = 0;
		$this->currency = get_woocommerce_currency();
		$this->custom = '';
		$this->invoiceNumber = '';

		if ( ! is_numeric( $this->shipping ) )
			$this->shipping = 0;

	}

	public function loadOrderDetails( $order_id ) {
		global $woocommerce;

		$order = new WC_Order( $order_id );
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
			$amount = round( $values['line_total'] / $values['qty'] , $decimals );
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
		$this->orderTotal = $this->totalItemAmount + $this->orderTax + $this->shipping;

		// need to compare WC totals with what PayPal will calculate to see if they match
		// if they do not match, check to see what the merchant would like to do
		// options are to remove line items or add a line item to adjust for the difference
		if ( $this->totalItemAmount != $roundedPayPalTotal ) {
			$settings = new WooCommerce_PayPal_Settings();
			$settings->loadSettings();
			$subtotalBehavior = $settings->subtotalMismatchBehavior;

			if ( WooCommerce_PayPal_Settings::subtotalMismatchBehaviorAddLineItem == $subtotalBehavior ) {
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

			} elseif ( WooCommerce_PayPal_Settings::subtotalMismatchBehaviorDropLineItems == $subtotalBehavior ) {
				// ...
				// Omit line items altogether
				unset($this->items);
			}

		}

		// enter discount shenanigans. item total cannot be 0 so make modifications accordingly
		if ( $this->totalItemAmount == $discounts ) {
			$settings = new WooCommerce_PayPal_Settings();
			$settings->loadSettings();
			$behavior = $settings->zeroSubtotalBehavior;

			if ( WooCommerce_PayPal_Settings::zeroSubtotalBehaviorModifyItems == $behavior ) {
				// ...
				// Go ahead and pass the discounts with the cart, but then add in a 0.01 line
				// item and add a 0.01 shipping discount.
				$discountLineItem = array(
					'name'        => 'Discount',
					'description' => 'Discount Amount',
					'quantity'    => 1,
					'amount'      => $discounts
				);

				$this->items[] = $discountLineItme;

				if ( $is_zdp_currency ) {
					$discount = 1;
				} else {
					$discount = 0.01;
				}

				$modifyLineItem = array(
					'name'          => 'Discount Offset',
					'description'   => 'Amount Discounted in Shipping',
					'quantity'      => 1,
					'amount'        => $discount
				);

				$this->items[] = $modifyLineItem;
				$this->shipDiscountAmount = $discount;

			} elseif ( WooCommerce_PayPal_Settings::zeroSubtotalBehaviorOmitLineItems == $behavior ) {
				// ...
				// Omit line items altogether
				unset($this->items);
				$this->shipDiscountAmount = 0;

			} else {
				// ...
				// Increase SHIPDISCAMT by the amount of all the coupons in the cart
				$this->shipDiscountAmount = round( $woocommerce->cart->get_order_discount_total(), $decimals );

			}
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
		}

		// after all of the discount shenanigans, load up the other standard variables
		$this->insurance = 0;
		$this->handling = 0;
		$this->currency = get_woocommerce_currency();
		$this->custom = '';
		$this->invoiceNumber = '';

		if ( ! is_numeric( $this->shipping ) )
			$this->shipping = 0;

	}

}
