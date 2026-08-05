<?php
/**
 * Plugin Name: Tasmekarun Dynamic Belt Engine
 * Description: موتور داینامیک فروشگاه تسمه کارون — کاتالوگ مجازی، قیمت ضریبی، موجودی و مدیای داینامیک
 * Version:     0.2.0
 * Author:      Tasme Karun
 * Text Domain: tasmekarun
 * Requires PHP: 7.4
 */
defined( 'ABSPATH' ) || exit;

define( 'TK_VERSION', '0.2.0' );
define( 'TK_PATH', plugin_dir_path( __FILE__ ) );
define( 'TK_URL',  plugin_dir_url( __FILE__ ) );

require_once TK_PATH . 'inc/schema.php';
require_once TK_PATH . 'inc/engine.php';
require_once TK_PATH . 'inc/frontend.php';
if ( is_admin() ) { require_once TK_PATH . 'inc/admin.php'; }

register_activation_hook( __FILE__, 'tk_activate' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

function tk_activate() {
	tk_create_tables();
	tk_seed_data();
	tk_seed_v2();
	add_option( 'tk_settings', array( 'phone' => '021-00000000', 'currency' => 'ریال' ) );
	update_option( 'tk_version', TK_VERSION );
	flush_rewrite_rules();
}

add_action( 'plugins_loaded', 'tk_maybe_upgrade', 5 );
function tk_maybe_upgrade() {
	if ( get_option( 'tk_version' ) !== TK_VERSION ) {
		tk_create_tables();
		tk_seed_data();
		tk_seed_v2();
		update_option( 'tk_version', TK_VERSION );
	}
}

/* بخش جدید: تسمه تایم (Time 127 و…) */
function tk_seed_v2() {
	global $wpdb; $p = $wpdb->prefix;
	$cat = (int) $wpdb->get_var( "SELECT id FROM {$p}tk_categories WHERE slug='timing'" );
	if ( $cat ) {
		$wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO {$p}tk_sections (category_id,slug,name_fa,formula_key,length_std,sort) VALUES (%d,'TIME','تسمه تایم (دندانه‌ای)','PLACEHOLDER','',90)",
			$cat ) );
	}
}