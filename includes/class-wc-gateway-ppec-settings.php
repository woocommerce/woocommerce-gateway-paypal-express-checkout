<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles settings retrieval from the settings API.
 */
class WC_Gateway_PPEC_Settings {

	protected $_settings        = array();
	protected $_supportedLocale = array(
		'da_DK', 'de_DE', 'en_AU', 'en_GB', 'en_US', 'es_ES', 'fr_CA', 'fr_FR',
		'he_IL', 'id_ID', 'it_IT', 'ja_JP', 'nl_NL', 'no_NO', 'pl_PL', 'pt_BR',
		'pt_PT', 'ru_RU', 'sv_SE', 'th_TH', 'tr_TR', 'zh_CN', 'zh_HK', 'zh_TW',
	);

	/**
	 * Flag to indicate setting has been loaded from DB.
	 * @var bool
	 */
	private $_is_setting_loaded = false;

	public function __get( $key ) {
		if ( array_key_exists( $key, $this->_settings ) ) {
			return $this->_settings[ $key ];
		}
		return null;
	}

	public function __isset( $name ) {
		return array_key_exists( $key, $this->_settings );
	}

	public function __construct() {
		$this->load_settings();
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
		}
		$this->_settings          = (array) get_option( 'woocommerce_ppec_paypal_settings', array() );
		$this->_is_setting_loaded = true;
		return $this;
	}

	/**
	 * Get API credentials for the live envionment.
	 * @return object
	 */
	public function get_live_api_credentials() {
		if ( $this->api_signature ) {
			return new WC_Gateway_PPEC_Client_Credential_Signature( $this->api_username, $this->api_password, $this->api_signature, $this->api_subject );
		} else {
			return new WC_Gateway_PPEC_Client_Credential_Certificate( $this->api_username, $this->api_password, $this->api_certificate, $this->api_subject );
		}
	}

	/**
	 * Get API credentials for the live envionment.
	 * @return object.
	 */
	public function get_sandbox_api_credentials() {
		if ( $this->sandbox_api_signature ) {
			return new WC_Gateway_PPEC_Client_Credential_Signature( $this->sandbox_api_username, $this->sandbox_api_password, $this->sandbox_api_signature, $this->sandbox_api_subject );
		} else {
			return new WC_Gateway_PPEC_Client_Credential_Certificate( $this->sandbox_api_username, $this->sandbox_api_password, $this->sandbox_api_certificate, $this->sandbox_api_subject );
		}
	}

	/**
	 * Get API credentials for the current envionment.
	 * @return object|false if invalid
	 */
	public function get_active_api_credentials() {
		if ( 'live' === $this->get_environment() ) {
			return $this->get_live_api_credentials();
		} else {
			return $this->get_sandbox_api_credentials();
		}
	}

	public function get_paypal_redirect_url( $token, $commit = false ) {
		$url = 'https://www.';

		if ( $this->environment !== 'live' ) {
			$url .= 'sandbox.';
		}

		$url .= 'paypal.com/';
		$url .= 'checkoutnow?';
		$url .= 'token=' . urlencode( $token );

		if ( $commit ) {
			$url .= '&useraction=commit';
		}

		return $url;
	}

	public function get_set_express_checkout_shortcut_params( $buckets = 1 ) {
		$params = array();

		if ( $this->logo_image_url ) {
			$params['LOGOIMG'] = $this->logo_image_url;
		}

		if ( apply_filters( 'woocommerce_paypal_express_checkout_allow_guests', true ) ) {
			$params['SOLUTIONTYPE'] = 'Sole';
		}

		if ( ! is_array( $buckets ) ) {
			$numBuckets = $buckets;
			$buckets = array();
			for ( $i = 0; $i < $numBuckets; $i++ ) {
				$buckets[] = $i;
			}
		}

		if ( 'yes' === $this->require_billing ) {
			$params['REQBILLINGADDRESS'] = '1';
		}

		foreach ( $buckets as $bucketNum ) {
			$params[ 'PAYMENTREQUEST_' . $bucketNum . '_PAYMENTACTION' ] = $this->get_paymentaction();

			if ( 'yes' === $this->instant_payments && 'sale' === $this->get_paymentaction() ) {
				$params[ 'PAYMENTREQUEST_' . $bucketNum . '_ALLOWEDPAYMENTMETHOD' ] = 'InstantPaymentOnly';
			}
		}

		return $params;
	}

	public function get_set_express_checkout_mark_params( $buckets = 1 ) {
		$params = array();

		if ( $this->logo_image_url ) {
			$params['LOGOIMG'] = $this->logo_image_url;
		}

		if ( false === apply_filters( 'woocommerce_paypal_express_checkout_allow_guests', true ) ) {
			$params['SOLUTIONTYPE'] = 'Sole';
		}

		if ( ! is_array( $buckets ) ) {
			$numBuckets = $buckets;
			$buckets = array();
			for ( $i = 0; $i < $numBuckets; $i++ ) {
				$buckets[] = $i;
			}
		}

		if ( 'yes' === $this->require_billing ) {
			$params['REQBILLINGADDRESS'] = '1';
		}

		foreach ( $buckets as $bucketNum ) {
			$params[ 'PAYMENTREQUEST_' . $bucketNum . '_PAYMENTACTION' ] = $this->get_paymentaction();

			if ( 'yes' === $this->instant_payments && 'sale' === $this->get_paymentaction() ) {
				$params[ 'PAYMENTREQUEST_' . $bucketNum . '_ALLOWEDPAYMENTMETHOD' ] = 'InstantPaymentOnly';
			}
		}

		return $params;
	}

	/**
	 * Get base parameters, based on settings instance, for DoExpressCheckoutCheckout NVP call.
	 *
	 * @see https://developer.paypal.com/docs/classic/api/merchant/DoExpressCheckoutPayment_API_Operation_NVP/
	 *
	 * @param WC_Order  $order   Order object
	 * @param int|array $buckets Number of buckets or list of bucket
	 *
	 * @return array DoExpressCheckoutPayment parameters
	 */
	public function get_do_express_checkout_params( WC_Order $order, $buckets = 1 ) {
		$params = array();
		if ( ! is_array( $buckets ) ) {
			$numBuckets = $buckets;
			$buckets = array();
			for ( $i = 0; $i < $numBuckets; $i++ ) {
				$buckets[] = $i;
			}
		}

		foreach ( $buckets as $bucketNum ) {
			$params[ 'PAYMENTREQUEST_' . $bucketNum . '_NOTIFYURL' ]     = WC()->api_request_url( 'WC_Gateway_PPEC' );
			$params[ 'PAYMENTREQUEST_' . $bucketNum . '_PAYMENTACTION' ] = $this->get_paymentaction();
			$params[ 'PAYMENTREQUEST_' . $bucketNum . '_INVNUM' ]        = $this->invoice_prefix . $order->get_order_number();
			$params[ 'PAYMENTREQUEST_' . $bucketNum . '_CUSTOM' ]        = json_encode( array( 'order_id' => $order->id, 'order_key' => $order->order_key ) );
		}

		return $params;
	}

	/**
	 * Is PPEC enabled
	 * @return bool
	 */
	public function is_enabled() {
		return 'yes' === $this->enabled;
	}

	/**
	 * Is logging enabled
	 * @return bool
	 */
	public function is_logging_enabled() {
		return 'yes' === $this->debug;
	}

	/**
	 * Payment action
	 * @return string
	 */
	public function get_paymentaction() {
		return $this->paymentaction === 'authorization' ? 'authorization' : 'sale';
	}

	/**
	 * Payment action
	 * @return string
	 */
	public function get_environment() {
		return $this->environment === 'sandbox' ? 'sandbox' : 'live';
	}

	/**
	 * Subtotal mismatches
	 * @return string
	 */
	public function get_subtotal_mismatch_behavior() {
		return $this->subtotal_mismatch_behavior === 'drop' ? 'drop' : 'add';
	}

	/**
	 * Get session length.
	 * @return int
	 */
	public function get_token_session_length() {
		// Really, we should map this to a merchant-configurable setting, but for now, we'll just set it to the default (3 hours).
		return 10800;
	}

	/**
	 * Whether currency has decimal restriction for PPCE to functions?
	 *
	 * @return bool True if it has restriction otherwise false
	 */
	public function currency_has_decimal_restriction() {
		return (
			'yes' === $this->enabled
			&&
			in_array( get_woocommerce_currency(), array( 'HUF', 'TWD', 'JPY' ) )
			&&
			0 !== absint( get_option( 'woocommerce_price_num_decimals', 2 ) )
		);
	}

	/**
	 * Get locale for PayPal.
	 * @return string
	 */
	public function get_paypal_locale() {
		$locale = get_locale();
		if ( ! in_array( $locale, $this->_supportedLocale ) ) {
			$locale = 'en_US';
		}
		return $locale;
	}
}
