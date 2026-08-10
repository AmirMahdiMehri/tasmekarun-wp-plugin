<?php
defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', 'tk_calc_assets' );
function tk_calc_assets() {
	global $post;
	if ( is_a( $post, 'WP_Post' ) && ( has_shortcode( $post->post_content, 'tk_calculator' ) || has_shortcode( $post->post_content, 'tk_proforma' ) || has_shortcode( $post->post_content, 'tk_pricelist' ) ) ) {
		wp_enqueue_style( 'tk-shop', TK_URL . 'assets/tk-shop.css', array(), TK_VERSION );
	}
}

/* ---------- ناوبری بین سه صفحه ---------- */
function tk_page_url_by_shortcode( $sc ) {
	global $wpdb;
	$id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->prefix}posts WHERE post_status='publish' AND post_type='page' AND post_content LIKE %s LIMIT 1", '%' . $wpdb->esc_like( $sc ) . '%' ) );
	return $id ? get_permalink( $id ) : '';
}
function tk_tools_nav() {
	echo '<nav style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px">';
	foreach ( array( 'tk_calculator' => 'محاسبه‌گر قیمت', 'tk_proforma' => 'پیش‌فاکتور', 'tk_pricelist' => 'لیست قیمت' ) as $sc => $label ) {
		$u = tk_page_url_by_shortcode( '[' . $sc . ']' );
		if ( $u ) { echo '<a href="' . esc_url( $u ) . '" style="padding:8px 14px;border:1px solid #ddd;border-radius:8px;background:#fff;text-decoration:none;color:#0e3a40">' . esc_html( $label ) . '</a>'; }
	}
	echo '</nav>';
}

/* ---------- AJAX محاسبه زنده ---------- */
add_action( 'wp_ajax_tk_calc', 'tk_ajax_calc' );
add_action( 'wp_ajax_nopriv_tk_calc', 'tk_ajax_calc' );
function tk_ajax_calc() {
	$lines = isset( $_POST['lines'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['lines'] ) ) : array();
	$out = array();
	foreach ( $lines as $line ) { $out[] = tk_parse_line( $line ); }
	wp_send_json( $out );
}

function tk_all_brands() {
	global $wpdb;
	return $wpdb->get_results( "SELECT id, name_fa, name_en FROM {$wpdb->prefix}tk_brands WHERE active=1" );
}

function tk_parse_tokens( $tokens ) {
	if ( ! $tokens ) { return null; }
	$cands = array( implode( ' ', $tokens ) );
	foreach ( $tokens as $t ) { $cands[] = $t; }
	for ( $i = 0; $i < count( $tokens ) - 1; $i++ ) { $cands[] = $tokens[ $i ] . ' ' . $tokens[ $i + 1 ]; }
	foreach ( $cands as $c ) {
		$p = TK_Engine::parse_sku( $c );
		if ( $p ) { return $p; }
	}
	return null;
}

/** خط: مدل + برند + تعداد — مثل a22 tiger 12 یا 6pk1665 2 یا 9.5 2125 falkan 10 */
function tk_parse_line( $line ) {
	$line = trim( (string) $line );
	if ( '' === $line ) { return array( 'ok' => false, 'empty' => true ); }
	$tokens = preg_split( '/\s+/', $line );

	$brand = null;
	foreach ( $tokens as $i => $t ) {
		$lt = strtolower( $t );
		foreach ( tk_all_brands() as $b ) {
			if ( strtolower( $b->name_en ) === $lt || $b->name_fa === $t || ( strlen( $lt ) > 2 && ( strpos( strtolower( $b->name_en ), $lt ) === 0 || mb_strpos( $b->name_fa, $t ) === 0 ) ) ) {
				$brand = $b; unset( $tokens[ $i ] ); $tokens = array_values( $tokens ); break 2;
			}
		}
	}

	$qty = 1;
	if ( count( $tokens ) > 1 && preg_match( '/^\d+$/', $tokens[ count( $tokens ) - 1 ] ) ) {
		$q = (int) array_pop( $tokens );
		if ( tk_parse_tokens( $tokens ) ) { $qty = $q; } else { $tokens[] = (string) $q; }
	}

	$parsed = tk_parse_tokens( $tokens );
	if ( ! $parsed ) { return array( 'ok' => false, 'msg' => 'مدل نامشخص است', 'line' => $line ); }
	if ( ! $brand ) { return array( 'ok' => false, 'msg' => 'برند نامشخص است', 'line' => $line ); }

	$sec = TK_Engine::section_by_slug( $parsed['section'] );
	$coef = TK_Engine::coefficient( (int) $brand->id, (int) $sec->id );
	if ( null === $coef ) { return array( 'ok' => false, 'msg' => 'ضریب این برند/سری تعریف نشده', 'line' => $line ); }

	$price = TK_Engine::price( (int) $brand->id, $parsed );
	$sku = ( $parsed['ribs'] ? $parsed['ribs'] . $sec->slug : $sec->slug ) . $parsed['size'];
	return array( 'ok' => true, 'sku' => $sku, 'brand' => $brand->name_fa, 'coef' => $coef, 'unit' => $price, 'qty' => $qty, 'total' => $price * $qty );
}

/* ---------- محاسبه‌گر ---------- */
add_shortcode( 'tk_calculator', 'tk_render_calculator' );
function tk_render_calculator() {
	$brands = tk_all_brands();
	$pf_url = tk_page_url_by_shortcode( '[tk_proforma]' );
	ob_start();
	?>
	<div class="tk-shop" dir="rtl">
	<h1>محاسبه‌گر قیمت تسمه</h1>
	<?php tk_tools_nav(); ?>
	<div style="display:flex;gap:8px;flex-wrap:wrap">
		<input id="tk-c-sku" placeholder="مدل: a25 یا 6pk1665 یا 9.5 2125" style="padding:10px;min-width:240px">
		<input id="tk-c-brand" list="tk-brands-list" placeholder="برند: tiger یا تایگر" style="padding:10px;min-width:180px">
		<datalist id="tk-brands-list">
			<?php foreach ( $brands as $b ) { echo '<option value="' . esc_attr( $b->name_fa ) . '">' . esc_html( $b->name_en ) . '</option>'; } ?>
		</datalist>
	</div>
	<div id="tk-c-result" style="margin-top:18px"></div>
	</div>
	<script>
	(function(){
		var AJAX = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
		var PF = '<?php echo esc_url( $pf_url ); ?>';
		var sku = document.getElementById('tk-c-sku'), br = document.getElementById('tk-c-brand'), box = document.getElementById('tk-c-result'), t = null;
		function fmt(n){ return Number(n).toLocaleString('fa-IR'); }
		function run(){
			if ( ! sku.value.trim() ) { box.innerHTML = ''; return; }
			var line = sku.value.trim() + ' ' + br.value.trim();
			var fd = new FormData(); fd.append('action','tk_calc'); fd.append('lines[]', line);
			fetch(AJAX, {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(res){
				var it = res[0];
				if ( ! it || it.empty ) { box.innerHTML = ''; return; }
				if ( ! it.ok ) { box.innerHTML = '<div style="border:1px solid #f3c1c1;background:#fdecea;color:#b32d2e;border-radius:10px;padding:12px 16px">' + it.msg + '</div>'; return; }
				box.innerHTML = '<div style="border:1px solid #ddd;border-radius:10px;padding:16px;background:#fff"><strong>تسمه ' + it.sku + ' — ' + it.brand + '</strong><div style="margin:8px 0">ضریب واحد: ' + fmt(it.coef) + ' | قیمت: <strong>' + fmt(it.unit) + ' ریال</strong></div><a class="tk-cta" href="' + PF + '?lines=' + encodeURIComponent(line) + '">افزودن به پیش‌فاکتور</a></div>';
			});
		}
		sku.addEventListener('input', function(){ clearTimeout(t); t = setTimeout(run, 250); });
		br.addEventListener('input', function(){ clearTimeout(t); t = setTimeout(run, 250); });
	})();
	</script>
	<?php
	return ob_get_clean();
}

/* ---------- پیش‌فاکتور متنی زنده ---------- */
add_shortcode( 'tk_proforma', 'tk_render_proforma' );
function tk_render_proforma() {
	ob_start();
	?>
	<div class="tk-shop" id="tk-pf" dir="rtl">
	<h1>پیش‌فاکتور — تسمه کارون</h1>
	<?php tk_tools_nav(); ?>
	<p><small>هر قلم در یک خط: <code dir="ltr">مدل برند تعداد</code> — مثال: <code dir="ltr">a22 tiger 12</code> — اینتر یعنی خط بعد</small></p>
	<textarea id="tk-pf-input" rows="8" style="width:100%;padding:12px;font-family:inherit" placeholder="a22 tiger 12&#10;a47 راینو 15&#10;b47 dongil 15"></textarea>
	<p style="margin-top:10px">
		<button class="tk-cta" type="button" id="tk-pf-calc">محاسبه پیش‌فاکتور</button>
		<button class="button" type="button" onclick="window.print()">چاپ</button>
	</p>
	<div id="tk-pf-table" style="margin-top:18px"></div>
	<div id="tk-pf-total" style="margin-top:12px;font-size:20px;font-weight:700;color:#0e3a40"></div>
	</div>
	<style>@media print{body *{visibility:hidden}#tk-pf,#tk-pf *{visibility:visible}#tk-pf{position:absolute;inset:0}}</style>
	<script>
	(function(){
		var AJAX = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
		var ta = document.getElementById('tk-pf-input'), tbl = document.getElementById('tk-pf-table'), tot = document.getElementById('tk-pf-total'), t = null;
		function fmt(n){ return Number(n).toLocaleString('fa-IR'); }
		function run(){
			var lines = ta.value.split('\n').map(function(s){return s.trim();}).filter(function(s){return s.length;});
			if ( ! lines.length ) { tbl.innerHTML = ''; tot.textContent = ''; return; }
			var fd = new FormData(); fd.append('action','tk_calc');
			lines.forEach(function(l){ fd.append('lines[]', l); });
			fetch(AJAX, {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(res){
				var html = '<table class="tk-specs"><tr><th>کالا</th><th>برند</th><th>ضریب واحد</th><th>قیمت تکی</th><th>تعداد</th><th>جمع</th></tr>';
				var total = 0;
				res.forEach(function(it){
					if ( it.ok ) {
						total += Number(it.total);
						html += '<tr><td>' + it.sku + '</td><td>' + it.brand + '</td><td>' + fmt(it.coef) + '</td><td>' + fmt(it.unit) + ' ریال</td><td>' + it.qty + '</td><td>' + fmt(it.total) + ' ریال</td></tr>';
					} else if ( ! it.empty ) {
						html += '<tr><td colspan="6" style="color:#b32d2e">' + (it.line || '') + ' — ' + it.msg + '</td></tr>';
					}
				});
				html += '</table>';
				tbl.innerHTML = html;
				tot.textContent = 'جمع کل: ' + fmt(total) + ' ریال';
			});
		}
		ta.addEventListener('input', function(){ clearTimeout(t); t = setTimeout(run, 300); });
		document.getElementById('tk-pf-calc').addEventListener('click', run);
		var q = new URLSearchParams(location.search);
		var add = q.get('lines');
		if ( add ) { ta.value = (ta.value ? ta.value + '\n' : '') + add; }
		if ( ta.value.trim() ) { run(); }
	})();
	</script>
	<?php
	return ob_get_clean();
}

/* ---------- لیست قیمت (ضریب‌های به‌روز برندها) ---------- */
add_shortcode( 'tk_pricelist', 'tk_render_pricelist' );
function tk_render_pricelist() {
	global $wpdb; $p = $wpdb->prefix;

	$pm = array();
	foreach ( $wpdb->get_results( "SELECT preset_id, section_id, coef FROM {$p}tk_preset_coefs" ) as $r ) { $pm[ $r->preset_id ][ $r->section_id ] = $r->coef; }
	$lg = array();
	foreach ( $wpdb->get_results( "SELECT brand_id, section_id, coef FROM {$p}tk_coefficients" ) as $r ) { $lg[ $r->brand_id ][ $r->section_id ] = $r->coef; }
	$ov = array();
	foreach ( $wpdb->get_results( "SELECT brand_id, section_id, coef_on, coef_value FROM {$p}tk_brand_series" ) as $r ) { if ( $r->coef_on ) { $ov[ $r->brand_id ][ $r->section_id ] = $r->coef_value; } }
	$brands = tk_all_brands();
	$sections = $wpdb->get_results( "SELECT id, slug FROM {$p}tk_sections WHERE active=1 ORDER BY slug" );

	ob_start();
	?>
	<div class="tk-shop" dir="rtl">
	<h1>لیست قیمت به‌روز</h1>
	<?php tk_tools_nav(); ?>
	<input id="tk-pl-search" placeholder="جستجوی برند (فارسی/انگلیسی)…" style="padding:10px;min-width:240px;margin-bottom:12px">
	<div style="max-height:70vh;overflow:auto;border:1px solid #ddd;border-radius:10px;background:#fff">
	<table class="tk-specs" id="tk-pl-table">
	<tr><th>برند</th><th>سری</th><th>ضریب (ریال)</th></tr>
	<?php
	foreach ( $brands as $b ) {
		$pid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT default_preset_id FROM {$p}tk_brands WHERE id=%d", $b->id ) );
		foreach ( $sections as $s ) {
			$coef = null;
			if ( isset( $ov[ $b->id ][ $s->id ] ) ) { $coef = $ov[ $b->id ][ $s->id ]; }
			elseif ( $pid && isset( $pm[ $pid ][ $s->id ] ) ) { $coef = $pm[ $pid ][ $s->id ]; }
			elseif ( isset( $lg[ $b->id ][ $s->id ] ) ) { $coef = $lg[ $b->id ][ $s->id ]; }
			if ( null === $coef || $coef <= 0 ) { continue; }
			echo '<tr data-b="' . esc_attr( mb_strtolower( $b->name_fa ) . '|' . strtolower( $b->name_en ) ) . '"><td>' . esc_html( $b->name_fa ) . ' (' . esc_html( $b->name_en ) . ')</td><td>' . esc_html( $s->slug ) . '</td><td>' . esc_html( number_format_i18n( $coef ) ) . '</td></tr>';
		}
	}
	?>
	</table>
	</div>
	</div>
	<script>
	(function(){
		var s = document.getElementById('tk-pl-search');
		s.addEventListener('input', function(){
			var q = s.value.trim().toLowerCase();
			document.querySelectorAll('#tk-pl-table tr[data-b]').forEach(function(tr){
				tr.style.display = ( ! q || tr.getAttribute('data-b').indexOf(q) !== -1 ) ? '' : 'none';
			});
		});
	})();
	</script>
	<?php
	return ob_get_clean();
}