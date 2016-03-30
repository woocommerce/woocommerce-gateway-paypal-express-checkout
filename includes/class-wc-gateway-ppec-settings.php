<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Gateway_PPEC_Settings {

	protected $params;
	protected $validParams = array(
		'enabled',
		'logging_enabled',
		'ppcEnabled',
		'environment',
		'liveApiCredentials',
		'sandboxApiCredentials',
		'enableInContextCheckout',
		'buttonSize',
		'markSize',
		'logoImageUrl',
		'paymentAction',
		'allowGuestCheckout',
		'zeroSubtotalBehavior',
		'subtotalMismatchBehavior',
		'ipnUrl',
		'blockEChecks',
		'requireBillingAddress',
		'liveAccountIsEnabledForBillingAddress',
		'sbAccountIsEnabledForBillingAddress',
	);

	protected $_supportedLocale = array(
		'da_DK', 'de_DE', 'en_AU', 'en_GB', 'en_US', 'es_ES', 'fr_CA', 'fr_FR',
		'he_IL', 'id_ID', 'it_IT', 'ja_JP', 'nl_NL', 'no_NO', 'pl_PL', 'pt_BR',
		'pt_PT', 'ru_RU', 'sv_SE', 'th_TH', 'tr_TR', 'zh_CN', 'zh_HK', 'zh_TW',
	);

	const PaymentActionSale          = 'Sale';
	const PaymentActionAuthorization = 'Authorization';

	const zeroSubtotalBehaviorModifyItems                   = 'modifyItems';
	const zeroSubtotalBehaviorOmitLineItems                 = 'omitLineItems';
	const zeroSubtotalBehaviorPassCouponsAsShippingDiscount = 'passCouponsAsShippingDiscount';

	const subtotalMismatchBehaviorAddLineItem   = 'addLineItem';
	const subtotalMismatchBehaviorDropLineItems = 'dropLineItems';

	const buttonSizeSmall  = 'small';
	const buttonSizeMedium = 'medium';

	const markSizeSmall  = 'small';
	const markSizeMedium = 'medium';
	const markSizeLarge  = 'large';

	/**
	 * Flag to indicate setting has been loaded from DB.
	 *
	 * @var bool
	 */
	private $_is_setting_loaded = false;

	public function __get( $name ) {
		if ( in_array( $name, $this->validParams ) ) {
			// Run the value through sanitization functions, if they exist
			$func_name = '_sanitize_' . $name;
			if ( method_exists( $this, $func_name ) ) {
				return $this->$func_name( $this->params[ $name ] );
			} else if ( array_key_exists( $name, $this->params ) ) {
				return $this->params[ $name ];
			} else {
				return null;
			}
		}

		return null;
	}

	public function __set( $name, $value ) {
		if ( in_array( $name, $this->validParams ) ) {
			// Run the value through sanitization and validation functions, if they exist
			$func_name = '_sanitize_' . $name;
			if ( method_exists( $this, $func_name ) ) {
				$value = $this->$func_name( $value );
			}

			$func_name = '_validate_' . $name;
			if ( method_exists( $this, $func_name ) ) {
				if ( $this->$func_name( $value ) ) {
					$this->params[ $name ] = $value;
				}
			} else {
				$this->params[ $name ] = $value;
			}
		}
	}

	public function __isset( $name ) {
		if ( in_array( $name, $this->validParams ) ) {
			return true;
		} else {
			return false;
		}
	}

	public function setApiSignatureCredentials( $username, $password, $signature, $subject = false, $environment = 'sandbox' ) {
		if ( 'live' == $environment ) {
			$this->liveApiCredentials = new WC_Gateway_PPEC_Client_Credential_Signature( $username, $password, $signature, $subject );
		} else {
			$this->sandboxApiCredentials = new WC_Gateway_PPEC_Client_Credential_Signature( $username, $password, $signature, $subject );
		}
	}

	public function setApiCertificateCredentialsFromFile( $username, $password, $certFile, $subject = false, $environment = 'sandbox' ) {
		$certString = file_get_contents( $certFile );
		if ( FALSE === $cert ) {
			// Failed to load the certificate
			// TODO: Add some logging
			return false;
		}

		$this->setApiCertificateCredentialsFromString( $username, $password, $certString, $subject, $environment );

		return true;

	}

	public function setApiCertificateCredentialsFromString( $username, $password, $certString, $subject = false, $environment = 'sandbox' ) {
		if ( 'live' == $environment ) {
			$this->liveApiCredentials = new WC_Gateway_PPEC_Client_Credential_Certificate( $username, $password, $certString, $subject );
		} else {
			$this->sandboxApiCredentials = new WC_Gateway_PPEC_Client_Credential_Certificate( $username, $password, $certString, $subject );
		}
	}

	public function getActiveApiCredentials() {
		if ( $this->environment == 'live' ) {
			return $this->liveApiCredentials;
		} else {
			return $this->sandboxApiCredentials;
		}
	}

	public function getPayPalRedirectUrl( $token, $commit = false ) {
		$url = 'https://www.';

		if ( $this->environment != 'live' ) {
			$url .= 'sandbox.';
		}

		$url .= 'paypal.com/';

		if ( $this->enableInContextCheckout ) {
			$url .= 'checkoutnow?';
		} else {
			$url .= 'cgi-bin/webscr?cmd=_express-checkout&';
		}

		$url .= 'token=' . urlencode( $token );

		if ( $commit ) {
			$url .= '&useraction=commit';
		}

		return $url;
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

	/**
	 * TODO: Probably merge with getSetECShortcutParameters
	 */
	protected function getBaseSetECShortcutParameters( $buckets = 1 ) {
		$params = array();

		if ( $this->logoImageUrl ) {
			$params['LOGOIMG'] = $this->logoImageUrl;
		}

		if ( $this->allowGuestCheckout ) {
			$params['SOLUTIONTYPE'] = 'Sole';
		}

		if ( ! is_array( $buckets ) ) {
			$numBuckets = $buckets;
			$buckets = array();
			for ( $i = 0; $i < $numBuckets; $i++ ) {
				$buckets[] = $i;
			}
		}

		if ( $this->requireBillingAddress ) {
			$params['REQBILLINGADDRESS'] = '1';
		}

		foreach ( $buckets as $bucketNum ) {
			$params[ 'PAYMENTREQUEST_' . $bucketNum . '_PAYMENTACTION' ] = $this->paymentAction;
			if ( $this->blockEChecks ) $params[ 'PAYMENTREQUEST_' . $bucketNum . '_ALLOWEDPAYMENTMETHOD' ] = 'InstantPaymentOnly';
		}

		return $params;
	}

	/**
	 * TODO: Probably merge with getSetECMarkParameters
	 */
	protected function getBaseSetECMarkParameters( $buckets = 1 ) {
		$params = array();

		if ( $this->logoImageUrl ) {
			$params['LOGOIMG'] = $this->logoImageUrl;
		}

		if ( $this->allowGuestCheckout ) {
			$params['SOLUTIONTYPE'] = 'Sole';
		}

		if ( ! is_array( $buckets ) ) {
			$numBuckets = $buckets;
			$buckets = array();
			for ( $i = 0; $i < $numBuckets; $i++ ) {
				$buckets[] = $i;
			}
		}

		if ( $this->requireBillingAddress ) {
			$params['REQBILLINGADDRESS'] = '1';
		}

		foreach ( $buckets as $bucketNum ) {
			$params[ 'PAYMENTREQUEST_' . $bucketNum . '_PAYMENTACTION' ] = $this->paymentAction;
			if ( $this->blockEChecks ) {
				$params[ 'PAYMENTREQUEST_' . $bucketNum . '_ALLOWEDPAYMENTMETHOD' ] = 'InstantPaymentOnly';
			}
		}

		return $params;
	}

	/**
	 * TODO: Probably merge with getDoECParameters
	 */
	protected function getBaseDoECParameters( $buckets = 1 ) {
		$params = array();
		if ( ! is_array( $buckets ) ) {
			$numBuckets = $buckets;
			$buckets = array();
			for ( $i = 0; $i < $numBuckets; $i++ ) {
				$buckets[] = $i;
			}
		}

		foreach ( $buckets as $bucketNum ) {
			$params[ 'PAYMENTREQUEST_' . $bucketNum . '_NOTIFYURL' ] = $this->ipnUrl;
			$params[ 'PAYMENTREQUEST_' . $bucketNum . '_PAYMENTACTION' ] = $this->paymentAction;
		}

		return $params;
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
		if ( in_array( $size, array( self::buttonSizeSmall, self::buttonSizeMedium ) ) ) {
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

	protected function _validate_paymentAction( $value ) {
		return in_array( $value, array( self::PaymentActionSale, self::PaymentActionAuthorization ) );
	}

	/**
	 * Load settings from DB.
	 *
	 * @param bool $force_reload Force reload, ignore
	 *
	 * @return WC_Gateway_PPEC_Settings Instance of WC_Gateway_PPEC_Settings
	 */
	public function loadSettings( $force_reload = false ) {
		if ( $this->_is_setting_loaded && ! $force_reload ) {
			return $this;
		}

		$this->enabled                               = get_option( 'pp_woo_enabled'                               );
		// $this->ppcEnabled                            = get_option( 'pp_woo_ppc_enabled'                           );
		$this->logging_enabled                       = get_option( 'pp_woo_logging_enabled' );
		$this->ppcEnabled                            = false; // defer this for next release.
		$this->buttonSize                            = get_option( 'pp_woo_button_size'                           );
		$this->markSize                              = get_option( 'pp_woo_mark_size'                             );
		$this->liveApiCredentials                    = get_option( 'pp_woo_liveApiCredentials'                    );
		$this->sandboxApiCredentials                 = get_option( 'pp_woo_sandboxApiCredentials'                 );
		$this->environment                           = get_option( 'pp_woo_environment'                           );
		$this->logoImageUrl                          = get_option( 'pp_woo_logoImageUrl'                          );
		$this->ipnUrl                                = get_option( 'pp_woo_ipnUrl'                                );
		$this->paymentAction                         = get_option( 'pp_woo_paymentAction'                         );
		$this->allowGuestCheckout                    = get_option( 'pp_woo_allowGuestCheckout'                    );
		$this->blockEChecks                          = get_option( 'pp_woo_blockEChecks'                          );
		$this->requireBillingAddress                 = get_option( 'pp_woo_requireBillingAddress'                 );
		$this->zeroSubtotalBehavior                  = get_option( 'pp_woo_zeroSubtotalBehavior'                  );
		$this->subtotalMismatchBehavior              = get_option( 'pp_woo_subtotalMismatchBehavior'              );
		$this->enableInContextCheckout               = get_option( 'pp_woo_enableInContextCheckout'               );
		$this->liveAccountIsEnabledForBillingAddress = get_option( 'pp_woo_liveAccountIsEnabledForBillingAddress' );
		$this->sbAccountIsEnabledForBillingAddress   = get_option( 'pp_woo_sbAccountIsEnabledForBillingAddress'   );

		$this->_is_setting_loaded = true;

		return $this;
	}

	public function saveSettings() {
		update_option( 'pp_woo_enabled'                              , $this->enabled                               );
		update_option( 'pp_woo_logging_enabled'                      , $this->logging_enabled                       );
		update_option( 'pp_woo_ppc_enabled'                          , $this->ppcEnabled                            );
		update_option( 'pp_woo_button_size'                          , $this->buttonSize                            );
		update_option( 'pp_woo_mark_size'                            , $this->markSize                              );
		update_option( 'pp_woo_liveApiCredentials'                   , $this->liveApiCredentials                    );
		update_option( 'pp_woo_sandboxApiCredentials'                , $this->sandboxApiCredentials                 );
		update_option( 'pp_woo_environment'                          , $this->environment                           );
		update_option( 'pp_woo_logoImageUrl'                         , $this->logoImageUrl                          );
		update_option( 'pp_woo_ipnUrl'                               , $this->ipnUrl                                );
		update_option( 'pp_woo_paymentAction'                        , $this->paymentAction                         );
		update_option( 'pp_woo_allowGuestCheckout'                   , $this->allowGuestCheckout                    );
		update_option( 'pp_woo_blockEChecks'                         , $this->blockEChecks                          );
		update_option( 'pp_woo_requireBillingAddress'                , $this->requireBillingAddress                 );
		update_option( 'pp_woo_zeroSubtotalBehavior'                 , $this->zeroSubtotalBehavior                  );
		update_option( 'pp_woo_subtotalMismatchBehavior'             , $this->subtotalMismatchBehavior              );
		update_option( 'pp_woo_enableInContextCheckout'              , $this->enableInContextCheckout               );
		update_option( 'pp_woo_liveAccountIsEnabledForBillingAddress', $this->liveAccountIsEnabledForBillingAddress );
		update_option( 'pp_woo_sbAccountIsEnabledForBillingAddress'  , $this->sbAccountIsEnabledForBillingAddress   );
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

	public function get_paypal_locale() {
		$locale = get_locale();
		if ( ! in_array( $locale, $this->_supportedLocale ) ) {
			$locale = 'en_US';
		}

		return $locale;
	}
}
