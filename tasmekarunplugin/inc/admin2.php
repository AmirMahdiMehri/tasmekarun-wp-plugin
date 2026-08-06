<?php
defined( 'ABSPATH' ) || exit;

/* ================= موجودی و ایمپورت ================= */
function tk_page_stock() {
	global $wpdb; $p = $wpdb->prefix;
	$step = '';

	if ( $_SERVER['REQUEST_METHOD'] === 'POST' && tk_ok() ) {
		if ( isset( $_POST['tk_upload'] ) && ! empty( $_FILES['tk_file']['tmp_name'] ) ) {
			$ext = strtolower( pathinfo( $_FILES['tk_file']['name'], PATHINFO_EXTENSION ) );
			if ( in_array( $ext, array( 'xlsx', 'csv' ), true ) ) {
				$up  = wp_upload_dir();
				$dir = $up['basedir'] . '/tk-import/';
				if ( ! file_exists( $dir ) ) { @mkdir( $dir, 0755, true ); }
				$target = $dir . time() . '-' . sanitize_file_name( $_FILES['tk_file']['name'] );
				move_uploaded_file( $_FILES['tk_file']['tmp_name'], $target );
				if ( 'csv' === $ext ) {
					set_transient( 'tk_import_rows', tk_csv_rows( $target ), HOUR_IN_SECONDS );
					$step = 'map';
				} else {
					$sheets = tk_xlsx_sheets( $target );
					set_transient( 'tk_import_file', $target, HOUR_IN_SECONDS );
					set_transient( 'tk_import_sheets', $sheets, HOUR_IN_SECONDS );
					if ( count( $sheets ) > 1 ) { $step = 'sheet'; }
					else { set_transient( 'tk_import_rows', tk_xlsx_rows( $target, $sheets[0]['file'] ), HOUR_IN_SECONDS ); $step = 'map'; }
				}
			}
		} elseif ( isset( $_POST['tk_sheet'] ) ) {
			$sheets = get_transient( 'tk_import_sheets' );
			$i = (int) $_POST['sheet'];
			if ( isset( $sheets[ $i ] ) ) {
				set_transient( 'tk_import_rows', tk_xlsx_rows( get_transient( 'tk_import_file' ), $sheets[ $i ]['file'] ), HOUR_IN_SECONDS );
				$step = 'map';
			}
		} elseif ( isset( $_POST['tk_run_map'] ) ) {
			$rows = get_transient( 'tk_import_rows' );
			$rep  = tk_run_import( $rows ? $rows : array(), (array) $_POST['map'] );
			set_transient( 'tk_import_report', $rep, HOUR_IN_SECONDS );
			$step = 'report';
		}
	}
	?>
	<div class="wrap" dir="rtl">
	<h1>موجودی انبار و ایمپورت اکسل</h1>
	<?php if ( 'sheet' === $step ) : $sheets = get_transient( 'tk_import_sheets' ); ?>
		<form method="post"><p><strong>شیت داده را انتخاب کنید:</strong></p>
		<?php wp_nonce_field( 'tk_act', 'tk_nonce' ); ?>
		<select name="sheet"><?php foreach ( $sheets as $i => $s ) : ?><option value="<?php echo $i; ?>"><?php echo esc_html( $s['name'] ); ?></option><?php endforeach; ?></select>
		<button name="tk_sheet" value="1" class="button button-primary">ادامه</button></form>
	<?php elseif ( 'map' === $step ) : $rows = get_transient( 'tk_import_rows' ); $header = $rows ? $rows[0] : array(); ?>
		<form method="post"><p><strong>نگاشت ستون‌ها:</strong></p>
		<?php wp_nonce_field( 'tk_act', 'tk_nonce' ); ?>
		<table class="widefat striped" style="max-width:700px"><tr><th>ستون فایل شما</th><th>معنی</th></tr>
		<?php foreach ( $header as $i => $h ) : $hl = strtolower( (string) $h );
			$g = ( strpos( $hl, 'model' ) !== false ) ? 'model' : ( ( strpos( $hl, 'brand' ) !== false ) ? 'brand' : ( ( strpos( $hl, 'qty' ) !== false ) ? 'qty' : ( ( strpos( $hl, 'loc' ) !== false ) ? 'location' : '' ) ) ); ?>
			<tr><td><?php echo esc_html( $h ); ?></td><td>
			<select name="map[<?php echo $i; ?>]">
				<option value="" <?php selected( $g, '' ); ?>>نادیده گرفتن</option>
				<option value="model" <?php selected( $g, 'model' ); ?>>مدل (Model)</option>
				<option value="brand" <?php selected( $g, 'brand' ); ?>>برند (Brand)</option>
				<option value="qty" <?php selected( $g, 'qty' ); ?>>تعداد (Qty)</option>
				<option value="location" <?php selected( $g, 'location' ); ?>>موقعیت (Location)</option>
			</select></td></tr>
		<?php endforeach; ?></table>
		<p><button name="tk_run_map" value="1" class="button button-primary">اجرای ایمپورت</button></p></form>
	<?php elseif ( 'report' === $step ) : $rep = get_transient( 'tk_import_report' ); ?>
		<div class="notice notice-success"><p>ایمپورت انجام شد ✔ — <?php echo (int) $rep['ok']; ?> ردیف | برندهای جدید: <?php echo (int) $rep['brands_new']; ?></p></div>
		<?php if ( ! empty( $rep['review'] ) ) : ?>
		<h3>نیازمند بررسی (<?php echo count( $rep['review'] ); ?>)</h3>
		<table class="widefat striped" style="max-width:850px"><tr><th>مدل</th><th>برند</th><th>تعداد</th><th>دلیل</th></tr>
		<?php foreach ( array_slice( $rep['review'], 0, 60 ) as $r ) : ?><tr><td><?php echo esc_html( $r[0] ); ?></td><td><?php echo esc_html( $r[1] ); ?></td><td><?php echo esc_html( $r[2] ); ?></td><td><?php echo esc_html( $r[3] ); ?></td></tr><?php endforeach; ?></table>
		<?php endif;
	endif; ?>
	<h2>آپلود فایل موجودی (xlsx یا csv)</h2>
	<form method="post" enctype="multipart/form-data">
		<?php wp_nonce_field( 'tk_act', 'tk_nonce' ); ?>
		<input type="file" name="tk_file" accept=".xlsx,.csv">
		<button name="tk_upload" value="1" class="button button-primary">بارگذاری و ادامه</button>
	</form>
	<h2>جست‌وجوی موجودی</h2>
	<?php $q = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ''; ?>
	<form method="get"><input type="hidden" name="page" value="tk-stock"><input type="search" name="q" value="<?php echo esc_attr( $q ); ?>" placeholder="مثلاً B70 یا Rhino"><button class="button">جستجو</button></form>
	<?php
	$sql = "SELECT st.*, b.name_fa AS bname, s.slug AS sslug FROM {$p}tk_stock st JOIN {$p}tk_brands b ON b.id=st.brand_id JOIN {$p}tk_sections s ON s.id=st.section_id";
	if ( '' !== $q ) {
		$like = '%' . $wpdb->esc_like( $q ) . '%';
		$sql .= $wpdb->prepare( " WHERE st.sku_raw LIKE %s OR b.name_fa LIKE %s OR s.slug LIKE %s", $like, $like, $like );
	}
	$sql .= ' ORDER BY st.updated_at DESC LIMIT 300';
	$st = $wpdb->get_results( $sql );
	?>
	<table class="widefat striped" style="max-width:950px"><tr><th>مدل</th><th>برند</th><th>بخش</th><th>سایز</th><th>تعداد</th><th>موقعیت</th></tr>
	<?php foreach ( $st as $r ) : ?><tr><td><?php echo esc_html( $r->sku_raw ); ?></td><td><?php echo esc_html( $r->bname ); ?></td><td><?php echo esc_html( $r->sslug ); ?></td><td><?php echo (int) $r->size; ?></td><td><?php echo (int) $r->qty; ?></td><td><?php echo esc_html( $r->location ); ?></td></tr><?php endforeach; ?>
	</table>
	</div>
	<?php
}

/* ================= برندها (افزودن / تغییر نام / حذف) ================= */
function tk_page_brands() {
	global $wpdb; $p = $wpdb->prefix;
	if ( $_SERVER['REQUEST_METHOD'] === 'POST' && tk_ok() ) {
		if ( isset( $_POST['tk_add_brand'] ) ) {
			$fa = sanitize_text_field( $_POST['name_fa_new'] ); $en = sanitize_text_field( $_POST['name_en_new'] );
			$slug = sanitize_title( $en ) ? sanitize_title( $en ) : sanitize_title( $fa );
			if ( ! $slug ) { $slug = 'brand-' . time(); }
			$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$p}tk_brands (slug,name_fa,name_en,sort,active) VALUES (%s,%s,%s,999,1)", $slug, $fa, $en ) );
		} elseif ( isset( $_POST['tk_update_brand'] ) ) {
			$id = (int) $_POST['tk_update_brand'];
			if ( isset( $_POST['name_fa'][ $id ], $_POST['name_en'][ $id ] ) ) {
				$wpdb->update( "{$p}tk_brands", array(
					'name_fa' => sanitize_text_field( $_POST['name_fa'][ $id ] ),
					'name_en' => sanitize_text_field( $_POST['name_en'][ $id ] ),
				), array( 'id' => $id ) );
			}
		} elseif ( isset( $_POST['tk_del_brand'] ) ) {
			$id = (int) $_POST['tk_del_brand'];
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}tk_brand_series WHERE brand_id=%d", $id ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}tk_stock WHERE brand_id=%d", $id ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}tk_coefficients WHERE brand_id=%d", $id ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}tk_brands WHERE id=%d", $id ) );
		} elseif ( isset( $_POST['tk_toggle_brand'] ) ) {
			$id = (int) $_POST['tk_toggle_brand'];
			$wpdb->query( $wpdb->prepare( "UPDATE {$p}tk_brands SET active = 1-active WHERE id=%d", $id ) );
		}
		tk_goto( $_SERVER['REQUEST_URI'] ); exit;
	}
	$brands = $wpdb->get_results( "SELECT * FROM {$p}tk_brands ORDER BY name_fa" );
	?>
	<div class="wrap" dir="rtl">
	<h1>برندها (<?php echo count( $brands ); ?>)</h1>
	<form method="post">
	<?php wp_nonce_field( 'tk_act', 'tk_nonce' ); ?>
	<table class="widefat striped" style="max-width:950px">
	<tr><th>نام فارسی</th><th>نام انگلیسی</th><th>وضعیت</th><th>عملیات</th></tr>
	<?php foreach ( $brands as $b ) : ?>
	<tr>
		<td><input type="text" name="name_fa[<?php echo $b->id; ?>]" value="<?php echo esc_attr( $b->name_fa ); ?>"></td>
		<td><input type="text" name="name_en[<?php echo $b->id; ?>]" value="<?php echo esc_attr( $b->name_en ); ?>" dir="ltr"></td>
		<td><?php echo $b->active ? 'فعال' : 'غیرفعال'; ?></td>
		<td style="white-space:nowrap">
			<button name="tk_update_brand" value="<?php echo $b->id; ?>" class="button button-small">ذخیره نام</button>
			<button name="tk_toggle_brand" value="<?php echo $b->id; ?>" class="button button-small"><?php echo $b->active ? 'غیرفعال' : 'فعال'; ?></button>
			<button name="tk_del_brand" value="<?php echo $b->id; ?>" class="button button-small" onclick="return confirm('برند و همه تنظیمات/موجودی آن حذف شود؟');">حذف</button>
		</td>
	</tr>
	<?php endforeach; ?>
	</table>
	<h2>افزودن برند</h2>
	<input type="text" name="name_fa_new" placeholder="نام فارسی" required>
	<input type="text" name="name_en_new" placeholder="نام انگلیسی" required>
	<button name="tk_add_brand" value="1" class="button button-primary">افزودن</button>
	</form></div>
	<?php
}

/* ================= دسته‌ها و بخش‌ها (کشویی + فرمول کل دسته) ================= */
function tk_page_sections() {
	global $wpdb; $p = $wpdb->prefix;
	if ( $_SERVER['REQUEST_METHOD'] === 'POST' && tk_ok() ) {
		if ( isset( $_POST['tk_add_section'] ) ) {
			$wpdb->query( $wpdb->prepare(
				"INSERT IGNORE INTO {$p}tk_sections (category_id,slug,name_fa,formula_key,size_min,size_max,length_std,sort,active) VALUES (%d,%s,%s,%s,%d,%d,%s,999,1)",
				(int) $_POST['category_id'], sanitize_text_field( $_POST['slug'] ), sanitize_text_field( $_POST['name_fa'] ),
				sanitize_text_field( $_POST['formula_key'] ), (int) $_POST['size_min'], (int) $_POST['size_max'], sanitize_text_field( $_POST['length_std'] ) ) );
		} elseif ( isset( $_POST['tk_save_formulas'] ) ) {
			foreach ( (array) $_POST['cat_formula'] as $cid => $fk ) {
				$wpdb->update( "{$p}tk_categories", array( 'formula_key' => sanitize_text_field( $fk ) ), array( 'id' => (int) $cid ) );
			}
			foreach ( (array) $_POST['sec_formula'] as $sid => $fk ) {
				$wpdb->update( "{$p}tk_sections", array( 'formula_key' => sanitize_text_field( $fk ) ), array( 'id' => (int) $sid ) );
			}
			foreach ( (array) $_POST['sec_min'] as $sid => $mv ) {
				$wpdb->update( "{$p}tk_sections", array( 'min_charge' => (int) $mv ), array( 'id' => (int) $sid ) );
			}
		}
		tk_goto( $_SERVER['REQUEST_URI'] ); exit;
	}
	tk_assets();
	$cats = $wpdb->get_results( "SELECT * FROM {$p}tk_categories ORDER BY sort" );
	$secs = $wpdb->get_results( "SELECT * FROM {$p}tk_sections ORDER BY slug" );
	$formulas = $wpdb->get_results( "SELECT * FROM {$p}tk_formulas ORDER BY title_fa" );
	?>
	<div class="wrap" dir="rtl">
	<h1>دسته‌ها و بخش‌ها — فرمول‌های پیشفرض</h1>
	<form method="post">
	<?php wp_nonce_field( 'tk_act', 'tk_nonce' ); ?>
	<?php foreach ( $cats as $c ) :
		$rows = array();
		foreach ( $secs as $s ) { if ( (int) $s->category_id === (int) $c->id ) { $rows[] = $s; } }
		if ( ! $rows ) { continue; }
		$over = 0;
		foreach ( $rows as $s ) { if ( '' !== $s->formula_key ) { $over++; } }
				echo '<div class="tk-acc"><div class="tk-acc-head"><span class="arr">‹</span><strong>' . esc_html( $c->name_en ) . '</strong><small style="color:#888">' . esc_html( $c->name_fa ) . '</small></div><div class="tk-acc-body">';
		echo '<div class="tk-row"><strong>فرمول کل دسته:</strong> <select name="cat_formula[' . $c->id . ']"><option value="">— بدون فرمول —</option>';
		foreach ( $formulas as $f ) { echo '<option value="' . esc_attr( $f->fkey ) . '" ' . selected( $c->formula_key, $f->fkey, false ) . '>' . esc_html( $f->title_fa . ' (' . $f->fkey . ')' ) . '</option>'; }
		echo '</select></div>';
				echo '<table class="widefat striped"><tr><th>بخش</th><th>فرمول (خالی = پیروی از دسته)</th><th>قفل قیمت (کف سایز)</th></tr>';
		foreach ( $rows as $s ) {
			echo '<tr><td><strong>' . esc_html( $s->slug ) . '</strong> — ' . esc_html( $s->name_fa ) . '</td><td><select name="sec_formula[' . $s->id . ']"><option value="">پیروی از دسته</option>';
			foreach ( $formulas as $f ) { echo '<option value="' . esc_attr( $f->fkey ) . '" ' . selected( $s->formula_key, $f->fkey, false ) . '>' . esc_html( $f->title_fa . ' (' . $f->fkey . ')' ) . '</option>'; }
			echo '</select></td><td><input type="number" name="sec_min[' . $s->id . ']" value="' . esc_attr( $s->min_charge ) . '" style="width:90px" placeholder="0 = بدون قفل"></td></tr>';
		}
		echo '</table></div></div>';
	endforeach; ?>
	<p class="tk-sticky-save"><button name="tk_save_formulas" value="1" class="button button-primary">ذخیره فرمول‌ها</button></p>
	</form>
	<h2>افزودن بخش جدید</h2>
	<form method="post"><?php wp_nonce_field( 'tk_act', 'tk_nonce' ); ?>
		<select name="category_id"><?php foreach ( $cats as $c ) : ?><option value="<?php echo $c->id; ?>"><?php echo esc_html( $c->name_en ); ?></option><?php endforeach; ?></select>
		<input type="text" name="slug" placeholder="slug مثل ZX" required style="direction:ltr">
		<input type="text" name="name_fa" placeholder="نام فارسی" required>
		<select name="formula_key"><option value="">پیروی از دسته</option><?php foreach ( $formulas as $f ) : ?><option value="<?php echo esc_attr( $f->fkey ); ?>"><?php echo esc_html( $f->fkey . ' — ' . $f->title_fa ); ?></option><?php endforeach; ?></select>
		<input type="number" name="size_min" value="16" style="width:90px"> تا <input type="number" name="size_max" value="3000" style="width:90px">
		<input type="text" name="length_std" value="Li" placeholder="Li/Lw/La" style="width:70px;direction:ltr">
		<button name="tk_add_section" value="1" class="button button-primary">افزودن بخش</button>
	</form></div>
	<?php
}

/* ================= تنظیمات ================= */
function tk_page_settings() {
	if ( $_SERVER['REQUEST_METHOD'] === 'POST' && tk_ok() && isset( $_POST['tk_save_settings'] ) ) {
		update_option( 'tk_settings', array(
			'phone'     => sanitize_text_field( $_POST['phone'] ),
			'currency'  => sanitize_text_field( $_POST['currency'] ),
			'tpl_title' => sanitize_text_field( $_POST['tpl_title'] ),
			'tpl_desc'  => wp_kses_post( $_POST['tpl_desc'] ),
		) );
		echo '<div class="notice notice-success"><p>تنظیمات ذخیره شد ✔</p></div>';
	}
	$set = wp_parse_args( get_option( 'tk_settings', array() ), array(
		'phone' => '021-00000000', 'currency' => 'ریال',
		'tpl_title' => 'تسمه {sku} برند {brand}',
		'tpl_desc'  => 'تسمه صنعتی {sku} برند {brand} — قیمت به‌روز و ضمانت اصالت. برای خرید عمده تماس بگیرید.',
	) );
	?>
	<div class="wrap" dir="rtl">
	<h1>تنظیمات تسمه کارون</h1>
	<form method="post"><?php wp_nonce_field( 'tk_act', 'tk_nonce' ); ?>
	<table class="form-table">
	<tr><th>شماره تلفن «برای خرید تماس بگیرید»</th><td><input type="text" name="phone" value="<?php echo esc_attr( $set['phone'] ); ?>" style="direction:ltr"></td></tr>
	<tr><th>واحد پول</th><td><input type="text" name="currency" value="<?php echo esc_attr( $set['currency'] ); ?>"></td></tr>
	<tr><th>قالب عنوان محصول</th><td><input type="text" name="tpl_title" value="<?php echo esc_attr( $set['tpl_title'] ); ?>" style="width:450px" dir="ltr"></td></tr>
	<tr><th>قالب توضیح محصول</th><td><textarea name="tpl_desc" rows="3" style="width:450px" dir="ltr"><?php echo esc_textarea( $set['tpl_desc'] ); ?></textarea><p class="description">جای‌نماها: {sku} {brand} {section} {size}</p></td></tr>
	</table>
	<button name="tk_save_settings" value="1" class="button button-primary">ذخیره تنظیمات</button>
	</form></div>
	<?php
}