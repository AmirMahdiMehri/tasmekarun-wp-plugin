<?php
defined( 'ABSPATH' ) || exit;

/* پل سرچ: سرچ پیش‌فرض وردپرس → جستجوی فروشگاه مجازی ما */
add_action( 'template_redirect', 'tk_search_bridge' );
function tk_search_bridge() {
	if ( is_admin() || ! is_search() ) return;
	$q = trim( (string) get_search_query() );
	if ( '' === $q ) return;
	wp_safe_redirect( add_query_arg( array( 'tk_s' => $q, 's' => $q ), home_url( '/shop/' ) ), 302 );
	exit;
}