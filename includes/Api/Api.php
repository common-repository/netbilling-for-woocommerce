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

namespace NETbilling\WooCommerce;

use SkyVerge\WooCommerce\PluginFramework\v5_10_12 as Framework;
use NETbilling\WooCommerce\API\Request;
use NETbilling\WooCommerce\API\Response;

defined( 'ABSPATH' ) or exit;

/**
 * NETbilling for WC API Class
 *
 * Handles sending/receiving/parsing of NETbilling API data, this is the main API
 * class responsible for communication with the NETbilling API
 *
 * @since 1.0.0
 */
class Api extends Framework\SV_WC_API_Base implements Framework\SV_WC_Payment_Gateway_API {


	/** the production endpoint */
	const PRODUCTION_ENDPOINT = 'https://secure.netbilling.com:1402/gw/sas/direct3.2';

	/** the test endpoint */
	const TEST_ENDPOINT = 'http://secure.netbilling.com:1401/gw/sas/direct3.2';

	/** @var string the gateway id */
	private $gateway_id;

	/** @var string NETbilling account ID */
	protected $account_id;

	/** @var string NETbilling site tag */
	protected $site_tag;

	/** @var \Requests_Response */
	protected $requests_response;

	/** @var \WC_Order|null order associated with the request, if any */
	protected $order;


	/**
	 * Constructor - setup request object and set endpoint
	 *
	 * @since 1.0.0
	 * @param string $gateway_id the plugin identifier
	 * @param string $api_environment the API environment
	 * @param string $account_id the account ID
	 * @param string $site_tag the site tag
	 * @return Api
	 */
	public function __construct( String $gateway_id, String $api_environment, String $account_id, String $site_tag ) {

		$this->gateway_id = $gateway_id;

		$this->request_uri = ( 'production' === $api_environment ) ? self::PRODUCTION_ENDPOINT : self::TEST_ENDPOINT;

		$this->account_id = $account_id;
		$this->site_tag = $site_tag;

		// WP HTTP API only allows ports 80, 443, 8080 by default, NETbilling uses 1401/1402
		add_filter( 'http_allowed_safe_ports', [ $this, 'allow_netbilling_api_port' ], 10, 2 );
	}


	/**
	 * Create a new credit card charge transaction
	 *
	 * @since 1.0.0
	 * @see Framework\SV_WC_Payment_Gateway_API::credit_card_charge()
	 * @param \WC_Order $order the order
	 * @return Response NETbilling API response object
	 * @throws \Exception network timeouts, etc
	 */
	public function credit_card_charge( \WC_Order $order ) : Response {

		$this->order = $order;

		$request = $this->get_new_request();

		$request->create_credit_card_charge( $order );

		return $this->perform_request( $request );
	}


	/**
	 * Create a new credit card auth transaction
	 *
	 * @since 1.0.0
	 * @see Framework\SV_WC_Payment_Gateway_API::credit_card_authorization()
	 * @param \WC_Order $order the order
	 * @return Response NETbilling API response object
	 * @throws \Exception network timeouts, etc
	 */
	public function credit_card_authorization( \WC_Order $order ) : Response {

		$this->order = $order;

		$this->request->create_credit_card_auth( $order );

		return $this->perform_request();
	}


	/**
	 * Perform a credit card capture for a given authorized order
	 *
	 * @since 1.0.0
	 * @see Framework\SV_WC_Payment_Gateway_API::credit_card_capture()
	 * @param \WC_Order $order the order
	 * @return Response credit card capture response
	 * @throws \Exception network timeouts, etc
	 */
	public function credit_card_capture( \WC_Order $order ) : Response {

		$this->order = $order;

		$this->request->create_credit_card_capture( $order );

		return $this->perform_request();
	}


	/**
	 * Store sensitive payment information for a particular customer.
	 *
	 * @since 1.0.0
	 * @see Framework\SV_WC_Payment_Gateway_API::tokenize_payment_method()
	 * @param \WC_Order $order the order with associated payment and customer info
	 * @return Response NETbilling API response object
	 * @throws \Exception network timeouts, etc
	 */
	public function tokenize_payment_method( \WC_Order $order ) : Response {

		$this->order = $order;

		$this->request->tokenize( $order );

		return $this->perform_request();
	}


	/**
	 * Return a new request with the response handler set
	 *
	 * @since 1.0.0
	 * @param array $args unused
	 * @return Request
	 */
	public function get_new_request( $args = [] ) {

		$this->set_response_handler( '\\NETbilling\WooCommerce\API\Response' );

		return new Request( $this->account_id, $this->site_tag );
	}


	/**
	 * WP HTTP API only allows ports 80, 443, 8080 by default so add the ports that NETbilling uses
	 *
	 * @since 1.0.0
	 * @param array $ports
	 * @param string $host
	 * @return array
	 */
	public function allow_netbilling_api_port( $ports, $host ) {

		if ( 'secure.netbilling.com' === $host ) {
			$ports = array_merge( $ports, [ 1401, 1402 ] );
		}

		return $ports;
	}


	/**
	 * Return the parsed response object for the request
	 *
	 * Overridden because the order object is needed to parse the response
	 *
	 * @since 1.0.0
	 * @param string $raw_response_body
	 * @return \SV_WC_API_Request response class instance which implements SV_WC_API_Request
	 */
	protected function get_parsed_response( $raw_response_body ) {

		$handler_class = $this->get_response_handler();

		return new $handler_class( $raw_response_body, $this->order );
	}


	/**
	 * Overridden to set the WP_HTTP_Requests_Response object
	 *
	 * In a future FW version, it would be convenient to have a method for this instead {MR 2022-04-14}
	 *
	 * @param string $request_uri
	 * @param array $request_args
	 * @return array|\WP_Error
	 */
	protected function do_remote_request( $request_uri, $request_args ) {

		$response = parent::do_remote_request( $request_uri, $request_args );

		if ( ! is_wp_error( $response ) && isset( $response['http_response'] ) && is_object( $response['http_response'] ) ) {
			$this->requests_response = $response['http_response']->get_response_object();
		}

		return $response;
	}


	/**
	 * Check for non-200 response codes, which indicate an error:
	 *
	 *   Status Codes (section 4.2 of http://secure.netbilling.com/public/docs/merchant/public/directmode/directmode3protocol.html)
	 *    200 - success
	 *    600-698 - invalid input
	 *    700-798 - processing error
	 *    699 or 799 - exception
	 *   Note that NETbilling returns a custom status message which is parsed and set here as well.
	 *
	 * @since 1.0.0
	 * @throws Framework\SV_WC_Payment_Gateway_Exception non-200 response codes
	 */
	protected function do_pre_parse_response_validation() {

		if ( 200 != $this->get_response_code() ) {

			$this->response_message = $this->get_custom_status_message( $this->requests_response->raw );

			throw new Framework\SV_WC_Payment_Gateway_Exception( $this->response_message );
		}
	}


	/**
	 * Parse the custom HTTP status message, e.g. HTTP/1.0 604 Missing Parameter (pay_type)
	 *
	 * @since 1.0.0
	 * @param string $raw_headers
	 * @return String
	 */
	private function get_custom_status_message( $raw_headers ) : String {

		// get the raw headers (adapted from WP_HTTP::processHeaders)
		$raw_headers = str_replace( "\r\n", "\n", $raw_headers );
		$raw_headers = preg_replace('/\n[ \t]/', ' ', $raw_headers);

		// spit the header string
		$headers = explode( "\n", $raw_headers );

		// get the HTTP status header string
		$message = array_shift( $headers );

		// strip the status code to retrieve the custom status message
		return trim( str_replace( "HTTP/1.0 {$this->get_response_code()}", '', $message ) );
	}


	/**
	 * Return the API ID, mainly used for namespacing actions
	 *
	 * @since 1.0.0
	 * @return string
	 */
	protected function get_api_id() : string {
		return $this->gateway_id;
	}


	/**
	 * Return the order associated with the request, if any
	 *
	 * @since 1.0.0
	 * @return \WC_Order|null
	 */
	public function get_order() {

		return $this->order;
	}


	/**
	 * Return the plugin instance
	 *
	 * @since 1.0.0
	 * @return Framework\SV_WC_Plugin
	 */
	public function get_plugin() {
		return \NETbilling\WooCommerce\Plugin::instance();
	}


	/** no-op methods - NETbilling does not support these ************************************/


	/**
	 * Perform an eCheck debit (ACH transaction) for the given order
	 *
	 * @since 1.0.0
	 * @see Framework\SV_WC_Payment_Gateway_API::check_debit()
	 * @param \WC_Order $order the order
	 * @return Response check debit response
	 * @throws \Exception network timeouts, etc
	 */
	public function check_debit( \WC_Order $order ) { }


	/**
	 * Perform a refund for the given order
	 *
	 * @since 1.0.0
	 * @see Framework\SV_WC_Payment_Gateway_API::refund()
	 * @param \WC_Order $order order object
	 * @return Response refund response
	 * @throws Framework\SV_WC_Payment_Gateway_Exception network timeouts, etc
	 */
	public function refund( \WC_Order $order ) { }


	/**
	 * Perform a void for the given order
	 *
	 * If the gateway does not support voids, this method can be a no-op.
	 *
	 * @since 1.0.0
	 * @see Framework\SV_WC_Payment_Gateway_API::void()
	 * @param \WC_Order $order order object
	 * @return Response void response
	 * @throws Framework\SV_WC_Payment_Gateway_Exception network timeouts, etc
	 */
	public function void( \WC_Order $order ) { }

	/**
	 * NETbilling does not support getting tokenized payment methods
	 *
	 * @since 1.0.0
	 * @see Framework\SV_WC_Payment_Gateway_API::supports_get_tokenized_payment_methods()
	 * @return bool false
	 */
	public function supports_get_tokenized_payment_methods() : bool {
		return false;
	}


	/**
	 * NETbilling does not support getting tokenized payment methods
	 *
	 * @since 1.0.0
	 * @see Framework\SV_WC_Payment_Gateway_API::get_tokenized_payment_methods()
	 * @param string $customer_id
	 * @return bool false
	 */
	public function get_tokenized_payment_methods( $customer_id ) : bool {
		return false;
	}


	/**
	 * NETbilling does not support deleting tokenized payment methods
	 *
	 * @since 1.0.0
	 * @see Framework\SV_WC_Payment_Gateway_API::supports_remove_tokenized_payment_method()
	 * @return boolean false
	 */
	public function supports_remove_tokenized_payment_method() : bool {
		return false;
	}


	/**
	 * NETbilling does not support deleting tokenized payment methods
	 *
	 * @since 1.0.0
	 * @see Framework\SV_WC_Payment_Gateway_API::remove_tokenized_payment_method()
	 * @param string $token
	 * @param string $customer_id optional unique customer id for gateways that support it
	 * @return boolean false
	 */
	public function remove_tokenized_payment_method( $token, $customer_id ) : bool {
		return false;
	}


	/**
	 * NETbilling does not support updating tokenized payment methods
	 *
	 * @since 1.0.0
	 * @see Framework\SV_WC_Payment_Gateway_API::supports_update_tokenized_payment_method()
	 * @return false
	 */
	public function supports_update_tokenized_payment_method() : bool {
		return false;
	}


	/**
	 * NETbilling does not support updating a tokenized payment method
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Order $order order object
	 */
	public function update_tokenized_payment_method( \WC_Order $order ) { }


}
