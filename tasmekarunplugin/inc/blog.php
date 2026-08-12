<?php
defined( 'ABSPATH' ) || exit;

/* ---------- متای سئوی سبک برای مقاله‌ها و آرشیو ---------- */
add_action( 'wp_head', 'tk_blog_seo', 1 );
function tk_blog_seo() {
	if ( is_singular( 'post' ) ) {
		$p = get_post();
		$desc = wp_trim_words( wp_strip_all_tags( $p->post_content ), 25, '…' );
		echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( get_the_title( $p ) ) . '">' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
		echo '<meta property="og:type" content="article">' . "\n";
		echo '<meta property="og:url" content="' . esc_url( get_permalink( $p ) ) . '">' . "\n";
	} elseif ( is_home() || is_category() || is_tag() ) {
		echo '<meta name="description" content="مقالات و راهنمای خرید تسمه صنعتی — تسمه کارون">' . "\n";
	}
}

/* ---------- کادر اتصال مقاله به فروشگاه/محاسبه‌گر (سئو داخلی + فروش) ---------- */
add_filter( 'the_content', 'tk_blog_cta' );
function tk_blog_cta( $content ) {
	if ( ! is_singular( 'post' ) ) return $content;
	$calc = function_exists( 'tk_page_url_by_shortcode' ) ? tk_page_url_by_shortcode( '[tk_calculator]' ) : '';
	$guide = function_exists( 'tk_page_url_by_shortcode' ) ? tk_page_url_by_shortcode( '[tk_ai_guide]' ) : '';
	$a = 'style="background:#0e3a40;color:#fff;padding:8px 14px;border-radius:8px;text-decoration:none"';
	$box = '<div style="margin-top:28px;border:1px solid #ddd;border-radius:12px;padding:16px;background:#f7f9f9">';
	$box .= '<strong>تسمه کارون — مرجع تسمه صنعتی</strong><div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">';
	$box .= '<a href="' . esc_url( home_url( '/shop/' ) ) . '" ' . $a . '>فروشگاه</a>';
	if ( $calc ) $box .= '<a href="' . esc_url( $calc ) . '" ' . $a . '>محاسبه‌گر قیمت</a>';
	if ( $guide ) $box .= '<a href="' . esc_url( $guide ) . '" ' . $a . '>راهنمای خرید</a>';
	$box .= '</div></div>';
	return $content . $box;
}