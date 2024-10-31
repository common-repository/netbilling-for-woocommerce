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

namespace NETbilling\WooCommerce\Gateway;

use SkyVerge\WooCommerce\PluginFramework\v5_10_12 as Framework;
use NETbilling\WooCommerce\Gateway;
use NETbilling\WooCommerce\Plugin as Plugin;

defined( 'ABSPATH' ) or exit;

/**
 * NETbilling for WC Credit Card gateway
 *
 * Handles all credit card purchases
 *
 * This is a direct credit card gateway that supports card types, charge,
 * authorization, tokenization, subscriptions and pre-orders.
 *
 * @since 1.0.0
 */
class Credit_Card extends Gateway {


	/**
	 * Initialize the gateway
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		parent::__construct(
			Plugin::CREDIT_CARD_GATEWAY_ID,
			Plugin::instance(),
			[
				'method_title'       => apply_filters( 'wc_payment_gateway_' . Plugin::CREDIT_CARD_GATEWAY_ID . '_credit_card_gateway_method_title', __( 'NETbilling', 'netbilling-for-woocommerce' ) ),
				'method_description' => apply_filters( 'wc_payment_gateway_' . Plugin::CREDIT_CARD_GATEWAY_ID . '_credit_card_gateway_method_description', __( 'Allow customers to securely pay using their credit cards with NETbilling.', 'netbilling-for-woocommerce' ) ),
				'supports'           => [
					self::FEATURE_PRODUCTS,
					self::FEATURE_CARD_TYPES,
					self::FEATURE_PAYMENT_FORM,
					self::FEATURE_TOKENIZATION,
					self::FEATURE_TOKEN_EDITOR,
					self::FEATURE_CREDIT_CARD_CHARGE,
					self::FEATURE_CREDIT_CARD_CHARGE_VIRTUAL,
					self::FEATURE_CREDIT_CARD_AUTHORIZATION,
					self::FEATURE_CREDIT_CARD_CAPTURE,
				 ],
				'payment_type'       => 'credit-card',
				'environments'       => ['production' => __( 'Production', 'netbilling-for-woocommerce' ), 'test' => __( 'Test', 'netbilling-for-woocommerce' ) ],
			]
		);
	}


	/**
	 * Return the default values for this payment method, used to pre-fill
	 * a valid test account number when in testing mode.
	 *
	 * @since 1.0.0
	 *
	 * @see Framework\SV_WC_Payment_Gateway::get_payment_method_defaults()
	 * @return array
	 */
	public function get_payment_method_defaults() : array {

		$defaults = parent::get_payment_method_defaults();

		if ( $this->is_test_environment() ) {

			$defaults['account-number'] = '4111111111111111';
			$defaults['exp-month']      = '1';
			$defaults['exp-year']       = date( 'Y' ) + 1;
			$defaults['csc']            = '123';
		}

		return $defaults;
	}


}
