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
 * NETbilling for WC API Response Class
 *
 * Parses response string received from NETbilling API, which is simply a URL-encoded string of parameters
 *
 * @link http://secure.netbilling.com/public/docs/merchant/public/directmode/directmode3protocol.html
 *
 * @since 1.0.0
 * @see Framework\SV_WC_Payment_Gateway_API_Response
 */
class Response implements Framework\SV_WC_Payment_Gateway_API_Response, Framework\SV_WC_Payment_Gateway_API_Create_Payment_Token_Response, Framework\SV_WC_Payment_Gateway_API_Authorization_Response {


	/** successful transaction code */
	const STATUS_CODE_SUCCESS = '1';

	/** successful auth transaction code */
	const STATUS_CODE_AUTH_SUCCESS = 'T';

	/** pending transaction code */
	const STATUS_CODE_PENDING = 'I';

	/** failed transaction code */
	const STATUS_CODE_FAILED = '0';

	/** failed eCheck transaction code */
	const STATUS_CODE_ECHECK_FAILED = 'F';

	/** duplicate transaction code */
	const STATUS_CODE_DUPLICATE = 'D';

	/** @var array URL-decoded and parsed parameters */
	protected $parameters = [];

	/** @var \WC_Order optional order object if this request was associated with an order */
	protected $order;


	/**
	 * Parse the response parameters from the raw URL-encoded response string
	 *
	 * @since 1.0.0
	 * @param string $response the raw URL-encoded response string
	 * @param \WC_Order $order the order object associated with this response
	 */
	public function __construct( $response, \WC_Order $order ) {

		$this->order = $order;

		// URL decode the response string and parse it
		parse_str( urldecode( $response ), $this->parameters );
	}


	/**
	 * Gets the transaction status code
	 *
	 * @since 1.0.0
	 * @see Framework\SV_WC_Payment_Gateway_API_Response::get_status_code()
	 * @return string status code
	 */
	public function get_status_code() {

		return ( isset( $this->parameters['status_code'] ) ) ? $this->parameters['status_code'] : null;
	}


	/**
	 * Gets the transaction status message
	 *
	 * @since 1.0.0
	 * @see Framework\SV_WC_Payment_Gateway_API_Response::get_status_message()
	 * @return string status message
	 */
	public function get_status_message() : string {

		// a human readable success/failure message
		$message = ( ! empty( $this->parameters['auth_msg'] ) ) ? $this->parameters['auth_msg'] : __( 'N/A', 'netbilling-for-woocommerce' );

		// occasionally this will contain additional information about a decline
		if ( ! empty( $this->parameters['reason_code2'] ) )
			$message = sprintf( '%s - %s', $message, $this->parameters['reason_code2'] );

		switch ( $this->get_status_code() ) {

			case self::STATUS_CODE_SUCCESS:       return sprintf( __( 'Successful transaction (%s)', 'netbilling-for-woocommerce' ), $message );
			case self::STATUS_CODE_PENDING:       return sprintf( __( 'Pending transaction (%s)', 'netbilling-for-woocommerce' ), $message );
			case self::STATUS_CODE_AUTH_SUCCESS:  return sprintf( __( 'Successful auth only transaction (%s)', 'netbilling-for-woocommerce' ), $message );
			case self::STATUS_CODE_FAILED:        return sprintf( __( 'Failed transaction (%s)', 'netbilling-for-woocommerce' ), $message );
			case self::STATUS_CODE_ECHECK_FAILED: return sprintf( __( 'Settlement failure or returned eCheck transaction (%s)', 'netbilling-for-woocommerce' ), $message );
			case self::STATUS_CODE_DUPLICATE:     return sprintf( __( 'Duplicate transaction (%s)', 'netbilling-for-woocommerce' ), $message );
			default:                              return sprintf( __( 'Unknown transaction (%s)', 'netbilling-for-woocommerce' ), $message );
		}
	}


	/**
	 * Checks if the transaction was successful
	 *
	 * NETbilling says this about transaction success:
	 *
	 * "Any unexpected status code other than "0" and "F" should be interpreted as a successful transaction"
	 *
	 * @since 1.0.0
	 * @see Framework\SV_WC_Payment_Gateway_API_Response::transaction_approved()
	 * @return bool true if approved, false otherwise
	 */
	public function transaction_approved() : bool {

		return ( self::STATUS_CODE_FAILED !== $this->get_status_code() && self::STATUS_CODE_ECHECK_FAILED !== $this->get_status_code() );
	}


	/**
	 * Returns true if the transaction is pending, such as an unfunded eCheck payment
	 *
	 * @since 1.0.0
	 * @see Framework\SV_WC_Payment_Gateway_API_Response::transaction_held()
	 * @return bool true if approved, false otherwise
	 */
	public function transaction_held() : bool {

		return ( self::STATUS_CODE_PENDING === $this->get_status_code() );
	}


	/**
	 * Gets the response transaction id, or null if there is no transaction id
	 * associated with this transaction.
	 *
	 * @since 1.0.0
	 * @see Framework\SV_WC_Payment_Gateway_API_Response::get_transaction_id()
	 * @return string transaction id
	 */
	public function get_transaction_id() {

		return ( ! empty( $this->parameters['trans_id'] ) ) ? $this->parameters['trans_id'] : null;
	}


	/**
	 * The authorization code is a 6 character returned by the processing bank to
	 * indicate that the charge will be paid by the card issuer.
	 *
	 * @since 1.0.0
	 * @see Framework\SV_WC_Payment_Gateway_API_Authorization_Response::get_authorization_code()
	 * @return string credit card authorization code
	 */
	public function get_authorization_code() {

		return ( ! empty( $this->parameters['auth_code'] ) ) ? $this->parameters['auth_code'] : null;
	}


	/**
	 * Returns the result of the AVS check
	 *
	 * @since 1.0.0
	 * @see Framework\SV_WC_Payment_Gateway_API_Authorization_Response::get_avs_result()
	 * @return string result of the AVS check, if any
	 */
	public function get_avs_result() {

		return ( ! empty( $this->parameters['avs_code'] ) ) ? $this->parameters['avs_code'] : null;
	}


	/**
	 * Returns the result of the CSC check
	 *
	 * @since 1.0.0
	 * @see Framework\SV_WC_Payment_Gateway_API_Authorization_Response::get_csc_result()
	 * @return string result of CSC check
	 */
	public function get_csc_result() {

		return ( ! empty( $this->parameters['cvv_code'] ) ) ? $this->parameters['cvv_code'] : null;
	}


	/**
	 * Returns true if the CSC check was successful
	 *
	 * @since 1.0.0
	 * @see Framework\SV_WC_Payment_Gateway_API_Authorization_Response::csc_match()
	 * @return bool true if the CSC check was successful
	 */
	public function csc_match() : bool {

		return 'M' == $this->get_csc_result();
	}


	/**
	 * Returns the payment token
	 *
	 * @since 1.0.0
	 * @see Framework\SV_WC_Payment_Gateway_API_Create_Payment_Token_Response::get_payment_token()
	 * @return Framework\SV_WC_Payment_Gateway_Payment_Token payment token
	 */
	public function get_payment_token() {

		$token = [
			'default'        => true,
			'type'           => $this->order->payment->type,
			'last_four'      => substr( $this->order->payment->account_number, -4 ),
			'account_number' => $this->order->payment->account_number,
		];

		// add credit card specific data
		if ( 'credit_card' === $this->order->payment->type ) {

			$token['exp_month'] = $this->order->payment->exp_month;
			$token['exp_year']  = $this->order->payment->exp_year;
		}

		// NETbilling uses the tokenization API call transaction ID as the token
		return new Framework\SV_WC_Payment_Gateway_Payment_Token( $this->get_transaction_id(), $token );
	}


	/**
	 * Returns a message appropriate for a frontend user.  This should be used
	 * to provide enough information to a user to allow them to resolve an
	 * issue on their own, but not enough to help nefarious folks fishing for
	 * info.
	 *
	 * @since 1.0.0
	 * @see Framework\SV_WC_Payment_Gateway_API_Response_Message_Helper
	 * @see Framework\SV_WC_Payment_Gateway_API_Response::get_user_message()
	 * @return string user message, if there is one
	 */
	public function get_user_message() {
		return null;
	}


	/**
	 * Returns the string representation of this response
	 *
	 * @since 1.0.0
	 * @see Framework\SV_WC_Payment_Gateway_API_Response::to_string()
	 * @return string response
	 */
	public function to_string() : string {

		return print_r( $this->parameters, true );
	}


	/**
	 * Returns the string representation of this response with any and all
	 * sensitive elements masked or removed
	 *
	 * @since 1.0.0
	 * @see Framework\SV_WC_Payment_Gateway_API_Response::to_string_safe()
	 * @return string response safe for logging/displaying
	 */
	public function to_string_safe() : string {

		// no sensitive data to mask
		return $this->to_string();

	}


	/**
	 * Get the payment type for this response.
	 *
	 * @since 1.8.0
	 * @return string The payment type. Either 'credit_card' or 'echeck'
	 */
	public function get_payment_type() : string {

		return $this->order->payment->type;
	}


}
