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

	public static function brand_series( $brand_id, $section_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}tk_brand_series WHERE brand_id=%d AND section_id=%d", $brand_id, $section_id ) );
	}

	public static function coefficient_source( $brand_id, $section_id ) {
		global $wpdb;
		$bs = self::brand_series( $brand_id, $section_id );
		if ( $bs && $bs->coef_on && null !== $bs->coef_value ) { return array( (float) $bs->coef_value, 'ضریب دستی سری' ); }
		$pid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT default_preset_id FROM {$wpdb->prefix}tk_brands WHERE id=%d", $brand_id ) );
		if ( $pid ) {
			$v = $wpdb->get_var( $wpdb->prepare( "SELECT coef FROM {$wpdb->prefix}tk_preset_coefs WHERE preset_id=%d AND section_id=%d", $pid, $section_id ) );
			if ( null !== $v ) { return array( (float) $v, 'پریست پیشفرض برند' ); }
		}
		$v = $wpdb->get_var( $wpdb->prepare( "SELECT coef FROM {$wpdb->prefix}tk_coefficients WHERE brand_id=%d AND section_id=%d", $brand_id, $section_id ) );
		if ( null !== $v ) { return array( (float) $v, 'جدول قدیمی' ); }
		return array( null, 'بدون ضریب' );
	}

	public static function coefficient( $brand_id, $section_id ) {
		$r = self::coefficient_source( $brand_id, $section_id );
		return $r[0];
	}

	/** فرمول: دستی سری ← فرمول خود بخش ← فرمول کل دسته */
	public static function formula_key_for( $brand_id, $section ) {
		global $wpdb;
		$bs = self::brand_series( $brand_id, (int) $section->id );
		if ( $bs && $bs->formula_on && $bs->formula_key ) { return $bs->formula_key; }
		if ( ! empty( $section->formula_key ) ) { return $section->formula_key; }
		$ck = $wpdb->get_var( $wpdb->prepare( "SELECT formula_key FROM {$wpdb->prefix}tk_categories WHERE id=%d", (int) $section->category_id ) );
		return $ck ? $ck : '';
	}
	public static function formula_expr( $fkey ) {
		global $wpdb;
		if ( '' === (string) $fkey ) { return ''; }
		$e = $wpdb->get_var( $wpdb->prepare( "SELECT expr FROM {$wpdb->prefix}tk_formulas WHERE fkey=%s", $fkey ) );
		return $e ? trim( $e ) : '';
	}

	/** قیمت = ارزیابی عبارت فرمول با متغیرهای LENGTH / RIBS / COEF — فقط سمت سرور */
	public static function price( $brand_id, $parsed ) {
		if ( ! $parsed ) { return null; }
		$sec = self::section_by_slug( $parsed['section'] );
		if ( ! $sec ) { return null; }
		$coef = self::coefficient( $brand_id, (int) $sec->id );
		if ( null === $coef ) { return null; }
		$expr = self::formula_expr( self::formula_key_for( $brand_id, $sec ) );
		if ( '' === $expr ) { return null; }
		return self::eval_expr( $expr, array(
			'LENGTH' => (float) $parsed['size'],
			'RIBS'   => (float) ( $parsed['ribs'] ? $parsed['ribs'] : 1 ),
			'COEF'   => (float) $coef,
		) );
	}

	/** ارزیاب امن عبارت: عدد، پرانتز، + - * / و توکن‌ها. بدون eval */
	public static function eval_expr( $expr, $vars ) {
		$vars = array_change_key_case( (array) $vars, CASE_UPPER );
		$len = strlen( $expr ); $i = 0; $tokens = array();
		while ( $i < $len ) {
			$c = $expr[ $i ];
			if ( ' ' === $c || "\t" === $c ) { $i++; continue; }
			if ( preg_match( '/[0-9.]/', $c ) ) {
				$j = $i; while ( $j < $len && preg_match( '/[0-9.]/', $expr[ $j ] ) ) { $j++; }
				$tokens[] = array( 'n', (float) substr( $expr, $i, $j - $i ) ); $i = $j; continue;
			}
			if ( preg_match( '/[A-Za-z_]/', $c ) ) {
				$j = $i; while ( $j < $len && preg_match( '/[A-Za-z_]/', $expr[ $j ] ) ) { $j++; }
				$name = strtoupper( substr( $expr, $i, $j - $i ) );
				if ( ! isset( $vars[ $name ] ) ) { return null; }
				$tokens[] = array( 'n', (float) $vars[ $name ] ); $i = $j; continue;
			}
			if ( strpos( '+-*/()', $c ) !== false ) { $tokens[] = array( 'o', $c ); $i++; continue; }
			return null;
		}
		$out = array(); $ops = array(); $prec = array( '+' => 1, '-' => 1, '*' => 2, '/' => 2 );
		foreach ( $tokens as $t ) {
			if ( 'n' === $t[0] ) { $out[] = $t; continue; }
			$o = $t[1];
			if ( '(' === $o ) { $ops[] = $o; continue; }
			if ( ')' === $o ) {
				while ( $ops && end( $ops ) !== '(' ) { $out[] = array( 'o', array_pop( $ops ) ); }
				if ( ! $ops ) { return null; }
				array_pop( $ops ); continue;
			}
			while ( $ops && end( $ops ) !== '(' && $prec[ end( $ops ) ] >= $prec[ $o ] ) { $out[] = array( 'o', array_pop( $ops ) ); }
			$ops[] = $o;
		}
		while ( $ops ) { $o = array_pop( $ops ); if ( '(' === $o ) { return null; } $out[] = array( 'o', $o ); }
		$st = array();
		foreach ( $out as $t ) {
			if ( 'n' === $t[0] ) { $st[] = $t[1]; continue; }
			if ( count( $st ) < 2 ) { return null; }
			$b = array_pop( $st ); $a = array_pop( $st );
			switch ( $t[1] ) {
				case '+': $st[] = $a + $b; break;
				case '-': $st[] = $a - $b; break;
				case '*': $st[] = $a * $b; break;
				case '/': if ( 0 == $b ) { return null; } $st[] = $a / $b; break;
			}
		}
		return 1 === count( $st ) ? $st[0] : null;
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

	public static function specs( $section_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}tk_specs WHERE section_id=%d", $section_id ) );
	}

	public static function series_image_url( $brand_id, $section_id ) {
		$bs = self::brand_series( $brand_id, $section_id );
		if ( $bs && $bs->image_id ) {
			$u = wp_get_attachment_image_url( (int) $bs->image_id, 'medium' );
			if ( $u ) { return $u; }
		}
		return '';
	}

	public static function description( $brand_name, $section, $bs = null ) {
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