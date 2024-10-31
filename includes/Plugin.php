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
 * NETbilling for WC gateway main plugin class.
 *
 * @since 1.0
 */
class Plugin extends Framework\SV_WC_Payment_Gateway_Plugin {


	/** string version number */
	const VERSION = '1.0.0';

	/** @var Plugin single instance of this plugin */
	protected static $instance;

	/** string the plugin id */
	const PLUGIN_ID = 'netbilling_for_wc';

	/** string the credit card gateway class name */
	const CREDIT_CARD_GATEWAY_CLASS_NAME = '\NETbilling\WooCommerce\Gateway\Credit_Card';

	/** string the eCheck gateway class name */
	const ECHECK_GATEWAY_CLASS_NAME = '\NETbilling\WooCommerce\Gateway\eCheck';

	/** string the credit card gateway ID */
	const CREDIT_CARD_GATEWAY_ID = 'netbilling';

	/** string the eCheck gateway ID */
	const ECHECK_GATEWAY_ID = 'netbilling_echeck';


	/**
	 * Setup main plugin class
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		parent::__construct(
			self::PLUGIN_ID,
			self::VERSION,
			[
				'gateways' => [
					self::CREDIT_CARD_GATEWAY_ID => self::CREDIT_CARD_GATEWAY_CLASS_NAME,
					// eChecks are not supported at the moment
					// self::ECHECK_GATEWAY_ID      => self::ECHECK_GATEWAY_CLASS_NAME,
				],
				'dependencies' => [],
				'require_ssl'  => true,
				'supports'     => [
					self::FEATURE_CAPTURE_CHARGE,
					self::FEATURE_MY_PAYMENT_METHODS,
				],
				'text_domain' => 'netbilling-for-woocommerce',
			]
		);

		// load gateway files
		$this->includes();
	}


	/**
	 * Loads any required files
	 *
	 * @since 1.0.0
	 */
	public function includes() {

		$plugin_path = $this->get_plugin_path();

		// gateway classes
		require_once( $plugin_path . '/Gateway.php' );
		require_once( $plugin_path . '/Gateways/CreditCard.php' );
		require_once( $plugin_path . '/Gateways/eCheck.php' );
	}


	/** Helper methods ******************************************************/


	/**
	 * Main NETbilling for WC class Instance, ensures only one instance is/can be loaded
	 *
	 * @since 1.0.0
	 * @return Plugin
	 */
	public static function instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}


	/**
	 * Gets the plugin documentation URL
	 *
	 * @since 1.0.0
	 * @see SV_WC_Plugin::get_documentation_url()
	 * @return string
	 */
	public function get_documentation_url() {

		return 'https://wordpress.org/plugins/netbilling-for-woocommerce/#faq';
	}


	/**
	 * Gets the plugin support URL
	 *
	 * @since 1.0.0
	 * @see SV_WC_Plugin::get_support_url()
	 * @return string
	 */
	public function get_support_url() {

		return 'https://wordpress.org/support/plugin/netbilling-for-woocommerce/';
	}


	/**
	 * Returns the plugin name, localized
	 *
	 * @since 1.0
	 * @see SV_WC_Payment_Gateway::get_plugin_name()
	 * @return string the plugin name
	 */
	public function get_plugin_name() : string {

		return apply_filters( 'wc_payment_gateway_' . $this->get_id() . '_plugin_name', __( 'NETbilling for WooCommerce', 'netbilling-for-woocommerce' ) );
	}

	/**
	 * Returns the plugin file, overridden because the plugin class is located within the src directory and the framework
	 * method assumes the plugin class is always in the same directory as the loader class
	 *
	 * @since 1.0.0
	 * @return string /dir/to/plugins/netbilling-for-woocommerce/netbilling-for-woocommerce.php
	 */
	public function get_plugin_file() : string {

		return trailingslashit( dirname( $this->get_file(), 2 ) ) . 'netbilling-for-woocommerce.php';
	}


	/**
	 * Returns __FILE__
	 *
	 * @since 1.0.0
	 * @return string the full path and filename of the plugin file
	 */
	protected function get_file() : string {

		return __FILE__;
	}


	/**
	 * Initializes the lifecycle handler.
	 *
	 * @since 1.0.0
	 */
	protected function init_lifecycle_handler() {

		require_once( $this->get_plugin_path() . '/Lifecycle.php' );

		$this->lifecycle_handler = new Lifecycle( $this );
	}


}
