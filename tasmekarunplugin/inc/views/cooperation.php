<?php defined( 'ABSPATH' ) || exit; ?>
<div class="tk-shop" dir="rtl">
<h1>همکاری با تسمه کارون</h1>
<?php if ( $done ) : ?><div style="border:1px solid #cfe8cf;background:#e8f5e9;color:#2e7d32;border-radius:10px;padding:12px 16px;margin-bottom:16px">درخواست شما ثبت شد ✔ — به‌زودی تماس می‌گیریم.</div><?php endif; ?>
<p>خرید عمده، همکاری پورسانتی یا واردات از چین؛ فرم زیر را پر کنید تا کارشناس ما با شما تماس بگیرد.</p>
<form method="post" style="max-width:560px;display:grid;gap:10px">
<?php wp_nonce_field( 'tk_coop', 'tk_coop_nonce' ); ?>
<label>نام و نام خانوادگی *<input name="coop_name" required style="width:100%;padding:10px"></label>
<label>شماره تماس *<input name="coop_phone" required dir="ltr" style="width:100%;padding:10px"></label>
<label>نوع همکاری *
<select name="coop_type" style="width:100%;padding:10px">
<option value="خرید عمده">خرید عمده</option>
<option value="همکاری پورسانتی">همکاری پورسانتی</option>
<option value="واردات از چین">واردات از چین</option>
<option value="سایر">سایر</option>
</select></label>
<label>شرکت / فروشگاه (اختیاری)<input name="coop_company" style="width:100%;padding:10px"></label>
<label>توضیحات (اختیاری)<textarea name="coop_message" rows="4" style="width:100%;padding:10px"></textarea></label>
<button type="submit" name="tk_coop_submit" value="1" style="background:#0e3a40;color:#fff;padding:12px 22px;border:none;border-radius:8px;cursor:pointer;font-weight:700">ارسال درخواست</button>
</form>
</div>