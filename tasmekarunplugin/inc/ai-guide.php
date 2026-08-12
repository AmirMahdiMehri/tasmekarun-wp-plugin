<?php
defined( 'ABSPATH' ) || exit;

/* ---------- شورت‌کد راهنمای خرید ---------- */
add_shortcode( 'tk_ai_guide', 'tk_render_ai_guide' );
function tk_render_ai_guide() {
	ob_start();
	include TK_PATH . 'inc/views/ai-guide.php';
	return ob_get_clean();
}