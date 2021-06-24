<?php
/**
 * Show PayPal Payments upgrade notice on plugins page
 *
 * @package woocommerce-paypal-express-checkout/templates
 */


// Generate Install / Activation / Config links.
$paypal_payments_path         = 'woocommerce-paypal-payments/woocommerce-paypal-payments.php';
$paypal_payments_install_link = wp_nonce_url(
	add_query_arg(
		array(
			'action' => 'install-plugin',
			'plugin' => dirname( $paypal_payments_path ),
		),
		admin_url( 'update.php' )
	),
	'install-plugin_' . dirname( $paypal_payments_path )
);

$paypal_payments_activate_link = wp_nonce_url(
	add_query_arg(
		array(
			'action' => 'activate',
			'plugin' => $paypal_payments_path,
		),
		admin_url( 'plugins.php' )
	),
	'activate-plugin_' . $paypal_payments_path
);
?>

<tr class="plugin-update-tr active notice-warning notice-alt"  id="ppec-migrate-notice">
	<td colspan="4" class="plugin-update colspanchange">
		<div class="update-message notice inline notice-warning notice-alt">
			<div class='ppec-notice-title ppec-notice-section'>
				<p>Upgrade to PayPal Payments: the best way to get paid with PayPal and WooCommerce</p>
			</div>
			<div class='ppec-notice-content ppec-notice-section'>
				<p><strong>WooCommerce PayPal Payments</strong> is a full-stack solution that offers powerful and flexible payment processing capabilities. Expand your business by connecting with over 370+ million active PayPal accounts around the globe. With PayPal, you can sell in 200+ markets and accept 100+ currencies. Plus, PayPal can automatically identify customer locations and offer country-specific, local payment methods.</p>

				<p>Upgrade now and get access to these great features:</p>

				<ul>
					<li>Give your customers their preferred ways to pay with one checkout solution. Accept <strong>PayPal</strong>, <strong>PayPal Credit</strong>, <strong>Pay Later</strong> options (available in the US, UK, France, and Germany), <strong>credit & debit cards</strong>, and country-specific, <strong>local payment methods</strong> on any device.</li>
					<li>Offer subscriptions and accept recurring payments as PayPal is compatible with <a target="_blank" href="https://woocommerce.com/products/woocommerce-subscriptions/"><strong>WooCommerce Subscriptions</strong></a>.</li>
				</ul>
			</div>
			<div class='ppec-notice-buttons ppec-notice-section hidden'>
				<?php //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<a id="ppec-install-paypal-payments" href="<?php echo $paypal_payments_install_link; ?>" class="button button-primary woocommerce-save-button">Upgrade to PayPal Payments now</a>
				<?php //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<a id="ppec-activate-paypal-payments" href="<?php echo $paypal_payments_activate_link; ?>" class="button button-primary woocommerce-save-button">Activate PayPal Payments now</a>
				<a href="https://woocommerce.com/products/woocommerce-paypal-payments/" target="_blank" class="button woocommerce-save-button">Learn more</a>
			</div>
		</div>
	</td>
</tr>
