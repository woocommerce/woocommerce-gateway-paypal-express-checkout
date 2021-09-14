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

<tr class="plugin-update-tr active notice-warning notice-alt"  id="ppec-migrate-notice" data-dismiss-nonce="<?php echo esc_attr( wp_create_nonce( 'ppec-upgrade-notice-dismiss' ) ); ?>">
	<td colspan="4" class="plugin-update colspanchange">
		<div class="notice notice-error inline update-message notice-alt is-dismissible">
			<div class='ppec-notice-title ppec-notice-section'>
				<p><strong>Action Required: Switch to WooCommerce PayPal Payments</strong></p>
			</div>
			<div class='ppec-notice-content ppec-notice-section'>
				<p>As of 1 Sept 2021, PayPal Checkout is officially retired from WooCommerce.com, and support for this product will end as of 1 March 2022.</p>
				<p>We highly recommend upgrading to <a href="https://woocommerce.com/products/woocommerce-paypal-payments/" target="_blank">PayPal Payments</a>, the latest, fully supported extension that includes all of the features of PayPal Checkout and more.</p>
			</div>
			<div class='ppec-notice-buttons ppec-notice-section hidden'>
				<?php //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<a id="ppec-install-paypal-payments" href="<?php echo $paypal_payments_install_link; ?>" class="button button-primary">Upgrade to PayPal Payments now</a>
				<?php //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<a id="ppec-activate-paypal-payments" href="<?php echo $paypal_payments_activate_link; ?>" class="button button-primary">Activate PayPal Payments now</a>
				<a href="https://docs.woocommerce.com/document/woocommerce-paypal-payments/paypal-payments-upgrade-guide/" target="_blank" class="button woocommerce-save-button">Learn more</a>
			</div>
		</div>
	</td>
</tr>
