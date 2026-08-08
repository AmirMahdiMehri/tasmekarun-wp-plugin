<?php
defined( 'ABSPATH' ) || exit;
global $wpdb;
$p = $wpdb->prefix;
$type = get_query_var( 'tk_shop' );
$cat_slug = get_query_var( 'tk_cat' );
$sec_slug = get_query_var( 'tk_sec' );
$brand_slug = get_query_var( 'tk_brand' );
$size_raw = get_query_var( 'tk_size' );

$cat = $cat_slug ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$p}tk_categories WHERE slug=%s AND active=1", $cat_slug ) ) : null;
$sec = $sec_slug ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$p}tk_sections WHERE slug=%s AND active=1", $sec_slug ) ) : null;
$brand = $brand_slug ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$p}tk_brands WHERE slug=%s AND active=1", $brand_slug ) ) : null;

get_header();
echo '<div class="tk-shop" dir="rtl">';

echo '<nav class="tk-crumbs"><a href="' . home_url( '/shop/' ) . '">فروشگاه</a>';
if ( $cat ) { echo ' / <a href="' . home_url( '/shop/' . $cat->slug . '/' ) . '">' . esc_html( $cat->name_en ) . '</a>'; }
if ( $sec ) { echo ' / <a href="' . home_url( '/shop/' . $cat->slug . '/' . $sec->slug . '/' ) . '">' . esc_html( $sec->slug ) . '</a>'; }
if ( $brand ) { echo ' / <a href="' . home_url( '/shop/' . $cat->slug . '/' . $sec->slug . '/' . $brand->slug . '/' ) . '">' . esc_html( $brand->name_fa ) . '</a>'; }
if ( 'product' === $type && $size_raw ) { echo ' / <span>' . esc_html( $sec->slug . $size_raw ) . '</span>'; }
echo '</nav>';

if ( 'cats' === $type ) {
	$cats = $wpdb->get_results( "SELECT * FROM {$p}tk_categories WHERE active=1 ORDER BY sort" );
	echo '<h1>دسته‌بندی تسمه‌ها</h1><div class="tk-grid">';
	foreach ( $cats as $c ) {
		echo '<a class="tk-card" href="' . home_url( '/shop/' . $c->slug . '/' ) . '"><strong>' . esc_html( $c->name_en ) . '</strong><small>' . esc_html( $c->name_fa ) . '</small></a>';
	}
	echo '</div>';

} elseif ( 'cat' === $type && $cat ) {
	$secs = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$p}tk_sections WHERE category_id=%d AND active=1 ORDER BY slug", $cat->id ) );
	echo '<h1>' . esc_html( $cat->name_en ) . ' — ' . esc_html( $cat->name_fa ) . '</h1><div class="tk-grid">';
	foreach ( $secs as $s ) {
		echo '<a class="tk-card" href="' . home_url( '/shop/' . $cat->slug . '/' . $s->slug . '/' ) . '"><strong>' . esc_html( $s->slug ) . '</strong><small>' . esc_html( $s->name_fa ) . '</small></a>';
	}
	echo '</div>';

} elseif ( 'sec' === $type && $cat && $sec ) {
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT b.* FROM {$p}tk_brand_series bs JOIN {$p}tk_brands b ON b.id=bs.brand_id WHERE bs.section_id=%d AND b.active=1 ORDER BY b.name_fa", $sec->id ) );
	echo '<h1>تسمه ' . esc_html( $sec->slug ) . ' — برندها</h1>';
	if ( ! $rows ) {
		echo '<p>هنوز برندی برای این سری فعال نشده است.</p>';
	} else {
		echo '<div class="tk-grid">';
		foreach ( $rows as $b ) {
			echo '<a class="tk-card" href="' . home_url( '/shop/' . $cat->slug . '/' . $sec->slug . '/' . $b->slug . '/' ) . '"><strong>' . esc_html( $b->name_fa ) . '</strong><small>' . esc_html( $b->name_en ) . '</small></a>';
		}
		echo '</div>';
	}

} elseif ( 'brand' === $type && $cat && $sec && $brand ) {
	$sizes = TK_Engine::size_set( $sec, $brand->id );
	$base = home_url( '/shop/' . $cat->slug . '/' . $sec->slug . '/' . $brand->slug . '/' );
	echo '<h1>تسمه ' . esc_html( $sec->slug ) . ' ' . esc_html( $brand->name_fa ) . ' <small>(' . count( $sizes ) . ' سایز)</small></h1>';
	echo '<form class="tk-jump" data-base="' . esc_url( $base ) . '" onsubmit="var v=this.querySelector(\'input\').value; if(v){location.href=this.dataset.base+encodeURIComponent(v)+\'/\';} return false;"><input type="number" placeholder="سایز، مثلا 50"><button class="tk-cta" type="submit">برو</button></form>';
	echo '<div class="tk-chips">';
	$show = array_slice( $sizes, 0, 300 );
	foreach ( $show as $v ) {
		echo '<a href="' . esc_url( $base . $v . '/' ) . '">' . esc_html( $sec->slug . $v ) . '</a>';
	}
	echo '</div>';
	if ( count( $sizes ) > 300 ) { echo '<p><small>' . ( count( $sizes ) - 300 ) . ' سایز دیگر — از جعبه جست‌وجوی سایز استفاده کنید.</small></p>'; }

} elseif ( 'product' === $type && $cat && $sec && $brand && $size_raw ) {
	$size = (int) $size_raw;
	$fkey = TK_Engine::formula_key_for( $brand->id, $sec );
	$ribs = null;
	if ( 'RIBS_LEN_COEF' === $fkey ) {
		$ribs = isset( $_GET['ribs'] ) ? (int) $_GET['ribs'] : 6;
	}
	$parsed = array( 'section' => $sec->slug, 'size' => $size, 'ribs' => $ribs );
	$allowed = TK_Engine::size_allowed( $brand->id, (int) $sec->id, $size );
	$price = TK_Engine::price( $brand->id, $parsed );
	$stock = TK_Engine::in_stock( $brand->id, (int) $sec->id, $size );
	$bs = TK_Engine::brand_series( $brand->id, (int) $sec->id );
	$img = TK_Engine::series_image_url( $brand->id, (int) $sec->id );
	$set = get_option( 'tk_settings', array() );
	$phone = isset( $set['phone'] ) ? $set['phone'] : '';
	$sku = ( $ribs ? $ribs . 'PK' : $sec->slug ) . $size;

	echo '<h1>تسمه ' . esc_html( $sku ) . ' برند ' . esc_html( $brand->name_fa ) . '</h1>';
	if ( ! $allowed ) {
		echo '<p>این سایز برای این برند تعریف نشده است. <a href="' . esc_url( home_url( '/shop/' . $cat->slug . '/' . $sec->slug . '/' . $brand->slug . '/' ) ) . '">مشاهده سایزهای موجود</a></p>';
	} else {
		echo '<div class="tk-prod">';
		echo '<div class="tk-prod-img">' . ( $img ? '<img src="' . esc_url( $img ) . '" alt="' . esc_attr( $sku ) . '">' : '<div style="border:1px dashed #bbb;border-radius:10px;height:200px;display:flex;align-items:center;justify-content:center;color:#999">تصویر سری ' . esc_html( $sec->slug ) . '</div>' ) . '</div>';
		echo '<div class="tk-prod-info">';
		if ( $stock ) { echo '<span class="tk-badge in">موجود در انبار</span>'; }
		echo '<div class="tk-price">' . ( null === $price ? 'برای استعلام قیمت تماس بگیرید' : esc_html( TK_Engine::fmt( $price ) ) ) . '</div>';
		if ( $ribs || 'RIBS_LEN_COEF' === $fkey ) {
			echo '<form method="get" class="tk-jump"><label>تعداد شیار: <select name="ribs" onchange="this.form.submit()">';
			foreach ( array( 3, 4, 5, 6, 7, 8, 9, 10, 12 ) as $rb ) { echo '<option value="' . $rb . '" ' . selected( $ribs, $rb, false ) . '>' . $rb . '</option>'; }
			echo '</select></label></form>';
		}
		echo '<a class="tk-cta" href="tel:' . esc_attr( $phone ) . '">' . ( $stock ? 'موجود — تماس برای خرید' : 'برای خرید تماس بگیرید' ) . ': ' . esc_html( $phone ) . '</a>';
		echo '</div></div>';
		echo '<p style="margin-top:18px">' . esc_html( TK_Engine::description( $brand->name_fa, $sec, $bs ) ) . '</p>';
		$spec = TK_Engine::specs( (int) $sec->id );
		if ( $spec ) {
			echo '<table class="tk-specs"><tr><th>عرض بالا</th><th>عرض پیچ</th><th>ارتفاع</th><th>زاویه</th><th>استاندارد طول</th><th>وزن/متر</th><th>تبدیل</th></tr>';
			echo '<tr><td>' . esc_html( $spec->top_w ) . '</td><td>' . esc_html( $spec->pitch_w ) . '</td><td>' . esc_html( $spec->height ) . '</td><td>' . esc_html( $spec->wedge ) . '°</td><td>' . esc_html( $spec->len_std ) . '</td><td>' . esc_html( $spec->weight ) . '</td><td dir="ltr">' . esc_html( trim( $spec->conv1 . ' | ' . $spec->conv2, ' |' ) ) . '</td></tr></table>';
		}
	}
} else {
	echo '<h1>یافت نشد</h1><p><a href="' . esc_url( home_url( '/shop/' ) ) . '">بازگشت به فروشگاه</a></p>';
}

echo '</div>';
get_footer();