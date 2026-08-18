<?php
defined( 'ABSPATH' ) || exit;

/* ---------- ابزار: آیا مقدار، رنگ است؟ ---------- */
function tk_is_color_val( $v ) {
	$v = trim( (string) $v );
	return (bool) preg_match( '/^#([0-9a-fA-F]{3,8})$/', $v ) || (bool) preg_match( '/^rgba?\(/i', $v );
}
/* ---------- خواندن رنگ از ساختار تو در توی قالب (لایهٔ idle) ---------- */
function tk_theme_color_from( $v ) {
	if ( is_string( $v ) && tk_is_color_val( $v ) ) { return trim( $v ); }
	if ( is_array( $v ) ) {
		if ( isset( $v['idle'] ) && is_string( $v['idle'] ) && tk_is_color_val( $v['idle'] ) ) { return trim( $v['idle'] ); }
		foreach ( $v as $x ) { if ( is_string( $x ) && tk_is_color_val( $x ) ) { return trim( $x ); } }
	}
	return null;
}
function tk_theme_get_opts() {
	$opts = get_option( 'xts-woodmart-options', array() );
	return is_array( $opts ) ? $opts : array();
}
/* ---------- رنگ اصلی قالب (کلید روشن) ---------- */
function tk_theme_primary_color() {
	static $cache = null;
	if ( null !== $cache ) { return $cache; }
	$opts = tk_theme_get_opts();
	$best = null;
	foreach ( array( 'primary-color', 'color-primary', 'primary_color', 'color_primary', 'primary' ) as $k ) {
		if ( isset( $opts[ $k ] ) ) { $best = tk_theme_color_from( $opts[ $k ] ); if ( $best ) { break; } }
	}
	if ( ! $best ) {
		foreach ( $opts as $k => $v ) {
			if ( is_string( $k ) && false !== strpos( $k, 'primary' ) ) { $best = tk_theme_color_from( $v ); if ( $best ) { break; } }
		}
	}
	$cache = $best ? $best : '#0E3A40';
	return $cache;
}
/* ---------- رنگ ثانویهٔ قالب (کلید خاموش) ---------- */
function tk_theme_secondary_color() {
	static $cache = null;
	if ( null !== $cache ) { return $cache; }
	$opts = tk_theme_get_opts();
	$best = null;
	foreach ( array( 'secondary-color', 'color-secondary', 'secondary_color', 'color_secondary', 'secondary' ) as $k ) {
		if ( isset( $opts[ $k ] ) ) { $best = tk_theme_color_from( $opts[ $k ] ); if ( $best ) { break; } }
	}
	if ( ! $best ) {
		foreach ( $opts as $k => $v ) {
			if ( is_string( $k ) && false !== strpos( $k, 'second' ) ) { $best = tk_theme_color_from( $v ); if ( $best ) { break; } }
		}
	}
	$cache = $best ? $best : '#cfd8dc';
	return $cache;
}
/* ---------- تزریق رنگ: روشن = اصلی | خاموش = ثانویه ---------- */
add_action( 'admin_head', 'tk_admin_toggle_color' );
function tk_admin_toggle_color() {
	$on  = esc_attr( tk_theme_primary_color() );
	$off = esc_attr( tk_theme_secondary_color() );
	echo '<style>.tk-sw span{background:' . $off . ' !important}.tk-sw input:checked+span{background:' . $on . ' !important}</style>';
}
add_action( 'wp_head', 'tk_front_toggle_color', 20 );
function tk_front_toggle_color() {
	$on  = esc_attr( tk_theme_primary_color() );
	$off = esc_attr( tk_theme_secondary_color() );
	echo '<style>.tk-unit-track{background:' . $off . ' !important}.tk-unit input:checked + .tk-unit-track{background:' . $on . ' !important}</style>';
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