<?php
defined( 'ABSPATH' ) || exit;

add_action( 'init', 'tk_shop_routes' );
function tk_shop_routes() {
	add_rewrite_rule( '^shop/?$', 'index.php?tk_shop=cats', 'top' );
	add_rewrite_rule( '^shop/([^/]+)/?$', 'index.php?tk_shop=cat&tk_cat=$matches[1]', 'top' );
	add_rewrite_rule( '^shop/([^/]+)/([^/]+)/?$', 'index.php?tk_shop=sec&tk_cat=$matches[1]&tk_sec=$matches[2]', 'top' );
	add_rewrite_rule( '^shop/([^/]+)/([^/]+)/([^/]+)/?$', 'index.php?tk_shop=brand&tk_cat=$matches[1]&tk_sec=$matches[2]&tk_brand=$matches[3]', 'top' );
	add_rewrite_rule( '^shop/([^/]+)/([^/]+)/([^/]+)/([^/]+)/?$', 'index.php?tk_shop=product&tk_cat=$matches[1]&tk_sec=$matches[2]&tk_brand=$matches[3]&tk_size=$matches[4]', 'top' );
}

add_filter( 'query_vars', 'tk_shop_qvars' );
function tk_shop_qvars( $vars ) {
	$vars[] = 'tk_shop'; $vars[] = 'tk_cat'; $vars[] = 'tk_sec'; $vars[] = 'tk_brand'; $vars[] = 'tk_size';
	return $vars;
}

add_action( 'init', 'tk_shop_flush_once', 20 );
function tk_shop_flush_once() {
	if ( get_option( 'tk_shop_rules_v' ) !== TK_VERSION ) {
		update_option( 'tk_shop_rules_v', TK_VERSION );
		flush_rewrite_rules();
	}
}

add_filter( 'template_include', 'tk_shop_template' );
function tk_shop_template( $tpl ) {
	if ( get_query_var( 'tk_shop' ) ) { return TK_PATH . 'inc/views/shop.php'; }
	return $tpl;
}

add_action( 'wp_enqueue_scripts', 'tk_shop_assets' );
function tk_shop_assets() {
	if ( get_query_var( 'tk_shop' ) || is_search() ) {
		wp_enqueue_style( 'tk-shop', TK_URL . 'assets/tk-shop.css', array(), TK_VERSION );
	}
}

/* آدرس /shop/ همیشه مال موتور ما (نه برگه فروشگاه ووکامرس) */
add_filter( 'request', 'tk_force_shop_root', 30 );
function tk_force_shop_root( $qv ) {
	if ( isset( $qv['pagename'] ) && 'shop' === $qv['pagename'] && ! isset( $qv['tk_shop'] ) ) {
		return array( 'tk_shop' => 'cats' );
	}
	return $qv;
}

/* ✅ fallback ضدگلوله: بدون اتکا به جدول rewrite،
   هر آدرس /shop/... رو مستقیم تفسیر کن و ویوی ما رو برگردون. */
add_filter( 'template_include', 'tk_shop_uri_fallback', 99 );
function tk_shop_uri_fallback( $tpl ) {
	if ( get_query_var( 'tk_shop' ) ) { return $tpl; }
	$home = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
	$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
	if ( $home && 0 === strpos( $path, $home . '/' ) ) { $path = trim( substr( $path, strlen( $home ) ), '/' ); }
	if ( 'shop' !== $path && 0 !== strpos( $path, 'shop/' ) ) { return $tpl; }
	$parts = array_values( array_filter( explode( '/', $path ) ) );
	array_shift( $parts ); // حذف 'shop'
	$levels = array( 'cats', 'cat', 'sec', 'brand', 'product' );
	set_query_var( 'tk_shop', $levels[ min( count( $parts ), 4 ) ] );
	if ( isset( $parts[0] ) ) { set_query_var( 'tk_cat',   $parts[0] ); }
	if ( isset( $parts[1] ) ) { set_query_var( 'tk_sec',   $parts[1] ); }
	if ( isset( $parts[2] ) ) { set_query_var( 'tk_brand', $parts[2] ); }
	if ( isset( $parts[3] ) ) { set_query_var( 'tk_size',  $parts[3] ); }
	return TK_PATH . 'inc/views/shop.php';
}

/* سرچ سایت = نتایج کاتالوگ مجازی */
add_filter( 'template_include', 'tk_search_template', 99 );
function tk_search_template( $tpl ) {
	if ( ! is_admin() && is_search() ) {
		return TK_PATH . 'inc/views/search.php';
	}
	return $tpl;
}