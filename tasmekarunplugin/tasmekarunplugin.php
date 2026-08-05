<?php
/**
 * Plugin Name: Tasmekarun Dynamic Belt Engine
 * Description: موتور داینامیک فروشگاه تسمه کارون — کاتالوگ مجازی، قیمت ضریبی، موجودی و مدیای داینامیک
 * Version:     0.1.0
 * Author:      Tasme Karun
 * Text Domain: tasmekarun
 * Requires PHP: 7.4
 */
defined( 'ABSPATH' ) || exit;

define( 'TK_VERSION', '0.1.0' );
define( 'TK_PATH', plugin_dir_path( __FILE__ ) );
define( 'TK_URL',  plugin_dir_url( __FILE__ ) );

require_once TK_PATH . 'inc/schema.php';
require_once TK_PATH . 'inc/engine.php';

register_activation_hook( __FILE__, 'tk_activate' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

function tk_activate() {
	tk_create_tables();
	tk_seed_data();
	add_option( 'tk_settings', array( 'phone' => '021-00000000', 'currency' => 'ریال' ) );
	flush_rewrite_rules();
}