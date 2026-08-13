<?php
defined( 'ABSPATH' ) || exit;

/* پل سرچ: سرچ پیش‌فرض وردپرس → فروشگاه مجازی ما
   نکتهٔ حیاتی: هرگز پارامتر s در آدرس مقصد نباشد (عامل حلقهٔ ریدایرکت) */
add_action( 'template_redirect', 'tk_search_bridge' );
function tk_search_bridge() {
	if ( is_admin() || ! is_search() ) return;
	if ( isset( $_GET['tk_s'] ) ) return; // ضدامنیت حلقه
	$q = trim( (string) get_search_query() );
	if ( '' === $q ) return;
	wp_safe_redirect( add_query_arg( 'tk_s', $q, home_url( '/shop/' ) ), 302 );
	exit;
}