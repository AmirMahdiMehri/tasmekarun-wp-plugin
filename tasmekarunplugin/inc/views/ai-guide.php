<?php defined( 'ABSPATH' ) || exit;
tk_calc_js_core();
$data = wp_json_encode( function_exists( 'tk_client_data' ) ? tk_client_data() : array( 'brands' => array(), 'sections' => array(), 'coef' => array() ) );
$calc_u = function_exists( 'tk_page_url_by_shortcode' ) ? tk_page_url_by_shortcode( '[tk_calculator]' ) : '';
$list_u = function_exists( 'tk_page_url_by_shortcode' ) ? tk_page_url_by_shortcode( '[tk_pricelist]' ) : '';
?>
<div class="tk-shop" dir="rtl">
<h1>راهنمای خرید تسمه</h1>
<p>متن روی تسمه یا مشخصاتش رو وارد کن تا معادلش رو پیدا کنیم.</p>
<div style="display:flex;gap:8px;flex-wrap:wrap">
<input id="tk-ai-q" style="flex:1;min-width:220px;padding:12px" placeholder="مثال: A52 | 8PK1333 | B-1270 Li | یا برند+مدل">
<button id="tk-ai-go" style="background:#0e3a40;color:#fff;border:none;border-radius:8px;padding:12px 20px;cursor:pointer;font-weight:700">پیدا کن</button>
</div>
<div id="tk-ai-res" style="margin-top:16px"></div>
<div id="tk-ai-help" style="display:none;margin-top:16px;border:1px dashed #bbb;border-radius:10px;padding:14px;background:#fafafa">
<strong>متن به‌صورت خودکار خوانده نشد؛ مشخصات ظاهری رو وارد کن:</strong>
<div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px">
<label>نوع: <select id="tk-ai-type" style="padding:10px">
<option value="v">تسمه پروانه‌ای (V)</option>
<option value="pk">شیاری (PK)</option>
<option value="cog">دندانه‌دار (X)</option>
<option value="time">تایمینگ</option>
<option value="flat">تسمه تخت</option>
</select></label>
<label>عرض (mm) / تعداد شیار: <input id="tk-ai-w" type="number" style="width:110px;padding:10px"></label>
<label>طول (mm): <input id="tk-ai-len" type="number" style="width:120px;padding:10px"></label>
</div>
<div id="tk-ai-sug" style="margin-top:10px"></div>
</div>
</div>
<script>
(function(){
var TK=<?php echo $data; ?>;
var E=tkInit(TK);
var W={'10':'Z','13':'A','17':'B','22':'C','32':'D','38':'E'};
function links(){ return '<div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">'
+( '<?php echo esc_url($calc_u); ?>' ? '<a href="<?php echo esc_url($calc_u); ?>" style="background:#0e3a40;color:#fff;padding:8px 14px;border-radius:8px;text-decoration:none">محاسبه قیمت</a>' : '' )
+( '<?php echo esc_url($list_u); ?>' ? '<a href="<?php echo esc_url($list_u); ?>" style="background:#585D61;color:#fff;padding:8px 14px;border-radius:8px;text-decoration:none">لیست قیمت</a>' : '' )
+'</div>'; }
function go(){
var q=document.getElementById('tk-ai-q').value.trim();
var res=document.getElementById('tk-ai-res'), help=document.getElementById('tk-ai-help');
if(!q){res.innerHTML='';help.style.display='none';return;}
var tokens=q.split(/\s+/), brandName='', idx=-1;
for(var i=0;i<tokens.length;i++){var sb=E.splitBrand(tokens[i]); if(sb){brandName=sb.b.name_fa;idx=i;break;}}
if(idx>-1) tokens.splice(idx,1);
var p=E.parseSku(tokens.join(' '));
if(p){
 res.innerHTML='<div style="border:1px solid #ddd;border-radius:10px;padding:16px;background:#fff"><strong>معادل تسمه شما: '+E.skuOf(p.section,p)+'</strong>'+(brandName?' — برند '+brandName:'')+'<div style="margin:6px 0;color:#555">سری: '+p.section+' | سایز: '+p.size+(p.ribs?' | شیار: '+p.ribs:'')+'</div>'+links()+'</div>';
 help.style.display='none';
}else{
 res.innerHTML='<p style="color:#b32d2e">متن به‌صورت خودکار خوانده نشد؛ مشخصات ظاهری را وارد کنید.</p>';
 help.style.display='block';
}
}
document.getElementById('tk-ai-go').addEventListener('click',go);
document.getElementById('tk-ai-q').addEventListener('keydown',function(e){if(e.key==='Enter')go();});
function sug(){
var t=document.getElementById('tk-ai-type').value;
var w=document.getElementById('tk-ai-w').value.trim();
var len=document.getElementById('tk-ai-len').value.trim();
var out='';
if(t==='v'){ var sec=W[w]||''; out= sec? 'پیشنهاد: تسمه '+sec+(len?' '+len:'') : 'عرض '+w+' استاندارد نیست؛ عرض‌های رایج: 10/13/17/22/32/38.'; }
else if(t==='pk'){ out= (w&&len)? 'پیشنهاد: '+w+'PK'+len : 'تعداد شیار و طول را وارد کنید.'; }
else if(t==='cog'){ var s2=W[w]?W[w]+'X':''; out= s2? 'پیشنهاد: '+s2+(len?' '+len:'') : 'عرض را به میلی‌متر وارد کنید (13/17/22…).'; }
else { out='این نوع نیاز به بررسی کارشناس دارد؛ از بخش «همکاری با ما» تماس بگیرید.'; }
document.getElementById('tk-ai-sug').innerHTML='<strong>'+out+'</strong>'+((t==='v'||t==='pk'||t==='cog')?links():'');
}
['tk-ai-type','tk-ai-w','tk-ai-len'].forEach(function(id){ var el=document.getElementById(id); el.addEventListener('input',sug); el.addEventListener('change',sug); });
})();
</script>