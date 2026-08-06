<?php
defined( 'ABSPATH' ) || exit;

class TK_Engine {

	public static function sections() {
		global $wpdb;
		$secs = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}tk_sections WHERE active=1 ORDER BY CHAR_LENGTH(slug) DESC" );
		return $secs ? $secs : array();
	}

	public static function parse_sku( $sku ) {
		$sku = trim( strtoupper( str_replace( ' ', '', $sku ) ) );
		if ( '' === $sku ) { return null; }

		if ( preg_match( '/^(\d+)PK(\d+)$/', $sku, $m ) ) {
			return array( 'section' => 'PK', 'size' => (int) $m[2], 'ribs' => (int) $m[1] );
		}
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

	/** تنظیمات سریِ برند (ردیف پوشه برند) */
	public static function brand_series( $brand_id, $section_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}tk_brand_series WHERE brand_id=%d AND section_id=%d", $brand_id, $section_id ) );
	}

	/** ضریب: دستیِ سری ← پریست پیشفرض برند ← جدول قدیمی */
	public static function coefficient( $brand_id, $section_id ) {
		global $wpdb;
		$bs = self::brand_series( $brand_id, $section_id );
		if ( $bs && $bs->coef_on && null !== $bs->coef_value ) { return (float) $bs->coef_value; }

		$pid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT default_preset_id FROM {$wpdb->prefix}tk_brands WHERE id=%d", $brand_id ) );
		if ( $pid ) {
			$v = $wpdb->get_var( $wpdb->prepare(
				"SELECT coef FROM {$wpdb->prefix}tk_preset_coefs WHERE preset_id=%d AND section_id=%d", $pid, $section_id ) );
			if ( null !== $v ) { return (float) $v; }
		}
		$v = $wpdb->get_var( $wpdb->prepare(
			"SELECT coef FROM {$wpdb->prefix}tk_coefficients WHERE brand_id=%d AND section_id=%d", $brand_id, $section_id ) );
		return null === $v ? null : (float) $v;
	}

	/** فرمول: دستیِ سری ← فرمول پیشفرض بخش */
	public static function formula_key_for( $brand_id, $section ) {
		$bs = self::brand_series( $brand_id, (int) $section->id );
		if ( $bs && $bs->formula_on && $bs->formula_key ) { return $bs->formula_key; }
		return $section->formula_key;
	}

	public static function price( $brand_id, $parsed ) {
		if ( ! $parsed ) { return null; }
		$sec = self::section_by_slug( $parsed['section'] );
		if ( ! $sec ) { return null; }
		$coef = self::coefficient( $brand_id, (int) $sec->id );
		if ( null === $coef ) { return null; }

		switch ( self::formula_key_for( $brand_id, $sec ) ) {
			case 'LEN_COEF':      return $coef * $parsed['size'];
			case 'RIBS_LEN_COEF': return $parsed['ribs'] ? $parsed['ribs'] * $parsed['size'] * $coef : null;
			default:              return null;
		}
	}

	public static function stock_row( $brand_id, $section_id, $size ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}tk_stock WHERE brand_id=%d AND section_id=%d AND size=%d", $brand_id, $section_id, $size ) );
	}

	public static function in_stock( $brand_id, $section_id, $size ) {
		$r = self::stock_row( $brand_id, $section_id, $size );
		return $r && (int) $r->qty > 0;
	}

	/** مشخصات فنی داینامیک سری */
	public static function specs( $section_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}tk_specs WHERE section_id=%d", $section_id ) );
	}

	/** عکس مشترک سری برند */
	public static function series_image_url( $brand_id, $section_id ) {
		$bs = self::brand_series( $brand_id, $section_id );
		if ( $bs && $bs->image_id ) {
			$u = wp_get_attachment_image_url( (int) $bs->image_id, 'medium' );
			if ( $u ) { return $u; }
		}
		return '';
	}

	/** دسکریپشن: دستیِ سری ← اتوماتیک با مشخصات فنی داینامیک */
	public static function description( $brand_name, $section, $bs = null ) {
		if ( ! $bs ) { $bs = self::brand_series( 0, 0 ); }
		if ( $bs && $bs->desc_on && ! empty( $bs->desc_text ) ) { return $bs->desc_text; }
		$out = 'تسمه ' . $section->name_fa . ' برند ' . $brand_name . ' — قیمت به‌روز و ضمانت اصالت؛ مناسب فروش عمده.';
		$spec = self::specs( (int) $section->id );
		if ( $spec ) {
			$parts = array();
			if ( $spec->top_w )   { $parts[] = 'عرض بالا: ' . $spec->top_w . ' میلی‌متر'; }
			if ( $spec->pitch_w ) { $parts[] = 'عرض پیچ: ' . $spec->pitch_w . ' میلی‌متر'; }
			if ( $spec->height )  { $parts[] = 'ارتفاع: ' . $spec->height . ' میلی‌متر'; }
			if ( $spec->wedge )   { $parts[] = 'زاویه گوه: ' . $spec->wedge . ' درجه'; }
			if ( $spec->len_std ) { $parts[] = 'استاندارد طول: ' . $spec->len_std; }
			if ( $spec->weight )  { $parts[] = 'وزن: ' . $spec->weight . ' کیلوگرم/متر'; }
			if ( $spec->conv1 )   { $parts[] = $spec->conv1; }
			if ( $spec->conv2 )   { $parts[] = $spec->conv2; }
			if ( $parts ) { $out .= ' مشخصات فنی: ' . implode(' | ', $parts); }
		}
		return $out;
	}

	public static function fmt( $rial ) {
		return number_format_i18n( (float) $rial ) . ' ریال';
	}
}