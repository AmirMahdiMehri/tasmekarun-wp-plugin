<?php
defined( 'ABSPATH' ) || exit;
/* ---------- تشخیص رنگ اصلی قالب (اسکن تو در تو + اولویت primary) ---------- */
function tk_theme_primary_color( &$found_key = null ) {
	static $cache = null, $cache_key = null;
	if ( null !== $cache ) { if ( null !== $found_key ) { $found_key = $cache_key; } return $cache; }
	$opts = get_option( 'xts-woodmart-options', array() );
	if ( ! is_array( $opts ) ) { $opts = array(); }
	$best = null; $best_key = '';
	$walk = function ( $node, $path ) use ( &$walk, &$best, &$best_key ) {
		foreach ( (array) $node as $k => $v ) {
			$p = ( '' === $path ) ? (string) $k : $path . '.' . $k;
			$color = null;
			if ( is_string( $v ) ) {
				$t = trim( $v );
				if ( preg_match( '/^#([0-9a-fA-F]{3,8})$/', $t ) || preg_match( '/^rgba?\(/i', $t ) ) { $color = $t; }
			} elseif ( is_array( $v ) && ( isset( $v['color'] ) || isset( $v['rgba'] ) ) ) {
				$c = isset( $v['color'] ) ? $v['color'] : $v['rgba'];
				if ( is_string( $c ) && '' !== trim( $c ) ) { $color = trim( $c ); }
			}
			if ( null !== $color ) {
				$lp = strtolower( $p );
				if ( false !== strpos( $lp, 'primary' ) && false === strpos( $lp, 'font' ) && false === strpos( $lp, 'typo' ) && false === strpos( $lp, 'text' ) && null === $best ) {
					$best = $color; $best_key = $p;
				}
			}
			if ( is_array( $v ) && ! isset( $v['color'] ) && ! isset( $v['rgba'] ) ) { $walk( $v, $p ); }
		}
	};
	$walk( $opts, '' );
	if ( null === $best ) { $best = '#0E3A40'; $best_key = 'fallback'; }
	$cache = $best; $cache_key = $best_key;
	if ( null !== $found_key ) { $found_key = $cache_key; }
	return $cache;
}
/* ---------- رنگ نهایی کلیدها: دستی ← خودکار ---------- */
function tk_toggle_color() {
	$set = get_option( 'tk_settings', array() );
	if ( is_array( $set ) && ! empty( $set['tk_toggle_color'] ) ) { return trim( $set['tk_toggle_color'] ); }
	return tk_theme_primary_color();
}
add_action( 'admin_head', 'tk_admin_toggle_color' );
function tk_admin_toggle_color() {
	echo '<style>.tk-sw input:checked+span{background:' . esc_attr( tk_toggle_color() ) . ' !important}</style>';
}
add_action( 'wp_head', 'tk_front_toggle_color', 20 );
function tk_front_toggle_color() {
	echo '<style>.tk-unit input:checked + .tk-unit-track{background:' . esc_attr( tk_toggle_color() ) . ' !important}</style>';
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