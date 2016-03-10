<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

require_once( 'lib/class-settings.php' );
require_once( 'lib/class-signature.php' );
require_once( 'lib/class-certificate.php' );

class WC_Gateway_PPEC_Settings extends PayPal_Settings {

	const zeroSubtotalBehaviorModifyItems                   = 'modifyItems';
	const zeroSubtotalBehaviorOmitLineItems                 = 'omitLineItems';
	const zeroSubtotalBehaviorPassCouponsAsShippingDiscount = 'passCouponsAsShippingDiscount';

	const subtotalMismatchBehaviorAddLineItem   = 'addLineItem';
	const subtotalMismatchBehaviorDropLineItems = 'dropLineItems';

	const buttonSizeSmall  = 'small';
	const buttonSizeMedium = 'medium';
	const buttonSizeLarge  = 'large';

	const markSizeSmall  = 'small';
	const markSizeMedium = 'medium';
	const markSizeLarge  = 'large';

	/**
	 * Flag to indicate setting has been loaded from DB.
	 *
	 * @var bool
	 */
	private $_is_setting_loaded = false;

	public function __construct() {
		$this->validParams = array_merge( $this->validParams, array(
			'enabled',
			'ppcEnabled',
			'buttonSize',
			'markSize',
			'zeroSubtotalBehavior',
			'subtotalMismatchBehavior',
			'liveAccountIsEnabledForBillingAddress',
			'sbAccountIsEnabledForBillingAddress',
			'ipsPrivateKey'
		) );
	}

	public function getSetECShortcutParameters() {
		return $this->getBaseSetECShortcutParameters();
	}

	public function getSetECMarkParameters() {
		return $this->getBaseSetECMarkParameters();
	}
	public function getDoECParameters() {
		return $this->getBaseDoECParameters();
	}

	protected function _sanitize_zeroSubtotalBehavior( $behavior ) {
		if ( self::zeroSubtotalBehaviorModifyItems == $behavior ||
				self::zeroSubtotalBehaviorOmitLineItems == $behavior ||
				self::zeroSubtotalBehaviorPassCouponsAsShippingDiscount == $behavior ) {
			return $behavior;
		} else {
			return self::zeroSubtotalBehaviorModifyItems;
		}
	}

	protected function _sanitize_subtotalMismatchBehavior( $behavior ) {
		if ( self::subtotalMismatchBehaviorAddLineItem == $behavior ||
				self::subtotalMismatchBehaviorDropLineItems == $behavior ) {
			return $behavior;
		} else {
			return self::subtotalMismatchBehaviorAddLineItem;
		}
	}

	protected function _sanitize_buttonSize( $size ) {
		if ( self::buttonSizeSmall == $size ||
				self::buttonSizeMedium == $size ||
				self::buttonSizeLarge == $size ) {
			return $size;
		} else {
			return self::buttonSizeMedium;
		}
	}

	protected function _sanitize_markSize( $size ) {
		if ( self::markSizeSmall == $size ||
				self::markSizeMedium == $size ||
				self::markSizeLarge == $size ) {
			return $size;
		} else {
			return self::markSizeSmall;
		}
	}

	/**
	 * Load settings from DB.
	 *
	 * @param bool $force_reload Force reload, ignore
	 *
	 * @return WC_Gateway_PPEC_Settings Instance of WC_Gateway_PPEC_Settings
	 */
	public function load_settings( $force_reload = false ) {

		if ( $this->_is_setting_loaded && ! $force_reload ) {
			return $this;

			$this->enabled                               = get_option( 'pp_woo_enabled' );
			$this->ppcEnabled                            = get_option( 'pp_woo_ppc_enabled' );
			$this->buttonSize                            = get_option( 'pp_woo_button_size' );
			$this->markSize                              = get_option( 'pp_woo_mark_size' );
			$this->liveApiCredentials                    = get_option( 'pp_woo_liveApiCredentials' );
			$this->sandboxApiCredentials                 = get_option( 'pp_woo_sandboxApiCredentials' );
			$this->environment                           = get_option( 'pp_woo_environment' );
			$thi->logoImageUrl                           = get_option( 'pp_woo_logoImageUrl' );
			$this->ipnUrl                                = get_option( 'pp_woo_ipnUrl' );
			$this->paymentAction                         = get_option( 'pp_woo_paymentAction' );
			$this->allowGuestCheckout                    = get_option( 'pp_woo_allowGuestCheckout' );
			$this->blockEChecks                          = get_option( 'pp_woo_blockEChecks' );
			$this->requireBillingAddress                 = get_option( 'pp_woo_requireBillingAddress' );
			$this->zeroSubtotalBehavior                  = get_option( 'pp_woo_zeroSubtotalBehavior' );
			$this->subtotalMismatchBehavior              = get_option( 'pp_woo_subtotalMismatchBehavior' );
			$this->enableInContextCheckout               = get_option( 'pp_woo_enableInContextCheckout' );
			$this->liveAccountIsEnabledForBillingAddress = get_option( 'pp_woo_liveAccountIsEnabledForBillingAddress' );
			$this->sbAccountIsEnabledForBillingAddress   = get_option( 'pp_woo_sbAccountIsEnabledForBillingAddress' );
			$this->ipsPrivateKey                         = get_option( 'pp_woo_ipsPrivateKey' );

			$this->_is_setting_loaded = true;

			return $this;
		}
	}
	public function saveSettings() {
		update_option( 'pp_woo_enabled'                              , serialize( $this->enabled                               ) );
		update_option( 'pp_woo_ppc_enabled'                          , serialize( $this->ppcEnabled                            ) );
		update_option( 'pp_woo_button_size'                          ,            $this->buttonSize                              );
		update_option( 'pp_woo_mark_size'                            ,            $this->markSize                                );
		update_option( 'pp_woo_liveApiCredentials'                   ,            $this->liveApiCredentials                      );
		update_option( 'pp_woo_sandboxApiCredentials'                ,            $this->sandboxApiCredentials                   );
		update_option( 'pp_woo_environment'                          ,            $this->environment                             );
		update_option( 'pp_woo_logoImageUrl'                         ,            $this->logoImageUrl                            );
		update_option( 'pp_woo_ipnUrl'                               ,            $this->ipnUrl                                  );
		update_option( 'pp_woo_paymentAction'                        ,            $this->paymentAction                           );
		update_option( 'pp_woo_allowGuestCheckout'                   , serialize( $this->allowGuestCheckout                    ) );
		update_option( 'pp_woo_blockEChecks'                         , serialize( $this->blockEChecks                          ) );
		update_option( 'pp_woo_requireBillingAddress'                , serialize( $this->requireBillingAddress                 ) );
		update_option( 'pp_woo_zeroSubtotalBehavior'                 ,            $this->zeroSubtotalBehavior                    );
		update_option( 'pp_woo_subtotalMismatchBehavior'             ,            $this->subtotalMismatchBehavior                );
		update_option( 'pp_woo_enableInContextCheckout'              , serialize( $this->enableInContextCheckout               ) );
		update_option( 'pp_woo_liveAccountIsEnabledForBillingAddress', serialize( $this->liveAccountIsEnabledForBillingAddress ) );
		update_option( 'pp_woo_sbAccountIsEnabledForBillingAddress'  , serialize( $this->sbAccountIsEnabledForBillingAddress   ) );
		update_option( 'pp_woo_ipsPrivateKey'                        ,            $this->ipsPrivateKey                           );
	}

	public function getECTokenSessionLength() {
		// Really, we should map this to a merchant-configurable setting, but for now, we'll just set it to the default (3 hours).
		return 10800;
	}

	/**
	 * Whether currency has decimal restriction for PPCE to functions?
	 *
	 * @return bool True if it has restriction otherwise false
	 */
	public function currency_has_decimal_restriction() {
		// Because PayPal will not accept HUF, TWD, or JPY with any decimal places,
		// we'll have to make sure that Woo uses 0 decimal places if the merchant
		// is using any of these three currencies.
		$currency = get_woocommerce_currency();
		$decimals = absint( get_option( 'woocommerce_price_num_decimals', 2 ) );
		$settings = $this->loadSettings();

		return (
			$settings->enabled
			&&
			in_array( $currency, array( 'HUF', 'TWD', 'JPY' ) )
			&&
			0 !== $decimals
		);
	}
}
