<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Gateway_PPEC_Credential_Validation {
	// This function fills in the $credentials variable with the credentials the user filled in on
	// the page, and returns true or false to indicate a success or error, respectively.  Why not
	// just return the credentials or false on failure?  Because the user might not fill in the
	// credentials at all, which isn't an error.  This way allows us to do it without returning an
	// error because the user didn't fill in the credentials.
	private function validate_credentials( $environment, &$credentials ) {

		// Object name we need to look for inside of $settings
		$creds_name = $environment . 'ApiCredentials';

		$api_user = $_POST[ $environment . '_api_username' ];
		$api_pass = $_POST[ $environment . '_api_password' ];
		$api_style = $_POST[ $environment . '_api_style' ];

		$subject = trim( $_POST[ '' . $environment . '_subject' ] );
		if ( empty( $subject ) ) {
			$subject = false;
		}

		$credentials = false;
		if ( 'signature' == $api_style ) {
			$api_sig = trim( $_POST[ '' . $environment . '_api_signature' ] );
		} elseif ( 'certificate' == $api_style ) {
			if ( array_key_exists( '' . $environment . '_api_certificate', $_FILES )
				&& array_key_exists( 'tmp_name', $_FILES[ '' . $environment . '_api_certificate' ] )
				&& array_key_exists( 'size', $_FILES[ '' . $environment . '_api_certificate' ] )
				&& $_FILES[ '' . $environment . '_api_certificate' ]['size'] ) {
				$api_cert = file_get_contents( $_FILES[ '' . $environment . '_api_certificate' ]['tmp_name'] );
				$_POST[ '' . $environment . '_api_cert_string' ] = base64_encode( $api_cert );
				unlink( $_FILES[ '' . $environment . '_api_certificate' ]['tmp_name'] );
				unset( $_FILES[ '' . $environment . '_api_certificate' ] );
			} elseif ( array_key_exists( '' . $environment . '_api_cert_string', $_POST ) && ! empty( $_POST[ '' . $environment . '_api_cert_string' ] ) ) {
				$api_cert = base64_decode( $_POST[ '' . $environment . '_api_cert_string' ] );
			}
		} else {
			WC_Admin_Settings::add_error( sprintf( __( 'Error: You selected an invalid credential type for your %s API credentials.', 'woocommerce-gateway-ppec' ), __( $environment, 'woocommerce-gateway-ppec' ) ) );
			return false;
		}

		if ( ! empty( $api_user ) ) {
			if ( empty( $api_pass ) ) {
				WC_Admin_Settings::add_error( sprintf( __( 'Error: You must enter a %s API password.' ), __( $environment, 'woocommerce-gateway-ppec' ) ) );
				return false;
			}

			if ( '********' == $api_pass ) {
				// Make sure we have a password on file.  If we don't, this value is invalid.
				if (  $this->$creds_name ) {
					if ( empty(  $this->$creds_name->apiPassword ) ) {
						$content =
						WC_Admin_Settings::add_error( sprintf( __( 'Error: The %s API password you provided is not valid.', 'woocommerce-gateway-ppec' ), __( $environment, 'woocommerce-gateway-ppec' ) ) );
						return false;
					}
					$api_pass =  $this->$creds_name->apiPassword;
				} else {
					WC_Admin_Settings::add_error( sprintf( __( 'Error: The %s API password you provided is not valid.', 'woocommerce-gateway-ppec' ), __( $environment, 'woocommerce-gateway-ppec' ) ) );
					return false;
				}
			}

			if ( 'signature' == $api_style ) {
				if ( ! empty( $api_sig ) ) {
					if ( '********' == $api_sig ) {
						// Make sure we have a signature on file.  If we don't, this value is invalid.
						if (  $this->$creds_name && is_a(  $this->$creds_name, 'PayPal_Signature_Credentials' ) ) {
							if ( empty(  $this->$creds_name->apiSignature ) ) {
								WC_Admin_Settings::add_error( sprintf( __( 'Error: The %s API signature you provided is not valid.', 'woocommerce-gateway-ppec' ), __( $environment, 'woocommerce-gateway-ppec' ) ) );
								return false;
							}
							$api_sig =  $this->$creds_name->apiSignature;
						} else {
							WC_Admin_Settings::add_error( sprintf( __( 'Error: The %s API signature you provided is not valid.', 'woocommerce-gateway-ppec' ), __( $environment, 'woocommerce-gateway-ppec' ) ) );
							return false;
						}
					}

					// Ok, test them out.
					$api_credentials = new PayPal_Signature_Credentials( $api_user, $api_pass, $api_sig, $subject );
					try {
						$payer_id = $this->test_api_credentials( $api_credentials, $environment );
						if ( ! $payer_id ) {
							WC_Admin_Settings::add_error( sprintf( __( 'Error: The %s credentials you provided are not valid.  Please double-check that you entered them correctly and try again.', 'woocommerce-gateway-ppec' ), __( $environment, 'woocommerce-gateway-ppec' ) ) );
							return false;
						}
						$api_credentials->payerID = $payer_id;
					} catch( PayPal_API_Exception $ex ) {
						$this->display_warning( sprintf( __( 'An error occurred while trying to validate your %s API credentials.  Unable to verify that your API credentials are correct.', 'woocommerce-gateway-ppec' ), __( $environment, 'woocommerce-gateway-ppec' ) ) );
					}

					$credentials = $api_credentials;
				} else {
					WC_Admin_Settings::add_error( sprintf( __( 'Error: You must provide a %s API signature.', 'woocommerce-gateway-ppec' ), __( $environment, 'woocommerce-gateway-ppec' ) ) );
					return false;
				}
			} else {
				if ( ! empty( $api_cert ) ) {
					$cert = openssl_x509_read( $api_cert );
					if ( false === $cert ) {
						WC_Admin_Settings::add_error( sprintf( __( 'Error: The %s API certificate is not valid.', 'woocommerce-gateway-ppec' ), __( $environment, 'woocommerce-gateway-ppec' ) ) );
						self::$process_admin_options_validation_error = true;
						return false;
					}

					$cert_info = openssl_x509_parse( $cert );
					$valid_until = $cert_info['validTo_time_t'];
					if ( $valid_until < time() ) {
						WC_Admin_Settings::add_error( sprintf( __( 'Error: The %s API certificate has expired.', 'woocommerce-gateway-ppec' ), __( $environment, 'woocommerce-gateway-ppec' ) ) );
						return false;
					}

					if ( $cert_info['subject']['CN'] != $api_user ) {
						WC_Admin_Settings::add_error( __( 'Error: The API username does not match the name in the API certificate.  Make sure that you have the correct API certificate.', 'woocommerce-gateway-ppec' ) );
						return false;
					}
				} else {
					// If we already have a cert on file, don't require one.
					if (  $this->$creds_name && is_a(  $this->$creds_name, 'PayPal_Certificate_Credentials' ) ) {
						if ( !  $this->$creds_name->apiCertificate ) {
							WC_Admin_Settings::add_error( sprintf( __( 'Error: You must provide a %s API certificate.', 'woocommerce-gateway-ppec' ), __( $environment, 'woocommerce-gateway-ppec' ) ) );
							return false;
						}
						$api_cert =  $this->$creds_name->apiCertificate;
					} else {
						WC_Admin_Settings::add_error( sprintf( __( 'Error: You must provide a %s API certificate.', 'woocommerce-gateway-ppec' ), __( $environment, 'woocommerce-gateway-ppec' ) ) );
						return false;
					}
				}

				$api_credentials = new PayPal_Certificate_Credentials( $api_user, $api_pass, $api_cert, $subject );
				try {
					$payer_id = $this->test_api_credentials( $api_credentials, $environment );
					if ( ! $payer_id ) {
						WC_Admin_Settings::add_error( sprintf( __( 'Error: The %s credentials you provided are not valid.  Please double-check that you entered them correctly and try again.', 'woocommerce-gateway-ppec' ), __( $environment, 'woocommerce-gateway-ppec' ) ) );
						return false;
					}
					$api_credentials->payerID = $payer_id;
				} catch( PayPal_API_Exception $ex ) {
					$this->display_warning( sprintf( __( 'An error occurred while trying to validate your %s API credentials.  Unable to verify that your API credentials are correct.', 'woocommerce-gateway-ppec' ), __( $environment, 'woocommerce-gateway-ppec' ) ) );
				}

				$credentials = $api_credentials;
			}
		}

		return true;
	}

	function test_api_credentials( $credentials, $environment = 'sandbox' ) {
		$api = new PayPal_API( $credentials, $environment );
		$result = $api->GetPalDetails();
		if ( 'Success' != $result['ACK'] && 'SuccessWithWarning' != $result['ACK'] ) {
			// Look at the result a little more closely to make sure it's a credentialing issue.
			$found_10002 = false;
			foreach ( $result as $index => $value ) {
				if ( preg_match( '/^L_ERRORCODE\d+$/', $index ) ) {
					if ( '10002' == $value ) {
						$found_10002 = true;
					}
				}
			}

			if ( $found_10002 ) {
				return false;
			} else {
				// Call failed for some other reason.
				throw new PayPal_API_Exception( $result );
			}
		}

		return $result['PAL'];
	}

}
