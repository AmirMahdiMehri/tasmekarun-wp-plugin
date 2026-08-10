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
			var sec = null;
			for ( var i=0;i<SECS.length;i++ ) if ( SECS[i].slug === p.section ) sec = SECS[i];
			if ( ! sec ) return { ok:false, msg:'سری نامشخص است' };
			var cm = TK.coef[sb.b.id] || {};
			var coef = cm[sec.id];
			if ( coef === undefined ) return { ok:false, msg:'ضریب این برند/سری تعریف نشده' };
			var len = Math.max(p.size, parseInt(sec.min_charge||0,10)||0);
			var unit = null;
			if ( sec.formula_key === 'LEN_COEF' ) unit = len * coef;
			else if ( sec.formula_key === 'RIBS_LEN_COEF' ) unit = p.ribs ? p.ribs * len * coef : null;
			if ( unit === null ) return { ok:false, msg:'فرمول این سری تعریف نشده؛ تماس بگیرید' };
			var q = parseInt(qty,10) || 1;
			return { ok:true, sku:(p.ribs?p.ribs+sec.slug:sec.slug)+p.size, brand:sb.b.name_fa, coef:coef, unit:unit, qty:q, total:unit*q };
		}
		/* پیشنهاد هوشمند برند هنگام تایپ (سریع، سمت مرورگر) */
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
					html += '<div data-v="' + (hasFa(items[hi===i?i:0].name_fa) && i===hi ? b.name_fa : (i===hi ? (hasFa(cur()) ? b.name_fa : b.name_en) : (hasFa(cur()) ? b.name_fa : b.name_en))) + '" style="padding:8px 12px;cursor:pointer;' + (i===hi?'background:#e8f0f5;':'') + '">' + b.name_fa + ' <small style="color:#777">(' + b.name_en + ')</small></div>';
				});
				box.innerHTML = html;
			}
			function cur(){ var t = tokenAtCaret(); return t.cur; }
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
				else if ( e.key === 'Tab' ){ e.preventDefault(); pick( items[ hi >= 0 ? hi : 0 ] ? (hasFa(cur()) ? items[hi>=0?hi:0].name_fa : items[hi>=0?hi:0].name_en) : '' ); }
				else if ( e.key === 'Escape' ){ close(); }
				else if ( e.key === 'Enter' ){ close(); }
			});
			document.addEventListener('click', function(e){ if ( box && ! box.contains(e.target) && e.target !== inp ) close(); });
		}
		return { fmt:fmt, splitBrand:splitBrand, parseSku:parseSku, compute:compute, attachBrandSuggest:attachBrandSuggest };
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

/* ---------- پیش‌فاکتور ---------- */
add_shortcode( 'tk_proforma', 'tk_render_proforma' );
function tk_render_proforma() {
	$data = wp_json_encode( tk_client_data() );
	ob_start();
	tk_calc_js_core();
	?>
	<div class="tk-shop" id="tk-pf" dir="rtl">
	<h1>پیش‌فاکتور — تسمه کارون</h1>
	<?php tk_tools_nav(); ?>
	<div style="margin-bottom:10px">
		<label>برند پیشفرض: <input id="tk-pf-brand" list="tk-brands-list" placeholder="مثلاً تایگر (اختیاری)" style="padding:10px;min-width:200px"></label>
		<?php tk_brands_datalist(); ?>
	</div>
	<div style="display:flex;gap:18px;flex-wrap:wrap;align-items:flex-start">
		<div style="flex:0 1 46%;min-width:280px">
			<input id="tk-pf-line" style="width:100%;padding:12px;box-sizing:border-box"
				placeholder="مدل+سایز (چسبیده) + برند (اختیاری) + تعداد — Enter = ثبت خط&#10;مثال: a22 12 یا c90 دانگیل 10">
			<p style="margin-top:10px">
				<button class="button" type="button" onclick="window.print()">چاپ</button>
				<button class="button" type="button" id="tk-pf-clear">پاک کردن</button>
			</p>
			<div id="tk-pf-total" style="margin-top:12px;font-size:20px;font-weight:700;color:#0e3a40"></div>
		</div>
		<div id="tk-pf-print" style="flex:1 1 46%;min-width:300px">
			<table class="tk-specs" id="tk-pf-table">
				<tr><th>کالا</th><th>برند</th><th>ضریب واحد</th><th>قیمت تکی</th><th>تعداد</th><th>جمع</th><th class="tk-del-col"></th></tr>
				<tbody id="tk-pf-body"></tbody>
			</table>
			<div id="tk-pf-total2" style="margin-top:10px;font-size:18px;font-weight:700;color:#0e3a40"></div>
		</div>
	</div>
	</div>
	<style>
	@media print{
		body *{visibility:hidden}
		#tk-pf-print, #tk-pf-print *{visibility:visible}
		#tk-pf-print{position:absolute;inset:0;width:100%}
		#tk-pf-print input{border:none;background:none}
		#tk-pf-print .tk-del, #tk-pf-print .tk-del-col{display:none}
	}
	</style>
	<script>
	(function(){
		var TK = <?php echo $data; ?>;
		var E = tkInit(TK);
		var line = document.getElementById('tk-pf-line'), defBrand = document.getElementById('tk-pf-brand');
		var body = document.getElementById('tk-pf-body');
		var tot = document.getElementById('tk-pf-total'), tot2 = document.getElementById('tk-pf-total2');
		var rows = [];
		E.attachBrandSuggest(line);
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
				if ( p && p.model ) rows.push(p);
			});
			render();
		}
		function render(){
			var db = defBrand.value.trim();
			var html = '';
			rows.forEach(function(r,i){
				html += '<tr data-i="'+i+'">'
					+ '<td><input data-col="model" value="'+ String(r.model||'').replace(/"/g,'&quot;') +'" style="width:110px"></td>'
					+ '<td><input data-col="brand" value="'+ String(r.brand||db).replace(/"/g,'&quot;') +'" placeholder="برند" style="width:90px"></td>'
					+ '<td class="c-coef"></td><td class="c-unit"></td>'
					+ '<td><input data-col="qty" type="number" min="1" value="'+ (r.qty||1) +'" style="width:60px"></td>'
					+ '<td class="c-total"></td>'
					+ '<td class="tk-del"><button type="button" class="button button-small tk-del-btn">حذف</button></td></tr>';
			});
			body.innerHTML = html;
			recalcAll();
		}
		function recalcRow(tr){
			var i = +tr.getAttribute('data-i'), r = rows[i];
			if ( ! r ) return;
			var res = E.compute(r.model, r.brand || defBrand.value, r.qty);
			tr.querySelector('.c-coef').textContent = res.ok ? E.fmt(res.coef) : '—';
			tr.querySelector('.c-unit').textContent = res.ok ? E.fmt(res.unit)+' ریال' : (res.msg||'—');
			tr.querySelector('.c-total').textContent = res.ok ? E.fmt(res.total)+' ریال' : '—';
			tr._total = res.ok ? res.total : 0;
		}
		function recalcAll(){
			var sum = 0;
			body.querySelectorAll('tr').forEach(function(tr){ recalcRow(tr); sum += tr._total||0; });
			var t = 'جمع کل: ' + E.fmt(sum) + ' ریال';
			tot.textContent = t; tot2.textContent = t;
		}
		line.addEventListener('keydown', function(e){
			if ( e.key === 'Enter' ) { e.preventDefault(); try { addLines(line.value); line.value=''; } catch(err){ tot.textContent = 'خطا: ' + err.message; } }
		});
		document.getElementById('tk-pf-clear').addEventListener('click', function(){ rows = []; render(); });
		defBrand.addEventListener('input', function(){ try { render(); } catch(e){} });
		body.addEventListener('input', function(e){
			var tr = e.target.closest('tr'); if ( ! tr ) return;
			var i = +tr.getAttribute('data-i'), col = e.target.getAttribute('data-col');
			if ( col === 'model' ) rows[i].model = e.target.value;
			else if ( col === 'brand' ) rows[i].brand = e.target.value;
			else if ( col === 'qty' ) rows[i].qty = +e.target.value || 1;
			try { recalcRow(tr); var sum = 0; body.querySelectorAll('tr').forEach(function(t){ sum += t._total||0; }); var t = 'جمع کل: ' + E.fmt(sum) + ' ریال'; tot.textContent = t; tot2.textContent = t; } catch(err){ tot.textContent = 'خطا: ' + err.message; }
		});
		body.addEventListener('click', function(e){
			if ( e.target.classList.contains('tk-del-btn') ) {
				var i = +e.target.closest('tr').getAttribute('data-i');
				rows.splice(i,1); render();
			}
		});
		body.addEventListener('keydown', function(e){
			var inp = e.target.closest('input'); if ( ! inp ) return;
			var tr = inp.closest('tr');
			function move(dir){
				var target = dir > 0 ? tr.nextElementSibling : tr.previousElementSibling;
				if ( target ) { var n = target.querySelector('input[data-col="'+inp.getAttribute('data-col')+'"]'); if ( n ) { e.preventDefault(); n.focus(); } }
				else if ( dir > 0 ) { e.preventDefault(); line.focus(); }
			}
			if ( e.key === 'ArrowDown' ) move(1);
			else if ( e.key === 'ArrowUp' ) move(-1);
			else if ( e.key === 'Enter' ) { e.preventDefault(); move(1); }
		});
	})();
	</script>
	<?php
	return ob_get_clean();
}

/* ---------- لیست قیمت (بدون تغییر) ---------- */
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