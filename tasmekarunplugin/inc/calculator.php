<?php
defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', 'tk_calc_assets' );
function tk_calc_assets() {
	global $post;
	if ( is_a( $post, 'WP_Post' ) && ( has_shortcode( $post->post_content, 'tk_calculator' ) || has_shortcode( $post->post_content, 'tk_proforma' ) || has_shortcode( $post->post_content, 'tk_pricelist' ) ) ) {
		wp_enqueue_style( 'tk-shop', TK_URL . 'assets/tk-shop.css', array(), TK_VERSION );
	}
}

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

function tk_client_data() {
	global $wpdb; $p = $wpdb->prefix;
	$brands = $wpdb->get_results( "SELECT id, name_fa, name_en, default_preset_id FROM {$p}tk_brands WHERE active=1" );
	$sections = $wpdb->get_results( "SELECT id, slug, formula_key, min_charge FROM {$p}tk_sections WHERE active=1" );
	$pm = array();
	foreach ( $wpdb->get_results( "SELECT preset_id, section_id, coef FROM {$p}tk_preset_coefs" ) as $r ) { $pm[ $r->preset_id ][ $r->section_id ] = (float) $r->coef; }
	$lg = array();
	foreach ( $wpdb->get_results( "SELECT brand_id, section_id, coef FROM {$p}tk_coefficients" ) as $r ) { $lg[ $r->brand_id ][ $r->section_id ] = (float) $r->coef; }
	$ov = array();
	foreach ( $wpdb->get_results( "SELECT brand_id, section_id, coef_value FROM {$p}tk_brand_series WHERE coef_on=1" ) as $r ) { $ov[ $r->brand_id ][ $r->section_id ] = (float) $r->coef_value; }
	$coef = array();
	foreach ( $brands as $b ) {
		$m = array();
		foreach ( $sections as $s ) {
			$c = null;
			if ( isset( $ov[ $b->id ][ $s->id ] ) ) { $c = $ov[ $b->id ][ $s->id ]; }
			elseif ( $b->default_preset_id && isset( $pm[ $b->default_preset_id ][ $s->id ] ) ) { $c = $pm[ $b->default_preset_id ][ $s->id ]; }
			elseif ( isset( $lg[ $b->id ][ $s->id ] ) ) { $c = $lg[ $b->id ][ $s->id ]; }
			if ( null !== $c && $c > 0 ) { $m[ (int) $s->id ] = $c; }
		}
		$coef[ (int) $b->id ] = $m;
	}
	return array( 'brands' => $brands, 'sections' => $sections, 'coef' => $coef );
}

function tk_brands_datalist() {
	global $wpdb;
	$brands = $wpdb->get_results( "SELECT name_fa, name_en FROM {$wpdb->prefix}tk_brands WHERE active=1" );
	echo '<datalist id="tk-brands-list">';
	foreach ( $brands as $b ) { echo '<option value="' . esc_attr( $b->name_fa ) . '">' . esc_html( $b->name_en ) . '</option>'; }
	echo '</datalist>';
}

/* ---------- موتور JS ---------- */
function tk_calc_js_core() {
	?>
	<script>
	function tkInit(TK){
		var SECS = TK.sections.slice().sort(function(a,b){ return b.slug.length - a.slug.length; });
		function fmt(n){ return Number(n||0).toLocaleString('fa-IR'); }
		function hasFa(s){ return /[\u0600-\u06FF]/.test(s); }
		function secOf(slug){ for (var i=0;i<SECS.length;i++) if (SECS[i].slug===slug) return SECS[i]; return null; }
		function unitFor(secSlug, parsed, coef){
			var sec = secOf(secSlug); if ( ! sec ) return null;
			var len = Math.max(parsed.size, parseInt(sec.min_charge||0,10)||0);
			if ( sec.formula_key === 'LEN_COEF' ) return len * coef;
			if ( sec.formula_key === 'RIBS_LEN_COEF' ) return parsed.ribs ? parsed.ribs * len * coef : null;
			return null;
		}
		function skuOf(secSlug, parsed){ var sec=secOf(secSlug); return (parsed.ribs?parsed.ribs+secSlug:secSlug)+parsed.size; }
		function splitBrand(t){
			if ( ! t ) return null;
			var lt = String(t).toLowerCase();
			for ( var i=0;i<TK.brands.length;i++ ){
				var b = TK.brands[i], en = String(b.name_en||'').toLowerCase(), fa = String(b.name_fa||'');
				if ( en === lt || fa === t ) return { b:b, q:0 };
				if ( en && lt.indexOf(en) === 0 ){ var r = lt.slice(en.length); if ( /^\d+$/.test(r) ) return { b:b, q:+r }; }
				if ( en && lt.length > 2 && en.indexOf(lt) === 0 ) return { b:b, q:0 };
				if ( fa && t.length > 1 && fa.indexOf(t) === 0 ) return { b:b, q:0 };
			}
			return null;
		}
		function parseSku(raw){
			var s = String(raw||'').toUpperCase().replace(/\s+/g,'');
			if ( ! s ) return null;
			var m = s.match(/^(\d+)PK(\d+)$/); if ( m ) return { section:'PK', size:+m[2], ribs:+m[1] };
			m = s.match(/^TIME(\d+)$/); if ( m ) return { section:'TIME', size:+m[1], ribs:0 };
			m = s.match(/^(\d+)(R[A-Z0-9].*)$/);
			if ( m ) {
				var rest = m[2];
				for ( var i=0;i<SECS.length;i++ ){
					var sl = SECS[i].slug.toUpperCase();
					if ( rest.indexOf(sl) === 0 ){
						var num = rest.slice(sl.length);
						if ( /^\d+(\.\d+)?$/.test(num) ) return { section:SECS[i].slug, size:+num, ribs:+m[1] };
					}
				}
			}
			for ( var j=0;j<SECS.length;j++ ){
				var slg = SECS[j].slug.toUpperCase();
				if ( slg && s.indexOf(slg) === 0 ){
					var rr = s.slice(slg.length);
					if ( /^\d+(\.\d+)?$/.test(rr) ) return { section:SECS[j].slug, size:+rr, ribs:0 };
				}
			}
			return null;
		}
		function compute(model, brandName, qty){
			var p = parseSku(model);
			if ( ! p ) return { ok:false, msg:'مدل نامشخص است' };
			var sb = splitBrand(brandName);
			if ( ! sb ) return { ok:false, msg:'برند نامشخص است' };
			var sec = secOf(p.section);
			if ( ! sec ) return { ok:false, msg:'سری نامشخص است' };
			var cm = TK.coef[sb.b.id] || {};
			var coef = cm[sec.id];
			if ( coef === undefined ) return { ok:false, msg:'ضریب این برند/سری تعریف نشده' };
			var unit = unitFor(p.section, p, coef);
			if ( unit === null ) return { ok:false, msg:'فرمول این سری تعریف نشده؛ تماس بگیرید' };
			var q = parseInt(qty,10) || 1;
			return { ok:true, sku:skuOf(p.section,p), brand:sb.b.name_fa, coef:coef, unit:unit, qty:q, total:unit*q };
		}
		function attachBrandSuggest(inp){
			var box = null, items = [], hi = -1;
			function ensure(){
				if ( box ) return box;
				box = document.createElement('div');
				box.style.cssText = 'position:absolute;background:#fff;border:1px solid #ddd;border-radius:8px;z-index:60;max-height:190px;overflow:auto;box-shadow:0 8px 20px rgba(0,0,0,.15);display:none';
				inp.parentElement.style.position = 'relative';
				inp.parentElement.appendChild(box);
				box.addEventListener('mousedown', function(e){
					var li = e.target.closest('[data-v]'); if ( ! li ) return;
					e.preventDefault(); pick(li.getAttribute('data-v'));
				});
				return box;
			}
			function tokenAtCaret(){
				var v = inp.value, c = inp.selectionStart === null ? v.length : inp.selectionStart;
				var upto = v.slice(0, c);
				var m = upto.match(/(\S*)$/);
				var cur = m ? m[1] : '';
				var prior = upto.slice(0, upto.length - cur.length).trim().length > 0;
				return { cur: cur, prior: prior };
			}
			function curTok(){ return tokenAtCaret().cur; }
			function matches(cur){
				var lc = cur.toLowerCase(), out = [];
				for ( var i=0;i<TK.brands.length && out.length<6;i++ ){
					var b = TK.brands[i], en = String(b.name_en||'').toLowerCase(), fa = String(b.name_fa||'');
					if ( en.indexOf(lc) === 0 || fa.indexOf(cur) === 0 ) out.push(b);
				}
				return out;
			}
			function paint(){
				if ( ! box ) return;
				var html = '';
				items.forEach(function(b,i){
					var name = hasFa(curTok()) ? b.name_fa : b.name_en;
					html += '<div data-v="'+name+'" style="padding:8px 12px;cursor:pointer;'+(i===hi?'background:#e8f0f5;':'')+'">'+b.name_fa+' <small style="color:#777">('+b.name_en+')</small></div>';
				});
				box.innerHTML = html;
			}
			function open(){
				var t = tokenAtCaret();
				if ( ! t.prior || ! t.cur || /^\d/.test(t.cur) || ! /[a-zA-Z\u0600-\u06FF]/.test(t.cur) ) { close(); return; }
				items = matches(t.cur);
				if ( ! items.length ) { close(); return; }
				hi = -1;
				var b = ensure();
				b.style.top = (inp.offsetTop + inp.offsetHeight + 4) + 'px';
				b.style.left = inp.offsetLeft + 'px';
				b.style.width = Math.max(inp.offsetWidth, 200) + 'px';
				b.style.display = 'block';
				paint();
			}
			function close(){ if ( box ) box.style.display = 'none'; hi = -1; }
			function isOpen(){ return box && box.style.display === 'block'; }
			function pick(name){
				var v = inp.value, c = inp.selectionStart === null ? v.length : inp.selectionStart;
				var upto = v.slice(0, c), after = v.slice(c);
				var m = upto.match(/(\S*)$/);
				var start = m ? c - m[1].length : c;
				inp.value = v.slice(0, start) + name + ' ' + after;
				var pos = start + name.length + 1;
				inp.focus(); inp.setSelectionRange(pos, pos);
				close();
				inp.dispatchEvent(new Event('input'));
			}
			inp.addEventListener('input', open);
			inp.addEventListener('keydown', function(e){
				if ( ! isOpen() ) return;
				if ( e.key === 'ArrowDown' ){ e.preventDefault(); hi = (hi+1) % items.length; paint(); }
				else if ( e.key === 'ArrowUp' ){ e.preventDefault(); hi = (hi-1+items.length) % items.length; paint(); }
				else if ( e.key === 'Tab' ){ e.preventDefault(); if(items.length) pick( hasFa(curTok()) ? items[hi>=0?hi:0].name_fa : items[hi>=0?hi:0].name_en ); }
				else if ( e.key === 'Escape' ){ close(); }
				else if ( e.key === 'Enter' ){ close(); }
			});
			document.addEventListener('click', function(e){ if ( box && ! box.contains(e.target) && e.target !== inp ) close(); });
		}
		return { fmt:fmt, splitBrand:splitBrand, parseSku:parseSku, compute:compute, attachBrandSuggest:attachBrandSuggest, secOf:secOf, unitFor:unitFor, skuOf:skuOf };
	}
	</script>
	<?php
}

/* ---------- محاسبه‌گر ---------- */
add_shortcode( 'tk_calculator', 'tk_render_calculator' );
function tk_render_calculator() {
	$data = wp_json_encode( tk_client_data() );
	ob_start();
	tk_calc_js_core();
	?>
	<div class="tk-shop" dir="rtl">
	<h1>محاسبه‌گر قیمت تسمه</h1>
	<?php tk_tools_nav(); ?>
	<input id="tk-c-line" style="padding:12px;width:100%;max-width:560px"
		placeholder="مدل و سایز (چسبیده) + برند + تعداد — مثال: a25 تایگر 12">
	<div id="tk-c-result" style="margin-top:18px"></div>
	</div>
	<script>
	(function(){
		var TK = <?php echo $data; ?>;
		var E = tkInit(TK);
		var line = document.getElementById('tk-c-line'), box = document.getElementById('tk-c-result');
		E.attachBrandSuggest(line);
		function run(){
			var v = line.value.trim();
			if ( ! v ) { box.innerHTML = ''; return; }
			var tokens = v.split(/\s+/), qty = 1;
			if ( tokens.length > 1 && /^\d+$/.test(tokens[tokens.length-1]) ) qty = +tokens.pop();
			var brandName = '', idx = -1, extraQ = 0;
			for ( var i=0;i<tokens.length;i++ ){
				var sb = E.splitBrand(tokens[i]);
				if ( sb ){ brandName = sb.b.name_fa; extraQ = sb.q; idx = i; break; }
			}
			if ( idx > -1 ) tokens.splice(idx,1);
			if ( extraQ && qty === 1 ) qty = extraQ;
			var r = E.compute(tokens.join(' '), brandName, qty);
			if ( ! r.ok ) { box.innerHTML = '<div style="border:1px solid #f3c1c1;background:#fdecea;color:#b32d2e;border-radius:10px;padding:12px 16px">' + r.msg + '</div>'; return; }
			var extra = r.qty > 1 ? ' | تعداد: ' + E.fmt(r.qty) + ' | <strong>قیمت کل: ' + E.fmt(r.total) + ' ریال</strong>' : '';
			box.innerHTML = '<div style="border:1px solid #ddd;border-radius:10px;padding:16px;background:#fff"><strong>تسمه ' + r.sku + ' — ' + r.brand + '</strong><div style="margin:8px 0">ضریب واحد: ' + E.fmt(r.coef) + ' | قیمت واحد: ' + E.fmt(r.unit) + ' ریال' + extra + '</div></div>';
		}
		line.addEventListener('input', function(){ try { run(); } catch (e) { box.textContent = 'خطا: ' + e.message; } });
	})();
	</script>
	<?php
	return ob_get_clean();
}

/* ---------- پیش‌فاکتور حرفه‌ای ---------- */
add_shortcode( 'tk_proforma', 'tk_render_proforma' );
function tk_render_proforma() {
	$data = wp_json_encode( tk_client_data() );
	$meta = wp_json_encode( array(
		'logo'  => get_option( 'tk_logo_url', '' ),
		'phone' => (function_exists('get_option') ? (is_array(get_option('tk_settings',array())) ? (isset(get_option('tk_settings',array())['phone']) ? get_option('tk_settings',array())['phone'] : '') : '') : ''),
		'site'  => 'tasmekarun.ir',
	) );
	ob_start();
	tk_calc_js_core();
	?>
	<div class="tk-shop" id="tk-pf" dir="rtl">
	<h1>پیش‌فاکتور — تسمه کارون</h1>
	<?php tk_tools_nav(); ?>

	<div id="tk-pf-readonly" style="display:none"></div>

	<div id="tk-pf-editor">
	<div style="margin-bottom:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
		<label>برند پیشفرض: <input id="tk-pf-brand" list="tk-brands-list" placeholder="مثلاً تایگر (اختیاری)" style="padding:10px;min-width:180px"></label>
		<label>تم فاکتور:
			<select id="tk-pf-theme" style="padding:10px">
				<option value="petrol">نفتی (لوگو)</option>
				<option value="olive">زیتونی</option>
				<option value="gray">طوسی</option>
				<option value="teal">فیروزه‌ای</option>
			</select>
		</label>
		<?php tk_brands_datalist(); ?>
	</div>
	<div style="display:flex;gap:18px;flex-wrap:wrap;align-items:flex-start">
		<div style="flex:0 1 46%;min-width:280px">
			<input id="tk-pf-line" style="width:100%;padding:12px;box-sizing:border-box"
				placeholder="مدل+سایز (چسبیده) + برند (اختیاری) + تعداد — Enter = ثبت خط&#10;مثال: a22 12 یا c90 دانگیل 10">
			<p style="margin-top:10px;display:flex;gap:6px;flex-wrap:wrap">
				<button class="button" type="button" id="tk-add-misc">+ محصول متفرقه</button>
				<button class="button" type="button" id="tk-add-custom">+ تسمه دلخواه</button>
				<button class="button" type="button" id="tk-pf-customize">ساخت فاکتور شخصی‌سازی‌شده</button>
			</p>
			<div id="tk-pf-custom" style="display:none;border:1px dashed #bbb;border-radius:10px;padding:12px;margin-top:8px;background:#fafafa">
				<label><input type="checkbox" id="tk-c-on"> حالت شخصی‌سازی (حذف برند تسمه کارون)</label><br><br>
				<label>لینک لوگو (اختیاری): <input id="tk-c-logo" dir="ltr" style="width:100%" placeholder="https://.../logo.png"></label><br><br>
				<label>نام سایت (اختیاری): <input id="tk-c-site" dir="ltr" style="width:100%"></label><br><br>
				<label>نام فروشگاه / فروشنده (اجباری): <input id="tk-c-seller" style="width:100%"></label>
			</div>
			<p style="margin-top:10px;display:flex;gap:6px;flex-wrap:wrap">
				<button class="button" type="button" onclick="window.print()">چاپ</button>
				<button class="button" type="button" id="tk-pf-share">اشتراک‌گذاری (کپی لینک)</button>
				<button class="button" type="button" id="tk-pf-online">فاکتور آنلاین</button>
				<button class="button" type="button" id="tk-pf-clear">پاک کردن</button>
			</p>
			<div id="tk-pf-total" style="margin-top:12px;font-size:20px;font-weight:800;color:#0e3a40"></div>
		</div>
		<div style="flex:1 1 46%;min-width:300px">
			<table class="tk-specs" id="tk-pf-table">
				<tr><th>کالا</th><th>برند</th><th>ضریب واحد <span id="tk-pf-pencilwrap"></span></th><th>قیمت تکی</th><th>تعداد</th><th>جمع</th><th class="tk-del-col"></th></tr>
				<tbody id="tk-pf-body"></tbody>
			</table>
		</div>
	</div>

	<div id="tk-pf-print" style="margin-top:24px"></div>
	</div>

	<style>
	.tk-inv{--inv-a:#0E3A40;--inv-b:#155a63;font-family:inherit;background:#fff;color:#1a1a1a;border-radius:14px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,.10)}
	.tk-inv--gray{--inv-a:#585D61;--inv-b:#7d8387}
	.tk-inv--olive{--inv-a:#5A7D2A;--inv-b:#7fa043}
	.tk-inv--teal{--inv-a:#0F6B6B;--inv-b:#159090}
	.tk-inv .inv-head{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:22px 26px;color:#fff;background:linear-gradient(135deg,var(--inv-a),var(--inv-b))}
	.tk-inv .inv-brand{display:flex;gap:12px;align-items:center}
	.tk-inv .inv-logo{width:56px;height:56px;object-fit:contain;background:#fff;border-radius:12px;padding:6px}
	.tk-inv .inv-logo-svg{width:56px;height:56px;background:#fff;border-radius:12px;padding:6px}
	.tk-inv .inv-title{font-size:22px;font-weight:800}
	.tk-inv .inv-sub{font-size:12px;opacity:.9}
	.tk-inv .inv-meta{text-align:left;font-size:12px;line-height:2}
	.tk-inv .inv-accent{height:6px;background:linear-gradient(90deg,var(--inv-b),var(--inv-a));opacity:.5}
	.tk-inv table{width:100%;border-collapse:collapse;font-size:13px}
	.tk-inv thead th{background:var(--inv-a);color:#fff;padding:10px 12px;font-weight:700}
	.tk-inv tbody td{padding:9px 12px;border-bottom:1px solid #eee}
	.tk-inv tbody tr:nth-child(even){background:#f6f8f8}
	.tk-inv .inv-total{display:flex;justify-content:flex-end;padding:16px 26px}
	.tk-inv .inv-total .box{background:linear-gradient(135deg,var(--inv-a),var(--inv-b));color:#fff;border-radius:12px;padding:12px 26px;font-size:17px;font-weight:800;box-shadow:0 6px 16px rgba(0,0,0,.15)}
	.tk-inv .inv-foot{display:flex;justify-content:space-between;gap:10px;padding:12px 26px;font-size:11px;color:#777;border-top:1px dashed #ddd}
	@media print{
		body *{visibility:hidden}
		#tk-pf-print, #tk-pf-print *{visibility:visible}
		#tk-pf-print{position:absolute;inset:0;width:100%;margin:0;box-shadow:none}
		#tk-pf-print .tk-inv{box-shadow:none;border-radius:0}
	}
	</style>

	<script>
	(function(){
		var TK = <?php echo $data; ?>;
		var META = <?php echo $meta; ?>;
		var E = tkInit(TK);
		var $ = function(id){ return document.getElementById(id); };
		var line = $('tk-pf-line'), defBrand = $('tk-pf-brand'), themeSel = $('tk-pf-theme');
		var body = $('tk-pf-body'), tot = $('tk-pf-total'), printBox = $('tk-pf-print');
		var pencilWrap = $('tk-pf-pencilwrap'), readonlyBox = $('tk-pf-readonly'), editorBox = $('tk-pf-editor');
		var customPanel = $('tk-pf-custom');
		E.attachBrandSuggest(line);

		var state = { rows: [], theme: 'petrol', invNo: '', custom: { on:false, logo:'', site:'', seller:'' }, coefEdit: false };

		function esc(s){ return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
		function escAttr(s){ return esc(s).replace(/"/g,'&quot;'); }

		function computeRow(r){
			if ( r.kind === 'misc' ) {
				var price = +r.price || 0, q = +r.qty || 1;
				return { ok:true, kind:'misc', sku:r.name||'—', brand:r.brand||'', coef:null, unit:price, qty:q, total:price*q };
			}
			if ( r.kind === 'custom' ) {
				var p = E.parseSku(r.model);
				if ( ! p ) return { ok:false, kind:'custom', msg:'مدل نامشخص' };
				var u = E.unitFor(p.section, p, +r.coef || 0);
				if ( u === null ) return { ok:false, kind:'custom', msg:'فرمول نامشخص' };
				var qq = +r.qty || 1;
				return { ok:true, kind:'custom', sku:E.skuOf(p.section,p), brand:r.brand||'دلخواه', coef:+r.coef||0, unit:u, qty:qq, total:u*qq };
			}
			var inf = E.compute(r.model, r.brand || defBrand.value, r.qty);
			if ( ! inf.ok ) return { ok:false, kind:'belt', msg:inf.msg };
			var coef = ( r.coefOv != null ? +r.coefOv : inf.coef );
			var pp = E.parseSku(r.model);
			var unit = E.unitFor(pp.section, pp, coef);
			var q2 = +r.qty || 1;
			return { ok:true, kind:'belt', sku:inf.sku, brand:inf.brand, coef:coef, unit:unit, qty:q2, total:unit*q2 };
		}

		function inlineLogo(){
			return '<svg class="inv-logo-svg" viewBox="0 0 64 64"><circle cx="32" cy="38" r="15" fill="none" stroke="#0E3A40" stroke-width="6"/><circle cx="32" cy="15" r="5" fill="#0E3A40"/><path d="M8 38a24 24 0 0 0 48 0" fill="none" stroke="#585D61" stroke-width="4" stroke-dasharray="4 4"/></svg>';
		}

		function invoiceHTML(){
			var c = state.custom, on = c && c.on;
			var logo = on ? (c.logo||'') : (META.logo||'');
			var store = on ? (c.seller||'فروشنده') : 'تسمه کارون';
			var site = on ? (c.site||'') : META.site;
			if ( ! state.invNo ) state.invNo = 'TK-' + Date.now().toString().slice(-6);
			var logoHtml = logo ? '<img class="inv-logo" src="'+escAttr(logo)+'" alt="">' : inlineLogo();
			var h = '<div class="tk-inv tk-inv--'+esc(state.theme)+'">';
			h += '<div class="inv-head"><div class="inv-brand">'+logoHtml+'<div><div class="inv-title">'+esc(store)+'</div>'+(site?'<div class="inv-sub">'+esc(site)+'</div>':'')+'</div></div>';
			h += '<div class="inv-meta"><div>پیش‌فاکتور شماره: '+esc(state.invNo)+'</div><div>تاریخ: '+new Date().toLocaleDateString('fa-IR')+'</div></div></div>';
			h += '<div class="inv-accent"></div>';
			h += '<table><thead><tr><th>کالا</th><th>برند</th><th>ضریب واحد</th><th>قیمت تکی</th><th>تعداد</th><th>جمع</th></tr></thead><tbody>';
			var total = 0;
			state.rows.forEach(function(r){
				var cr = computeRow(r);
				if ( cr.ok ) total += cr.total;
				h += '<tr><td>'+esc(cr.sku)+'</td><td>'+esc(cr.brand)+'</td><td>'+(cr.coef!=null?E.fmt(cr.coef):'—')+'</td><td>'+(cr.ok?E.fmt(cr.unit)+' ریال':'—')+'</td><td>'+cr.qty+'</td><td>'+(cr.ok?E.fmt(cr.total)+' ریال':'—')+'</td></tr>';
			});
			h += '</tbody></table>';
			h += '<div class="inv-total"><div class="box">جمع کل: '+E.fmt(total)+' ریال</div></div>';
			h += '<div class="inv-foot"><span>'+(on?esc(c.seller||''):'تسمه کارون — '+esc(META.site))+'</span><span>'+(on?'':('تماس: '+esc(META.phone)))+'</span></div>';
			h += '</div>';
			return h;
		}

		function renderEditor(){
			var html = '';
			state.rows.forEach(function(r,i){
				var cr = computeRow(r);
				html += '<tr data-i="'+i+'">';
				html += '<td><input data-col="model" value="'+escAttr(r.kind==='misc'?r.name:r.model)+'" style="width:100px"></td>';
				html += '<td><input data-col="brand" value="'+escAttr(r.brand||'')+'" placeholder="برند" style="width:80px"></td>';
				if ( r.kind === 'misc' ) html += '<td>—</td>';
				else if ( r.kind === 'custom' ) html += '<td><input data-col="coef" type="number" value="'+(r.coef||0)+'" style="width:80px"></td>';
				else html += ( state.coefEdit ? '<td><input data-col="coefov" type="number" value="'+(cr.coef!=null?cr.coef:0)+'" style="width:80px"></td>' : '<td class="c-coef">'+(cr.coef!=null?E.fmt(cr.coef):'—')+'</td>' );
				if ( r.kind === 'misc' ) html += '<td><input data-col="price" type="number" value="'+(r.price||0)+'" style="width:90px"></td>';
				else html += '<td class="c-unit">'+(cr.ok?E.fmt(cr.unit)+' ریال':'—')+'</td>';
				html += '<td><input data-col="qty" type="number" min="1" value="'+(r.qty||1)+'" style="width:55px"></td>';
				html += '<td class="c-total">'+(cr.ok?E.fmt(cr.total)+' ریال':'—')+'</td>';
				html += '<td class="tk-del"><button type="button" class="button button-small tk-del-btn">حذف</button></td></tr>';
			});
			body.innerHTML = html;
			pencilWrap.innerHTML = state.coefEdit
				? ' <button type="button" class="button button-small tk-apply" style="background:#2e7d32;color:#fff" title="اعمال">✔</button> <button type="button" class="button button-small tk-cancel" style="background:#c62828;color:#fff" title="انصراف">✖</button>'
				: ' <button type="button" class="button button-small tk-pencil" title="ویرایش ضریب‌ها">✎</button>';
			recalc();
		}

		function recalc(){
			var sum = 0;
			state.rows.forEach(function(r){ var cr = computeRow(r); if ( cr.ok ) sum += cr.total; });
			var t = 'جمع کل: ' + E.fmt(sum) + ' ریال';
			tot.textContent = t;
			printBox.innerHTML = invoiceHTML();
		}

		function splitLine(l){
			var tokens = l.split(/\s+/).filter(function(s){return s;});
			if ( ! tokens.length ) return null;
			var qty = 1;
			if ( tokens.length > 1 && /^\d+$/.test(tokens[tokens.length-1]) ) qty = +tokens.pop();
			var brandTok = '', idx = -1, extraQ = 0;
			for ( var i=0;i<tokens.length;i++ ){
				var sb = E.splitBrand(tokens[i]);
				if ( sb ){ brandTok = tokens[i]; extraQ = sb.q; idx = i; break; }
			}
			if ( idx > -1 ) tokens.splice(idx,1);
			if ( extraQ && qty === 1 ) qty = extraQ;
			return { model: tokens.join(' '), brand: brandTok, qty: qty };
		}
		function addLines(text){
			text.split('\n').forEach(function(l){
				var p = splitLine(l.trim());
				if ( p && p.model ) state.rows.push({ kind:'belt', model:p.model, brand:p.brand, qty:p.qty, coefOv:null });
			});
			renderEditor();
		}

		line.addEventListener('keydown', function(e){
			if ( e.key === 'Enter' ){ e.preventDefault(); try { addLines(line.value); line.value=''; } catch(err){ tot.textContent='خطا: '+err.message; } }
		});
		$('tk-add-misc').addEventListener('click', function(){ state.rows.push({kind:'misc',name:'',brand:'',price:0,qty:1}); renderEditor(); });
		$('tk-add-custom').addEventListener('click', function(){ state.rows.push({kind:'custom',model:'',brand:'',coef:0,qty:1}); renderEditor(); });
		$('tk-pf-clear').addEventListener('click', function(){ if(confirm('کل پیش‌فاکتور پاک شود؟')){ state.rows=[]; state.coefEdit=false; renderEditor(); } });
		defBrand.addEventListener('input', function(){ recalc(); });
		themeSel.addEventListener('change', function(){ state.theme = themeSel.value; recalc(); });

		$('tk-pf-customize').addEventListener('click', function(){ customPanel.style.display = customPanel.style.display==='none' ? 'block' : 'none'; });
		function syncCustom(){
			state.custom = { on: $('tk-c-on').checked, logo: $('tk-c-logo').value.trim(), site: $('tk-c-site').value.trim(), seller: $('tk-c-seller').value.trim() };
			recalc();
		}
		['tk-c-on','tk-c-logo','tk-c-site','tk-c-seller'].forEach(function(id){ $(id).addEventListener('input', syncCustom); });

		pencilWrap.addEventListener('click', function(e){
			if ( e.target.classList.contains('tk-pencil') ){
				if ( confirm('آیا مطمئن هستید می‌خواهید ضریب‌ها را تغییر دهید؟' ) ){ state.coefEdit = true; renderEditor(); }
			} else if ( e.target.classList.contains('tk-apply') ){
				state.coefEdit = false; renderEditor();
			} else if ( e.target.classList.contains('tk-cancel') ){
				state.rows.forEach(function(r){ r.coefOv = null; });
				state.coefEdit = false; renderEditor();
			}
		});

		body.addEventListener('input', function(e){
			var tr = e.target.closest('tr'); if ( ! tr ) return;
			var i = +tr.getAttribute('data-i'), r = state.rows[i], col = e.target.getAttribute('data-col');
			if ( ! r ) return;
			if ( col === 'model' ){ if (r.kind==='misc') r.name = e.target.value; else r.model = e.target.value; }
			else if ( col === 'brand' ) r.brand = e.target.value;
			else if ( col === 'qty' ) r.qty = +e.target.value || 1;
			else if ( col === 'price' ) r.price = +e.target.value || 0;
			else if ( col === 'coef' ) r.coef = +e.target.value || 0;
			else if ( col === 'coefov' ) r.coefOv = e.target.value === '' ? null : +e.target.value;
			var cr = computeRow(r);
			tr.querySelector('.c-unit').textContent = cr.ok ? E.fmt(cr.unit)+' ریال' : '—';
			tr.querySelector('.c-total').textContent = cr.ok ? E.fmt(cr.total)+' ریال' : '—';
			var coefCell = tr.querySelector('.c-coef'); if ( coefCell ) coefCell.textContent = cr.coef!=null ? E.fmt(cr.coef) : '—';
			recalc();
		});
		body.addEventListener('click', function(e){
			if ( e.target.classList.contains('tk-del-btn') ){
				state.rows.splice(+e.target.closest('tr').getAttribute('data-i'),1);
				renderEditor();
			}
		});
		body.addEventListener('keydown', function(e){
			var inp = e.target.closest('input'); if ( ! inp ) return;
			var tr = inp.closest('tr');
			function move(dir){
				var target = dir > 0 ? tr.nextElementSibling : tr.previousElementSibling;
				if ( target ){ var n = target.querySelector('input[data-col="'+inp.getAttribute('data-col')+'"]'); if ( n ){ e.preventDefault(); n.focus(); } }
				else if ( dir > 0 ){ e.preventDefault(); line.focus(); }
			}
			if ( e.key === 'ArrowDown' ) move(1);
			else if ( e.key === 'ArrowUp' ) move(-1);
			else if ( e.key === 'Enter' ){ e.preventDefault(); move(1); }
		});

		/* ---------- اشتراک / فاکتور آنلاین ---------- */
		function serialize(){
			return btoa(unescape(encodeURIComponent(JSON.stringify({ rows:state.rows, theme:state.theme, custom:state.custom, invNo:state.invNo }))));
		}
		function shareUrl(){
			var base = location.pathname;
			return location.origin + base + '?inv=' + serialize();
		}
		$('tk-pf-share').addEventListener('click', function(){
			var u = shareUrl();
			if ( navigator.clipboard ) { navigator.clipboard.writeText(u).then(function(){ alert('لینک کپی شد ✔'); }); }
			else { prompt('لینک فاکتور:', u); }
		});
		$('tk-pf-online').addEventListener('click', function(){ window.open(shareUrl(), '_blank'); });

		/* ---------- حالت فقط-خواندنی از لینک ---------- */
		var q = new URLSearchParams(location.search);
		var inv = q.get('inv');
		if ( inv ) {
			try {
				var st = JSON.parse(decodeURIComponent(escape(atob(inv))));
				state.rows = st.rows || []; state.theme = st.theme || 'petrol'; state.custom = st.custom || state.custom; state.invNo = st.invNo || '';
				editorBox.style.display = 'none';
				readonlyBox.style.display = 'block';
				readonlyBox.innerHTML = invoiceHTML();
				printBox.innerHTML = invoiceHTML();
			} catch(e) { /* ignore */ }
		} else {
			renderEditor();
		}
	})();
	</script>
	<?php
	return ob_get_clean();
}

/* ---------- لیست قیمت ---------- */
add_shortcode( 'tk_pricelist', 'tk_render_pricelist' );
function tk_render_pricelist() {
	global $wpdb; $p = $wpdb->prefix;
	$pm = array();
	foreach ( $wpdb->get_results( "SELECT preset_id, section_id, coef FROM {$p}tk_preset_coefs" ) as $r ) { $pm[ $r->preset_id ][ $r->section_id ] = $r->coef; }
	$lg = array();
	foreach ( $wpdb->get_results( "SELECT brand_id, section_id, coef FROM {$p}tk_coefficients" ) as $r ) { $lg[ $r->brand_id ][ $r->section_id ] = $r->coef; }
	$ov = array();
	foreach ( $wpdb->get_results( "SELECT brand_id, section_id, coef_on, coef_value FROM {$p}tk_brand_series" ) as $r ) { if ( $r->coef_on ) { $ov[ $r->brand_id ][ $r->section_id ] = $r->coef_value; } }
	$brands = $wpdb->get_results( "SELECT id, name_fa, name_en FROM {$p}tk_brands WHERE active=1" );
	$sections = $wpdb->get_results( "SELECT id, slug FROM {$p}tk_sections WHERE active=1 ORDER BY slug" );
	ob_start();
	?>
	<div class="tk-shop" dir="rtl">
	<h1>لیست قیمت به‌روز</h1>
	<?php tk_tools_nav(); ?>
	<input id="tk-pl-search" placeholder="جستجوی برند + سری — مثال: تایگر a" style="padding:10px;min-width:260px;margin-bottom:12px">
	<div style="max-height:70vh;overflow:auto">
	<?php
	foreach ( $brands as $b ) {
		$pid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT default_preset_id FROM {$p}tk_brands WHERE id=%d", $b->id ) );
		$rows_html = '';
		foreach ( $sections as $s ) {
			$coef = null;
			if ( isset( $ov[ $b->id ][ $s->id ] ) ) { $coef = $ov[ $b->id ][ $s->id ]; }
			elseif ( $pid && isset( $pm[ $pid ][ $s->id ] ) ) { $coef = $pm[ $pid ][ $s->id ]; }
			elseif ( isset( $lg[ $b->id ][ $s->id ] ) ) { $coef = $lg[ $b->id ][ $s->id ]; }
			if ( null === $coef || $coef <= 0 ) { continue; }
			$rows_html .= '<tr data-s="' . esc_attr( strtolower( $s->slug ) ) . '"><td>' . esc_html( $s->slug ) . '</td><td>' . esc_html( number_format_i18n( $coef ) ) . '</td></tr>';
		}
		if ( ! $rows_html ) { continue; }
		echo '<details class="tk-pl-brand" data-b="' . esc_attr( mb_strtolower( $b->name_fa ) . '|' . strtolower( $b->name_en ) ) . '" style="margin-bottom:6px">';
		echo '<summary style="padding:10px 14px;cursor:pointer;background:#fff;border:1px solid #ddd;border-radius:8px;list-style:none">' . esc_html( $b->name_fa ) . ' (' . esc_html( $b->name_en ) . ')</summary>';
		echo '<table class="tk-specs"><tr><th>سری</th><th>ضریب (ریال)</th></tr>' . $rows_html . '</table></details>';
	}
	?>
	</div>
	</div>
	<script>
	(function(){
		var s = document.getElementById('tk-pl-search');
		function apply(){
			var q = s.value.trim().toLowerCase();
			var tokens = q ? q.split(/\s+/) : [];
			document.querySelectorAll('.tk-pl-brand').forEach(function(d){
				var b = d.getAttribute('data-b'), any = false;
				d.querySelectorAll('tr[data-s]').forEach(function(tr){
					var slug = tr.getAttribute('data-s');
					var vis = tokens.every(function(t){ return b.indexOf(t) !== -1 || slug.indexOf(t) !== -1; });
					tr.style.display = vis ? '' : 'none';
					if ( vis ) any = true;
				});
				d.style.display = any ? '' : 'none';
				if ( q && any ) d.open = true;
			});
		}
		s.addEventListener('input', apply);
	})();
	</script>
	<?php
	return ob_get_clean();
}