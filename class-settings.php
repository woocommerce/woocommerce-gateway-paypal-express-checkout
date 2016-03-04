<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

require_once( 'lib/class-settings.php' );
require_once( 'lib/class-signature.php' );
require_once( 'lib/class-certificate.php' );

class WooCommerce_PayPal_Settings extends PayPal_Settings {

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

	public function loadSettings() {
		$this->enabled                               = unserialize( get_option( 'pp_woo_enabled'                               ) );
		$this->ppcEnabled                            = unserialize( get_option( 'pp_woo_ppc_enabled'                           ) );
		$this->buttonSize                            =              get_option( 'pp_woo_button_size'                             );
		$this->markSize                              =              get_option( 'pp_woo_mark_size'                               );
		$this->liveApiCredentials                    =              get_option( 'pp_woo_liveApiCredentials'                      );
		$this->sandboxApiCredentials                 =              get_option( 'pp_woo_sandboxApiCredentials'                   );
		$this->environment                           =              get_option( 'pp_woo_environment'                             );
		$this->logoImageUrl                          =              get_option( 'pp_woo_logoImageUrl'                            );
		$this->ipnUrl                                =              get_option( 'pp_woo_ipnUrl'                                  );
		$this->paymentAction                         =              get_option( 'pp_woo_paymentAction'                           );
		$this->allowGuestCheckout                    = unserialize( get_option( 'pp_woo_allowGuestCheckout'                    ) );
		$this->blockEChecks                          = unserialize( get_option( 'pp_woo_blockEChecks'                          ) );
		$this->requireBillingAddress                 = unserialize( get_option( 'pp_woo_requireBillingAddress'                 ) );
		$this->zeroSubtotalBehavior                  =              get_option( 'pp_woo_zeroSubtotalBehavior'                    );
		$this->subtotalMismatchBehavior              =              get_option( 'pp_woo_subtotalMismatchBehavior'                );
		$this->enableInContextCheckout               = unserialize( get_option( 'pp_woo_enableInContextCheckout'               ) );
		$this->liveAccountIsEnabledForBillingAddress = unserialize( get_option( 'pp_woo_liveAccountIsEnabledForBillingAddress' ) );
		$this->sbAccountIsEnabledForBillingAddress   = unserialize( get_option( 'pp_woo_sbAccountIsEnabledForBillingAddress'   ) );
		$this->ipsPrivateKey                         =              get_option( 'pp_woo_ipsPrivateKey'                           );
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
}
