<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles settings retrieval from the settings API.
 */
class WC_Gateway_PPEC_Settings {

	/**
	 * Setting values from get_option.
	 *
	 * @var array
	 */
	protected $_settings = array();

	/**
	 * List of locales supported by PayPal.
	 *
	 * @var array
	 */
	protected $_supported_locales = array(
		'ar_AE',
		'ar_BH',
		'ar_DZ',
		'ar_EG',
		'ar_JO',
		'ar_KW',
		'ar_MA',
		'ar_OM',
		'ar_QA',
		'ar_SA',
		'ar_TN',
		'ar_YE',
		'cs_CZ',
		'da_DK',
		'da_FO',
		'da_GL',
		'de_AT',
		'de_CH',
		'de_DE',
		'de_LU',
		'el_GR',
		'en_AD',
		'en_AE',
		'en_AG',
		'en_AI',
		'en_AL',
		'en_AM',
		'en_AN',
		'en_AO',
		'en_AR',
		'en_AT',
		'en_AU',
		'en_AW',
		'en_AZ',
		'en_BA',
		'en_BB',
		'en_BE',
		'en_BF',
		'en_BG',
		'en_BH',
		'en_BI',
		'en_BJ',
		'en_BM',
		'en_BN',
		'en_BO',
		'en_BR',
		'en_BS',
		'en_BT',
		'en_BW',
		'en_BY',
		'en_BZ',
		'en_CA',
		'en_CD',
		'en_CG',
		'en_CH',
		'en_CI',
		'en_CK',
		'en_CL',
		'en_CM',
		'en_CO',
		'en_CR',
		'en_CV',
		'en_CY',
		'en_CZ',
		'en_DE',
		'en_DJ',
		'en_DK',
		'en_DM',
		'en_DO',
		'en_DZ',
		'en_EC',
		'en_EE',
		'en_EG',
		'en_ER',
		'en_ES',
		'en_ET',
		'en_FI',
		'en_FJ',
		'en_FK',
		'en_FM',
		'en_FO',
		'en_FR',
		'en_GA',
		'en_GB',
		'en_GD',
		'en_GE',
		'en_GF',
		'en_GI',
		'en_GL',
		'en_GM',
		'en_GN',
		'en_GP',
		'en_GR',
		'en_GT',
		'en_GW',
		'en_GY',
		'en_HK',
		'en_HN',
		'en_HR',
		'en_HU',
		'en_ID',
		'en_IE',
		'en_IL',
		'en_IN',
		'en_IS',
		'en_IT',
		'en_JM',
		'en_JO',
		'en_JP',
		'en_KE',
		'en_KG',
		'en_KH',
		'en_KI',
		'en_KM',
		'en_KN',
		'en_KR',
		'en_KW',
		'en_KY',
		'en_KZ',
		'en_LA',
		'en_LC',
		'en_LI',
		'en_LK',
		'en_LS',
		'en_LT',
		'en_LU',
		'en_LV',
		'en_MA',
		'en_MC',
		'en_MD',
		'en_ME',
		'en_MG',
		'en_MH',
		'en_MK',
		'en_ML',
		'en_MN',
		'en_MQ',
		'en_MR',
		'en_MS',
		'en_MT',
		'en_MU',
		'en_MV',
		'en_MW',
		'en_MX',
		'en_MY',
		'en_MZ',
		'en_NA',
		'en_NC',
		'en_NE',
		'en_NF',
		'en_NG',
		'en_NI',
		'en_NL',
		'en_NO',
		'en_NP',
		'en_NR',
		'en_NU',
		'en_NZ',
		'en_OM',
		'en_PA',
		'en_PE',
		'en_PF',
		'en_PG',
		'en_PH',
		'en_PL',
		'en_PM',
		'en_PN',
		'en_PT',
		'en_PW',
		'en_PY',
		'en_QA',
		'en_RE',
		'en_RO',
		'en_RS',
		'en_RU',
		'en_RW',
		'en_SA',
		'en_SB',
		'en_SC',
		'en_SE',
		'en_SG',
		'en_SH',
		'en_SI',
		'en_SJ',
		'en_SK',
		'en_SL',
		'en_SM',
		'en_SN',
		'en_SO',
		'en_SR',
		'en_ST',
		'en_SV',
		'en_SZ',
		'en_TC',
		'en_TD',
		'en_TG',
		'en_TH',
		'en_TJ',
		'en_TM',
		'en_TN',
		'en_TO',
		'en_TR',
		'en_TT',
		'en_TV',
		'en_TW',
		'en_TZ',
		'en_UA',
		'en_UG',
		'en_US',
		'en_UY',
		'en_VA',
		'en_VC',
		'en_VE',
		'en_VG',
		'en_VN',
		'en_VU',
		'en_WF',
		'en_WS',
		'en_YE',
		'en_YT',
		'en_ZA',
		'en_ZM',
		'en_ZW',
		'es_AD',
		'es_AE',
		'es_AG',
		'es_AI',
		'es_AM',
		'es_AN',
		'es_AO',
		'es_AR',
		'es_AW',
		'es_AZ',
		'es_BB',
		'es_BF',
		'es_BH',
		'es_BI',
		'es_BJ',
		'es_BM',
		'es_BO',
		'es_BS',
		'es_BW',
		'es_BZ',
		'es_CD',
		'es_CG',
		'es_CK',
		'es_CL',
		'es_CO',
		'es_CR',
		'es_CV',
		'es_CZ',
		'es_DJ',
		'es_DM',
		'es_DO',
		'es_DZ',
		'es_EC',
		'es_EE',
		'es_EG',
		'es_ER',
		'es_ES',
		'es_ET',
		'es_FI',
		'es_FJ',
		'es_FK',
		'es_FO',
		'es_GA',
		'es_GD',
		'es_GE',
		'es_GF',
		'es_GI',
		'es_GL',
		'es_GM',
		'es_GN',
		'es_GP',
		'es_GR',
		'es_GT',
		'es_GW',
		'es_GY',
		'es_HN',
		'es_HU',
		'es_IE',
		'es_JM',
		'es_JO',
		'es_KE',
		'es_KG',
		'es_KI',
		'es_KM',
		'es_KN',
		'es_KW',
		'es_KY',
		'es_KZ',
		'es_LC',
		'es_LI',
		'es_LS',
		'es_LT',
		'es_LU',
		'es_LV',
		'es_MA',
		'es_MG',
		'es_MH',
		'es_ML',
		'es_MQ',
		'es_MR',
		'es_MS',
		'es_MU',
		'es_MW',
		'es_MX',
		'es_MZ',
		'es_NA',
		'es_NC',
		'es_NE',
		'es_NF',
		'es_NI',
		'es_NR',
		'es_NU',
		'es_NZ',
		'es_OM',
		'es_PA',
		'es_PE',
		'es_PF',
		'es_PG',
		'es_PM',
		'es_PN',
		'es_PW',
		'es_PY',
		'es_QA',
		'es_RE',
		'es_RO',
		'es_RS',
		'es_RW',
		'es_SA',
		'es_SB',
		'es_SC',
		'es_SH',
		'es_SI',
		'es_SJ',
		'es_SK',
		'es_SL',
		'es_SM',
		'es_SN',
		'es_SO',
		'es_SR',
		'es_ST',
		'es_SV',
		'es_SZ',
		'es_TC',
		'es_TD',
		'es_TG',
		'es_TJ',
		'es_TM',
		'es_TN',
		'es_TT',
		'es_TV',
		'es_TZ',
		'es_UA',
		'es_UG',
		'es_US',
		'es_UY',
		'es_VA',
		'es_VC',
		'es_VE',
		'es_VG',
		'es_VU',
		'es_WF',
		'es_YE',
		'es_YT',
		'es_ZA',
		'es_ZM',
		'fi_FI',
		'fr_AD',
		'fr_AE',
		'fr_AG',
		'fr_AI',
		'fr_AM',
		'fr_AN',
		'fr_AO',
		'fr_AW',
		'fr_AZ',
		'fr_BB',
		'fr_BE',
		'fr_BF',
		'fr_BH',
		'fr_BI',
		'fr_BJ',
		'fr_BM',
		'fr_BO',
		'fr_BS',
		'fr_BW',
		'fr_BZ',
		'fr_CA',
		'fr_CD',
		'fr_CG',
		'fr_CH',
		'fr_CI',
		'fr_CK',
		'fr_CL',
		'fr_CM',
		'fr_CO',
		'fr_CR',
		'fr_CV',
		'fr_CZ',
		'fr_DJ',
		'fr_DM',
		'fr_DO',
		'fr_DZ',
		'fr_EC',
		'fr_EE',
		'fr_EG',
		'fr_ER',
		'fr_ET',
		'fr_FI',
		'fr_FJ',
		'fr_FK',
		'fr_FO',
		'fr_FR',
		'fr_GA',
		'fr_GD',
		'fr_GE',
		'fr_GF',
		'fr_GI',
		'fr_GL',
		'fr_GM',
		'fr_GN',
		'fr_GP',
		'fr_GR',
		'fr_GT',
		'fr_GW',
		'fr_GY',
		'fr_HN',
		'fr_HU',
		'fr_IE',
		'fr_JM',
		'fr_JO',
		'fr_KE',
		'fr_KG',
		'fr_KI',
		'fr_KM',
		'fr_KN',
		'fr_KW',
		'fr_KY',
		'fr_KZ',
		'fr_LC',
		'fr_LI',
		'fr_LS',
		'fr_LT',
		'fr_LU',
		'fr_LV',
		'fr_MA',
		'fr_MC',
		'fr_MG',
		'fr_MH',
		'fr_ML',
		'fr_MQ',
		'fr_MR',
		'fr_MS',
		'fr_MU',
		'fr_MW',
		'fr_MZ',
		'fr_NA',
		'fr_NC',
		'fr_NE',
		'fr_NF',
		'fr_NI',
		'fr_NR',
		'fr_NU',
		'fr_NZ',
		'fr_OM',
		'fr_PA',
		'fr_PE',
		'fr_PF',
		'fr_PG',
		'fr_PM',
		'fr_PN',
		'fr_PW',
		'fr_QA',
		'fr_RE',
		'fr_RO',
		'fr_RS',
		'fr_RW',
		'fr_SA',
		'fr_SB',
		'fr_SC',
		'fr_SH',
		'fr_SI',
		'fr_SJ',
		'fr_SK',
		'fr_SL',
		'fr_SM',
		'fr_SN',
		'fr_SO',
		'fr_SR',
		'fr_ST',
		'fr_SV',
		'fr_SZ',
		'fr_TC',
		'fr_TD',
		'fr_TG',
		'fr_TJ',
		'fr_TM',
		'fr_TN',
		'fr_TT',
		'fr_TV',
		'fr_TZ',
		'fr_UA',
		'fr_UG',
		'fr_US',
		'fr_UY',
		'fr_VA',
		'fr_VC',
		'fr_VE',
		'fr_VG',
		'fr_VU',
		'fr_WF',
		'fr_YE',
		'fr_YT',
		'fr_ZA',
		'fr_ZM',
		'he_IL',
		'hu_HU',
		'id_ID',
		'it_IT',
		'ja_JP',
		'ko_KR',
		'nl_BE',
		'nl_NL',
		'no_NO',
		'pl_PL',
		'pt_BR',
		'pt_PT',
		'ru_EE',
		'ru_LT',
		'ru_LV',
		'ru_RU',
		'ru_UA',
		'sk_SK',
		'sv_SE',
		'th_TH',
		'tr_TR',
		'zh_AD',
		'zh_AE',
		'zh_AG',
		'zh_AI',
		'zh_AM',
		'zh_AN',
		'zh_AO',
		'zh_AW',
		'zh_AZ',
		'zh_BB',
		'zh_BF',
		'zh_BH',
		'zh_BI',
		'zh_BJ',
		'zh_BM',
		'zh_BO',
		'zh_BS',
		'zh_BW',
		'zh_BZ',
		'zh_CD',
		'zh_CG',
		'zh_CK',
		'zh_CL',
		'zh_CN',
		'zh_CO',
		'zh_CR',
		'zh_CV',
		'zh_CZ',
		'zh_DJ',
		'zh_DM',
		'zh_DO',
		'zh_DZ',
		'zh_EC',
		'zh_EE',
		'zh_EG',
		'zh_ER',
		'zh_ET',
		'zh_FI',
		'zh_FJ',
		'zh_FK',
		'zh_FO',
		'zh_GA',
		'zh_GD',
		'zh_GE',
		'zh_GF',
		'zh_GI',
		'zh_GL',
		'zh_GM',
		'zh_GN',
		'zh_GP',
		'zh_GR',
		'zh_GT',
		'zh_GW',
		'zh_GY',
		'zh_HK',
		'zh_HN',
		'zh_HU',
		'zh_IE',
		'zh_JM',
		'zh_JO',
		'zh_KE',
		'zh_KG',
		'zh_KI',
		'zh_KM',
		'zh_KN',
		'zh_KW',
		'zh_KY',
		'zh_KZ',
		'zh_LC',
		'zh_LI',
		'zh_LS',
		'zh_LT',
		'zh_LU',
		'zh_LV',
		'zh_MA',
		'zh_MG',
		'zh_MH',
		'zh_ML',
		'zh_MQ',
		'zh_MR',
		'zh_MS',
		'zh_MU',
		'zh_MW',
		'zh_MZ',
		'zh_NA',
		'zh_NC',
		'zh_NE',
		'zh_NF',
		'zh_NI',
		'zh_NR',
		'zh_NU',
		'zh_NZ',
		'zh_OM',
		'zh_PA',
		'zh_PE',
		'zh_PF',
		'zh_PG',
		'zh_PM',
		'zh_PN',
		'zh_PW',
		'zh_QA',
		'zh_RE',
		'zh_RO',
		'zh_RS',
		'zh_RW',
		'zh_SA',
		'zh_SB',
		'zh_SC',
		'zh_SH',
		'zh_SI',
		'zh_SJ',
		'zh_SK',
		'zh_SL',
		'zh_SM',
		'zh_SN',
		'zh_SO',
		'zh_SR',
		'zh_ST',
		'zh_SV',
		'zh_SZ',
		'zh_TC',
		'zh_TD',
		'zh_TG',
		'zh_TJ',
		'zh_TM',
		'zh_TN',
		'zh_TT',
		'zh_TV',
		'zh_TW',
		'zh_TZ',
		'zh_UA',
		'zh_UG',
		'zh_US',
		'zh_UY',
		'zh_VA',
		'zh_VC',
		'zh_VE',
		'zh_VG',
		'zh_VU',
		'zh_WF',
		'zh_YE',
		'zh_YT',
		'zh_ZA',
		'zh_ZM'
	);

	/**
	 * Flag to indicate setting has been loaded from DB.
	 *
	 * @var bool
	 */
	private $_is_setting_loaded = false;

	public function __set( $key, $value ) {
		if ( array_key_exists( $key, $this->_settings ) ) {
			$this->_settings[ $key ] = $value;
		}
	}

	public function __get( $key ) {
		if ( array_key_exists( $key, $this->_settings ) ) {
			return $this->_settings[ $key ];
		}
		return null;
	}

	public function __isset( $key ) {
		return array_key_exists( $key, $this->_settings );
	}

	public function __construct() {
		$this->load();
	}

	/**
	 * Load settings from DB.
	 *
	 * @since 1.2.0
	 *
	 * @param bool $force_reload Force reload settings
	 *
	 * @return WC_Gateway_PPEC_Settings Instance of WC_Gateway_PPEC_Settings
	 */
	public function load( $force_reload = false ) {
		if ( $this->_is_setting_loaded && ! $force_reload ) {
			return $this;
		}
		$this->_settings          = (array) get_option( 'woocommerce_ppec_paypal_settings', array() );
		$this->_is_setting_loaded = true;
		return $this;
	}

	/**
	 * Load settings from DB.
	 *
	 * @deprecated
	 */
	public function load_settings( $force_reload = false ) {
		_deprecated_function( __METHOD__, '1.2.0', 'WC_Gateway_PPEC_Settings::load' );
		return $this->load( $force_reload );
	}

	/**
	 * Save current settings.
	 *
	 * @since 1.2.0
	 */
	public function save() {
		update_option( 'woocommerce_ppec_paypal_settings', $this->_settings );
	}

	/**
	 * Get API credentials for live envionment.
	 *
	 * @return WC_Gateway_PPEC_Client_Credential_Signature|WC_Gateway_PPEC_Client_Credential_Certificate
	 */
	public function get_live_api_credentials() {
		if ( $this->api_certificate ) {
			return new WC_Gateway_PPEC_Client_Credential_Certificate( $this->api_username, $this->api_password, $this->api_certificate, $this->api_subject );
		}

		return new WC_Gateway_PPEC_Client_Credential_Signature( $this->api_username, $this->api_password, $this->api_signature, $this->api_subject );
	}

	/**
	 * Get API credentials for sandbox envionment.
	 *
	 * @return WC_Gateway_PPEC_Client_Credential_Signature|WC_Gateway_PPEC_Client_Credential_Certificate
	 */
	public function get_sandbox_api_credentials() {
		if ( $this->sandbox_api_certificate ) {
			return new WC_Gateway_PPEC_Client_Credential_Certificate( $this->sandbox_api_username, $this->sandbox_api_password, $this->sandbox_api_certificate, $this->sandbox_api_subject );
		}

		return new WC_Gateway_PPEC_Client_Credential_Signature( $this->sandbox_api_username, $this->sandbox_api_password, $this->sandbox_api_signature, $this->sandbox_api_subject );
	}

	/**
	 * Get API credentials for the current envionment.
	 *
	 * @return object
	 */
	public function get_active_api_credentials() {
		return 'live' === $this->get_environment() ? $this->get_live_api_credentials() : $this->get_sandbox_api_credentials();
	}

	/**
	 * Get PayPal redirect URL.
	 *
	 * @param string $token  Token
	 * @param bool   $commit If set to true, 'useraction' parameter will be set
	 *                       to 'commit' which makes PayPal sets the button text
	 *                       to **Pay Now** ont the PayPal _Review your information_
	 *                       page.
	 * @param bool   $ppc    Whether to use PayPal credit.
	 *
	 * @return string PayPal redirect URL
	 */
	public function get_paypal_redirect_url( $token, $commit = false, $ppc = false ) {
		$url = 'https://www.';

		if ( 'live' !== $this->environment ) {
			$url .= 'sandbox.';
		}

		$url .= 'paypal.com/checkoutnow?token=' . urlencode( $token );

		if ( $commit ) {
			$url .= '&useraction=commit';
		}

		if ( $ppc ) {
			$url .= '#/checkout/chooseCreditOffer';
		}

		return $url;
	}

	public function get_set_express_checkout_shortcut_params( $buckets = 1 ) {
		_deprecated_function( __METHOD__, '1.2.0', 'WC_Gateway_PPEC_Client::get_set_express_checkout_params' );

		return wc_gateway_ppec()->client->get_set_express_checkout_params( array( 'skip_checkout' => true ) );
	}

	public function get_set_express_checkout_mark_params( $buckets = 1 ) {
		_deprecated_function( __METHOD__, '1.2.0', 'WC_Gateway_PPEC_Client::get_set_express_checkout_params' );

		// Still missing order_id in args.
		return wc_gateway_ppec()->client->get_set_express_checkout_params( array(
			'skip_checkout' => false,
		) );
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
			$num_buckets = $buckets;
			$buckets = array();
			for ( $i = 0; $i < $num_buckets; $i++ ) {
				$buckets[] = $i;
			}
		}

		foreach ( $buckets as $bucket_num ) {
			$params[ 'PAYMENTREQUEST_' . $bucket_num . '_NOTIFYURL' ]     = WC()->api_request_url( 'WC_Gateway_PPEC' );
			$params[ 'PAYMENTREQUEST_' . $bucket_num . '_PAYMENTACTION' ] = $this->get_paymentaction();
			$params[ 'PAYMENTREQUEST_' . $bucket_num . '_INVNUM' ]        = $this->invoice_prefix . $order->get_order_number();
			$params[ 'PAYMENTREQUEST_' . $bucket_num . '_CUSTOM' ]        = json_encode( array( 'order_id' => $order->id, 'order_key' => $order->order_key ) );
		}

		return $params;
	}

	/**
	 * Is PPEC enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return 'yes' === $this->enabled;
	}

	/**
	 * Is logging enabled.
	 *
	 * @return bool
	 */
	public function is_logging_enabled() {
		return 'yes' === $this->debug;
	}

	/**
	 * Get payment action from setting.
	 *
	 * @return string
	 */
	public function get_paymentaction() {
		return 'authorization' === $this->paymentaction ? 'authorization' : 'sale';
	}

	/**
	 * Get active environment from setting.
	 *
	 * @return string
	 */
	public function get_environment() {
		return 'sandbox' === $this->environment ? 'sandbox' : 'live';
	}

	/**
	 * Subtotal mismatches.
	 *
	 * @return string
	 */
	public function get_subtotal_mismatch_behavior() {
		return 'drop' === $this->subtotal_mismatch_behavior ? 'drop' : 'add';
	}

	/**
	 * Get session length.
	 *
	 * @todo Map this to a merchant-configurable setting
	 *
	 * @return int
	 */
	public function get_token_session_length() {
		return 10800; // 3h
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
	 *
	 * @return string
	 */
	public function get_paypal_locale() {
		$locale = get_locale();
		if ( ! in_array( $locale, $this->_supported_locales ) ) {
			$locale = 'en_US';
		}
		return apply_filters( 'woocommerce_paypal_express_checkout_paypal_locale', $locale );
	}

	/**
	 * Get brand name form settings.
	 *
	 * Default to site's name if brand_name in settings empty.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public function get_brand_name() {
		$brand_name = $this->brand_name ? $this->brand_name : get_bloginfo( 'name', 'display' );

		/**
		 * Character length and limitations for this parameter is 127 single-byte
		 * alphanumeric characters.
		 *
		 * @see https://developer.paypal.com/docs/classic/api/merchant/SetExpressCheckout_API_Operation_NVP/
		 */
		if ( ! empty( $brand_name ) ) {
			$brand_name = substr( $brand_name, 0, 127 );
		}

		/**
		 * Filters the brand name in PayPal hosted checkout pages.
		 *
		 * @since 1.2.0
		 *
		 * @param string Brand name
		 */
		return apply_filters( 'woocommerce_paypal_express_checkout_get_brand_name', $brand_name );
	}

	/**
	 * Checks whether PayPal Credit is enabled.
	 *
	 * @since 1.2.0
	 *
	 * @return bool Returns true if PayPal Credit is enabled and supported
	 */
	public function is_credit_enabled() {
		return 'yes' === $this->credit_enabled && wc_gateway_ppec_is_credit_supported();
	}

	/**
	 * Checks if currency in setting supports 0 decimal places.
	 *
	 * @since 1.2.0
	 *
	 * @return bool Returns true if currency supports 0 decimal places
	 */
	public function is_currency_supports_zero_decimal() {
		return in_array( get_woocommerce_currency(), array( 'HUF', 'JPY', 'TWD' ) );
	}

	/**
	 * Get number of digits after the decimal point.
	 *
	 * @since 1.2.0
	 *
	 * @return int Number of digits after the decimal point. Either 2 or 0
	 */
	public function get_number_of_decimal_digits() {
		return $this->is_currency_supports_zero_decimal() ? 0 : 2;
	}
}
