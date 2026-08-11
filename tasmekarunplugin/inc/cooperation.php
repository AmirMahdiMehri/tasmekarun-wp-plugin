<?php
defined( 'ABSPATH' ) || exit;

/* ---------- جدول درخواست‌ها (یک‌بار) ---------- */
add_action( 'plugins_loaded', 'tk_coop_maybe_create_table' );
function tk_coop_maybe_create_table() {
	if ( get_option( 'tk_coop_table_v' ) ) return;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	global $wpdb;
	$charset = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$wpdb->prefix}tk_coop_requests (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		name VARCHAR(190) NOT NULL,
		phone VARCHAR(50) NOT NULL,
		type VARCHAR(100) NOT NULL,
		company VARCHAR(190) DEFAULT '',
		message TEXT,
		created_at DATETIME NOT NULL,
		PRIMARY KEY  (id)
	) $charset" );
	update_option( 'tk_coop_table_v', 1 );
}

/* ---------- شورت‌کد (دارایی فقط وقتی لازم باشه داخل خود ویو هست) ---------- */
add_shortcode( 'tk_cooperation', 'tk_render_cooperation' );
function tk_render_cooperation() {
	$done = isset( $_GET['tk_coop'] ) && 'ok' === $_GET['tk_coop'];
	ob_start();
	include TK_PATH . 'inc/views/cooperation.php';
	return ob_get_clean();
}

/* ---------- پردازش فرم ---------- */
add_action( 'init', 'tk_coop_handle_submit' );
function tk_coop_handle_submit() {
	if ( ! isset( $_POST['tk_coop_submit'] ) ) return;
	if ( ! isset( $_POST['tk_coop_nonce'] ) || ! wp_verify_nonce( $_POST['tk_coop_nonce'], 'tk_coop' ) ) return;
	global $wpdb;
	$wpdb->insert( $wpdb->prefix . 'tk_coop_requests', array(
		'name'       => sanitize_text_field( isset($_POST['coop_name']) ? $_POST['coop_name'] : '' ),
		'phone'      => sanitize_text_field( isset($_POST['coop_phone']) ? $_POST['coop_phone'] : '' ),
		'type'       => sanitize_text_field( isset($_POST['coop_type']) ? $_POST['coop_type'] : '' ),
		'company'    => sanitize_text_field( isset($_POST['coop_company']) ? $_POST['coop_company'] : '' ),
		'message'    => sanitize_textarea_field( isset($_POST['coop_message']) ? $_POST['coop_message'] : '' ),
		'created_at' => current_time( 'mysql' ),
	) );
	$url = function_exists( 'tk_page_url_by_shortcode' ) ? tk_page_url_by_shortcode( '[tk_cooperation]' ) : '';
	wp_safe_redirect( add_query_arg( 'tk_coop', 'ok', $url ? $url : home_url() ) );
	exit;
}

/* ---------- صفحه ادمین (فقط در ادمین لود می‌شه) ---------- */
add_action( 'admin_menu', 'tk_coop_admin_menu', 60 );
function tk_coop_admin_menu() {
	add_submenu_page( 'tasmekarun', 'درخواست‌های همکاری', 'همکاری با ما', 'manage_options', 'tk-coop', 'tk_coop_admin_page' );
}
function tk_coop_admin_page() {
	global $wpdb;
	if ( isset( $_GET['del'] ) ) { $wpdb->delete( $wpdb->prefix . 'tk_coop_requests', array( 'id' => (int) $_GET['del'] ) ); }
	$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}tk_coop_requests ORDER BY id DESC" );
	echo '<div class="wrap" dir="rtl"><h1>درخواست‌های همکاری</h1>';
	echo '<table class="widefat striped"><tr><th>تاریخ</th><th>نام</th><th>تماس</th><th>نوع</th><th>شرکت</th><th>توضیحات</th><th></th></tr>';
	foreach ( $rows as $r ) {
		echo '<tr><td>' . esc_html( $r->created_at ) . '</td><td>' . esc_html( $r->name ) . '</td><td dir="ltr">' . esc_html( $r->phone ) . '</td><td>' . esc_html( $r->type ) . '</td><td>' . esc_html( $r->company ) . '</td><td>' . esc_html( $r->message ) . '</td><td><a href="' . esc_url( add_query_arg( 'del', $r->id ) ) . '" onclick="return confirm(\'حذف شود؟\');">حذف</a></td></tr>';
	}
	echo '</table></div>';
}