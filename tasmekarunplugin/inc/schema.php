<?php
defined( 'ABSPATH' ) || exit;

function tk_create_tables() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();
	$p = $wpdb->prefix;

	dbDelta( "CREATE TABLE {$p}tk_formulas (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		fkey VARCHAR(60) NOT NULL,
		title_fa VARCHAR(190) NOT NULL,
		expr VARCHAR(190) NOT NULL DEFAULT '',
		PRIMARY KEY  (id), UNIQUE KEY fkey (fkey)
	) $charset;" );

	dbDelta( "CREATE TABLE {$p}tk_categories (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		slug VARCHAR(190) NOT NULL,
		name_fa VARCHAR(190) NOT NULL,
		name_en VARCHAR(190) NOT NULL DEFAULT '',
		sort INT NOT NULL DEFAULT 0,
		active TINYINT NOT NULL DEFAULT 1,
		PRIMARY KEY  (id), UNIQUE KEY slug (slug)
	) $charset;" );

	dbDelta( "CREATE TABLE {$p}tk_sections (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		category_id BIGINT UNSIGNED NOT NULL,
		slug VARCHAR(60) NOT NULL,
		name_fa VARCHAR(190) NOT NULL,
		formula_key VARCHAR(60) NOT NULL DEFAULT 'LEN_COEF',
		size_min INT NOT NULL DEFAULT 16,
		size_max INT NOT NULL DEFAULT 3000,
		length_std VARCHAR(8) NOT NULL DEFAULT 'Li',
		sort INT NOT NULL DEFAULT 0,
		active TINYINT NOT NULL DEFAULT 1,
		PRIMARY KEY  (id), UNIQUE KEY slug (slug), KEY cat (category_id)
	) $charset;" );

	dbDelta( "CREATE TABLE {$p}tk_brands (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		slug VARCHAR(190) NOT NULL,
		name_fa VARCHAR(190) NOT NULL,
		name_en VARCHAR(190) NOT NULL DEFAULT '',
		sort INT NOT NULL DEFAULT 0,
		active TINYINT NOT NULL DEFAULT 1,
		PRIMARY KEY  (id), UNIQUE KEY slug (slug)
	) $charset;" );

	dbDelta( "CREATE TABLE {$p}tk_coefficients (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		brand_id BIGINT UNSIGNED NOT NULL,
		section_id BIGINT UNSIGNED NOT NULL,
		coef DECIMAL(16,2) NOT NULL DEFAULT 0,
		updated_at DATETIME NOT NULL,
		PRIMARY KEY  (id), UNIQUE KEY bs (brand_id,section_id)
	) $charset;" );

	dbDelta( "CREATE TABLE {$p}tk_stock (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		brand_id BIGINT UNSIGNED NOT NULL,
		section_id BIGINT UNSIGNED NOT NULL,
		size INT NOT NULL,
		qty INT NOT NULL DEFAULT 0,
		location VARCHAR(60) NOT NULL DEFAULT '',
		sku_raw VARCHAR(120) NOT NULL DEFAULT '',
		updated_at DATETIME NOT NULL,
		PRIMARY KEY  (id), UNIQUE KEY bss (brand_id,section_id,size)
	) $charset;" );
}

/* ---------- داده اولیه (Seed) ---------- */
function tk_seed_data() {
	global $wpdb;
	$p = $wpdb->prefix;
	$now = current_time( 'mysql' );

	// فرمول‌ها
	$formulas = array(
		array( 'LEN_COEF',      'طول × ضریب',                'LENGTH * COEF' ),
		array( 'RIBS_LEN_COEF', 'شیار × طول × ضریب',         'RIBS * LENGTH * COEF' ),
		array( 'PLACEHOLDER',   'تعریف دستی توسط مدیر',      '' ),
	);
	foreach ( $formulas as $f ) {
		$wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO {$p}tk_formulas (fkey,title_fa,expr) VALUES (%s,%s,%s)", $f[0], $f[1], $f[2] ) );
	}

	// دسته‌ها
	$cats = array(
		array( 'classic-v-belt', 'تسمه پروانه‌ای کلاسیک', 'Classic V Belt' ),
		array( 'cogged-classic', 'تسمه کلاسیک دندانه‌دار', 'Cogged Classic' ),
		array( 'narrow-v',       'تسمه پروانه‌ای باریک', 'Narrow V Belt' ),
		array( 'cogged-narrow',  'تسمه باریک دندانه‌دار', 'Cogged Narrow' ),
		array( 'light-duty',     'تسمه سبک', 'Light Duty' ),
		array( 'banded',         'تسمه باندی (چندردیفه)', 'Banded V Belt' ),
		array( 'hexangular',     'تسمه شش‌ضلعی', 'Hexangular' ),
		array( 'poly-v',         'تسمه شیاری (PK)', 'Poly-V / Ribbed' ),
		array( 'special',        'سایر / ویژه', 'Special' ),
		array( 'timing',         'تسمه تایمینگ', 'Timing' ),
		array( 'conveyor',       'تسمه نقاله', 'Conveyor' ),
		array( 'flat',           'تسمه تخت', 'Flat Transmission' ),
	);
	$cat_id = array();
	foreach ( $cats as $i => $c ) {
		$wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO {$p}tk_categories (slug,name_fa,name_en,sort) VALUES (%s,%s,%s,%d)", $c[0], $c[1], $c[2], $i ) );
		$cat_id[ $c[0] ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$p}tk_categories WHERE slug=%s", $c[0] ) );
	}

	// بخش‌ها: [slug, cat, نام, فرمول, استاندارد طول]
	$secs = array(
		array( 'Z','classic-v-belt','Z','LEN_COEF','Li' ), array( 'A','classic-v-belt','A','LEN_COEF','Li' ),
		array( 'B','classic-v-belt','B','LEN_COEF','Li' ), array( 'C','classic-v-belt','C','LEN_COEF','Li' ),
		array( 'D','classic-v-belt','D','LEN_COEF','Li' ), array( 'E','classic-v-belt','E','LEN_COEF','Li' ),
		array( 'F','classic-v-belt','F','LEN_COEF','Li' ),
		array( 'ZX','cogged-classic','ZX','LEN_COEF','Li' ), array( 'AX','cogged-classic','AX','LEN_COEF','Li' ),
		array( 'BX','cogged-classic','BX','LEN_COEF','Li' ), array( 'CX','cogged-classic','CX','LEN_COEF','Li' ),
		array( 'DX','cogged-classic','DX','LEN_COEF','Li' ), array( 'EX','cogged-classic','EX','LEN_COEF','Li' ),
		array( 'SPZ','narrow-v','SPZ','LEN_COEF','Lw' ), array( 'SPA','narrow-v','SPA','LEN_COEF','Lw' ),
		array( 'SPB','narrow-v','SPB','LEN_COEF','Lw' ), array( 'SPC','narrow-v','SPC','LEN_COEF','Lw' ),
		array( '3V','narrow-v','3V (9N)','LEN_COEF','La' ), array( '5V','narrow-v','5V (15N)','LEN_COEF','La' ), array( '8V','narrow-v','8V (25N)','LEN_COEF','La' ),
		array( 'XPZ','cogged-narrow','XPZ','LEN_COEF','Lw' ), array( 'XPA','cogged-narrow','XPA','LEN_COEF','Lw' ),
		array( 'XPB','cogged-narrow','XPB','LEN_COEF','Lw' ), array( 'XPC','cogged-narrow','XPC','LEN_COEF','Lw' ),
		array( '3VX','cogged-narrow','3VX','LEN_COEF','La' ), array( '5VX','cogged-narrow','5VX','LEN_COEF','La' ), array( '8VX','cogged-narrow','8VX','LEN_COEF','La' ),
		array( '3L','light-duty','3L','LEN_COEF','La' ), array( '4L','light-duty','4L','LEN_COEF','La' ), array( '5L','light-duty','5L','LEN_COEF','La' ),
		array( 'HA','banded','HA','LEN_COEF','Li' ), array( 'HB','banded','HB','LEN_COEF','Li' ), array( 'HC','banded','HC','LEN_COEF','Li' ), array( 'HD','banded','HD','LEN_COEF','Li' ),
		array( 'AA','hexangular','AA','LEN_COEF','Li' ), array( 'BB','hexangular','BB','LEN_COEF','Li' ), array( 'CC','hexangular','CC','LEN_COEF','Li' ),
		array( 'PK','poly-v','PK (شیاری)','RIBS_LEN_COEF','Lw' ),
		array( 'M','special','M','LEN_COEF','Li' ), array( '9.5','special','9.5','LEN_COEF','Lw' ), array( '9.5X','special','9.5X','LEN_COEF','Lw' ),
		array( 'RA','special','RA','LEN_COEF','Li' ), array( 'RB','special','RB','LEN_COEF','Li' ),
		array( 'RAX','special','RAX','LEN_COEF','Li' ), array( 'RBX','special','RBX','LEN_COEF','Li' ),
		array( 'R3V','special','R3V','LEN_COEF','La' ), array( 'R3VX','special','R3VX','LEN_COEF','La' ),
		array( '8M','timing','8M','PLACEHOLDER','' ), array( '14M','timing','14M','PLACEHOLDER','' ), array( 'XL','timing','XL','PLACEHOLDER','' ), array( 'H','timing','H','PLACEHOLDER','' ),
		array( 'EP','conveyor','EP','PLACEHOLDER','' ), array( 'ST','conveyor','ST','PLACEHOLDER','' ),
		array( 'OZ','flat','OZ','PLACEHOLDER','' ),
	);
	foreach ( $secs as $i => $s ) {
		$wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO {$p}tk_sections (category_id,slug,name_fa,formula_key,length_std,sort) VALUES (%d,%s,%s,%s,%s,%d)",
			$cat_id[ $s[1] ], $s[0], $s[2], $s[3], $s[4], $i ) );
	}

	// برندها
	$brands = array(
		array('rhino','راینو','Rhino'), array('tiger','تایگر','Tiger'), array('blman','بی‌ال‌من','BL MAN'),
		array('falkan','فالکن','Falkan'), array('first','فرست','First'), array('timken','تیمکن','Timken'),
		array('hanyoung','هانیونگ','Hanyoung'), array('sanyoung','سانیونگ','San Young'), array('kgs','کی‌جی‌اس','K.G.S'),
		array('black','بلک','Black'), array('dongil','دونگیل','Dongil'), array('swr','اس‌دبلیوآر','SWR'),
		array('dongil-strong','دونگیل استرونگا','Dongil Strong'), array('avocet','آووست','Avocet'),
		array('powergrip','پاورگریپ','PowerGrip'), array('bando','باندو','Bando'), array('cobra','کبرا','Cobra'),
		array('cane-drive','سی‌اِن درایو','CANe Drive'), array('cayoo','کایو','Cayoo'), array('fuju','فوجو','Fuju'),
		array('jager','یگر','Jager'), array('master','مستر','Master'), array('royal','رویال','Royal'),
		array('fonex','فونکس','Fonex'), array('fortex','فورتکس','Fortex'), array('hankook','هانکوک','Hankook'),
		array('mitsuboshi','میتسوبوشی','Mitsuboshi'), array('contitech','کنتی‌تک','Contitech'),
		array('optibelt','اپتیبلت','Optibelt'), array('goodyear','گودیر','Goodyear'),
		array('hanchang','هانچانگ','Hanchang'), array('hunyoung','هیونیانگ','Hunyoung'),
		array('yangsan','یانگسان','Yangsan'), array('continental','کنتیننتال','Continental'), array('firelli','فایرلی','Firelli'),
	);
	foreach ( $brands as $i => $b ) {
		$wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO {$p}tk_brands (slug,name_fa,name_en,sort) VALUES (%s,%s,%s,%d)", $b[0], $b[1], $b[2], $i ) );
	}

	// ضریب‌های اولیه (لیست Tiger & BL MAN — 1405/02/12)
	$coefs = array(
		'A' => 72500, 'B' => 115000, 'C' => 200000, 'D' => 400000, 'M' => 72500,
		'AX' => 145000, 'BX' => 210000, 'CX' => 320000, 'DX' => 420000, 'AA' => 165000,
		'SPA' => 107000, 'SPB' => 170000, 'SPC' => 330000,
		'RA' => 110000, 'RB' => 165000, 'RAX' => 205000, 'RBX' => 280000, 'R3V' => 165000, 'R3VX' => 165000,
		'PK' => 1000,
	);
	foreach ( array( 'tiger', 'blman' ) as $bslug ) {
		$bid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$p}tk_brands WHERE slug=%s", $bslug ) );
		foreach ( $coefs as $sslug => $val ) {
			$sid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$p}tk_sections WHERE slug=%s", $sslug ) );
			if ( ! $bid || ! $sid ) { continue; }
			$wpdb->query( $wpdb->prepare(
				"INSERT IGNORE INTO {$p}tk_coefficients (brand_id,section_id,coef,updated_at) VALUES (%d,%d,%f,%s)",
				$bid, $sid, $val, $now ) );
		}
	}
}