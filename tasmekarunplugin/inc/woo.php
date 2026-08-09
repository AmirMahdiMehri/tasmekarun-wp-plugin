<?php
defined( 'ABSPATH' ) || exit;

function tk_woo_brand_name( $id ) {
	global $wpdb;
	return $wpdb->get_var( $wpdb->prepare( "SELECT name_fa FROM {$wpdb->prefix}tk_brands WHERE id=%d", (int) $id ) );
}

/* محصول پایه مخفی per برند×بخش */
function tk_woo_ensure_base_product( $brand_id, $section_id ) {
	$sku = 'TKBASE-' . $brand_id . '-' . $section_id;
	$ids = wc_get_products( array( 'limit' => 1, 'sku' => $sku, 'status' => 'publish', 'return' => 'ids' ) );
	if ( $ids ) { return (int) $ids[0]; }

	$sec = TK_Engine::section_by_id( $section_id );
	$product = new WC_Product_Simple();
	$product->set_name( 'تسمه ' . ( $sec ? $sec->slug : '' ) . ' — ' . tk_woo_brand_name( $brand_id ) );
	$product->set_sku( $sku );
	$product->set_regular_price( 0 );
	$product->set_price( 0 );
	$product->set_catalog_visibility( 'hidden' );
	$product->set_status( 'publish' );
	$id = $product->save();
	update_post_meta( $id, '_tk_base', 1 );
	update_post_meta( $id, '_tk_brand', (int) $brand_id );
	update_post_meta( $id, '_tk_section', (int) $section_id );
	return $id;
}

/* اعتبارسنجی قبل از افزودن به سبد */
add_filter( 'woocommerce_add_to_cart_validation', 'tk_woo_validate', 10, 3 );
function tk_woo_validate( $passed, $product_id, $qty ) {
	if ( ! get_post_meta( $product_id, '_tk_base', true ) ) { return $passed; }
	$brand_id = isset( $_REQUEST['tk_brand'] ) ? (int) $_REQUEST['tk_brand'] : 0;
	$section_id = isset( $_REQUEST['tk_section'] ) ? (int) $_REQUEST['tk_section'] : 0;
	$size = isset( $_REQUEST['tk_size'] ) ? (int) $_REQUEST['tk_size'] : 0;
	if ( ! $brand_id || ! $section_id || ! $size ) { wc_add_notice( 'اطلاعات سایز ناقص است.', 'error' ); return false; }
	if ( ! TK_Engine::size_allowed( $brand_id, $section_id, $size ) ) { wc_add_notice( 'این سایز برای این برند تعریف نشده است.', 'error' ); return false; }
	if ( ! TK_Engine::in_stock( $brand_id, $section_id, $size ) ) { wc_add_notice( 'موجودی انبار این سایز تمام شده؛ برای خرید تماس بگیرید.', 'error' ); return false; }
	return $passed;
}

/* چسباندن اطلاعات سایز به آیتم سبد */
add_filter( 'woocommerce_add_cart_item_data', 'tk_woo_cart_item_data', 10, 2 );
function tk_woo_cart_item_data( $data, $product_id ) {
	if ( ! get_post_meta( $product_id, '_tk_base', true ) ) { return $data; }
	$data['tk_brand'] = isset( $_REQUEST['tk_brand'] ) ? (int) $_REQUEST['tk_brand'] : 0;
	$data['tk_section'] = isset( $_REQUEST['tk_section'] ) ? (int) $_REQUEST['tk_section'] : 0;
	$data['tk_size'] = isset( $_REQUEST['tk_size'] ) ? (int) $_REQUEST['tk_size'] : 0;
	$data['tk_ribs'] = isset( $_REQUEST['tk_ribs'] ) ? (int) $_REQUEST['tk_ribs'] : 0;
	$data['unique_key'] = md5( $data['tk_brand'] . '|' . $data['tk_section'] . '|' . $data['tk_size'] . '|' . $data['tk_ribs'] );
	return $data;
}

/* قیمت زنده موتور داخل سبد */
add_action( 'woocommerce_before_calculate_totals', 'tk_woo_set_price', 20 );
function tk_woo_set_price( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) { return; }
	if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) { return; }
	foreach ( $cart->get_cart() as $item ) {
		if ( empty( $item['tk_section'] ) ) { continue; }
		$sec = TK_Engine::section_by_id( (int) $item['tk_section'] );
		if ( ! $sec ) { continue; }
		$price = TK_Engine::price( (int) $item['tk_brand'], array(
			'section' => $sec->slug,
			'size'    => (int) $item['tk_size'],
			'ribs'    => $item['tk_ribs'] ? (int) $item['tk_ribs'] : null,
		) );
		if ( null !== $price ) { $item['data']->set_price( $price ); }
	}
}

/* نمایش مشخصات تسمه در سبد */
add_filter( 'woocommerce_get_item_data', 'tk_woo_item_data', 10, 2 );
function tk_woo_item_data( $other, $item ) {
	if ( empty( $item['tk_section'] ) ) { return $other; }
	$sec = TK_Engine::section_by_id( (int) $item['tk_section'] );
	if ( ! $sec ) { return $other; }
	$sku = ( $item['tk_ribs'] ? $item['tk_ribs'] . 'PK' : $sec->slug ) . $item['tk_size'];
	$other[] = array( 'name' => 'تسمه', 'value' => $sku . ' — ' . tk_woo_brand_name( $item['tk_brand'] ) );
	return $other;
}

/* چک موجودی موقع checkout */
add_action( 'woocommerce_check_cart_items', 'tk_woo_check_stock' );
function tk_woo_check_stock() {
	foreach ( WC()->cart->get_cart() as $item ) {
		if ( empty( $item['tk_section'] ) ) { continue; }
		if ( ! TK_Engine::in_stock( (int) $item['tk_brand'], (int) $item['tk_section'], (int) $item['tk_size'] ) ) {
			wc_add_notice( 'موجودی یکی از اقلام سبد شما تمام شده؛ لطفاً آن را حذف کنید یا تماس بگیرید.', 'error' );
		}
	}
}

/* حذف درخواست اضافی cart-fragments از صفحه‌های غیر فروشگاهی */
add_action( 'wp_enqueue_scripts', 'tk_no_cart_fragments', 20 );
function tk_no_cart_fragments() {
	if ( function_exists( 'is_cart' ) && ! is_cart() && ! is_checkout() ) {
		wp_dequeue_script( 'wc-cart-fragments' );
	}
}