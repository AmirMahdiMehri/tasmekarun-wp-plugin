<?php
defined( 'ABSPATH' ) || exit;

/** موتور داینامیک تسمه کارون */
class TK_Engine {

	/** همه بخش‌های فعال (مرتب بر اساس طول slug برای پارس درست) */
	public static function sections() {
		global $wpdb;
		$secs = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}tk_sections WHERE active=1 ORDER BY CHAR_LENGTH(slug) DESC" );
		return $secs ? $secs : array();
	}

	/** پارس SKU: خروجی ['section'=>'A','size'=>50,'ribs'=>null] */
	public static function parse_sku( $sku ) {
		$sku = trim( strtoupper( str_replace( ' ', '', $sku ) ) );
		if ( '' === $sku ) { return null; }

		// حالت شیاری: 8PK1333
		if ( preg_match( '/^(\d+)PK(\d+)$/', $sku, $m ) ) {
			return array( 'section' => 'PK', 'size' => (int) $m[2], 'ribs' => (int) $m[1] );
		}
				// حالت تسمه تایم: Time 127
		if ( preg_match( '/^TIME(\d+)$/', $sku, $m ) ) {
			return array( 'section' => 'TIME', 'size' => (int) $m[1], 'ribs' => null );
		}
		foreach ( self::sections() as $s ) {
			if ( 0 === strpos( $sku, strtoupper( $s->slug ) ) ) {
				$rest = substr( $sku, strlen( $s->slug ) );
				if ( preg_match( '/^\d+(\.\d+)?$/', $rest ) ) {
					return array( 'section' => $s->slug, 'size' => (int) $rest, 'ribs' => null );
				}
			}
		}
		return null;
	}

	public static function section_by_slug( $slug ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}tk_sections WHERE slug=%s", $slug ) );
	}

	public static function coefficient( $brand_id, $section_id ) {
		global $wpdb;
		$v = $wpdb->get_var( $wpdb->prepare(
			"SELECT coef FROM {$wpdb->prefix}tk_coefficients WHERE brand_id=%d AND section_id=%d", $brand_id, $section_id ) );
		return null === $v ? null : (float) $v;
	}

	/** محاسبه قیمت — فقط سمت سرور */
	public static function price( $brand_id, $parsed ) {
		if ( ! $parsed ) { return null; }
		$sec = self::section_by_slug( $parsed['section'] );
		if ( ! $sec ) { return null; }
		$coef = self::coefficient( $brand_id, (int) $sec->id );
		if ( null === $coef ) { return null; }

		switch ( $sec->formula_key ) {
			case 'LEN_COEF':
				return $coef * $parsed['size'];
			case 'RIBS_LEN_COEF':
				return $parsed['ribs'] ? $parsed['ribs'] * $parsed['size'] * $coef : null;
			default:
				return null; // PLACEHOLDER: بعداً توسط مدیر تعریف می‌شود
		}
	}

	/** موجودی از اکسل انبار */
	public static function stock_row( $brand_id, $section_id, $size ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}tk_stock WHERE brand_id=%d AND section_id=%d AND size=%d",
			$brand_id, $section_id, $size ) );
	}

	public static function in_stock( $brand_id, $section_id, $size ) {
		$r = self::stock_row( $brand_id, $section_id, $size );
		return $r && (int) $r->qty > 0;
	}

	public static function fmt( $rial ) {
		return number_format_i18n( (float) $rial ) . ' ریال';
	}
}