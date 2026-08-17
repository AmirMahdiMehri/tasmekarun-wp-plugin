<?php
defined( 'ABSPATH' ) || exit;
/* ---------- رنگ اصلی قالب (WoodMart) ---------- */
function tk_theme_primary_color() {
	static $cache = null;
	if ( null !== $cache ) { return $cache; }
	$keys = array( 'color-primary', 'primary-color', 'primary_color', 'color_primary', 'main-color', 'primary' );
	$clean = function ( $v ) { $v = trim( (string) $v ); return preg_match( '/^#?[0-9a-fA-F]{3,8}$/', $v ) ? ( 0 === strpos( $v, '#' ) ? $v : '#' . $v ) : null; };
	if ( function_exists( 'woodmart_get_opt' ) ) {
		foreach ( $keys as $k ) { $v = $clean( woodmart_get_opt( $k ) ); if ( $v ) { $cache = $v; return $cache; } }
	}
	$opts = get_option( 'woodmart_theme_options', array() );
	if ( is_array( $opts ) ) {
		foreach ( $keys as $k ) { if ( isset( $opts[ $k ] ) ) { $v = $clean( $opts[ $k ] ); if ( $v ) { $cache = $v; return $cache; } } }
		foreach ( $opts as $k => $v ) { if ( is_string( $k ) && false !== strpos( $k, 'primar' ) ) { $v2 = $clean( $v ); if ( $v2 ) { $cache = $v2; return $cache; } } }
	}
	$cache = '#0E3A40';
	return $cache;
}
/* کلیدهای on/off ادمین ← رنگ اصلی قالب */
add_action( 'admin_head', 'tk_admin_toggle_color' );
function tk_admin_toggle_color() {
	echo '<style>.tk-sw input:checked+span{background:' . esc_attr( tk_theme_primary_color() ) . ' !important}</style>';
}
/* کلید on/off فرانت (تومان/ریال) ← رنگ اصلی قالب */
add_action( 'wp_head', 'tk_front_toggle_color', 20 );
function tk_front_toggle_color() {
	echo '<style>.tk-unit input:checked + .tk-unit-track{background:' . esc_attr( tk_theme_primary_color() ) . ' !important}</style>';
}
/* ---------- واحد پول روی کالاها (پیش‌فرض: تومان) ---------- */
function tk_prices_toman() {
	$set = get_option( 'tk_settings', array() );
	if ( is_array( $set ) && array_key_exists( 'prices_toman', $set ) ) { return (bool) $set['prices_toman']; }
	return true;
}
function tk_money_display( $rial ) {
	if ( null === $rial ) { return ''; }
	$rial = (float) $rial;
	return tk_prices_toman() ? number_format_i18n( round( $rial / 10 ) ) . ' تومان' : number_format_i18n( $rial ) . ' ریال';
}