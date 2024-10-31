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
 * Plugin lifecycle handler.
 *
 * @since 1.0.0
 */
class Lifecycle extends Framework\Plugin\Lifecycle {


	/**
	 * If upgrading from the legacy plugin, show an upgrade notice.
	 *
	 * @since 1.0.0
	 */
	public function add_admin_notices() {

		parent::add_admin_notices();

		if ( 'yes' === get_option( 'wc_netbilling_for_wc_legacy_upgrade' ) ) {

			// TODO: in a future version, the legacy_upgrade option can be deleted {MR 2022-02-16}

			// notice to delete the legacy plugin
			$message = sprintf(
			/* translators: Placeholders: %1$s - <strong>, %2$s - </strong>, %3$s - <a> tag, %4$s - </a> tag */
				esc_html__( '%1$sYou have upgraded to the latest version of NETbilling for WooCommerce%2$s. The legacy NETbilling for WooCommerce Gateway plugin has been deactivated and can be %3$ssafely deleted%4$s.', 'netbilling-for-woocommerce' ),
				'<strong>', '</strong>',
				'<a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">', '</a>'
			);

			$this->get_plugin()->get_admin_notice_handler()->add_admin_notice( $message, 'legacy-upgrade', [
				'always_show_on_settings' => false,
			] );

			// notice that eChecks are not currently supported
			$message = sprintf(
				esc_html__( '%1$sNETbilling for WooCommerce%2$s: Please note that eChecks are not currently supported.' ), '<strong>', '</strong>'
			);
			$this->get_plugin()->get_admin_notice_handler()->add_admin_notice( $message, 'echecks-not-supported', [
				'always_show_on_settings' => false,
			] );
		}
	}


}
