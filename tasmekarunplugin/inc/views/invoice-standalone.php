<?php
defined( 'ABSPATH' ) || exit;
$raw = (string) get_query_var( 'tk_inv' );
$data = json_decode( base64_decode( strtr( $raw, '-_', '+/' ) ), true );
if ( ! is_array( $data ) ) { status_header( 404 ); echo '<!doctype html><html dir="rtl"><body style="font-family:sans-serif;text-align:center;padding:60px">فاکتور نامعتبر است.</body></html>'; exit; }
$brand = isset( $data['custom'] ) ? $data['custom'] : array();
$on = ! empty( $brand['on'] );
$meta = isset( $data['meta'] ) ? $data['meta'] : array();
$store = $on ? ( ! empty( $brand['seller'] ) ? $brand['seller'] : 'فروشنده' ) : 'تسمه کارون';
$site = $on ? ( ! empty( $brand['site'] ) ? $brand['site'] : '' ) : ( isset( $meta['site'] ) ? $meta['site'] : 'tasmekarun.ir' );
$logo = $on ? ( ! empty( $brand['logo'] ) ? $brand['logo'] : '' ) : ( isset( $meta['logo'] ) ? $meta['logo'] : '' );
$t = isset( $data['totals'] ) ? $data['totals'] : array( 'total' => 0, 'disc' => 0, 'payable' => 0, 'pct' => 0 );
$rows = isset( $data['rows'] ) ? $data['rows'] : array();
?>
<!doctype html><html dir="rtl" lang="fa"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>فاکتور <?php echo esc_html( $data['invNo'] ); ?></title>
<style id="pagecss">@page{size:A4 portrait;margin:10mm}</style>
<style>
*{ -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
body{margin:0;background:#e9edee;font-family:inherit}
.tk-bar{position:sticky;top:0;display:flex;justify-content:space-between;align-items:center;gap:8px;padding:10px 16px;background:#0E3A40;color:#fff;z-index:9}
.tk-bar button{border:none;border-radius:8px;padding:8px 14px;cursor:pointer;font-weight:700;background:#ffffff22;color:#fff}
.tk-bar button.on{background:#5A7D2A}
.bar-left,.bar-right{display:flex;gap:6px}
.sheet{--inv-a:#0E3A40;--inv-c:#5A7D2A;width:794px;max-width:96%;margin:18px auto;background:#fff;color:#1a1a1a;border-radius:14px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,.15)}
body.a5 .sheet{width:595px;font-size:12px}
.inv-head{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:22px 26px;color:#fff;background:linear-gradient(135deg,var(--inv-a),#155a63)}
.inv-brand{display:flex;gap:12px;align-items:center}
.inv-logo{width:56px;height:56px;object-fit:contain;background:#fff;border-radius:12px;padding:6px}
.inv-logo-svg{width:56px;height:56px;background:#fff;border-radius:12px;padding:6px}
.inv-title{font-size:22px;font-weight:800}.inv-sub{font-size:12px;opacity:.9}
.inv-meta{text-align:left;font-size:12px;line-height:2}
.inv-accent{height:6px;background:linear-gradient(90deg,var(--inv-c),var(--inv-a),#585D61)}
table{width:100%;border-collapse:collapse;font-size:13px}
thead th{background:var(--inv-a);color:#fff;padding:10px 12px;font-weight:700}
tbody td{padding:9px 12px;border-bottom:1px solid #eee}
tbody tr:nth-child(even){background:#f6f8f8}
.inv-totals{padding:14px 26px;font-size:14px;line-height:2.1}
.inv-totals .pay{display:inline-block;background:linear-gradient(135deg,var(--inv-a),var(--inv-c));color:#fff;border-radius:10px;padding:8px 20px;font-size:16px;font-weight:800}
.inv-foot{display:flex;justify-content:space-between;gap:10px;padding:12px 26px;font-size:11px;color:#777;border-top:1px dashed #ddd}
@media print{.tk-bar{display:none}.sheet{margin:0;box-shadow:none;border-radius:0;max-width:100%}}
</style></head>
<body class="a4">
<div class="tk-bar">
	<div class="bar-right"><button id="btn-a4" class="on">A4</button><button id="btn-a5">A5</button></div>
	<div class="bar-left"><button id="btn-share">اشتراک</button><button id="btn-print">چاپ</button></div>
</div>
<div class="sheet">
	<div class="inv-head">
		<div class="inv-brand">
			<?php if ( $logo ) { echo '<img class="inv-logo" src="' . esc_url( $logo ) . '" alt="">'; } elseif ( ! $on ) { echo '<svg class="inv-logo-svg" viewBox="0 0 64 64"><circle cx="32" cy="38" r="15" fill="none" stroke="#0E3A40" stroke-width="6"/><circle cx="32" cy="15" r="5" fill="#0E3A40"/><path d="M8 38a24 24 0 0 0 48 0" fill="none" stroke="#585D61" stroke-width="4" stroke-dasharray="4 4"/></svg>'; } ?>
			<div><div class="inv-title"><?php echo esc_html( $store ); ?></div><?php if ( $site ) echo '<div class="inv-sub">' . esc_html( $site ) . '</div>'; ?></div>
		</div>
		<div class="inv-meta"><div>پیش‌فاکتور شماره: <?php echo esc_html( $data['invNo'] ); ?></div><div>تاریخ: <?php echo esc_html( date_i18n( 'Y/m/d' ) ); ?></div></div>
	</div>
	<div class="inv-accent"></div>
	<table><thead><tr><th>کالا</th><th>برند</th><th>ضریب واحد</th><th>قیمت تکی</th><th>تعداد</th><th>تخفیف</th><th>جمع</th></tr></thead><tbody>
	<?php foreach ( $rows as $r ) : ?>
		<tr><td><?php echo esc_html( $r['sku'] ); ?></td><td><?php echo esc_html( $r['brand'] ); ?></td><td><?php echo $r['coef'] != null ? esc_html( number_format_i18n( $r['coef'] ) ) : '—'; ?></td><td><?php echo esc_html( number_format_i18n( $r['unit'] ) ); ?> ریال</td><td><?php echo esc_html( $r['qty'] ); ?></td><td><?php echo $r['disc'] > 0 ? esc_html( number_format_i18n( $r['disc'] ) . '٪' ) : '—'; ?></td><td><?php echo esc_html( number_format_i18n( $r['net'] ) ); ?> ریال</td></tr>
	<?php endforeach; ?>
	</tbody></table>
	<div class="inv-totals">جمع کل: <?php echo esc_html( number_format_i18n( $t['total'] ) ); ?> ریال
		<?php if ( $t['disc'] > 0 ) echo '<br>تخفیف: −' . esc_html( number_format_i18n( $t['disc'] ) ) . ' ریال'; ?>
		<br><span class="pay">مبلغ قابل پرداخت: <?php echo esc_html( number_format_i18n( $t['payable'] ) ); ?> ریال</span></div>
	<div class="inv-foot"><span><?php echo $on ? esc_html( ! empty( $brand['seller'] ) ? $brand['seller'] : '' ) : ('تسمه کارون — ' . esc_html( $site )); ?></span><span><?php echo $on ? ( ! empty( $brand['phone'] ) ? 'تماس: ' . esc_html( $brand['phone'] ) : '' ) : ('تماس: ' . esc_html( isset( $meta['phone'] ) ? $meta['phone'] : '' )); ?></span></div>
</div>
<script>
(function(){
	var css=document.getElementById('pagecss');
	function setSize(s){
		document.body.className=s;
		css.textContent='@page{size:'+s.toUpperCase()+' portrait;margin:10mm}';
		document.getElementById('btn-a4').classList.toggle('on',s==='a4');
		document.getElementById('btn-a5').classList.toggle('on',s==='a5');
	}
	document.getElementById('btn-a4').addEventListener('click',function(){setSize('a4');});
	document.getElementById('btn-a5').addEventListener('click',function(){setSize('a5');});
	document.getElementById('btn-print').addEventListener('click',function(){window.print();});
	function copyText(u){
		function done(){ alert('لینک فاکتور کپی شد ✔'); }
		function fallback(){
			var ta=document.createElement('textarea'); ta.value=u; ta.style.position='fixed'; ta.style.opacity='0';
			document.body.appendChild(ta); ta.select();
			try{ document.execCommand('copy'); done(); }catch(e){ prompt('لینک را کپی کنید:', u); }
			document.body.removeChild(ta);
		}
		if ( navigator.clipboard && window.isSecureContext ) navigator.clipboard.writeText(u).then(done, fallback);
		else fallback();
	}
	document.getElementById('btn-share').addEventListener('click',function(){
		var u=location.href;
		copyText(u);
		if ( navigator.share ) navigator.share({title:document.title,url:u}).catch(function(){});
	});
})();
</script>
</body></html>