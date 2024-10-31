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
 * @author    NETbilling
 * @copyright Copyright (c) 2013-2022, NETbilling, Inc. (support@netbilling.com)
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

namespace NETbilling\WooCommerce;

use SkyVerge\WooCommerce\PluginFramework\v5_10_12 as Framework;

defined( 'ABSPATH' ) or exit;

/**
 * NETbilling for WC Payment Gateway Parent Class
 *
 * Functionality which is shared between the credit card and echeck gateways
 *
 * @since 1.0.0
 */
class Gateway extends Framework\SV_WC_Payment_Gateway_Direct {


	/** @var string the NETbilling account ID */
	protected $account_id;

	/** @var string the NETbilling site tag */
	protected $site_tag;

	/** @var Api instance */
	protected $api;

	/** @var array shared settings names */
	protected $shared_settings_names = ['account_id'];


	/**
	 * Returns an array of form fields specific for NETbilling
	 *
	 * @since 1.0.0
	 * @see Framework\SV_WC_Payment_Gateway::get_method_form_fields()
	 * @return array of form fields
	 */
	protected function get_method_form_fields() : array {

		return [

			'account_id' => [
				'title'    => __( 'Account ID', 'netbilling-for-woocommerce' ),
				'type'     => 'text',
				'desc_tip' => __( 'Your NETbilling account ID', 'netbilling-for-woocommerce' ),
			],

			'site_tag' => [
				'title'    => __( 'Site Tag', 'netbilling-for-woocommerce' ),
				'type'     => 'text',
				'desc_tip' => __( 'Configured within your NETbilling account - this controls which email templates will be used as well as for accounting purposes', 'netbilling-for-woocommerce' ),
			]
		];
	}


	/**
	 * Add some additional details to the tokenization field
	 *
	 * @since 1.0.0
	 * @see Framework\SV_WC_Payment_Gateway::add_tokenization_form_fields()
	 * @param array $form_fields gateway form fields
	 * @return array $form_fields gateway form fields
	 */
	protected function add_tokenization_form_fields( $form_fields ) : array {

		$form_fields = parent::add_tokenization_form_fields( $form_fields );

		$form_fields['tokenization']['description'] = __( 'PCI storage must be enabled on your NETbilling account, see Step 6 of the Credit Card setup page.', 'netbilling-for-woocommerce' );

		return $form_fields;
	}


	/**
	 * Hide the environment option when WP_DEBUG is off as NETbilling recommended users not have this option
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {

		parent::admin_options();

		if ( defined( 'WP_DEBUG' ) && ! WP_DEBUG ) {
			wc_enqueue_js( "$( 'select[name=woocommerce_netbilling_environment], select[name=woocommerce_netbilling_echeck_environment' ).closest( 'tr' ).hide();" );
		}
	}


	/**
	 * Determines whether the gateway is properly configured to perform transactions, NETbilling only requires account ID.
	 *
	 * @see Framework\SV_WC_Payment_Gateway::is_configured()
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function is_configured() : bool {

		$is_configured = parent::is_configured();

		// missing configuration
		if ( ! $this->get_account_id() ) {
			$is_configured = false;
		}

		return $is_configured;
	}


	/**
	 * NETbilling has a combined sale/tokenize protocol
	 *
	 * @since 1.0.0
	 * @see WC_Payment_Gateway_Direct::tokenize_with_sale()
	 * @return boolean true
	 */
	public function tokenize_with_sale() : bool {
		return true;
	}


	/** Subscriptions support *************************************************/


	/**
	 * Tweak the labels shown when editing the payment method for a Subscription
	 *
	 * @hooked from Framework\SV_WC_Payment_Gateway_Integration_Subscriptions
	 *
	 * @since 1.0.0
	 * @see Framework\SV_WC_Payment_Gateway_Integration_Subscriptions::admin_add_payment_meta()
	 * @param array $meta payment meta
	 * @param WC_Subscription $subscription subscription being edited, unused
	 * @return array
	 */
	public function subscriptions_admin_add_payment_meta( $meta, $subscription ) : array {

		if ( isset( $meta[ $this->get_id() ] ) ) {

			// customer ID is not used
			unset( $meta[ $this->get_id() ]['post_meta'][ $this->get_order_meta_prefix() . 'customer_id' ] );
		}

		return $meta;
	}


	/**
	 * Validate the payment meta for a Subscription by ensuring the customer
	 * profile ID is numeric
	 *
	 * @since 1.0.0
	 * @see Framework\SV_WC_Payment_Gateway_Integration_Subscriptions::admin_validate_payment_meta()
	 * @param array $meta payment meta
	 * @throws \Exception if payment profile/customer profile IDs are not numeric
	 */
	public function subscriptions_admin_validate_payment_meta( $meta ) {

		// customer profile ID (payment_token) must be numeric
		if ( ! ctype_digit( (string) $meta['post_meta'][ $this->get_order_meta_prefix() . 'payment_token' ]['value'] ) ) {
			throw new \Exception( __( 'Payment token must be numeric.', 'netbilling-for-woocommerce' ) );
		}
	}


	/** Admin ******************************************************/


	/**
	 * Returns the NETbilling transaction URL for the given order
	 *
	 * @since 1.0.0
	 * @see Framework\SV_WC_Payment_Gateway::get_transaction_url()
	 * @param \WC_Order $order the order object
	 * @return string transaction url or null if not supported
	 */
	public function get_transaction_url( $order ) : string {

		if ( $trans_id = $this->get_order_meta( $order, 'trans_id' ) ) {
			$this->view_transaction_url = 'https://secure.netbilling.com/merchant/viewtrans?trans_id=' . $trans_id;
		}

		return parent::get_transaction_url( $order );
	}


	/** Getter methods ******************************************************/


	/**
	 * Get the API object
	 *
	 * @return Api NETbilling API instance
	 * @see Framework\SV_WC_Payment_Gateway::get_api()
	 * @since 1.0.0
	 */
	public function get_api() : Api {

		if ( isset( $this->api ) ) {
			return $this->api;
		}

		require_once( Plugin::instance()->get_plugin_path() . '/Api/Api.php' );
		require_once( Plugin::instance()->get_plugin_path() . '/Api/Request.php' );
		require_once( Plugin::instance()->get_plugin_path() . '/Api/Response.php' );

		return $this->api = new Api( $this->get_id(), $this->get_environment(), $this->get_account_id(), $this->get_site_tag() );
	}


	/**
	 * Get the account ID
	 *
	 * @since 1.0.0
	 * @return string the account ID
	 */
	public function get_account_id() : string {

		return $this->account_id;
	}


	/**
	 * Get the site tag set
	 *
	 * @since 1.0.0
	 * @return string the sanitized site tag
	 */
	public function get_site_tag() : string {

		return preg_replace( '/[^\d\w\-\.]/i', '', $this->site_tag );
	}


	/**
	 * Overridden because NETbilling doesn't use a customer id
	 *
	 * @since 1.0.0
	 * @see Framework\SV_WC_Payment_Gateway::get_customer_id_user_meta_name()
	 * @param $environment_id
	 * @return bool false
	 */
	public function get_customer_id_user_meta_name( $environment_id = null ) : bool {
		return false;
	}


	/**
	 * Overridden because NETbilling doesn't use a customer id
	 *
	 * @since 1.0.0
	 * @see Framework\SV_WC_Payment_Gateway::get_guest_customer_id()
	 * @param \WC_Order $order
	 * @return bool false
	 */
	public function get_guest_customer_id( \WC_Order $order ) : bool {
		return false;
	}


	/**
	 * Overridden because NETbilling doesn't use a customer id
	 *
	 * @since 1.0.0
	 * @see Framework\SV_WC_Payment_Gateway::get_customer_id()
	 * @param int $user_id
	 * @param array $args
	 * @return bool false
	 */
	public function get_customer_id( $user_id, $args = [] ) : bool {
		return false;
	}


}
