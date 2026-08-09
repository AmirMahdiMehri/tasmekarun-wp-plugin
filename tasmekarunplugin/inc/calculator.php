<?php
defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', 'tk_calc_assets' );
function tk_calc_assets() {
	global $post;
	if ( is_a( $post, 'WP_Post' ) && ( has_shortcode( $post->post_content, 'tk_calculator' ) || has_shortcode( $post->post_content, 'tk_proforma' ) ) ) {
		wp_enqueue_style( 'tk-shop', TK_URL . 'assets/tk-shop.css', array(), TK_VERSION );
	}
}

/* ---------- محاسبه‌گر قیمت ---------- */
add_shortcode( 'tk_calculator', 'tk_render_calculator' );
function tk_render_calculator() {
	global $wpdb; $p = $wpdb->prefix;

	/* افزودن به پیش‌فاکتور */
	$added = false;
	if ( isset( $_POST['tk_pf_add'] ) && isset( $_POST['tk_nonce'] ) && wp_verify_nonce( $_POST['tk_nonce'], 'tk_act' ) && function_exists( 'WC' ) ) {
		$items = WC()->session->get( 'tk_proforma', array() );
		$items[] = array(
			'sku'   => sanitize_text_field( $_POST['tk_pf_sku'] ),
			'brand' => sanitize_text_field( $_POST['tk_pf_brand'] ),
			'price' => (float) $_POST['tk_pf_price'],
			'qty'   => 1,
		);
		WC()->session->set( 'tk_proforma', $items );
		$added = true;
	}

	$sections = $wpdb->get_results( "SELECT s.id, s.slug, c.name_fa AS cname FROM {$p}tk_sections s JOIN {$p}tk_categories c ON c.id=s.category_id WHERE s.active=1 ORDER BY c.sort, s.slug" );
	$brands   = $wpdb->get_results( "SELECT * FROM {$p}tk_brands WHERE active=1 ORDER BY name_fa" );

	$sel_sec   = isset( $_GET['tk_sec'] ) ? (int) $_GET['tk_sec'] : 0;
	$sel_brand = isset( $_GET['tk_brand'] ) ? (int) $_GET['tk_brand'] : 0;
	$size = isset( $_GET['tk_size'] ) ? (int) $_GET['tk_size'] : 0;
	$ribs = isset( $_GET['tk_ribs'] ) ? (int) $_GET['tk_ribs'] : 0;

	ob_start();
	echo '<div class="tk-shop" dir="rtl">';
	if ( $added ) { echo '<p style="background:#e8f5e9;color:#2e7d32;padding:10px 14px;border-radius:8px">به پیش‌فاکتور اضافه شد ✔</p>'; }
	echo '<h1>محاسبه‌گر قیمت تسمه</h1>';
	echo '<form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">';
	echo '<select name="tk_sec" required style="padding:10px"><option value="">انتخاب سری…</option>';
	$last = null;
	foreach ( $sections as $s ) {
		if ( $last !== $s->cname ) { if ( null !== $last ) { echo '</optgroup>'; } echo '<optgroup label="' . esc_attr( $s->cname ) . '">'; $last = $s->cname; }
		echo '<option value="' . (int) $s->id . '" ' . selected( $sel_sec, (int) $s->id, false ) . '>' . esc_html( $s->slug ) . '</option>';
	}
	if ( null !== $last ) { echo '</optgroup>'; }
	echo '</select>';
	echo '<select name="tk_brand" required style="padding:10px"><option value="">انتخاب برند…</option>';
	foreach ( $brands as $b ) { echo '<option value="' . (int) $b->id . '" ' . selected( $sel_brand, (int) $b->id, false ) . '>' . esc_html( $b->name_fa ) . '</option>'; }
	echo '</select>';
	echo '<input type="number" min="1" name="tk_size" placeholder="سایز / طول" value="' . ( $size ? $size : '' ) . '" required style="padding:10px;width:120px">';
	echo '<input type="number" min="1" name="tk_ribs" placeholder="شیار/باند (اختیاری)" value="' . ( $ribs ? $ribs : '' ) . '" style="padding:10px;width:140px">';
	echo '<button class="tk-cta" type="submit">محاسبه قیمت</button></form>';

	if ( $sel_sec && $sel_brand && $size ) {
		$sec = TK_Engine::section_by_id( $sel_sec );
		$price = TK_Engine::price( $sel_brand, array( 'section' => $sec->slug, 'size' => $size, 'ribs' => $ribs ? $ribs : null ) );
		$sku = ( $ribs ? $ribs . $sec->slug : $sec->slug ) . $size;
		$bname = $wpdb->get_var( $wpdb->prepare( "SELECT name_fa FROM {$p}tk_brands WHERE id=%d", $sel_brand ) );
		echo '<div style="margin-top:18px;border:1px solid #ddd;border-radius:10px;padding:16px;background:#fff">';
		echo '<strong>تسمه ' . esc_html( $sku ) . ' — ' . esc_html( $bname ) . '</strong>';
		echo '<div class="tk-price">' . ( null === $price ? 'برای این ترکیب ضریب/فرمول تعریف نشده؛ تماس بگیرید.' : esc_html( TK_Engine::fmt( $price ) ) ) . '</div>';
		if ( null !== $price && function_exists( 'WC' ) ) {
			echo '<form method="post" style="margin:0">' . wp_nonce_field( 'tk_act', 'tk_nonce', true )
				. '<input type="hidden" name="tk_pf_add" value="1">'
				. '<input type="hidden" name="tk_pf_sku" value="' . esc_attr( $sku ) . '">'
				. '<input type="hidden" name="tk_pf_brand" value="' . esc_attr( $bname ) . '">'
				. '<input type="hidden" name="tk_pf_price" value="' . esc_attr( $price ) . '">'
				. '<button class="tk-cta" type="submit">افزودن به پیش‌فاکتور</button></form>';
		}
		echo '</div>';
	}
	echo '</div>';
	return ob_get_clean();
}

/* ---------- پیش‌فاکتور ---------- */
add_shortcode( 'tk_proforma', 'tk_render_proforma' );
function tk_render_proforma() {
	if ( ! function_exists( 'WC' ) ) { return '<p>پیش‌فاکتور به ووکامرس نیاز دارد.</p>'; }
	$items = WC()->session->get( 'tk_proforma', array() );

	if ( isset( $_POST['tk_nonce'] ) && wp_verify_nonce( $_POST['tk_nonce'], 'tk_act' ) ) {
		if ( isset( $_POST['tk_pf_clear'] ) ) {
			$items = array();
		} elseif ( isset( $_POST['tk_pf_update'] ) ) {
			foreach ( $items as $k => $it ) {
				if ( isset( $_POST['pf_qty'][ $k ] ) ) { $items[ $k ]['qty'] = max( 1, (int) $_POST['pf_qty'][ $k ] ); }
			}
		} elseif ( isset( $_POST['tk_pf_remove'] ) ) {
			unset( $items[ (int) $_POST['tk_pf_remove'] ] );
			$items = array_values( $items );
		}
		WC()->session->set( 'tk_proforma', $items );
	}

	$set = get_option( 'tk_settings', array() );
	$phone = isset( $set['phone'] ) ? $set['phone'] : '';

	ob_start();
	echo '<div class="tk-shop" id="tk-pf" dir="rtl">';
	echo '<h1>پیش‌فاکتور — تسمه کارون</h1>';
	echo '<p><small>تاریخ: ' . esc_html( date_i18n( 'Y/m/d' ) ) . ' | تماس: ' . esc_html( $phone ) . '</small></p>';
	if ( ! $items ) {
		echo '<p>اقلامی اضافه نشده است. از صفحه «محاسبه‌گر قیمت تسمه» اقدام کنید.</p>';
	} else {
		echo '<form method="post">';
		wp_nonce_field( 'tk_act', 'tk_nonce' );
		echo '<table class="tk-specs"><tr><th>ردیف</th><th>کالا</th><th>برند</th><th>قیمت واحد</th><th>تعداد</th><th>جمع</th><th></th></tr>';
		$total = 0;
		foreach ( $items as $k => $it ) {
			$row = (float) $it['price'] * (int) $it['qty'];
			$total += $row;
			echo '<tr><td>' . ( $k + 1 ) . '</td><td>' . esc_html( $it['sku'] ) . '</td><td>' . esc_html( $it['brand'] ) . '</td><td>' . esc_html( number_format_i18n( $it['price'] ) ) . '</td>'
				. '<td><input type="number" min="1" name="pf_qty[' . $k . ']" value="' . (int) $it['qty'] . '" style="width:70px"></td>'
				. '<td>' . esc_html( number_format_i18n( $row ) ) . '</td>'
				. '<td><button name="tk_pf_remove" value="' . $k . '" class="button button-small">حذف</button></td></tr>';
		}
		echo '<tr><td colspan="5"><strong>جمع کل (ریال)</strong></td><td colspan="2"><strong>' . esc_html( number_format_i18n( $total ) ) . '</strong></td></tr>';
		echo '</table>';
		echo '<p style="margin-top:12px"><button name="tk_pf_update" value="1" class="tk-cta" type="submit">به‌روزرسانی</button> '
			. '<button name="tk_pf_clear" value="1" class="button" type="submit" onclick="return confirm(\'کل پیش‌فاکتور پاک شود؟\');">پاک کردن</button> '
			. '<button type="button" class="button" onclick="window.print()">چاپ</button></p>';
		echo '</form>';
	}
	echo '</div>';
	echo '<style>@media print{body *{visibility:hidden}#tk-pf,#tk-pf *{visibility:visible}#tk-pf{position:absolute;inset:0}}</style>';
	return ob_get_clean();
}