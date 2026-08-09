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
	if ( get_query_var( 'tk_shop' ) ) {
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