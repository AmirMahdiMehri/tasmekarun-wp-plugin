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

/* داده ضریب‌ها برای محاسبه آنی سمت مرورگر (ضریب‌ها طبق تصمیم مالک عمومی‌اند) */
function tk_client_data() {
	global $wpdb; $p = $wpdb->prefix;
	$brands = $wpdb->get_results( "SELECT id, name_fa, name_en, default_preset_id FROM {$p}tk_brands WHERE active=1" );
	$sections = $wpdb->get_results( "SELECT id, slug, formula_key, min_charge FROM {$p}tk_sections WHERE active=1" );
	$formulas = array();
	foreach ( $wpdb->get_results( "SELECT fkey, expr FROM {$p}tk_formulas" ) as $f ) { $formulas[ $f->fkey ] = $f->expr; }
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
	return array( 'brands' => $brands, 'sections' => $sections, 'formulas' => $formulas, 'coef' => $coef );
}

function tk_brands_datalist() {
	global $wpdb;
	$brands = $wpdb->get_results( "SELECT name_fa, name_en FROM {$wpdb->prefix}tk_brands WHERE active=1" );
	echo '<datalist id="tk-brands-list">';
	foreach ( $brands as $b ) { echo '<option value="' . esc_attr( $b->name_fa ) . '">' . esc_html( $b->name_en ) . '</option>'; }
	echo '</datalist>';
}

/* ---------- موتور JS مشترک ---------- */
function tk_calc_js_core() {
	?>
	<script>
	function tkInit(TK){
		var SECS = TK.sections.slice().sort(function(a,b){ return b.slug.length - a.slug.length; });
		var bySlug = {}; SECS.forEach(function(s){ bySlug[s.slug.toUpperCase()] = s; });
		function fmt(n){ return Number(n).toLocaleString('fa-IR'); }
		function findBrand(t){
			if ( ! t ) return null;
			var lt = t.toLowerCase();
			for ( var i=0;i<TK.brands.length;i++ ){
				var b = TK.brands[i];
				if ( b.name_en.toLowerCase() === lt || b.name_fa === t ) return b;
				if ( lt.length > 2 && ( b.name_en.toLowerCase().indexOf(lt) === 0 || b.name_fa.indexOf(t) === 0 ) ) return b;
			}
			return null;
		}
		function parseSku(raw){
			var s = (raw||'').toUpperCase().replace(/\s+/g,'');
			if ( ! s ) return null;
			var m = s.match(/^(\d+)PK(\d+)$/); if ( m ) return { section:'PK', size:+m[2], ribs:+m[1] };
			m = s.match(/^TIME(\d+)$/); if ( m ) return { section:'TIME', size:+m[1], ribs:null };
			m = s.match(/^(\d+)(R.+)$/);
			if ( m ) {
				var rest = m[2];
				for ( var i=0;i<SECS.length;i++ ){
					var sl = SECS[i].slug.toUpperCase();
					if ( rest.indexOf(sl) === 0 ){
						var num = rest.slice(sl.length);
						if ( /^\d+(\.\d+)?$/.test(num) ) return { section: SECS[i].slug, size:+num, ribs:+m[1] };
					}
				}
			}
			for ( var j=0;j<SECS.length;j++ ){
				var slg = SECS[j].slug.toUpperCase();
				if ( s.indexOf(slg) === 0 ){
					var r = s.slice(slg.length);
					if ( /^\d+(\.\d+)?$/.test(r) ) return { section: SECS[j].slug, size:+r, ribs:null };
				}
			}
			return null;
		}
		function evalExpr(expr, vars){
			var toks=[], i=0, n=expr.length, bad=false;
			while ( i<n ){
				var c = expr[i];
				if ( c===' '||c==='\t' ){ i++; continue; }
				if ( /[0-9.]/.test(c) ){ var j=i; while(j<n&&/[0-9.]/.test(expr[j]))j++; toks.push(['n',parseFloat(expr.slice(i,j))]); i=j; continue; }
				if ( /[A-Za-z_]/.test(c) ){ var k=i; while(k<n&&/[A-Za-z_]/.test(expr[k]))k++; var nm=expr.slice(i,k).toUpperCase(); if(!(nm in vars)){bad=true;break;} toks.push(['n',vars[nm]]); i=k; continue; }
				if ( '+-*/()'.indexOf(c)!==-1 ){ toks.push(['o',c]); i++; continue; }
				bad=true; break;
			}
			if ( bad ) return null;
			var out=[], ops=[], prec={'+':1,'-':1,'*':2,'/':2};
			for ( var t=0;t<toks.length;t++ ){
				var tk=toks[t];
				if ( tk[0]==='n' ){ out.push(tk); continue; }
				var o=tk[1];
				if ( o==='(' ){ ops.push(o); continue; }
				if ( o===')' ){ while(ops.length&&ops[ops.length-1]!=='(')out.push(['o',ops.pop()]); if(!ops.length)return null; ops.pop(); continue; }
				while(ops.length&&ops[ops.length-1]!=='('&&prec[ops[ops.length-1]]>=prec[o])out.push(['o',ops.pop()]);
				ops.push(o);
			}
			while(ops.length){ var o2=ops.pop(); if(o2==='(')return null; out.push(['o',o2]); }
			var st=[];
			for ( var x=0;x<out.length;x++ ){
				var t2=out[x];
				if ( t2[0]==='n' ){ st.push(t2[1]); continue; }
				if ( st.length<2 ) return null;
				var b=st.pop(), a=st.pop();
				if ( t2[1]==='+' ) st.push(a+b); else if ( t2[1]==='-' ) st.push(a-b); else if ( t2[1]==='*' ) st.push(a*b); else { if(!b) return null; st.push(a/b); }
			}
			return st.length===1 ? st[0] : null;
		}
		function compute(model, brandText, qty){
			var parsed = parseSku(model);
			if ( ! parsed ) return { ok:false, msg:'مدل نامشخص' };
			var brand = findBrand(brandText);
			if ( ! brand ) return { ok:false, msg:'برند نامشخص' };
			var sec = bySlug[parsed.section.toUpperCase()];
			if ( ! sec ) return { ok:false, msg:'سری نامشخص' };
			var cm = TK.coef[brand.id]; var coef = cm ? cm[sec.id] : undefined;
			if ( coef === undefined ) return { ok:false, msg:'ضریب تعریف نشده' };
			var expr = TK.formulas[sec.formula_key];
			if ( ! expr ) return { ok:false, msg:'فرمول تعریف نشده' };
			var len = Math.max(parsed.size, (sec.min_charge|0));
			var unit = evalExpr(expr, { LENGTH:len, RIBS:(parsed.ribs||1), COEF:coef });
			if ( unit === null ) return { ok:false, msg:'محاسبه نشد' };
			var q = qty|0 || 1;
			return { ok:true, sku:(parsed.ribs?parsed.ribs+sec.slug:sec.slug)+parsed.size, brand:brand.name_fa, coef:coef, unit:unit, qty:q, total:unit*q };
		}
		return { fmt:fmt, findBrand:findBrand, parseSku:parseSku, compute:compute };
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
		function run(){
			var v = line.value.trim();
			if ( ! v ) { box.innerHTML=''; return; }
			var tokens = v.split(/\s+/), qty = 1;
			if ( tokens.length > 1 && /^\d+$/.test(tokens[tokens.length-1]) ) { qty = +tokens.pop(); }
			var brandTok = null, idx = -1;
			for ( var i=0;i<tokens.length;i++ ){ if ( E.findBrand(tokens[i]) ){ brandTok = tokens[i]; idx = i; break; } }
			if ( idx > -1 ) tokens.splice(idx,1);
			var r = E.compute(tokens.join(' '), brandTok || '', qty);
			if ( ! r.ok ) { box.innerHTML = '<div style="border:1px solid #f3c1c1;background:#fdecea;color:#b32d2e;border-radius:10px;padding:12px 16px">' + r.msg + '</div>'; return; }
			var extra = r.qty > 1 ? ' | تعداد: ' + E.fmt(r.qty) + ' | <strong>قیمت کل: ' + E.fmt(r.total) + ' ریال</strong>' : '';
			box.innerHTML = '<div style="border:1px solid #ddd;border-radius:10px;padding:16px;background:#fff"><strong>تسمه ' + r.sku + ' — ' + r.brand + '</strong><div style="margin:8px 0">ضریب واحد: ' + E.fmt(r.coef) + ' | قیمت واحد: ' + E.fmt(r.unit) + ' ریال' + extra + '</div></div>';
		}
		line.addEventListener('input', run);
	})();
	</script>
	<?php
	return ob_get_clean();
}

/* ---------- پیش‌فاکتور: جدول هم‌تراز قابل ویرایش + برند پیشفرض ---------- */
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
				<button class="button" type="button" id="tk-pf-clear" onclick="return confirm('کل پیش‌فاکتور پاک شود؟');">پاک کردن</button>
			</p>
			<div id="tk-pf-total" style="margin-top:12px;font-size:20px;font-weight:700;color:#0e3a40"></div>
		</div>
		<div style="flex:1 1 46%;min-width:300px">
			<table class="tk-specs" id="tk-pf-table">
				<tr><th>کالا</th><th>برند</th><th>ضریب واحد</th><th>قیمت تکی</th><th>تعداد</th><th>جمع</th><th></th></tr>
				<tbody id="tk-pf-body"></tbody>
			</table>
		</div>
	</div>
	</div>
	<style>@media print{body *{visibility:hidden}#tk-pf,#tk-pf *{visibility:visible}#tk-pf{position:absolute;inset:0}}</style>
	<script>
	(function(){
		var TK = <?php echo $data; ?>;
		var E = tkInit(TK);
		var line = document.getElementById('tk-pf-line'), defBrand = document.getElementById('tk-pf-brand');
		var body = document.getElementById('tk-pf-body'), tot = document.getElementById('tk-pf-total');
		var rows = [];

		function splitLine(l){
			var tokens = l.split(/\s+/).filter(function(s){return s;});
			if ( ! tokens.length ) return null;
			var qty = 1;
			if ( tokens.length > 1 && /^\d+$/.test(tokens[tokens.length-1]) ) { qty = +tokens.pop(); }
			var brandTok = '', idx = -1;
			for ( var i=0;i<tokens.length;i++ ){ if ( E.findBrand(tokens[i]) ){ brandTok = tokens[i]; idx = i; break; } }
			if ( idx > -1 ) tokens.splice(idx,1);
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
			var html = '';
			rows.forEach(function(r,i){
				html += '<tr data-i="'+i+'">'
					+ '<td><input data-col="model" value="'+ (r.model||'').replace(/"/g,'&quot;') +'" style="width:110px"></td>'
					+ '<td><input data-col="brand" value="'+ (r.brand||'').replace(/"/g,'&quot;') +'" placeholder="برند" style="width:90px"></td>'
					+ '<td class="c-coef"></td><td class="c-unit"></td>'
					+ '<td><input data-col="qty" type="number" min="1" value="'+ (r.qty||1) +'" style="width:60px"></td>'
					+ '<td class="c-total"></td>'
					+ '<td><button type="button" class="button button-small tk-del">حذف</button></td></tr>';
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
			tot.textContent = 'جمع کل: ' + E.fmt(sum) + ' ریال';
		}
		line.addEventListener('keydown', function(e){
			if ( e.key === 'Enter' ) { e.preventDefault(); addLines(line.value); line.value=''; }
		});
		document.getElementById('tk-pf-clear').addEventListener('click', function(){ rows = []; render(); });
		defBrand.addEventListener('input', recalcAll);
		body.addEventListener('input', function(e){
			var tr = e.target.closest('tr'); if ( ! tr ) return;
			var i = +tr.getAttribute('data-i'), col = e.target.getAttribute('data-col');
			if ( col === 'model' ) rows[i].model = e.target.value;
			else if ( col === 'brand' ) rows[i].brand = e.target.value;
			else if ( col === 'qty' ) rows[i].qty = +e.target.value || 1;
			recalcRow(tr);
			var sum = 0; body.querySelectorAll('tr').forEach(function(t){ sum += t._total||0; });
			tot.textContent = 'جمع کل: ' + E.fmt(sum) + ' ریال';
		});
		body.addEventListener('click', function(e){
			if ( e.target.classList.contains('tk-del') ) {
				var i = +e.target.closest('tr').getAttribute('data-i');
				rows.splice(i,1); render();
			}
		});
		/* جابه‌جایی بین سلول‌ها با فلش‌ها و Enter */
		body.addEventListener('keydown', function(e){
			var inp = e.target.closest('input'); if ( ! inp ) return;
			var tr = inp.closest('tr');
			var move = function(dir){
				var target = dir > 0 ? tr.nextElementSibling : tr.previousElementSibling;
				if ( target ) { var n = target.querySelector('input[data-col="'+inp.getAttribute('data-col')+'"]'); if ( n ) { e.preventDefault(); n.focus(); } }
				else if ( dir > 0 ) { e.preventDefault(); line.focus(); }
			};
			if ( e.key === 'ArrowDown' ) move(1);
			else if ( e.key === 'ArrowUp' ) move(-1);
			else if ( e.key === 'Enter' ) { e.preventDefault(); move(1); }
		});
	})();
	</script>
	<?php
	return ob_get_clean();
}

/* ---------- لیست قیمت (کشویی، بدون تغییر) ---------- */
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