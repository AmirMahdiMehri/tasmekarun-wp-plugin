<?php
defined( 'ABSPATH' ) || exit;
global $wpdb;
$p = $wpdb->prefix;
$q = get_search_query();
$term = strtoupper( trim( (string) $q ) );

get_header();
echo '<div class="tk-shop" dir="rtl">';
echo '<h1>نتیجه جستجو برای: ' . esc_html( $q ) . '</h1>';

if ( '' === $term ) {
	echo '<p>عبارتی وارد نشده است.</p>';
} else {
	$like = '%' . $wpdb->esc_like( $term ) . '%';
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT st.size, st.qty, b.id AS bid, b.name_fa AS bname, b.slug AS bslug, s.slug AS sslug, c.slug AS cslug
		 FROM {$p}tk_stock st
		 JOIN {$p}tk_brands b ON b.id = st.brand_id
		 JOIN {$p}tk_sections s ON s.id = st.section_id
		 JOIN {$p}tk_categories c ON c.id = s.category_id
		 WHERE st.sku_raw LIKE %s ORDER BY st.sku_raw LIMIT 60", $like ) );

	$parsed = TK_Engine::parse_sku( $term );
	$brand_rows = array();
	if ( $parsed ) {
		$sec = TK_Engine::section_by_slug( $parsed['section'] );
		if ( $sec ) {
			$brand_rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT b.id AS bid, b.name_fa AS bname, b.slug AS bslug, c.slug AS cslug, s.slug AS sslug
				 FROM {$p}tk_brand_series bs
				 JOIN {$p}tk_brands b ON b.id = bs.brand_id
				 JOIN {$p}tk_sections s ON s.id = bs.section_id
				 JOIN {$p}tk_categories c ON c.id = s.category_id
				 WHERE bs.section_id = %d AND b.active = 1 ORDER BY b.name_fa LIMIT 12", $sec->id ) );
		}
	}

	if ( ! $rows && ! $brand_rows ) {
		echo '<p>چیزی پیدا نشد. مدل را مثل B70 یا AX58 یا 8PK1460 بنویسید.</p>';
	} else {
		echo '<div class="tk-grid">';
		$seen = array();
		foreach ( $rows as $r ) {
			$key = $r->bslug . '-' . $r->sslug . '-' . $r->size;
			if ( isset( $seen[ $key ] ) ) { continue; }
			$seen[ $key ] = 1;
			$price = TK_Engine::price( (int) $r->bid, array( 'section' => $r->sslug, 'size' => (int) $r->size, 'ribs' => null ) );
			$url = home_url( '/shop/' . $r->cslug . '/' . $r->sslug . '/' . $r->bslug . '/' . $r->size . '/' );
			echo '<a class="tk-card" href="' . esc_url( $url ) . '"><strong>' . esc_html( $r->sslug . $r->size ) . '</strong><small>' . esc_html( $r->bname ) . '</small><small>' . ( null === $price ? 'تماس بگیرید' : esc_html( TK_Engine::fmt( $price ) ) ) . ( (int) $r->qty > 0 ? ' — موجود در انبار' : '' ) . '</small></a>';
		}
		foreach ( $brand_rows as $br ) {
			$key = $br->bslug . '-' . $br->sslug . '-' . $parsed['size'];
			if ( isset( $seen[ $key ] ) ) { continue; }
			$seen[ $key ] = 1;
			$price = TK_Engine::price( (int) $br->bid, array( 'section' => $br->sslug, 'size' => (int) $parsed['size'], 'ribs' => isset( $parsed['ribs'] ) ? $parsed['ribs'] : null ) );
			$url = home_url( '/shop/' . $br->cslug . '/' . $br->sslug . '/' . $br->bslug . '/' . (int) $parsed['size'] . '/' );
			echo '<a class="tk-card" href="' . esc_url( $url ) . '"><strong>' . esc_html( $br->sslug . $parsed['size'] ) . '</strong><small>' . esc_html( $br->bname ) . '</small><small>' . ( null === $price ? 'تماس بگیرید' : esc_html( TK_Engine::fmt( $price ) ) ) . '</small></a>';
		}
		echo '</div>';
	}
}
echo '</div>';
get_footer();