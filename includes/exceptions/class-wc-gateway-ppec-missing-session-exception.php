<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class PayPal_Missing_Session_Exception extends Exception {
	public function __construct() {
		parent::__construct( 'The buyer\'s session information could not be found.' );
	}
}
