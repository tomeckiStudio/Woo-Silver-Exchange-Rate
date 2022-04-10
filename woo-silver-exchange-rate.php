<?php
/*
 * Plugin Name:       Woo Silver Exchange Rate
 * Description:       Silver exchange rate plugin for WooCommerce via metals-api.com
 * Version:           1.0.1
 * Author:            tomeckiStudio
 * Author URI:        https://tomecki.studio/
 * Text Domain:       woo-silver-exchange-rate
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.html
 */

defined('ABSPATH') or die('You do not have permissions to this file!');

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))){
	add_action('init', 'wser_init');
}else{
	add_action('admin_init', 'wser_plugin_deactivate');
	add_action('admin_notices', 'wser_woocommerce_missing_notice');
}

function wser_init(){
	if(is_admin() && current_user_can('administrator')){
		include_once 'includes/wser-backend.php';
	}
	include_once 'includes/wser-frontend.php';
}

function wser_woocommerce_missing_notice(){
	echo sprintf('<div class="error"><p>%s</p></div>', __( 'You need an active WooCommerce for the Woo Silver Exchange Rate plugin to work!', 'woo-silver-exchange-rate'));
	if (isset($_GET['activate']))
		unset($_GET['activate']);	
}

function wser_plugin_deactivate(){
	deactivate_plugins(plugin_basename(__FILE__));
}

function wser_activation_hook(){
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	global $wpdb;
	
	$table_name = $wpdb->prefix . "currencies"; 

	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		  id mediumint(9) NOT NULL AUTO_INCREMENT,
		  name tinytext NOT NULL,
		  price text NOT NULL,
		  date text NOT NULL,
		  PRIMARY KEY  (id)
		) $charset_collate;";

	dbDelta($sql);

	$wpdb->insert(
		$table_name, 
		array(
			'name' => 'silver', 
			'price' => 0, 
			'date' => date("Y-m-d H:i:s"),
		) 
	);
}
register_activation_hook(__FILE__, 'wser_activation_hook');

function wser_deactivation_hook(){
	global $wpdb;

	$table_name = $wpdb->prefix . "currencies";

	$wpdb->query("DROP TABLE IF EXISTS {$table_name}");
}
register_deactivation_hook(__FILE__, 'wser_deactivation_hook');
