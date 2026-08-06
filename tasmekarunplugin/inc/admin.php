<?php
defined( 'ABSPATH' ) || exit;
require_once TK_PATH . 'inc/import.php';
require_once TK_PATH . 'inc/admin2.php';

add_action( 'admin_menu', 'tk_admin_menu' );
function tk_admin_menu() {
	add_menu_page( 'تسمه کارون', 'تسمه کارون', 'manage_options', 'tasmekarun', 'tk_page_folders', 'dashicons-groups', 56 );
	add_submenu_page( 'tasmekarun', 'پوشه‌های برند', 'پوشه‌های برند', 'manage_options', 'tasmekarun', 'tk_page_folders' );
	add_submenu_page( 'tasmekarun', 'پریست‌های ضریب', 'پریست‌های ضریب', 'manage_options', 'tk-presets', 'tk_page_presets' );
	add_submenu_page( 'tasmekarun', 'فرمول‌ها', 'فرمول‌ها', 'manage_options', 'tk-formulas', 'tk_page_formulas' );
	add_submenu_page( 'tasmekarun', 'تست قیمت', 'تست قیمت', 'manage_options', 'tk-test', 'tk_page_test' );
	add_submenu_page( 'tasmekarun', 'موجودی و ایمپورت', 'موجودی و ایمپورت', 'manage_options', 'tk-stock', 'tk_page_stock' );
	add_submenu_page( 'tasmekarun', 'برندها', 'برندها', 'manage_options', 'tk-brands', 'tk_page_brands' );
	add_submenu_page( 'tasmekarun', 'دسته‌ها و بخش‌ها', 'دسته‌ها و بخش‌ها', 'manage_options', 'tk-sections', 'tk_page_sections' );
	add_submenu_page( 'tasmekarun', 'تنظیمات', 'تنظیمات', 'manage_options', 'tk-settings', 'tk_page_settings' );
}

function tk_ok() {
	return isset( $_POST['tk_nonce'] ) && wp_verify_nonce( $_POST['tk_nonce'], 'tk_act' ) && current_user_can( 'manage_options' );
}

function tk_goto( $url ) {
	echo '<meta charset="utf-8"><script>location.replace(' . wp_json_encode( $url ) . ')</script>';
	exit;
}

function tk_assets() { ?>
	<style>
	.tk-folders{display:flex;flex-wrap:wrap;gap:14px;margin-top:14px}
	.tk-folder{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:12px 14px;width:220px;text-align:center}
	.tk-folder input[type=text]{width:100%;margin-bottom:4px}
	.tk-sticky-save{position:fixed;bottom:18px;left:18px;z-index:99999;margin:0}
	.tk-sticky-save .button{box-shadow:0 4px 14px rgba(0,0,0,.3)}
	.tk-folder .ico{font-size:38px;line-height:1.2}
	.tk-series{border:1px solid #e0e0e0;border-radius:6px;padding:10px 12px;margin:8px 0;background:#fff}
	.tk-series-body{margin-top:10px;border-top:1px dashed #ddd;padding-top:10px}
	.tk-row{margin:6px 0;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
	.tk-sw{position:relative;display:inline-block;width:44px;height:22px;flex:0 0 auto}
	.tk-sw input{opacity:0;width:0;height:0;position:absolute}
	.tk-sw span{position:absolute;inset:0;background:#ccc;border-radius:22px;transition:.2s;cursor:pointer}
	.tk-sw span:before{content:"";position:absolute;width:16px;height:16px;border-radius:50%;background:#fff;top:3px;right:3px;transition:.2s}
	.tk-sw input:checked+span{background:#2271b1}
	.tk-sw input:checked+span:before{right:25px}
	.tk-acc{border:1px solid #dcdcde;border-radius:6px;margin:10px 0;background:#fff}
	.tk-acc-head{display:flex;align-items:center;gap:10px;padding:10px 14px;cursor:pointer;font-weight:600}
	.tk-acc-head .arr{display:inline-block;transition:.2s}
	.tk-acc.open .arr{transform:rotate(90deg)}
	.tk-acc-body{display:none;padding:8px 14px 14px;border-top:1px solid #eee}
	.tk-acc.open .tk-acc-body{display:block}
	</style>
	<script>
	document.addEventListener('click', function(e){
		var h = e.target.closest('.tk-acc-head');
		if ( h && ! e.target.closest('input,select,button,a,label') ) { h.closest('.tk-acc').classList.toggle('open'); }
	});
	</script>
	<?php
}

/* ================= پوشه‌های برند ================= */
function tk_page_folders() {
	global $wpdb; $p = $wpdb->prefix;

	if ( $_SERVER['REQUEST_METHOD'] === 'POST' && tk_ok() ) {
		$is_range = isset( $_POST['tk_add_range'] ) || isset( $_POST['tk_del_range'] ) || isset( $_POST['tk_reset_ranges'] );

		if ( isset( $_POST['tk_save_folder'] ) || $is_range ) {
			$bid = (int) $_POST['brand_id'];
			$wpdb->update( "{$p}tk_brands", array( 'default_preset_id' => (int) $_POST['preset_id'] ), array( 'id' => $bid ) );
			$series = isset( $_POST['series'] ) ? (array) $_POST['series'] : array();
			$ids = array_map( 'intval', array_keys( $series ) );
			if ( $ids ) {
				$wpdb->query( "DELETE FROM {$p}tk_brand_series WHERE brand_id=$bid AND section_id NOT IN (" . implode( ',', $ids ) . ')' );
			} else {
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}tk_brand_series WHERE brand_id=%d", $bid ) );
			}
			foreach ( $series as $sid => $f ) {
				$wpdb->query( $wpdb->prepare(
					"INSERT INTO {$p}tk_brand_series (brand_id,section_id,coef_on,coef_value,formula_on,formula_key,image_id,desc_on,desc_text) VALUES (%d,%d,%d,%s,%d,%s,%d,%d,%s)
					 ON DUPLICATE KEY UPDATE coef_on=VALUES(coef_on), coef_value=VALUES(coef_value), formula_on=VALUES(formula_on), formula_key=VALUES(formula_key), image_id=VALUES(image_id), desc_on=VALUES(desc_on), desc_text=VALUES(desc_text)",
					$bid, (int) $sid,
					! empty( $f['coef_on'] ) ? 1 : 0,
					( isset( $f['coef_value'] ) && '' !== $f['coef_value'] ) ? floatval( $f['coef_value'] ) : null,
					! empty( $f['formula_on'] ) ? 1 : 0,
					! empty( $f['formula_key'] ) ? sanitize_text_field( $f['formula_key'] ) : null,
					isset( $f['image_id'] ) ? (int) $f['image_id'] : 0,
					! empty( $f['desc_on'] ) ? 1 : 0,
					isset( $f['desc_text'] ) ? wp_kses_post( $f['desc_text'] ) : null ) );
			}
		}

		if ( $is_range ) {
			$bid = (int) $_POST['brand_id'];
			$msg = 'saved';
			if ( isset( $_POST['tk_add_range'] ) ) {
				$sid = (int) $_POST['tk_add_range'];
				$sec = TK_Engine::section_by_id( $sid );
				$f   = isset( $_POST['rng'][ $sid ] ) ? (array) $_POST['rng'][ $sid ] : array();
				$mn = $mx = $st = null;
				if ( trim( (string) ( isset( $f['in_single'] ) ? $f['in_single'] : '' ) ) !== '' ) {
					$mn = $mx = (int) $f['in_single']; $st = 1;
				} elseif ( trim( (string) ( isset( $f['in_min'] ) ? $f['in_min'] : '' ) ) !== '' && trim( (string) ( isset( $f['in_max'] ) ? $f['in_max'] : '' ) ) !== '' ) {
					$mn = (int) $f['in_min']; $mx = (int) $f['in_max'];
					$st = isset( $f['in_step'] ) && (int) $f['in_step'] > 0 ? (int) $f['in_step'] : 1;
				}
				if ( null !== $mn && $sec && $mn <= $mx ) {
					$cur = array_flip( TK_Engine::size_set( $sec, $bid ) );
					$all = true;
					for ( $v = $mn; $v <= $mx; $v += $st ) { if ( ! isset( $cur[ $v ] ) ) { $all = false; break; } }
					if ( $all ) { $msg = 'dup'; }
					else { $wpdb->query( $wpdb->prepare( "INSERT INTO {$p}tk_series_ranges (brand_id,section_id,mode,min,max,step) VALUES (%d,%d,'in',%d,%d,%d)", $bid, $sid, $mn, $mx, $st ) ); }
				}
			} elseif ( isset( $_POST['tk_del_range'] ) ) {
				$sid = (int) $_POST['tk_del_range'];
				$sec = TK_Engine::section_by_id( $sid );
				$f   = isset( $_POST['rng'][ $sid ] ) ? (array) $_POST['rng'][ $sid ] : array();
				$mn = $mx = $st = null;
				if ( trim( (string) ( isset( $f['out_single'] ) ? $f['out_single'] : '' ) ) !== '' ) {
					$mn = $mx = (int) $f['out_single']; $st = 1;
				} elseif ( trim( (string) ( isset( $f['out_min'] ) ? $f['out_min'] : '' ) ) !== '' && trim( (string) ( isset( $f['out_max'] ) ? $f['out_max'] : '' ) ) !== '' ) {
					$mn = (int) $f['out_min']; $mx = (int) $f['out_max'];
					$st = isset( $f['out_step'] ) && (int) $f['out_step'] > 0 ? (int) $f['out_step'] : 1;
				}
				if ( null !== $mn && $sec && $mn <= $mx ) {
					$cur = array_flip( TK_Engine::size_set( $sec, $bid ) );
					$any = false;
					for ( $v = $mn; $v <= $mx; $v += $st ) { if ( isset( $cur[ $v ] ) ) { $any = true; break; } }
					if ( ! $any ) { $msg = 'noop'; }
					else { $wpdb->query( $wpdb->prepare( "INSERT INTO {$p}tk_series_ranges (brand_id,section_id,mode,min,max,step) VALUES (%d,%d,'out',%d,%d,%d)", $bid, $sid, $mn, $mx, $st ) ); }
				}
			} elseif ( isset( $_POST['tk_reset_ranges'] ) ) {
				$sid = (int) $_POST['tk_reset_ranges'];
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}tk_series_ranges WHERE brand_id=%d AND section_id=%d", $bid, $sid ) );
				$msg = 'reset';
			}
			tk_goto( add_query_arg( array( 'page' => 'tasmekarun', 'brand' => $bid, 'tkmsg' => $msg ), admin_url( 'admin.php' ) ) ); exit;
		}

		if ( isset( $_POST['tk_save_folder'] ) ) {
			tk_goto( add_query_arg( array( 'page' => 'tasmekarun', 'brand' => (int) $_POST['brand_id'], 'tkmsg' => 'saved' ), admin_url( 'admin.php' ) ) ); exit;
		}
		if ( isset( $_POST['tk_add_brand'] ) ) {
			$fa = sanitize_text_field( $_POST['name_fa_new'] ); $en = sanitize_text_field( $_POST['name_en_new'] );
			$slug = sanitize_title( $en ) ? sanitize_title( $en ) : sanitize_title( $fa );
			if ( ! $slug ) { $slug = 'brand-' . time(); }
			$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$p}tk_brands (slug,name_fa,name_en,sort,active) VALUES (%s,%s,%s,999,1)", $slug, $fa, $en ) );
			tk_goto( add_query_arg( array( 'page' => 'tasmekarun' ), admin_url( 'admin.php' ) ) ); exit;
		}
		if ( isset( $_POST['tk_update_brand'] ) ) {
			$id = (int) $_POST['tk_update_brand'];
			if ( isset( $_POST['name_fa'][ $id ], $_POST['name_en'][ $id ] ) ) {
				$wpdb->update( "{$p}tk_brands", array(
					'name_fa' => sanitize_text_field( $_POST['name_fa'][ $id ] ),
					'name_en' => sanitize_text_field( $_POST['name_en'][ $id ] ),
				), array( 'id' => $id ) );
			}
			tk_goto( add_query_arg( array( 'page' => 'tasmekarun' ), admin_url( 'admin.php' ) ) ); exit;
		}
		if ( isset( $_POST['tk_del_brand'] ) ) {
			$id = (int) $_POST['tk_del_brand'];
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}tk_brand_series WHERE brand_id=%d", $id ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}tk_stock WHERE brand_id=%d", $id ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}tk_coefficients WHERE brand_id=%d", $id ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}tk_brands WHERE id=%d", $id ) );
			tk_goto( add_query_arg( array( 'page' => 'tasmekarun' ), admin_url( 'admin.php' ) ) ); exit;
		}
	}

	$bid = isset( $_GET['brand'] ) ? (int) $_GET['brand'] : 0;
	tk_assets();

	$map_msg = array(
		'saved' => array( 'ذخیره شد ✔', 'success' ),
		'dup'   => array( 'این بازه/سایز قبلاً در لیست ساخت پوشش داده شده بود؛ تکراری اضافه نشد.', 'warning' ),
		'noop'  => array( 'این سایز/بازه در لیست ساخت نیست؛ چیزی کم نشد.', 'warning' ),
		'reset' => array( 'بازه‌ها ریست شد؛ حالا کل بازه بخش فعال است.', 'success' ),
	);
	if ( isset( $_GET['tkmsg'], $map_msg[ $_GET['tkmsg'] ] ) ) {
		$m = $map_msg[ $_GET['tkmsg'] ];
		echo '<div class="notice notice-' . $m[1] . '"><p>' . esc_html( $m[0] ) . '</p></div>';
	}

	if ( ! $bid ) {
		$q = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		echo '<div class="wrap" dir="rtl"><h1>پوشه‌های برند</h1>';
		echo '<input type="search" id="tk-brand-search" style="margin:10px 0;max-width:320px" placeholder="جستجوی زنده برند…">';
		echo '<form method="post" style="display:flex;gap:8px;flex-wrap:wrap;margin:12px 0">' . wp_nonce_field( 'tk_act', 'tk_nonce', true ) . '
		<input type="text" name="name_fa_new" placeholder="نام فارسی پوشه جدید" required>
		<input type="text" name="name_en_new" placeholder="نام انگلیسی" dir="ltr" required>
		<button name="tk_add_brand" value="1" class="button button-primary">افزودن پوشه</button></form>';
		if ( '' !== $q ) {
			$like = '%' . $wpdb->esc_like( $q ) . '%';
			$brands = $wpdb->get_results( $wpdb->prepare( "SELECT b.*, (SELECT COUNT(*) FROM {$p}tk_brand_series x WHERE x.brand_id=b.id) AS cnt FROM {$p}tk_brands b WHERE b.name_fa LIKE %s OR b.name_en LIKE %s ORDER BY b.name_fa", $like, $like ) );
		} else {
			$brands = $wpdb->get_results( "SELECT b.*, (SELECT COUNT(*) FROM {$p}tk_brand_series x WHERE x.brand_id=b.id) AS cnt FROM {$p}tk_brands b ORDER BY b.name_fa" );
		}
		echo '<div class="tk-folders">';
		foreach ( $brands as $b ) {
			echo '<div class="tk-folder"><form method="post" style="margin:0">' . wp_nonce_field( 'tk_act', 'tk_nonce', true ) . '
			<div class="ico">📁</div>
			<input type="text" name="name_fa[' . $b->id . ']" value="' . esc_attr( $b->name_fa ) . '">
			<input type="text" name="name_en[' . $b->id . ']" value="' . esc_attr( $b->name_en ) . '" dir="ltr">
			<small>' . (int) $b->cnt . ' سری فعال</small><br>
			<a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'tasmekarun', 'brand' => $b->id ), admin_url( 'admin.php' ) ) ) . '">باز کردن</a>
			<button name="tk_update_brand" value="' . $b->id . '" class="button button-small">ذخیره نام</button>
			<button name="tk_del_brand" value="' . $b->id . '" class="button button-small" onclick="return confirm(\'پوشه و همه تنظیمات و موجودی آن حذف شود؟\');">حذف</button>
			</form></div>';
		}
		echo '</div></div>';
		echo <<<HTML
<script>
(function(){
	var bs = document.getElementById('tk-brand-search');
	if ( ! bs ) return;
	bs.addEventListener('input', function(){
		var q = bs.value.trim().toLowerCase();
		document.querySelectorAll('.tk-folder').forEach(function(f){
			var fa = f.querySelector('input[name^="name_fa"]'), en = f.querySelector('input[name^="name_en"]');
			var t = ((fa ? fa.value : '') + ' ' + (en ? en.value : '')).toLowerCase();
			f.style.display = ( ! q || t.indexOf(q) !== -1 ) ? '' : 'none';
		});
	});
})();
</script>
HTML;
		return;
	}

	$brand = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$p}tk_brands WHERE id=%d", $bid ) );
	if ( ! $brand ) { return; }
	wp_enqueue_media();
	$presets  = $wpdb->get_results( "SELECT * FROM {$p}tk_presets ORDER BY title_fa" );
	$formulas = $wpdb->get_results( "SELECT * FROM {$p}tk_formulas ORDER BY title_fa" );
	$cats     = $wpdb->get_results( "SELECT * FROM {$p}tk_categories ORDER BY sort" );
	$secs_all = $wpdb->get_results( "SELECT * FROM {$p}tk_sections ORDER BY slug" );
	$rows     = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$p}tk_brand_series WHERE brand_id=%d", $bid ) );
	$map = array();
	foreach ( $rows as $r ) { $map[ (int) $r->section_id ] = $r; }
	?>
	<div class="wrap" dir="rtl">
	<h1>پوشه برند: <?php echo esc_html( $brand->name_fa ); ?> <a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=tasmekarun' ) ); ?>">بازگشت</a></h1>
	<form method="post">
		<?php wp_nonce_field( 'tk_act', 'tk_nonce' ); ?>
		<input type="hidden" name="brand_id" value="<?php echo $bid; ?>">
		<div class="tk-row" style="margin-bottom:14px">
			<strong>ضریب پیشفرض این برند:</strong>
			<select name="preset_id">
				<option value="0">— بدون پریست —</option>
				<?php foreach ( $presets as $pr ) : ?><option value="<?php echo $pr->id; ?>" <?php selected( (int) $brand->default_preset_id, (int) $pr->id ); ?>><?php echo esc_html( $pr->title_fa ); ?></option><?php endforeach; ?>
			</select>
		</div>
	<?php
	foreach ( $cats as $c ) {
		$secs = array();
		foreach ( $secs_all as $s ) { if ( (int) $s->category_id === (int) $c->id ) { $secs[] = $s; } }
		if ( ! $secs ) { continue; }
		$on_cnt = 0;
		foreach ( $secs as $s ) { if ( isset( $map[ (int) $s->id ] ) ) { $on_cnt++; } }
		$open = $on_cnt > 0;
		echo '<div class="tk-acc' . ( $open ? ' open' : '' ) . '">';
		echo '<div class="tk-acc-head"><span class="arr">‹</span><strong>' . esc_html( $c->name_en ) . '</strong><small style="color:#888">' . esc_html( $c->name_fa ) . '</small><label style="margin-right:auto;font-weight:normal"><input type="checkbox" class="js-cat-on" data-cat="' . $c->id . '" ' . checked( $on_cnt === count( $secs ), true, false ) . '> کل این دسته</label></div>';
		echo '<div class="tk-acc-body">';
		foreach ( $secs as $s ) {
			$sid = (int) $s->id;
			$r = isset( $map[ $sid ] ) ? $map[ $sid ] : null;
			$on = null !== $r;
			?>
			<div class="tk-series">
				<label style="cursor:pointer"><input type="checkbox" class="js-series-on" name="series[<?php echo $sid; ?>][on]" value="1" <?php checked( $on ); ?>> <strong><?php echo esc_html( $s->slug ); ?></strong> — <?php echo esc_html( $s->name_fa ); ?></label>
				<div class="tk-series-body" style="<?php echo $on ? '' : 'display:none'; ?>">
					<div class="tk-row">
						<label class="tk-sw"><input type="checkbox" class="js-flip" data-target="tk-cv-<?php echo $sid; ?>" name="series[<?php echo $sid; ?>][coef_on]" value="1" <?php checked( $r && $r->coef_on ); ?>><span></span></label>
						ضریب دستی
						<span id="tk-cv-<?php echo $sid; ?>" style="<?php echo ( $r && $r->coef_on ) ? '' : 'display:none'; ?>">
							<input type="number" step="any" name="series[<?php echo $sid; ?>][coef_value]" value="<?php echo esc_attr( $r ? $r->coef_value : '' ); ?>" style="width:140px">
						</span>
						<small>(خاموش = ضریب از پریست پیشفرض برند)</small>
					</div>
					<div class="tk-row">
						<label class="tk-sw"><input type="checkbox" class="js-flip" data-target="tk-fk-<?php echo $sid; ?>" name="series[<?php echo $sid; ?>][formula_on]" value="1" <?php checked( $r && $r->formula_on ); ?>><span></span></label>
						فرمول اختصاصی
						<span id="tk-fk-<?php echo $sid; ?>" style="<?php echo ( $r && $r->formula_on ) ? '' : 'display:none'; ?>">
							<select name="series[<?php echo $sid; ?>][formula_key]">
								<?php foreach ( $formulas as $fo ) : ?><option value="<?php echo esc_attr( $fo->fkey ); ?>" <?php selected( $r ? $r->formula_key : '', $fo->fkey ); ?>><?php echo esc_html( $fo->title_fa . ' (' . $fo->fkey . ')' ); ?></option><?php endforeach; ?>
							</select>
						</span>
					</div>
					<div class="tk-row">
						عکس مشترک سری:
						<button type="button" class="button tk-up-btn" data-hid="tk-img-<?php echo $sid; ?>" data-img="tk-im-<?php echo $sid; ?>">انتخاب عکس</button>
						<input type="hidden" id="tk-img-<?php echo $sid; ?>" name="series[<?php echo $sid; ?>][image_id]" value="<?php echo esc_attr( $r ? $r->image_id : 0 ); ?>">
						<img id="tk-im-<?php echo $sid; ?>" src="<?php echo esc_url( $r && $r->image_id ? wp_get_attachment_image_url( (int) $r->image_id, 'thumbnail' ) : '' ); ?>" style="<?php echo ( $r && $r->image_id ) ? '' : 'display:none'; ?>max-height:40px;border-radius:4px">
					</div>
					<div class="tk-row">
						<label class="tk-sw"><input type="checkbox" class="js-flip" data-target="tk-dt-<?php echo $sid; ?>" name="series[<?php echo $sid; ?>][desc_on]" value="1" <?php checked( $r && $r->desc_on ); ?>><span></span></label>
						دسکریپشن دستی
						<span id="tk-dt-<?php echo $sid; ?>" style="<?php echo ( $r && $r->desc_on ) ? '' : 'display:none'; ?>;width:100%">
							<textarea name="series[<?php echo $sid; ?>][desc_text]" rows="3" style="width:100%"><?php echo esc_textarea( $r ? $r->desc_text : '' ); ?></textarea>
						</span>
					</div>
					<div class="tk-row" style="border-top:1px dashed #ddd;padding-top:8px">
						<strong>بازه‌های موجود در سایت:</strong>
						<?php
						if ( ! TK_Engine::ranges( $bid, $sid ) ) {
							echo '<small>کل بازه بخش (' . (int) $s->size_min . ' تا ' . (int) $s->size_max . ')</small>';
						} else {
							$chunks = TK_Engine::compress_set( TK_Engine::size_set( $s, $bid ) );
							$fmt = function ( $ch ) {
								if ( $ch[0] === $ch[1] ) { return (string) $ch[0]; }
								return $ch[0] . '–' . $ch[1] . ( $ch[2] > 1 ? '/' . $ch[2] : '' );
							};
							$csstyle = 'margin-left:6px;background:#f0f0f1;padding:2px 8px;border-radius:4px';
							$first = array_slice( $chunks, 0, 12 );
							$rest  = array_slice( $chunks, 12 );
							foreach ( $first as $ch ) { echo '<code style="' . $csstyle . '">' . $fmt( $ch ) . '</code>'; }
							foreach ( TK_Engine::ranges( $bid, $sid ) as $or ) {
								if ( 'out' === $or->mode ) {
									echo '<code style="margin-left:6px;background:#fcf0f1;color:#b32d2e;padding:2px 8px;border-radius:4px">حذف: ' . $or->min . ( $or->max !== $or->min ? '–' . $or->max : '' ) . ( $or->step > 1 ? '/' . $or->step : '' ) . '</code>';
								}
							}
						}
						?>
					</div>
					<div class="tk-row">
						<button name="tk_add_range" value="<?php echo $sid; ?>" class="button button-small">اضافه کردن</button>
						ابتدای بازه <input type="number" name="rng[<?php echo $sid; ?>][in_min]" style="width:90px">
						انتهای بازه <input type="number" name="rng[<?php echo $sid; ?>][in_max]" style="width:90px">
						ضریب بازه (گام) <input type="number" name="rng[<?php echo $sid; ?>][in_step]" style="width:80px" placeholder="1">
						اضافه کردن تکی <input type="number" name="rng[<?php echo $sid; ?>][in_single]" style="width:90px">
					</div>
					<div class="tk-row">
						<button name="tk_del_range" value="<?php echo $sid; ?>" class="button button-small">کم کردن</button>
						ابتدای بازه <input type="number" name="rng[<?php echo $sid; ?>][out_min]" style="width:90px">
						انتهای بازه <input type="number" name="rng[<?php echo $sid; ?>][out_max]" style="width:90px">
						ضریب بازه (گام) <input type="number" name="rng[<?php echo $sid; ?>][out_step]" style="width:80px" placeholder="1">
						کسر کردن تکی <input type="number" name="rng[<?php echo $sid; ?>][out_single]" style="width:90px">
					</div>
					<div class="tk-row">
						<button name="tk_reset_ranges" value="<?php echo $sid; ?>" class="button button-small" onclick="return confirm('همه بازه‌های این سری حذف شود؟');">ریست کردن بازه‌ها</button>
					</div>
				</div>
			</div>
			<?php
		}
		echo '</div></div>';
	}
	?>
		<p><button name="tk_save_folder" value="1" class="button button-primary">ذخیره پوشه</button></p>
	</form>
	<script>
	document.addEventListener('change', function(e){
		var t = e.target;
		if ( t.classList.contains('js-cat-on') ) {
			t.closest('.tk-acc').querySelectorAll('.js-series-on').forEach(function(s){ s.checked = t.checked; s.dispatchEvent(new Event('change')); });
		} else if ( t.classList.contains('js-series-on') ) {
			t.closest('.tk-series').querySelector('.tk-series-body').style.display = t.checked ? 'block' : 'none';
		} else if ( t.classList.contains('js-flip') ) {
			var el = document.getElementById(t.dataset.target);
			if ( el ) el.style.display = t.checked ? '' : 'none';
		}
	});
	document.addEventListener('click', function(e){
		var mb = e.target.closest('.tk-more-btn');
		if ( mb ) {
			var l = mb.nextElementSibling;
			if ( l ) { l.style.display = ( l.style.display === 'none' ? 'inline' : 'none' ); }
			return;
		}
		var b = e.target.closest('.tk-up-btn');
		if ( ! b ) return;
		e.preventDefault();
		var hid = document.getElementById(b.dataset.hid), img = document.getElementById(b.dataset.img);
		var m = wp.media({ multiple: false });
		m.on('select', function(){
			var a = m.state().get('selection').first().toJSON();
			hid.value = a.id; img.src = a.url; img.style.display = 'inline-block';
		});
		m.open();
	});
	var bs = document.getElementById('tk-brand-search');
	if ( bs ) {
		bs.addEventListener('input', function(){
			var q = bs.value.trim().toLowerCase();
			document.querySelectorAll('.tk-folder').forEach(function(f){
				var fa = f.querySelector('input[name^="name_fa"]'), en = f.querySelector('input[name^="name_en"]');
				var t = ((fa ? fa.value : '') + ' ' + (en ? en.value : '')).toLowerCase();
				f.style.display = ( ! q || t.indexOf(q) !== -1 ) ? '' : 'none';
			});
		});
	}
	</script>
	</div>
	<?php
}
/* ================= پریست‌های ضریب ================= */
function tk_page_presets() {
	global $wpdb; $p = $wpdb->prefix;

	if ( $_SERVER['REQUEST_METHOD'] === 'POST' && tk_ok() ) {
		if ( isset( $_POST['tk_add_preset'] ) ) {
			$wpdb->query( $wpdb->prepare( "INSERT INTO {$p}tk_presets (title_fa) VALUES (%s)", sanitize_text_field( $_POST['title_fa'] ) ) );
		} elseif ( isset( $_POST['tk_del_preset'] ) ) {
			$id = (int) $_POST['preset_id'];
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}tk_presets WHERE id=%d", $id ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}tk_preset_coefs WHERE preset_id=%d", $id ) );
			$wpdb->query( $wpdb->prepare( "UPDATE {$p}tk_brands SET default_preset_id=0 WHERE default_preset_id=%d", $id ) );
		} elseif ( isset( $_POST['tk_save_preset_coefs'] ) ) {
			$id = (int) $_POST['preset_id'];
			foreach ( (array) $_POST['pcoef'] as $sid => $v ) {
				$sid = (int) $sid;
				if ( '' === trim( (string) $v ) ) {
					$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}tk_preset_coefs WHERE preset_id=%d AND section_id=%d", $id, $sid ) );
				} else {
					$wpdb->query( $wpdb->prepare( "INSERT INTO {$p}tk_preset_coefs (preset_id,section_id,coef) VALUES (%d,%d,%f) ON DUPLICATE KEY UPDATE coef=VALUES(coef)", $id, $sid, floatval( $v ) ) );
				}
			}
		}
		tk_goto( add_query_arg( array( 'page' => 'tk-presets' ), admin_url( 'admin.php' ) ) . ( isset( $_POST['edit'] ) ? '&edit=' . (int) $_POST['edit'] : '' ) ); exit;
	}

	tk_assets();
	$edit = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0;
	echo '<div class="wrap" dir="rtl"><h1>پریست‌های ضریب پیشفرض</h1>';

	if ( $edit ) {
		$pr = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$p}tk_presets WHERE id=%d", $edit ) );
		if ( $pr ) {
			$coefs = $wpdb->get_results( $wpdb->prepare( "SELECT section_id, coef FROM {$p}tk_preset_coefs WHERE preset_id=%d", $edit ) );
			$cmap = array();
			foreach ( $coefs as $c ) { $cmap[ (int) $c->section_id ] = $c->coef; }
			$cats = $wpdb->get_results( "SELECT * FROM {$p}tk_categories ORDER BY sort" );
			$secs = $wpdb->get_results( "SELECT * FROM {$p}tk_sections ORDER BY slug" );
			echo '<h2>ضریب‌های پریست: ' . esc_html( $pr->title_fa ) . '</h2><form method="post">' . wp_nonce_field( 'tk_act', 'tk_nonce', true ) . '<input type="hidden" name="preset_id" value="' . $edit . '"><input type="hidden" name="edit" value="' . $edit . '">';
			foreach ( $cats as $c ) {
				$rows = array();
				foreach ( $secs as $s ) { if ( (int) $s->category_id === (int) $c->id ) { $rows[] = $s; } }
				if ( ! $rows ) { continue; }
				$has = false;
				foreach ( $rows as $s ) { if ( isset( $cmap[ (int) $s->id ] ) ) { $has = true; } }
				echo '<div class="tk-acc' . ( $has ? ' open' : '' ) . '"><div class="tk-acc-head"><span class="arr">‹</span><strong>' . esc_html( $c->name_en ) . '</strong><small style="color:#888">' . esc_html( $c->name_fa ) . '</small></div><div class="tk-acc-body">';
				echo '<table class="widefat striped"><tr><th>بخش</th><th>ضریب (خالی = حذف)</th></tr>';
				foreach ( $rows as $s ) {
					echo '<tr><td><strong>' . esc_html( $s->slug ) . '</strong></td><td><input type="number" step="any" name="pcoef[' . $s->id . ']" value="' . esc_attr( isset( $cmap[ (int) $s->id ] ) ? $cmap[ (int) $s->id ] : '' ) . '" style="width:150px"></td></tr>';
				}
				echo '</table></div></div>';
			}
			echo '<p><button name="tk_save_preset_coefs" value="1" class="button button-primary">ذخیره ضریب‌ها</button></p></form>';
		}
	} else {
		$presets = $wpdb->get_results( "SELECT pr.*, (SELECT COUNT(*) FROM {$p}tk_preset_coefs pc WHERE pc.preset_id=pr.id) AS cc, (SELECT COUNT(*) FROM {$p}tk_brands b WHERE b.default_preset_id=pr.id) AS bc FROM {$p}tk_presets pr ORDER BY pr.title_fa" );
		echo '<table class="widefat striped" style="max-width:800px"><tr><th>نام پریست</th><th>تعداد ضریب</th><th>برندهای استفاده‌کننده</th><th>عملیات</th></tr>';
		foreach ( $presets as $pr ) {
			echo '<tr><td>' . esc_html( $pr->title_fa ) . '</td><td>' . (int) $pr->cc . '</td><td>' . (int) $pr->bc . '</td><td>
			<a class="button button-small" href="' . esc_url( add_query_arg( array( 'page' => 'tk-presets', 'edit' => $pr->id ), admin_url( 'admin.php' ) ) ) . '">ویرایش ضریب‌ها</a>
			<form method="post" style="display:inline" onsubmit="return confirm(\'پریست حذف شود؟\');">' . wp_nonce_field( 'tk_act', 'tk_nonce', true ) . '<input type="hidden" name="preset_id" value="' . $pr->id . '"><button name="tk_del_preset" value="1" class="button button-small">حذف</button></form>
			</td></tr>';
		}
		echo '</table>';
		echo '<h2>افزودن پریست جدید</h2><form method="post">' . wp_nonce_field( 'tk_act', 'tk_nonce', true ) . '<input type="text" name="title_fa" placeholder="مثلاً چینی درجه ۲" required> <button name="tk_add_preset" value="1" class="button button-primary">افزودن</button></form>';
	}
	echo '</div>';
}

/* ================= فرمول‌ها ================= */
function tk_page_formulas() {
	global $wpdb; $p = $wpdb->prefix;
	$err = '';

	if ( $_SERVER['REQUEST_METHOD'] === 'POST' && tk_ok() ) {
		if ( isset( $_POST['tk_save_formula'] ) ) {
			$fkey = sanitize_text_field( $_POST['fkey'] );
			$expr = sanitize_text_field( $_POST['expr'] );
			$test = TK_Engine::eval_expr( $expr, array( 'LENGTH' => 100, 'RIBS' => 8, 'COEF' => 1000 ) );
			if ( '' === $fkey || null === $test ) {
				$err = 'عبارت نامعتبر است. فقط عدد، پرانتز، عملگرهای + - * / و توکن‌های LENGTH و RIBS و COEF مجازند.';
			} else {
				$wpdb->query( $wpdb->prepare( "INSERT INTO {$p}tk_formulas (fkey,title_fa,expr) VALUES (%s,%s,%s) ON DUPLICATE KEY UPDATE title_fa=VALUES(title_fa), expr=VALUES(expr)", $fkey, sanitize_text_field( $_POST['title_fa'] ), $expr ) );
			}
		} elseif ( isset( $_POST['tk_del_formula'] ) ) {
			$fkey = sanitize_text_field( $_POST['fkey'] );
			$used = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}tk_sections WHERE formula_key=%s", $fkey ) )
			      + (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}tk_categories WHERE formula_key=%s", $fkey ) )
			      + (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$p}tk_brand_series WHERE formula_key=%s", $fkey ) );
			if ( $used ) {
				$err = 'این فرمول در ' . $used . ' جا استفاده شده؛ اول استفاده‌ها را عوض کنید.';
			} else {
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$p}tk_formulas WHERE fkey=%s", $fkey ) );
			}
		}
	}

	tk_assets();
	$formulas = $wpdb->get_results( "SELECT * FROM {$p}tk_formulas ORDER BY title_fa" );
	echo '<div class="wrap" dir="rtl"><h1>فرمول‌های محاسبه قیمت</h1>';
	if ( $err ) { echo '<div class="notice notice-error"><p>' . esc_html( $err ) . '</p></div>'; }
	echo '<table class="widefat striped" style="max-width:900px"><tr><th>کد</th><th>نام</th><th>عبارت محاسبه</th><th>تست (B70 با ضریب ۱۰۰۰۰)</th><th>عملیات</th></tr>';
	foreach ( $formulas as $f ) {
		$tv = TK_Engine::eval_expr( $f->expr, array( 'LENGTH' => 70, 'RIBS' => 1, 'COEF' => 100000 ) );
		echo '<tr><td><code>' . esc_html( $f->fkey ) . '</code></td><td>' . esc_html( $f->title_fa ) . '</td><td dir="ltr"><code>' . esc_html( $f->expr ) . '</code></td><td>' . ( null === $tv ? '—' : esc_html( number_format_i18n( $tv ) ) ) . '</td>
		<td><form method="post" style="display:inline">' . wp_nonce_field( 'tk_act', 'tk_nonce', true ) . '<input type="hidden" name="fkey" value="' . esc_attr( $f->fkey ) . '"><button name="tk_del_formula" value="1" class="button button-small" onclick="return confirm(\'فرمول حذف شود؟\');">حذف</button></form></td></tr>';
	}
	echo '</table>';
	echo '<h2>افزودن / ویرایش فرمول</h2>
	<form method="post">' . wp_nonce_field( 'tk_act', 'tk_nonce', true ) . '
	<p>کد فرمول (انگلیسی): <input type="text" name="fkey" dir="ltr" required placeholder="STD"> &nbsp; نام: <input type="text" name="title_fa" required placeholder="طول در ضریب"></p>
	<p>عبارت محاسبه: <input type="text" name="expr" dir="ltr" style="width:340px" required placeholder="LENGTH * COEF"></p>
	<p class="description">LENGTH = طول تسمه · RIBS = تعداد شیار · COEF = ضریب. عملگرهای مجاز: + - * / و پرانتز. مثال شیاری: <code dir="ltr">RIBS * LENGTH * COEF</code></p>
	<p><button name="tk_save_formula" value="1" class="button button-primary">ثبت فرمول</button></p></form>
	</div>';
}

/* ================= تست قیمت زنده ================= */
function tk_page_test() {
	global $wpdb; $p = $wpdb->prefix;
	tk_assets();
	$brands = $wpdb->get_results( "SELECT * FROM {$p}tk_brands ORDER BY name_fa" );
	$model = isset( $_GET['model'] ) ? sanitize_text_field( wp_unslash( $_GET['model'] ) ) : '';
	$bslug = isset( $_GET['brand'] ) ? sanitize_text_field( wp_unslash( $_GET['brand'] ) ) : '';
	echo '<div class="wrap" dir="rtl"><h1>تست قیمت زنده</h1>
	<form method="get" style="display:flex;gap:8px;flex-wrap:wrap">
	<input type="hidden" name="page" value="tk-test">
	<input type="text" name="model" value="' . esc_attr( $model ) . '" placeholder="مدل: A50 یا 8PK1460" dir="ltr" required>
	<select name="brand"><option value="">— برند —</option>';
	foreach ( $brands as $b ) { echo '<option value="' . esc_attr( $b->slug ) . '" ' . selected( $bslug, $b->slug, false ) . '>' . esc_html( $b->name_fa ) . '</option>'; }
	echo '</select><button class="button button-primary">محاسبه</button></form>';

	if ( '' !== $model ) {
		$b = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$p}tk_brands WHERE slug=%s", $bslug ) );
		$parsed = TK_Engine::parse_sku( $model );
		echo '<table class="widefat striped" style="max-width:700px;margin-top:14px">';
		if ( ! $b ) { echo '<tr><td>برند انتخاب نشده/پیدا نشد</td></tr>'; }
		elseif ( ! $parsed ) { echo '<tr><td>مدل پارس نشد (بخش ناشناخته)</td></tr>'; }
		else {
			$sec = TK_Engine::section_by_slug( $parsed['section'] );
			list( $coef, $csrc ) = TK_Engine::coefficient_source( $b->id, (int) $sec->id );
			$fkey = TK_Engine::formula_key_for( $b->id, $sec );
			$expr = TK_Engine::formula_expr( $fkey );
			$price = TK_Engine::price( $b->id, $parsed );
			$stock = TK_Engine::in_stock( $b->id, (int) $sec->id, $parsed['size'] );
			echo '<tr><th>پارس مدل</th><td>بخش: <strong>' . esc_html( $parsed['section'] ) . '</strong> | سایز: ' . (int) $parsed['size'] . ( $parsed['ribs'] ? ' | شیار: ' . (int) $parsed['ribs'] : '' ) . '</td></tr>';
			echo '<tr><th>ضریب</th><td>' . ( null === $coef ? '—' : esc_html( number_format_i18n( $coef ) ) ) . ' <small>(' . esc_html( $csrc ) . ')</small></td></tr>';
			echo '<tr><th>فرمول</th><td><code>' . esc_html( $fkey ) . '</code> → <code dir="ltr">' . esc_html( $expr ) . '</code></td></tr>';
			echo '<tr><th>قیمت نهایی</th><td><strong>' . ( null === $price ? '— (ضریب یا فرمول ناقص)' : esc_html( TK_Engine::fmt( $price ) ) ) . '</strong></td></tr>';
			echo '<tr><th>موجودی انبار</th><td>' . ( $stock ? '✅ موجود' : '⚠️ ناموجود (دکمه تماس)' ) . '</td></tr>';
			echo '<tr><th>بازه ساخت</th><td>' . ( TK_Engine::size_allowed( $b->id, (int) $sec->id, $parsed['size'] ) ? '✅ این سایز ساخته/نمایش داده می‌شود' : '❌ خارج از بازه‌های مجاز این برند' ) . '</td></tr>';
		}
		echo '</table>';
	}
	echo '</div>';
}
/* لوگوی افزونه در لیست افزونه‌ها */
add_action( 'admin_head-plugins.php', 'tk_plugin_icon' );
function tk_plugin_icon() {
	$pngs = glob( TK_PATH . 'assets/*.png' );
	$svgs = glob( TK_PATH . 'assets/*.svg' );
	$file = $pngs ? $pngs[0] : ( $svgs ? $svgs[0] : '' );
	if ( ! $file ) { return; }
	$url = TK_URL . 'assets/' . basename( $file );
	$j = wp_json_encode( $url );
	echo '<script>document.addEventListener("DOMContentLoaded",function(){
		var r=document.querySelector(\'tr[data-plugin="tasmekarunplugin/tasmekarunplugin.php"]\');
		if(!r){var c=document.querySelector(\'input[type="checkbox"][value="tasmekarunplugin/tasmekarunplugin.php"]\');if(c){r=c.closest("tr");}}
		if(!r){return;}
		var i=r.querySelector(".plugin-icon");if(!i){return;}
		if(i.tagName==="IMG"){i.src='.$j.';}else{i.style.backgroundImage="url("+'.$j.'+")";i.style.backgroundSize="100% 100%";}
	});</script>';
}