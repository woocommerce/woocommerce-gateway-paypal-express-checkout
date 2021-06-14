<?php
/**
 * Show PayPal Payments upgrade notice on plugins page
 *
 * @package woocommerce-paypal-express-checkout/templates
 */

// Generate Install / Activation / Config links.
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

// Template for rendering buttons.
$button_data = array(
	'id'         => null,
	'href'       => null,
	'text'       => null,
	'attributes' => array(),
);

if ( $is_active_paypal_payments && $is_active_paypal_payments ) {

	// PayPal Payments installed and active.
	$button_data['id']   = 'ppec-configure-paypal-payments';
	$button_data['href'] = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway' );
	$button_data['text'] = 'Configure PayPal Payments now';

} elseif ( $is_installed_paypal_payments ) {

	// PayPal Payments installed, but inactive.
	$button_data['id']         = 'ppec-activate-paypal-payments';
	$button_data['href']       = $paypal_payments_activate_link;
	$button_data['text']       = 'Activate PayPal Payments now';
	$button_data['attributes'] = array(
		'data-install-id'   => 'ppec-install-paypal-payments',
		'data-install-text' => 'Upgrade to PayPal Payments now',
		'data-install-link' => $paypal_payments_install_link,
	);

} else {

	// PayPal Payments is not installed.
	$button_data['id']   = 'ppec-install-paypal-payments';
	$button_data['href'] = $paypal_payments_install_link;
	$button_data['text'] = 'Upgrade to PayPal Payments now';

}
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
			<div class='ppec-notice-buttons ppec-notice-section'>
				<?php
				$extra_attributes = '';
				if ( ! empty( $button_data['attributes'] ) ) {
					$extra_attributes = implode(
						' ',
						array_map(
							function ( $key, $value ) {
								return $key . '="' . $value . '"';
							},
							array_keys( $button_data['attributes'] ),
							$button_data['attributes']
						)
					);
				}
				?>
				<?php //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<a id="<?php echo $button_data['id']; ?>" href="<?php echo $button_data['href']; ?>" <?php echo $extra_attributes; ?> class="button button-primary woocommerce-save-button"><?php echo $button_data['text']; ?></a>
				<a href="https://woocommerce.com/products/woocommerce-paypal-payments/" target="_blank" class="button woocommerce-save-button">Learn more</a>
			</div>
		</div>
	</td>
</tr>
