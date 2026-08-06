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

	/* برند + ستون جدید default_preset_id */
	dbDelta( "CREATE TABLE {$p}tk_brands (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		slug VARCHAR(190) NOT NULL,
		name_fa VARCHAR(190) NOT NULL,
		name_en VARCHAR(190) NOT NULL DEFAULT '',
		default_preset_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		sort INT NOT NULL DEFAULT 0,
		active TINYINT NOT NULL DEFAULT 1,
		PRIMARY KEY  (id), UNIQUE KEY slug (slug)
	) $charset;" );

	/* پریست‌های ضریب پیشفرض (چینی درجه۱ و…) */
	dbDelta( "CREATE TABLE {$p}tk_presets (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		title_fa VARCHAR(190) NOT NULL,
		PRIMARY KEY  (id)
	) $charset;" );

	dbDelta( "CREATE TABLE {$p}tk_preset_coefs (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		preset_id BIGINT UNSIGNED NOT NULL,
		section_id BIGINT UNSIGNED NOT NULL,
		coef DECIMAL(16,2) NOT NULL DEFAULT 0,
		PRIMARY KEY  (id), UNIQUE KEY ps (preset_id,section_id)
	) $charset;" );

	/* تنظیمات هر سری برای هر برند (پوشه برند) */
	dbDelta( "CREATE TABLE {$p}tk_brand_series (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		brand_id BIGINT UNSIGNED NOT NULL,
		section_id BIGINT UNSIGNED NOT NULL,
		coef_on TINYINT NOT NULL DEFAULT 0,
		coef_value DECIMAL(16,2) DEFAULT NULL,
		formula_on TINYINT NOT NULL DEFAULT 0,
		formula_key VARCHAR(60) DEFAULT NULL,
		image_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		desc_on TINYINT NOT NULL DEFAULT 0,
		desc_text TEXT,
		PRIMARY KEY  (id), UNIQUE KEY bs (brand_id,section_id)
	) $charset;" );

	/* مشخصات فنی داینامیک هر سری (از جدول PDF) */
	dbDelta( "CREATE TABLE {$p}tk_specs (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		section_id BIGINT UNSIGNED NOT NULL,
		top_w VARCHAR(20) NOT NULL DEFAULT '',
		pitch_w VARCHAR(20) NOT NULL DEFAULT '',
		height VARCHAR(20) NOT NULL DEFAULT '',
		wedge VARCHAR(10) NOT NULL DEFAULT '',
		conv1 VARCHAR(40) NOT NULL DEFAULT '',
		conv2 VARCHAR(40) NOT NULL DEFAULT '',
		len_std VARCHAR(8) NOT NULL DEFAULT '',
		weight VARCHAR(20) NOT NULL DEFAULT '',
		PRIMARY KEY  (id), UNIQUE KEY s (section_id)
	) $charset;" );

	/* موجودی (بدون تغییر) */
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

	/* جدول قدیمی ضریب‌ها: فقط به عنوان فال‌بک نگه داشته می‌شود */
	dbDelta( "CREATE TABLE {$p}tk_coefficients (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		brand_id BIGINT UNSIGNED NOT NULL,
		section_id BIGINT UNSIGNED NOT NULL,
		coef DECIMAL(16,2) NOT NULL DEFAULT 0,
		updated_at DATETIME NOT NULL,
		PRIMARY KEY  (id), UNIQUE KEY bs (brand_id,section_id)
	) $charset;" );
}

/* ---------- Seed مرحله ۱ (بدون تغییر قبلی) ---------- */
function tk_seed_data() {
	global $wpdb;
	$p = $wpdb->prefix;
	$now = current_time( 'mysql' );

	$formulas = array(
		array( 'LEN_COEF',      'طول × ضریب',        'LENGTH * COEF' ),
		array( 'RIBS_LEN_COEF', 'شیار × طول × ضریب', 'RIBS * LENGTH * COEF' ),
		array( 'PLACEHOLDER',   'تعریف دستی توسط مدیر', '' ),
	);
	foreach ( $formulas as $f ) {
		$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$p}tk_formulas (fkey,title_fa,expr) VALUES (%s,%s,%s)", $f[0], $f[1], $f[2] ) );
	}

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
		array( 'timing',         'تسمه تایم', 'Timing' ),
		array( 'conveyor',       'تسمه نقاله', 'Conveyor' ),
		array( 'flat',           'تسمه تخت', 'Flat Transmission' ),
	);
	$cat_id = array();
	foreach ( $cats as $i => $c ) {
		$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$p}tk_categories (slug,name_fa,name_en,sort) VALUES (%s,%s,%s,%d)", $c[0], $c[1], $c[2], $i ) );
		$cat_id[ $c[0] ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$p}tk_categories WHERE slug=%s", $c[0] ) );
	}

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
		$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$p}tk_brands (slug,name_fa,name_en,sort) VALUES (%s,%s,%s,%d)", $b[0], $b[1], $b[2], $i ) );
	}

	/* ضریب‌های اولیه فقط به عنوان فال‌بک legacy */
	$coefs = tk_tiger_coefs();
	foreach ( array( 'tiger', 'blman' ) as $bslug ) {
		$bid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$p}tk_brands WHERE slug=%s", $bslug ) );
		foreach ( $coefs as $sslug => $val ) {
			$sid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$p}tk_sections WHERE slug=%s", $sslug ) );
			if ( ! $bid || ! $sid ) { continue; }
			$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$p}tk_coefficients (brand_id,section_id,coef,updated_at) VALUES (%d,%d,%f,%s)", $bid, $sid, $val, $now ) );
		}
	}
}

function tk_tiger_coefs() {
	return array(
		'A' => 72500, 'B' => 115000, 'C' => 200000, 'D' => 400000, 'M' => 72500,
		'AX' => 145000, 'BX' => 210000, 'CX' => 320000, 'DX' => 420000, 'AA' => 165000,
		'SPA' => 107000, 'SPB' => 170000, 'SPC' => 330000,
		'RA' => 110000, 'RB' => 165000, 'RAX' => 205000, 'RBX' => 280000, 'R3V' => 165000, 'R3VX' => 165000,
		'PK' => 1000,
	);
}

function tk_seed_v2() {
	global $wpdb; $p = $wpdb->prefix;
	$cat = (int) $wpdb->get_var( "SELECT id FROM {$p}tk_categories WHERE slug='timing'" );
	if ( $cat ) {
		$wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO {$p}tk_sections (category_id,slug,name_fa,formula_key,length_std,sort) VALUES (%d,'TIME','تسمه تایم (دندانه‌ای)','PLACEHOLDER','',90)", $cat ) );
	}
}

/* ---------- Seed مرحله ۳: پریست‌ها + مشخصات فنی ---------- */
function tk_seed_v3() {
	global $wpdb; $p = $wpdb->prefix;

	$pid = (int) $wpdb->get_var( "SELECT id FROM {$p}tk_presets WHERE title_fa='چینی درجه ۱ (لیست تایگر)'" );
	if ( ! $pid ) {
		$wpdb->insert( "{$p}tk_presets", array( 'title_fa' => 'چینی درجه ۱ (لیست تایگر)' ) );
		$pid = (int) $wpdb->insert_id;
		foreach ( array( 'چینی درجه ۲', 'ایرانی', 'جنس خوب اصلی (اورجینال)' ) as $t ) {
			$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$p}tk_presets (title_fa) VALUES (%s)", $t ) );
		}
		foreach ( tk_tiger_coefs() as $sslug => $val ) {
			$sid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$p}tk_sections WHERE slug=%s", $sslug ) );
			if ( $sid ) {
				$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$p}tk_preset_coefs (preset_id,section_id,coef) VALUES (%d,%d,%f)", $pid, $sid, $val ) );
			}
		}
		foreach ( array( 'tiger', 'falkan', 'rhino', 'avocet', 'blman' ) as $bslug ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$p}tk_brands SET default_preset_id=%d WHERE slug=%s", $pid, $bslug ) );
		}
	}

	/* مشخصات فنی هر سری — از جدول PDF (عرض بالا/پیچ، ارتفاع، زاویه، تبدیل‌ها، استاندارد، وزن) */
	$specs = array(
		'Z'=>array('10','8.5','6','40','Li=Lw-25','La=Li+38','Li','0.065'),
		'A'=>array('13','11','8','40','Li=Lw-33','La=Li+50','Li','0.112'),
		'B'=>array('17','14','11','40','Li=Lw-43','La=Li+69','Li','0.192'),
		'C'=>array('22','19','14','40','Li=Lw-56','La=Li+88','Li','0.31'),
		'D'=>array('32','27','19','40','Li=Lw-82','La=Li+119','Li','0.61'),
		'E'=>array('38','32','23','40','Li=Lw-95','La=Li+145','Li','0.94'),
		'F'=>array('50','42.5','30','40','Li=Lw-120','La=Li+188','Li','1.58'),
		'SPZ'=>array('10','8','8','40','Lw=Li+37','La=Li+50','Lw','0.075'),
		'SPA'=>array('13','11','10','40','Lw=Li+45','La=Li+63','Lw','0.125'),
		'SPB'=>array('17','14','14','40','Lw=Li+60','La=Li+88','Lw','0.22'),
		'SPC'=>array('22','19','18','40','Lw=Li+83','La=Li+113','Lw','0.375'),
		'3V'=>array('9.5','','8','40','La=Li+50','','La','0.075'),
		'5V'=>array('16','','13.5','40','La=Li+85','','La','0.21'),
		'8V'=>array('25.5','','23','40','La=Li+145','','La','0.525'),
		'3L'=>array('10','8.5','6','40','Li=Lw-25','La=Li+38','La','0.07'),
		'4L'=>array('13','11','8','40','Li=Lw-33','La=Li+50','La','0.112'),
		'5L'=>array('17','14','11','40','Li=Lw-43','La=Li+69','La','0.19'),
		'ZX'=>array('10','8.5','6','40','Li=Lw-25','La=Li+38','Li','0.062'),
		'AX'=>array('13','11','8','40','Li=Lw-33','La=Li+50','Li','0.099'),
		'BX'=>array('17','14','11','40','Li=Lw-43','La=Li+69','Li','0.176'),
		'CX'=>array('22','19','14','40','Li=Lw-56','La=Li+88','Li','0.276'),
		'DX'=>array('32','27','19','40','Li=Lw-82','La=Li+119','Li','0.598'),
		'EX'=>array('38','32','23','40','Li=Lw-95','La=Li+145','Li','0.933'),
		'XPZ'=>array('10','8','8','40','Li=La-50','Lw=La-13','Lw','0.065'),
		'XPA'=>array('13','11','10','40','Li=La-63','Lw=La-18','Lw','0.11'),
		'XPB'=>array('17','14','14','40','Li=La-82','Lw=La-22','Lw','0.2'),
		'XPC'=>array('22','19','18','40','Li=La-113','Lw=La-30','Lw','0.323'),
		'3VX'=>array('9.5','','8','40','Li=La-50','Lw=Le-4','La','0.063'),
		'5VX'=>array('16','','13.5','40','Li=La-82','Lw=Le-11','La','0.182'),
		'8VX'=>array('25.5','','23','40','Li=La-144','Lw=Le-16','La','0.51'),
		'AA'=>array('13','10','','40','Li=La-63','','Li','0.15'),
		'BB'=>array('17','13','','40','Li=La-82','','Li','0.25'),
		'CC'=>array('22','17','','40','Li=La-107','','Li','0.44'),
		'HA'=>array('13.6','','10','40','Li=La-63','Li=Lw-33','Li','0.163'),
		'HB'=>array('17','','13','40','Li=La-82','Li=Lw-43','Li','0.266'),
		'HC'=>array('22.4','','16','40','Li=La-100','Li=Lw-56','Li','0.45'),
		'HD'=>array('32.8','','21.5','40','Li=La-135','Li=Lw-82','Li','0.798'),
	);
	foreach ( $specs as $slug => $v ) {
		$sid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$p}tk_sections WHERE slug=%s", $slug ) );
		if ( $sid ) {
			$wpdb->query( $wpdb->prepare(
				"INSERT IGNORE INTO {$p}tk_specs (section_id,top_w,pitch_w,height,wedge,conv1,conv2,len_std,weight) VALUES (%d,%s,%s,%s,%s,%s,%s,%s,%s)",
				$sid, $v[0], $v[1], $v[2], $v[3], $v[4], $v[5], $v[6], $v[7] ) );
		}
	}
}