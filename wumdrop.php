<?php
/*
Plugin Name: WooCommerce WumDrop Integration
Plugin URI: http://woothemes.com/woocommerce
Description: WumDrop deliveries for WooCommerce
Version: 1.0.0
Author: Hugh Lashbrooke
Author URI: http://www.hughlashbrooke.com
Requires at least: 4.0
Tested up to: 4.1

	Copyright: © 2015 WumDrop
	License: GNU General Public License v2.0+
	License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Required functions
 */
if ( ! function_exists( 'woothemes_queue_update' ) )
	require_once( 'woo-includes/woo-functions.php' );

/**
 * Plugin updates
 */
woothemes_queue_update( plugin_basename( __FILE__ ), '', '' );

add_action( 'plugins_loaded', 'woocommerce_wumdrop_init', 0 );

/**
 * Initialise the shipping module
 **/
function woocommerce_wumdrop_init() {

    if ( ! class_exists( 'WC_Shipping_Method' ) ) {
    	return;
    }

    require_once( plugin_basename( 'classes/class-wc-wumdrop.php' ) );

    global $wc_wumdrop;
    $wc_wumdrop = new WC_WumDrop( __FILE__ );

    require_once( plugin_basename( 'classes/class-wc-wumdrop-method.php' ) );

    add_filter('woocommerce_shipping_methods', 'woocommerce_wumdrop_add' );
}

/**
 * Add the shipping module to WooCommerce
 **/
function woocommerce_wumdrop_add( $methods ) {
    $methods[] = 'WC_WumDrop_Method';
    return $methods;
}

?>