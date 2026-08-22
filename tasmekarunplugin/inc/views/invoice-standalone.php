<?php
defined( 'ABSPATH' ) || exit;
$raw  = (string) get_query_var( 'tk_inv' );
$data = json_decode( base64_decode( strtr( $raw, '-_', '+/' ) ), true );
if ( ! is_array( $data ) ) { status_header( 404 ); echo '<!doctype html><html dir="rtl"><body style="font-family:sans-serif;text-align:center;padding:60px">فاکتور نامعتبر است.</body></html>'; exit; }
$brand = isset( $data['custom'] ) ? (array) $data['custom'] : array();
$on    = ! empty( $brand['on'] );
$meta  = isset( $data['meta'] ) ? (array) $data['meta'] : array();
$store = $on ? ( ! empty( $brand['seller'] ) ? $brand['seller'] : 'فروشنده' ) : 'تسمه کارون';
$site  = $on ? ( ! empty( $brand['site'] ) ? $brand['site'] : '' ) : ( isset( $meta['site'] ) ? $meta['site'] : 'tasmekarun.ir' );
$logo  = $on ? ( ! empty( $brand['logo'] ) ? $brand['logo'] : '' ) : ( isset( $meta['logo'] ) ? $meta['logo'] : '' );
$phone = $on ? ( ! empty( $brand['phone'] ) ? $brand['phone'] : '' ) : ( isset( $meta['phone'] ) ? $meta['phone'] : '' );
$buyer      = isset( $brand['buyer'] ) ? (string) $brand['buyer'] : '';
$buyerphone = isset( $brand['buyerphone'] ) ? (string) $brand['buyerphone'] : '';
$buyeraddr  = isset( $brand['buyeraddr'] ) ? (string) $brand['buyeraddr'] : '';
$date  = ! empty( $brand['date'] ) ? $brand['date'] : ( function_exists( 'tk_jalali_str' ) ? tk_jalali_str() : '' );
$t     = isset( $data['totals'] ) ? (array) $data['totals'] : array( 'total' => 0, 'disc' => 0, 'payable' => 0, 'pct' => 0 );
$rows  = isset( $data['rows'] ) ? (array) $data['rows'] : array();
$col_a = function_exists( 'tk_theme_primary_color' ) ? tk_theme_primary_color() : '#1c61e7';
$col_b = function_exists( 'tk_theme_secondary_color' ) ? tk_theme_secondary_color() : '#edf0f8';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>فاکتور خرید</title>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap" rel="stylesheet">
<style id="pagecss">@page{size:210mm 280mm;margin:0}</style>
<style>
  *{box-sizing:border-box;margin:0;padding:0;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}
  body{font-family:'Vazirmatn',Tahoma,sans-serif;background:#e9e9e9;color:#1a1a1a}
  .tk-bar{position:sticky;top:0;display:flex;justify-content:space-between;align-items:center;gap:8px;padding:10px 16px;background:#fff;border-bottom:1px solid #e3e7ea;z-index:9}
  .tk-bar button{border:1px solid #d7dce2;border-radius:8px;padding:8px 14px;cursor:pointer;font-weight:700;background:#fff;color:#3c4652;font-family:inherit}
  .tk-bar button.on{background:var(--inv-a);border-color:var(--inv-a);color:#fff}
  .bar-left,.bar-right{display:flex;gap:6px}
  .page{--inv-a:<?php echo esc_attr( $col_a ); ?>;--inv-b:<?php echo esc_attr( $col_b ); ?>;width:210mm;min-height:280mm;margin:12px auto;background:#fff;
        padding:40px 38px 50px;box-shadow:0 1px 5px rgba(0,0,0,.3);position:relative}
  body.a5 .page{width:148mm;min-height:210mm;padding:24px 20px 44px;font-size:11px}
  @media print{body{background:none}.tk-bar{display:none}.page{margin:0;box-shadow:none}}

  .title{text-align:center;font-size:20px;font-weight:700;margin-bottom:26px}

  .header{display:flex;justify-content:space-between;align-items:flex-start;margin-top:8px}
  .logo{text-align:center;width:70px}
  .logo svg{width:57px;height:57px;display:block;margin:0 auto 2px}
  .logo img{width:57px;height:57px;object-fit:contain;display:block;margin:0 auto 2px}
  .logo .name{font-size:15px;font-weight:700;color:var(--inv-a)}
  .factor-info{margin-left:126px;font-size:13px;line-height:28px}

  .parties{display:flex;justify-content:space-between;margin-top:62px;font-size:13px}
  .parties .left{margin-left:220px}
  .hamrah{margin-top:20px;font-size:13px}

  .table-wrap{border:1px solid #cfd4df;border-radius:8px;overflow:hidden;margin-top:10px}
  table{width:100%;border-collapse:collapse;font-size:13px}
  thead th{background:var(--inv-b);font-weight:700;text-align:center;padding:9px 5px;
           border-left:1px solid #d8dce6}
  thead th:last-child{border-left:none}
  tbody td{padding:8px 6px;border-top:1px solid #d8dce6;border-left:1px solid #d8dce6;
           text-align:center}
  tbody td:last-child{border-left:none}
  td.start{text-align:right}
  .num{font-weight:700}
  .unit{color:#9aa1ad;font-size:10px}

  .totals{width:280px;margin-top:14px;margin-right:auto;margin-left:38px;font-size:13px}
  .trow{display:flex;justify-content:space-between;align-items:center;padding:10px 0}
  .trow.border{border-bottom:1px solid #ddd}
  .tval{font-weight:700;font-size:15px}

  .signature{margin-top:72px;font-size:14px}

  /* فوتر چسبیده به انتهای مطلق صفحه */
  .footer{position:absolute;bottom:0;left:0;right:0;background:var(--inv-b);height:42px;
          display:flex;align-items:center;justify-content:space-between;padding:0 18px}
  .footer .design{display:flex;align-items:center;gap:9px;color:#8f8f8f;font-size:12px}
  .footer .design svg{width:22px;height:22px}
  .footer .design .name{color:var(--inv-a);font-weight:700;font-size:15px}
  .footer .url{color:#9b9b9b;font-size:12px;direction:ltr}
</style>
</head>
<body class="a4">
<div class="tk-bar">
<div class="bar-right"><button id="btn-a4" class="on">A4</button><button id="btn-a5">A5</button></div>
<div class="bar-left"><button id="btn-share">اشتراک</button><button id="btn-print">چاپ</button></div>
</div>
<div class="page">

  <h1 class="title">فاکتور خرید</h1>

  <div class="header">
    <div class="logo">
      <?php if ( $logo ) { echo '<img src="' . esc_url( $logo ) . '" alt="">'; } elseif ( ! $on ) { ?>
      <svg viewBox="0 0 48 48">
        <circle cx="10" cy="10" r="6" class="lg-b"/><circle cx="38" cy="10" r="6" class="lg-b"/>
        <circle cx="10" cy="38" r="6" class="lg-b"/><circle cx="38" cy="38" r="6" class="lg-b"/>
        <path d="M13 35 C 20 28, 28 20, 35 13" class="lg-a" stroke-width="9" stroke-linecap="round" fill="none"/>
        <style>.lg-b{fill:var(--inv-b)}.lg-a{stroke:var(--inv-a)}</style>
      </svg>
      <?php } ?>
      <div class="name"><?php echo esc_html( $store ); ?></div>
    </div>
    <div class="factor-info">
      <div><b>شماره فاکتور:</b> <?php echo esc_html( $data['invNo'] ); ?>-</div>
      <div><b>تاریخ صدور:</b> <?php echo esc_html( $date ); ?></div>
      <div><b>تاریخ سررسید:</b> <?php echo esc_html( $date ); ?></div>
    </div>
  </div>

  <div class="parties">
    <div><b>شخص/شرکت:</b> <?php echo esc_html( $store ); ?></div>
    <div class="left"><b>شخص/شرکت:</b> <?php echo esc_html( $buyer !== '' ? $buyer : '—' ); ?></div>
  </div>
  <div class="hamrah"><b>همراه:</b> <?php echo esc_html( trim( $buyerphone . ( $buyeraddr ? ' — ' . $buyeraddr : '' ) ) ); ?></div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:4%">#</th>
          <th style="width:7%">کد کالا</th>
          <th style="width:19%">کالا/خدمات</th>
          <th style="width:14%">مقدار/واحد</th>
          <th style="width:18%">مبلغ واحد</th>
          <th style="width:12%">تخفیف</th>
          <th style="width:11%">مالیات</th>
          <th style="width:15%">مبلغ کل</th>
        </tr>
      </thead>
      <tbody>
        <?php $i = 0; foreach ( $rows as $r ) : $i++; ?>
        <tr>
          <td><?php echo (int) $i; ?></td>
          <td><?php echo esc_html( $r['sku'] ); ?></td>
          <td><?php echo esc_html( $r['brand'] ? 'تسمه برند ' . $r['brand'] : $r['sku'] ); ?></td>
          <td class="start"><span class="num"><?php echo esc_html( number_format_i18n( $r['qty'] ) ); ?></span> <span class="unit">عدد</span></td>
          <td class="start"><span class="num"><?php echo esc_html( number_format_i18n( $r['unit'] ) ); ?></span> <span class="unit">ریال</span></td>
          <td><?php echo $r['disc'] > 0 ? esc_html( number_format_i18n( $r['disc'] ) . '٪' ) : '۰'; ?></td>
          <td>۰</td>
          <td class="start"><span class="num"><?php echo esc_html( number_format_i18n( $r['net'] ) ); ?></span> <span class="unit">ریال</span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="totals">
    <div class="trow border">
      <b>مبلغ کل</b>
      <span class="tval"><?php echo esc_html( number_format_i18n( $t['total'] ) ); ?> <span class="unit">ریال</span></span>
    </div>
    <?php if ( $t['disc'] > 0 ) : ?>
    <div class="trow border">
      <b>تخفیف</b>
      <span class="tval">−<?php echo esc_html( number_format_i18n( $t['disc'] ) ); ?> <span class="unit">ریال</span></span>
    </div>
    <?php endif; ?>
    <div class="trow">
      <b>مجموع</b>
      <span class="tval"><?php echo esc_html( number_format_i18n( $t['payable'] ) ); ?> <span class="unit">ریال</span></span>
    </div>
  </div>

  <div class="signature">محل امضا خریدار</div>

  <div class="footer">
    <div class="design">
      <svg viewBox="0 0 48 48">
        <circle cx="11" cy="11" r="6" class="lg-b2"/>
        <circle cx="37" cy="37" r="6" class="lg-b2"/>
        <path d="M11 37 L37 11" class="lg-a2" stroke-width="9" stroke-linecap="round"/>
        <style>.lg-b2{fill:var(--inv-b)}.lg-a2{stroke:var(--inv-a)}</style>
      </svg>
      <span class="name"><?php echo esc_html( $store ); ?></span>
    </div>
    <div class="url"><?php echo esc_html( trim( ( $site ? $site : '' ) . ( $phone ? ' | ' . $phone : '' ) ) ); ?></div>
  </div>

</div>
<script>
(function(){
var css=document.getElementById('pagecss');
function setSize(s){
document.body.className=s;
css.textContent='@page{size:'+(s==='a4'?'210mm 280mm':'148mm 210mm')+';margin:0}';
document.getElementById('btn-a4').classList.toggle('on',s==='a4');
document.getElementById('btn-a5').classList.toggle('on',s==='a5');
}
document.getElementById('btn-a4').addEventListener('click',function(){setSize('a4');});
document.getElementById('btn-a5').addEventListener('click',function(){setSize('a5');});
document.getElementById('btn-print').addEventListener('click',function(){window.print();});
function copyText(u){
function done(){ alert('لینک فاکتور کپی شد ✔'); }
function fallback(){
var ta=document.createElement('textarea'); ta.value=u; ta.style.position='fixed'; ta.opacity='0';
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
</body>
</html>