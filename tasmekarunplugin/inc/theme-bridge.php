<?php
defined( 'ABSPATH' ) || exit;

/* ---------- تشخیص رنگ اصلی قالب (WoodMart راستچین: xts-woodmart-options) ---------- */
function tk_theme_primary_color() {
	static $cache = null;
	if ( null !== $cache ) { return $cache; }
	$is_color = function ( $v ) {
		$v = trim( (string) $v );
		return (bool) preg_match( '/^#([0-9a-fA-F]{3,8})$/', $v ) || (bool) preg_match( '/^rgba?\(/i', $v );
	};
	$opts = get_option( 'xts-woodmart-options', array() );
	if ( ! is_array( $opts ) ) { $opts = array(); }
	/* ۱) کلیدهای محتمل */
	foreach ( array( 'primary-color', 'color-primary', 'primary_color', 'color_primary', 'primary' ) as $k ) {
		if ( isset( $opts[ $k ] ) && $is_color( $opts[ $k ] ) ) { $cache = trim( $opts[ $k ] ); return $cache; }
	}
	/* ۲) اسکن: هر کلیدی که «primary» داره و مقدارش رنگه */
	foreach ( $opts as $k => $v ) {
		if ( is_string( $k ) && false !== strpos( $k, 'primary' ) && $is_color( $v ) ) { $cache = trim( $v ); return $cache; }
	}
	/* ۳) فال‌بک برند */
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