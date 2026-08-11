<?php
defined( 'ABSPATH' ) || exit;

/* ---------- جدول‌ها (یک‌بار) ---------- */
add_action( 'plugins_loaded', 'tk_cmp_maybe_create_tables' );
function tk_cmp_maybe_create_tables() {
	if ( get_option( 'tk_cmp_tables_v' ) ) return;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	global $wpdb; $charset = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$wpdb->prefix}tk_compare (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		brand_id BIGINT UNSIGNED NOT NULL,
		sort INT NOT NULL DEFAULT 0,
		rating TINYINT NOT NULL DEFAULT 0,
		note VARCHAR(255) DEFAULT '',
		PRIMARY KEY (id), UNIQUE KEY brand (brand_id)
	) $charset" );
	dbDelta( "CREATE TABLE {$wpdb->prefix}tk_compare_reviews (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		brand_id BIGINT UNSIGNED NOT NULL,
		name VARCHAR(190) NOT NULL,
		rating TINYINT NOT NULL DEFAULT 5,
		message TEXT,
		status TINYINT NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL,
		PRIMARY KEY (id)
	) $charset" );
	update_option( 'tk_cmp_tables_v', 1 );
}

function tk_cmp_row( $brand_id ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}tk_compare WHERE brand_id=%d", $brand_id ) );
	if ( ! $row ) {
		$max = (int) $wpdb->get_var( "SELECT MAX(sort) FROM {$wpdb->prefix}tk_compare" );
		$wpdb->insert( $wpdb->prefix . 'tk_compare', array( 'brand_id' => $brand_id, 'sort' => $max + 1, 'rating' => 0, 'note' => '' ) );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}tk_compare WHERE brand_id=%d", $brand_id ) );
	}
	return $row;
}

/* ---------- اکشن‌های ادمین (جابه‌جایی/تأیید/حذف) ---------- */
add_action( 'admin_init', 'tk_cmp_admin_actions' );
function tk_cmp_admin_actions() {
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) return;
	global $wpdb; $t = $wpdb->prefix . 'tk_compare';
	if ( isset( $_GET['tk_cmp_up'] ) || isset( $_GET['tk_cmp_down'] ) ) {
		$id = (int) ( isset( $_GET['tk_cmp_up'] ) ? $__cmp_up = $_GET['tk_cmp_up'] : $_GET['tk_cmp_down'] );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE id=%d", $id ) );
		if ( $row ) {
			$up = isset( $_GET['tk_cmp_up'] );
			$other = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE sort " . ( $up ? '<' : '>' ) . " %d ORDER BY sort " . ( $up ? 'DESC' : 'ASC' ) . " LIMIT 1", $row->sort ) );
			if ( $other ) {
				$wpdb->update( $t, array( 'sort' => $other->sort ), array( 'id' => $row->id ) );
				$wpdb->update( $t, array( 'sort' => $row->sort ), array( 'id' => $other->id ) );
			}
		}
		wp_safe_redirect( remove_query_arg( array( 'tk_cmp_up', 'tk_cmp_down' ) ) ); exit;
	}
	if ( isset( $_GET['tk_cmp_approve'] ) ) { $wpdb->update( $wpdb->prefix . 'tk_compare_reviews', array( 'status' => 1 ), array( 'id' => (int) $_GET['tk_cmp_approve'] ) ); wp_safe_redirect( remove_query_arg( 'tk_cmp_approve' ) ); exit; }
	if ( isset( $_GET['tk_cmp_revd'] ) ) { $wpdb->delete( $wpdb->prefix . 'tk_compare_reviews', array( 'id' => (int) $_GET['tk_cmp_revd'] ) ); wp_safe_redirect( remove_query_arg( 'tk_cmp_revd' ) ); exit; }
}

/* ---------- منوی ادمین ---------- */
add_action( 'admin_menu', 'tk_cmp_admin_menu', 60 );
function tk_cmp_admin_menu() {
	add_submenu_page( 'tasmekarun', 'مقایسه برندها', 'مقایسه برندها', 'manage_options', 'tk-compare', 'tk_cmp_admin_page' );
}
function tk_cmp_admin_page() {
	global $wpdb; $p = $wpdb->prefix;
	if ( isset( $_POST['tk_cmp_save'] ) && check_admin_referer( 'tk_cmp', 'tk_cmp_nonce' ) ) {
		foreach ( (array) $_POST['rating'] as $bid => $r ) {
			$row = tk_cmp_row( (int) $bid );
			$wpdb->update( $p . 'tk_compare', array( 'rating' => (int) $r, 'note' => sanitize_text_field( isset( $_POST['note'][ $bid ] ) ? $_POST['note'][ $bid ] : '' ) ), array( 'id' => $row->id ) );
		}
		echo '<div class="notice notice-success"><p>ذخیره شد ✔</p></div>';
	}
	$q = "SELECT b.id, b.name_fa, b.name_en, c.id AS cid, c.sort, c.rating, c.note FROM {$p}tk_brands b LEFT JOIN {$p}tk_compare c ON c.brand_id=b.id WHERE b.active=1 ORDER BY c.sort ASC, b.id ASC";
	foreach ( $wpdb->get_results( $q ) as $b ) { if ( ! $b->cid ) tk_cmp_row( $b->id ); }
	$brands = $wpdb->get_results( $q );
	$reviews = $wpdb->get_results( "SELECT r.*, b.name_fa FROM {$p}tk_compare_reviews r JOIN {$p}tk_brands b ON b.id=r.brand_id ORDER BY r.status ASC, r.id DESC" );
	echo '<div class="wrap" dir="rtl"><h1>مقایسه برندها</h1>';
	echo '<form method="post">'; wp_nonce_field( 'tk_cmp', 'tk_cmp_nonce' );
	echo '<table class="widefat striped"><tr><th>ترتیب</th><th>برند</th><th>امتیاز (۰-۵)</th><th>یادداشت رتبه‌بندی</th></tr>';
	foreach ( $brands as $b ) {
		echo '<tr><td style="white-space:nowrap">';
		if ( $b->cid ) {
			echo '<a class="button" href="' . esc_url( add_query_arg( 'tk_cmp_up', $b->cid ) ) . '">↑</a> ';
			echo '<a class="button" href="' . esc_url( add_query_arg( 'tk_cmp_down', $b->cid ) ) . '">↓</a>';
		}
		echo '</td><td>' . esc_html( $b->name_fa ) . ' (' . esc_html( $b->name_en ) . ')</td>';
		echo '<td><select name="rating[' . $b->id . ']">';
		for ( $i = 0; $i <= 5; $i++ ) echo '<option value="' . $i . '" ' . selected( (int) $b->rating, $i, false ) . '>' . $i . '</option>';
		echo '</select></td>';
		echo '<td><input type="text" style="width:100%" name="note[' . $b->id . ']" value="' . esc_attr( $b->note ) . '"></td></tr>';
	}
	echo '</table><p><button name="tk_cmp_save" value="1" class="button button-primary">ذخیره رتبه‌ها</button></p></form>';
	echo '<h2>دیدگاه‌های کاربران</h2><table class="widefat striped"><tr><th>وضعیت</th><th>نام</th><th>برند</th><th>امتیاز</th><th>متن</th><th></th></tr>';
	foreach ( $reviews as $rv ) {
		echo '<tr><td>' . ( $rv->status ? 'تأیید شده' : 'در انتظار' ) . '</td><td>' . esc_html( $rv->name ) . '</td><td>' . esc_html( $rv->name_fa ) . '</td><td>' . (int) $rv->rating . '</td><td>' . esc_html( $rv->message ) . '</td><td>';
		if ( ! $rv->status ) echo '<a class="button" href="' . esc_url( add_query_arg( 'tk_cmp_approve', $rv->id ) ) . '">تأیید</a> ';
		echo '<a class="button" href="' . esc_url( add_query_arg( 'tk_cmp_revd', $rv->id ) ) . '" onclick="return confirm(\'حذف؟\');">حذف</a></td></tr>';
	}
	echo '</table></div>';
}

/* ---------- ثبت دیدگاه از فرانت ---------- */
add_action( 'init', 'tk_cmp_handle_review' );
function tk_cmp_handle_review() {
	if ( ! isset( $_POST['tk_review_submit'] ) ) return;
	if ( ! isset( $_POST['tk_rev_nonce'] ) || ! wp_verify_nonce( $_POST['tk_rev_nonce'], 'tk_rev' ) ) return;
	global $wpdb;
	$wpdb->insert( $wpdb->prefix . 'tk_compare_reviews', array(
		'brand_id'   => (int) $_POST['rev_brand'],
		'name'       => sanitize_text_field( $_POST['rev_name'] ),
		'rating'     => max( 1, min( 5, (int) $_POST['rev_rating'] ) ),
		'message'    => sanitize_textarea_field( $_POST['rev_message'] ),
		'created_at' => current_time( 'mysql' ),
	) );
	$url = function_exists( 'tk_page_url_by_shortcode' ) ? tk_page_url_by_shortcode( '[tk_compare]' ) : '';
	wp_safe_redirect( add_query_arg( 'tk_rev', 'ok', $url ? $url : home_url() ) );
	exit;
}

/* ---------- شورت‌کد ---------- */
add_shortcode( 'tk_compare', 'tk_render_compare' );
function tk_render_compare() {
	ob_start();
	include TK_PATH . 'inc/views/compare.php';
	return ob_get_clean();
}