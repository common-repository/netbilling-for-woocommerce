<?php
/**
 * NETbilling for WooCommerce
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to support@netbilling.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade NETbilling for WooCommerce to newer
 * versions in the future. If you wish to customize NETbilling for WooCommerce for your
 * needs please refer to https://wordpress.org/plugins/netbilling-for-woocommerce/
 *
 * @package   NETbilling-for-WC/Gateway
 * @author    NETbilling
 * @copyright Copyright (c) 2013-2022, NETbilling, Inc. (support@netbilling.com)
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace NETbilling\WooCommerce\API;

use SkyVerge\WooCommerce\PluginFramework\v5_10_12 as Framework;

defined( 'ABSPATH' ) or exit;

/**
 * NETbilling for WC API Request Class
 *
 * Generates query string required by API specs to perform an API request
 *
 * @link http://secure.netbilling.com/public/docs/merchant/public/directmode/directmode3protocol.html
 *
 * @since 1.0.0
 */
class Request implements Framework\SV_WC_Payment_Gateway_API_Request {


	/** pay type for credit card transactions */
	const PAY_TYPE_CREDIT_CARD = 'C';

	/** pay type for eCheck transactions */
	const PAY_TYPE_ECHECK = 'K';

	/** transaction type for credit card authorization */
	const TRAN_TYPE_AUTH = 'A';

	/** transaction type for credit card capture */
	const TRAN_TYPE_CAPTURE = 'D';

	/** transaction type for credit card charge */
	const TRAN_TYPE_SALE = 'S';

	/** transaction type for tokenizing the payment method, called a "quasi-transaction" by NETbilling */
	const TRAN_TYPE_TOKENIZE = 'Q';

	/** @var array the request parameters */
	private $parameters = [];

	/** @var string the NETbilling account ID */
	private $account_id;

	/** @var \WC_Order optional order object if this request was associated with an order */
	protected $order;


	/**
	 * Construct an NETbilling request object
	 *
	 * @since 1.0.0
	 * @param string $account_id the account ID
	 * @param string $site_tag the site tag
	 */
	public function __construct( $account_id, $site_tag ) {

		$this->account_id = $account_id;

		$this->site_tag = $site_tag;
	}


	/**
	 * Creates a credit card auth request for the payment method/
	 * customer associated with $order
	 *
	 * @since 1.0.0
	 * @param \WC_Order $order the order object
	 */
	public function create_credit_card_auth( \WC_Order $order ) {

		$this->create_transaction( $order, self::PAY_TYPE_CREDIT_CARD, self::TRAN_TYPE_AUTH );
	}


	/**
	 * Capture funds for a credit card authorization
	 *
	 * @since 1.0.0
	 * @param \WC_Order $order the order object
	 */
	public function create_credit_card_capture( \WC_Order $order ) {

		$this->order = $order;

		$parameters = [
			'pay_type'  => self::PAY_TYPE_CREDIT_CARD,
			'tran_type' => self::TRAN_TYPE_CAPTURE,
			'orig_id'   => $order->get_meta( '_wc_netbilling_trans_id' ),
			'amount'    => $order->capture->amount,
		];

		foreach ( $parameters as $key => $value ) {
			$this->add_parameter( $key, $value );
		}
	}

	/**
	 * Creates a credit card charge request for the payment method/
	 * customer associated with $order
	 *
	 * @since 1.0.0
	 * @param \WC_Order $order the order object
	 */
	public function create_credit_card_charge( \WC_Order $order ) {

		$this->create_transaction( $order, self::PAY_TYPE_CREDIT_CARD, self::TRAN_TYPE_SALE );
	}


	/**
	 * Creates a eCheck debit request for the payment method/
	 * customer associated with $order
	 *
	 * @since 1.0.0
	 * @param \WC_Order $order the order object
	 */
	public function create_check_debit( \WC_Order $order ) {

		$this->create_transaction( $order, self::PAY_TYPE_ECHECK, self::TRAN_TYPE_SALE );
	}


	/**
	 * Tokenizes the customer's payment method for later use by performing a $0.00 auth
	 *
	 * The quasi-sale type doesn't actually verify the card provided at the bank level (e.g. CVV checks are not performed)
	 * so performing an actual auth is preferrable as it ensures the card is valid
	 *
	 * @since 1.0.0
	 * @param \WC_Order $order the order object
	 */
	public function tokenize( \WC_Order $order ) {

		$order->payment_total = 0.00;

		$this->create_transaction( $order, ( 'credit_card' === $order->payment->type ) ? self::PAY_TYPE_CREDIT_CARD : self::PAY_TYPE_ECHECK, self::TRAN_TYPE_AUTH );
	}


	/**
	 * Helper method to create a transaction
	 *
	 * @since 1.0.0
	 * @param \WC_Order $order the order object
	 * @param string $pay_type the type of payment, either credit card or eCheck, defined using the PAY_TYPE_* constants above
	 * @param string $transaction_type the type of transaction, defined using the TRAN_TYPE_* constants above
	 */
	public function create_transaction( \WC_Order $order, $pay_type, $transaction_type ) {

		$this->order = $order;

		// set required fields for credit card transaction
		$parameters = [
			'pay_type'     => $pay_type,
			'tran_type'    => $transaction_type,
			'amount'       => $order->payment_total,
			'tax_amount'   => number_format( $order->get_total_tax(), 2, '.', '' ),
			'ship_amount'  => number_format( $order->get_shipping_total(), 2, '.', '' ),
			'bill_name1'   => $order->get_billing_first_name( 'edit' ),
			'bill_name2'   => $order->get_billing_last_name( 'edit' ),
			'bill_street'  => trim( $order->get_billing_address_1( 'edit' ) . ' ' . $order->get_billing_address_2( 'edit' ) ),
			'bill_city'    => $order->get_billing_city( 'edit' ),
			'bill_state'   => $order->get_billing_state( 'edit' ),
			'bill_zip'     => $order->get_billing_postcode( 'edit' ),
			'bill_country' => $order->get_billing_country( 'edit' ),
		];

		if ( $order->has_shipping_address() ) {

			$parameters['ship_name1']   = $order->get_shipping_first_name( 'edit' );
			$parameters['ship_name2']   = $order->get_shipping_last_name( 'edit' );
			$parameters['ship_street']  = trim( $order->get_shipping_address_1( 'edit' ) . ' ' . $order->get_shipping_address_2( 'edit' ) );
			$parameters['ship_city']    = $order->get_shipping_city( 'edit' );
			$parameters['ship_state']   = $order->get_shipping_state( 'edit' );
			$parameters['ship_zip']     = $order->get_shipping_postcode( 'edit' );
			$parameters['ship_country'] = $order->get_shipping_country( 'edit' );
		}

		$parameters['cust_email']   = $order->get_billing_email( 'edit' );
		$parameters['cust_phone']   = $order->get_billing_phone( 'edit' );
		$parameters['cust_ip']      = $order->get_customer_ip_address( 'edit' );
		$parameters['cust_browser'] = $order->get_customer_user_agent( 'edit' );
		$parameters['description']  = $order->description;

		// add common transaction parameters
		foreach ( $parameters as $key => $value ) {
			$this->add_parameter( $key, $value );
		}

		// finally, add credit card fields
		if ( self::PAY_TYPE_CREDIT_CARD === $pay_type ) {

			$this->add_credit_card_parameters( $order );

		} else {

			// or add eCheck fields
			$this->add_echeck_parameters( $order );
		}

		// charges using tokenization should have CVV checks disabled since CVV is not stored
		if ( ! empty( $order->payment->token ) ) {

			$this->add_parameter( 'disable_cvv2', 'true' );
		}
	}


	/**
	 * Helper to add the necessary credit card parameters
	 *
	 * @since 1.0.0
	 * @param \WC_Order $order the WC order object
	 */
	private function add_credit_card_parameters( \WC_Order $order ) {

		$parameters = array();

		// if using a saved card, the card number is simply the token
		if ( ! empty( $order->payment->token ) ) {

			$parameters['card_number'] = "CS:{$order->payment->token}";

		} else {

			// otherwise add the card number/expiration date
			$parameters['card_number'] = $order->payment->account_number;
			$parameters['card_expire'] = $order->payment->exp_month . $order->payment->exp_year;

			// add CSC if available
			if ( ! empty( $order->payment->csc ) ) {
				$parameters['card_cvv2'] = $order->payment->csc;
			}
		}

		// add parameters
		foreach ( $parameters as $key => $value ) {
			$this->add_parameter( $key, $value );
		}
	}


	/**
	 * Helper to add the necessary eCheck parameters
	 *
	 * @since 1.0.0
	 * @param WC_Order $order the WC order object
	 */
	private function add_echeck_parameters( $order ) {

		$parameters = array();

		if ( ! empty( $order->payment->token ) ) {

			$parameters['card_number'] = "CS:{$order->payment->token}";

		} else {

			// account number is required in the format: <routing number>:<account number>
			$parameters['account_number'] = $order->payment->routing_number . ':' . $order->payment->account_number;

			// "assent key" is required
			$parameters['assent_key'] = $order->payment->assent_key;
		}

		// add parameters
		foreach ( $parameters as $key => $value ) {
			$this->add_parameter( $key, $value );
		}
	}


	/**
	 * Helper to return formed query string
	 *
	 * @since 1.0.0
	 * @return string query string
	 */
	public function to_query_string() : string {

		// account ID & site tag for every request
		$this->add_parameter( 'account_id', $this->account_id );
		$this->add_parameter( 'site_tag', $this->site_tag );

		// allow modification of parameters
		$this->parameters = apply_filters( 'wc_netbilling_request_parameters', $this->parameters, $this->get_order() );

		// check parameters lengths and truncate if necessary
		$this->validate_parameters();

		$query_string = http_build_query( $this->parameters, null, '&' );

		// PHP versions prior to 5.4 didn't allow a encoding type to be specified for http_build_query, so the query string needs to be manually adjusted to respect RFC 3986
		$query_string = str_replace( '+', '%20', $query_string );

		return $query_string;
	}


	/**
	 * Returns the string representation of this request
	 *
	 * @since 1.0.0
	 * @see Framework\SV_WC_Payment_Gateway_API_Request::to_string()
	 * @return string request query string
	 */
	public function to_string() : string {

		return $this->to_query_string();
	}


	/**
	 * Returns the string representation of this request with any and all
	 * sensitive elements masked or removed
	 *
	 * @since 1.0.0
	 * @see Framework\SV_WC_Payment_Gateway_API_Request::to_string_safe()
	 * @return string the request query string, safe for logging/displaying
	 */
	public function to_string_safe() : string {

		// replace account ID
		$this->parameters['account_id'] = str_repeat( 'X', strlen( $this->parameters['account_id'] ) );

		// replace card number
		if ( ! empty( $this->parameters['card_number'] ) ) {
			$this->parameters['card_number'] = str_repeat( 'X', strlen( $this->parameters['card_number'] ) - 4 ) . substr( $this->parameters['card_number'], - 4 );
		}

		// replace CSC
		if ( ! empty( $this->parameters['card_cvv2'] ) ) {
			$this->parameters['card_cvv2'] = str_repeat( 'X', strlen( $this->parameters['card_cvv2'] ) );
		}

		return $this->to_query_string();
	}


	/** Helper Methods ******************************************************/


	/**
	 * Helper to set request parameters
	 *
	 * @since 1.0.0
	 * @param string $key the parameter name
	 * @param string $value the parameter value
	 */
	private function add_parameter( $key, $value ) {

		$this->parameters[ $key ] = $value;
	}


	/**
	 * Helper to validate each parameter, currently this only checks that each field is within it's allowable length
	 *
	 * @since 1.0.0
	 */
	private function validate_parameters() {

		$fields = array(
			'bill_name1'          => 20,
			'bill_name2'          => 20,
			'bill_street'         => 80,
			'bill_city'           => 40,
			'bill_state'          => 30,
			'bill_zip'            => 20,
			'bill_country'        => 2,
			'ship_name1'          => 20,
			'ship_name2'          => 20,
			'ship_street'         => 80,
			'ship_city'           => 40,
			'ship_state'          => 30,
			'ship_zip'            => 20,
			'ship_country'        => 2,
			'cust_email'          => 60,
			'cust_phone'          => 40,
			'cust_ip'             => 15,
			'site_tag'            => 12,
			'description'         => 4000,
			'user_data'           => 4000,
			'misc_info'           => 4000,
			'bill_photo_id_no'    => 20,
			'bill_photo_id_state' => 2,
		);

		// check each field and truncate to max length as needed
		foreach ( $fields as $field_name => $field_max_length ) {
			if ( ! empty( $this->parameters[ $field_name ] ) && strlen( $this->parameters[ $field_name ] ) > $field_max_length ) {
				$this->parameters[ $field_name ] = substr( $this->parameters[ $field_name ], 0, $field_max_length );
			}
		}
	}


	/**
	 * Returns the method for this request. NETbilling uses the API default
	 * (POST)
	 *
	 * @since 1.0.0
	 * @return null
	 */
	public function get_method() { }


	/**
	 * Returns the request path for this request. NETbilling request paths
	 * do not vary per request.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_path() {
		return '';
	}


	/**
	 * No-op: returns the query parameters.
	 *
	 * @since 1.0.0
	 *
	 * @return null
	 */
	public function get_params() { }


	/**
	 * No-op: returns the request data.
	 *
	 * @since 1.0.0
	 *
	 * @return null
	 */
	public function get_data() { }


	/**
	 * Returns the order associated with this request, if there was one
	 *
	 * @since 1.0.0
	 * @return \WC_Order the order object
	 */
	public function get_order() : \WC_Order {

		return $this->order;
	}


}
