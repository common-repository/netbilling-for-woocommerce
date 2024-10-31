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
 * NETbilling for WC eCheck Payment Gateway
 *
 * Handles all eCheck purchases
 *
 * This is a direct eCheck gateway that supports tokenization, subscriptions and pre-orders.
 *
 * @since 1.0.0
 */
class eCheck extends Gateway {


	/**
	 * Initialize the gateway
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		parent::__construct(
			Plugin::ECHECK_GATEWAY_ID,
			plugin::instance(),
			[
				'method_title'       => apply_filters( 'wc_payment_gateway_' . Plugin::ECHECK_GATEWAY_ID . '_gateway_method_title', __( 'NETbilling eCheck', 'netbilling-for-woocommerce' ) ),
				'method_description' => apply_filters( 'wc_payment_gateway_' . Plugin::ECHECK_GATEWAY_ID . '_gateway_method_description', __( 'Allow customers to securely pay using their bank accounts with NETbilling.', 'netbilling-for-woocommerce' ) ),
				'supports'           => [
					self::FEATURE_PRODUCTS,
					self::FEATURE_TOKENIZATION,
					self::FEATURE_TOKEN_EDITOR,
					self::FEATURE_PAYMENT_FORM,
				 ],
				'payment_type'       => 'echeck',
				'environments'       => ['production' => __( 'Production', 'netbilling-for-woocommerce' ), 'test' => __( 'Test', 'netbilling-for-woocommerce' )],
				'shared_settings'    => $this->shared_settings_names,
			]
		);

		// remove support for customer payment method changes (eChecks don't support $0 transactions)
		if ( $this->supports_subscriptions() ) {
			$this->remove_support( ['subscription_payment_method_change_customer'] );
		}

		// remove account type field
		add_filter( 'wc_' . $this->get_id() . '_payment_form_default_echeck_fields', array( $this, 'modify_payment_form_fields' ), 5 );

		// add assent fields to payment form
		add_action( 'wc_' . $this->get_id() . '_payment_form', array( $this, 'add_assent_field' ), 1 );
	}


	/**
	 * Removes the "account type" field for eCheck payments.
	 *
	 * @since 1.0.0
	 *
	 * @param array $fields the payment fields
	 * @return array updated fields
	 */
	public function modify_payment_form_fields( $fields ) : array {

		unset( $fields['account-type'] );

		return $fields;
	}


	/**
	 * Add the assent message to the payment form.
	 *
	 * @since 1.0.0
	 */
	public function add_assent_field() {

		$order_total = ( $order = wc_get_order( $this->get_checkout_pay_page_order_id() ) ) ? $order->get_total() : WC()->cart->total;
		$img_src     = sprintf( 'https://secure.nbcheck.com/assent/v1_png?account=%s&amount=%s&site_tag=%s', $this->get_account_id(), $order_total, $this->get_site_tag() );

		$this->add_assent_field_js();

		ob_start();
		?>
		<p class="form-row form-row-wide wc-<?php echo sanitize_html_class( $this->get_id_dasherized() ); ?>-assent-message">
			<img src="<?php echo esc_url( $img_src ); ?>" name="assent_img" onMouseOver="confirm_assent(1)" onMouseOut="confirm_assent(2)" onClick="confirm_assent(3)"  style="max-height:100px;float:left;" />
		</p>
		<input type="hidden" id="assent_key" name="assent_key" />
		<div class="clear"></div>
		<?php

		echo ob_get_clean();
	}


	/**
	 * Adds the assent field JavaScript.
	 *
	 * @since 1.0.0
	 * @link https://secure.netbilling.com/public/docs/agent/public/directmode/assent_key.html
	 */
	protected function add_assent_field_js() {

		ob_start();
		?>
<script>
	function get_assent_amount(amt) { var s = document.images.assent_img.src; var a1 = 8 + Math.max( s.indexOf("?amount="), s.indexOf("&amount=")); var p = s.substr(a1); var a2 = s.indexOf("&",a1); if (a2 >= 0) { p = p.substr(0,a2) } return parseFloat(p); } function set_assent_amount(amt) { var s = document.images.assent_img.src; var a1 = 8 + Math.max( s.indexOf("?amount="), s.indexOf("&amount=")); var a2 = s.indexOf("&",a1); var p = s.substr(0,a1) + amt; if (a2 > 0) { p += s.substr(a2); } document.images.assent_img.src = p; confirm_assent(4); } function get_assent_recurring_amount(amt) { var s = document.images.assent_img.src; var a1 = 18 + Math.max( s.indexOf("?recurring_amount="), s.indexOf("&recurring_amount=")); var p = s.substr(a1); var a2 = s.indexOf("&",a1); if (a2 >= 0) { p = p.substr(0,a2) } return parseFloat(p); } function set_assent_recurring_amount(amt) { var s = document.images.assent_img.src; var a1 = 18 + Math.max( s.indexOf("?recurring_amount="), s.indexOf("&recurring_amount=")); var a2 = s.indexOf("&",a1); var p = s.substr(0,a1) + amt; if (a2 > 0) { p += s.substr(a2); } document.images.assent_img.src = p; confirm_assent(4); } function toggle_assent_recurring_amount(seq_no,desc,amt,ckd) { var s; var a1; var a2; var p; if (seq_no < 10) { seq_no = "0"+seq_no; } s = document.images.assent_img.src; a1 = Math.max( s.indexOf("?cs"+seq_no+"="), s.indexOf("&cs"+seq_no+"=")); p = s; if (a1 < 0 && ckd) { p = s + "&cs" + seq_no + "=" + amt; } else if (a1 >= 0) { a1 += 6; a2 = s.indexOf("&",a1); p = s.substr(0,a1); if (ckd) { p += amt }; if (a2 > 0) { p += s.substr(a2); } } s = p; a1 = Math.max( s.indexOf("?cd"+seq_no+"="), s.indexOf("&cd"+seq_no+"=")); if (a1 < 0 && ckd) { p = s + "&cd" + seq_no + "=" + escape(desc); } else if (a1 >= 0) { a1 += 6; a2 = s.indexOf("&",a1); p = s.substr(0,a1); if (ckd) { p += escape(desc); } if (a2 > 0) { p += s.substr(a2); } } document.images.assent_img.src = p; confirm_assent(4); } function confirm_assent(act) { var s = document.images.assent_img.src; var sl = s.lastIndexOf("/") + 1; var p = s.substr(0,sl); var qm = s.indexOf("?"); var x = s.substr(sl,qm-sl)+"-"; var q = s.substr(qm); var ck = x.indexOf("-checked") > 0; var fc = x.indexOf("-focused") > 0; switch(act) {case 0:return ck; case 1:fc=true;break; case 2:fc=false;break; case 3:ck=!ck; break; case 4: ck=false;} x = x.substr(0,x.indexOf("-")); if(ck) { x+="-checked"; } if(fc) { x+="-focused"; } var ak = q.indexOf("assent_key"); var rn = ""; var f = document.getElementById("assent_key"); if (!f) { alert('Webmaster: You must have an assent_key to update.')} if (ak < 0 && ck) { rn="8999999999999"+Math.floor(Math.random()*99999999999999); rn=rn.substr(rn.length-14); q += "&assent_key=" + rn; f.value = rn; } document.images.assent_img.src=p+x+q; }
</script>
		<?php

		echo ob_get_clean();
	}


	/**
	 * Return the default values for this payment method, used to pre-fill
	 * an authorize.net valid test account number when in testing mode
	 *
	 * @since 1.0.0
	 *
	 * @see Framework\SV_WC_Payment_Gateway::get_payment_method_defaults()
	 * @return array
	 */
	public function get_payment_method_defaults() : array {

		$defaults = parent::get_payment_method_defaults();

		if ( $this->is_test_environment() ) {

			$defaults['routing-number'] = '123456789';
			$defaults['account-number'] = '8675309';
		}

		return $defaults;
	}


	/**
	 * Override the framework get_order to add the NETbilling "assent key" which is required to
	 * process eCheck transactions
	 *
	 * @since 1.0.0
	 * @param int $order_id the WC order ID
	 * @see WC_Payment_Gateway::get_order()
	 * @return WC_Order
	 */
	public function get_order( $order_id ) : WC_Order {

		// add common order members
		$order = parent::get_order( $order_id );

		// add NETbilling "assent key" which is required to process eChecks
		$order->payment->assent_key = Framework\SV_WC_Helper::get_posted_value( 'assent_key' );

		return $order;
	}


}
