<?php
defined( 'ABSPATH' ) || exit;

/* گارد حلقه: هر ریدایرکت به «همین صفحهٔ فعلی» لغو می‌شه.
   این ماژول خطای ERR_TOO_MANY_REDIRECTS رو ساختاری غیرممکن می‌کنه. */
add_filter( 'wp_redirect', 'tk_loop_guard', 1, 2 );
function tk_loop_guard( $location, $status ) {
	if ( ! $location ) return $location;
	$norm = function ( $u ) {
		$u = preg_replace( '#^https?://#i', '', (string) $u );
		return untrailingslashit( $u );
	};
	$host = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '';
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
	if ( $norm( $location ) === $norm( $host . $uri ) ) {
		return false; // لغو ریدایرکتِ خودبه‌خود
	}
	return $location;
}