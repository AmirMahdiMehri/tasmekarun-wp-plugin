<?php defined( 'ABSPATH' ) || exit;
global $wpdb; $p = $wpdb->prefix;
$done = isset( $_GET['tk_rev'] ) && 'ok' === $_GET['tk_rev'];
$data = function_exists( 'tk_client_data' ) ? tk_client_data() : null;
$rows = $wpdb->get_results( "SELECT b.id, b.name_fa, b.name_en, c.rating, c.note FROM {$p}tk_brands b JOIN {$p}tk_compare c ON c.brand_id=b.id WHERE b.active=1 ORDER BY c.sort ASC, b.id ASC" );
?>
<div class="tk-shop" dir="rtl">
<h1>مقایسه برندها</h1>
<?php if ( $done ) : ?><div style="border:1px solid #cfe8cf;background:#e8f5e9;color:#2e7d32;border-radius:10px;padding:12px 16px;margin-bottom:16px">دیدگاه شما ثبت شد و پس از تأیید نمایش داده می‌شود ✔</div><?php endif; ?>
<p>رتبه‌بندی برندها توسط کارشناسان تسمه کارون؛ برای دیدن قیمت‌ها و دیدگاه‌ها هر برند را باز کنید.</p>
<?php $rank = 0; foreach ( $rows as $b ) : $rank++;
	$coefmap = ( $data && isset( $data['coef'][ $b->id ] ) ) ? $data['coef'][ $b->id ] : array();
	$reviews = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$p}tk_compare_reviews WHERE brand_id=%d AND status=1 ORDER BY id DESC", $b->id ) );
?>
<details style="border:1px solid #ddd;border-radius:10px;margin-bottom:10px;background:#fff">
<summary style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:12px 16px;cursor:pointer;list-style:none">
	<span style="font-weight:800;color:#0e3a40">#<?php echo $rank; ?></span>
	<strong><?php echo esc_html( $b->name_fa ); ?> (<?php echo esc_html( $b->name_en ); ?>)</strong>
	<span style="color:#f5a623"><?php echo str_repeat( '★', (int) $b->rating ) . str_repeat( '☆', 5 - (int) $b->rating ); ?></span>
	<?php if ( $b->note ) echo '<small style="color:#666">' . esc_html( $b->note ) . '</small>'; ?>
</summary>
<div style="padding:0 16px 16px">
	<?php if ( $data ) : ?>
	<h4>لیست قیمت (سینک با موتور)</h4>
	<table class="tk-specs"><tr><th>سری</th><th>ضریب (ریال)</th></tr>
	<?php foreach ( $data['sections'] as $s ) : $c = isset( $coefmap[ $s->id ] ) ? $coefmap[ $s->id ] : null; if ( null === $c ) continue; ?>
		<tr><td><?php echo esc_html( $s->slug ); ?></td><td><?php echo esc_html( number_format_i18n( $c ) ); ?></td></tr>
	<?php endforeach; ?>
	</table>
	<?php endif; ?>
	<h4>دیدگاه کاربران (<?php echo count( $reviews ); ?>)</h4>
	<?php if ( ! $reviews ) echo '<p style="color:#777">هنوز دیدگاهی ثبت نشده.</p>'; ?>
	<?php foreach ( $reviews as $rv ) : ?>
		<div style="border-top:1px dashed #eee;padding:8px 0">
			<strong><?php echo esc_html( $rv->name ); ?></strong> <span style="color:#f5a623"><?php echo str_repeat( '★', (int) $rv->rating ); ?></span>
			<p style="margin:4px 0 0;color:#444"><?php echo esc_html( $rv->message ); ?></p>
		</div>
	<?php endforeach; ?>
	<h4>ثبت دیدگاه</h4>
	<form method="post" style="display:grid;gap:8px;max-width:480px">
		<?php wp_nonce_field( 'tk_rev', 'tk_rev_nonce' ); ?>
		<input type="hidden" name="rev_brand" value="<?php echo (int) $b->id; ?>">
		<input name="rev_name" required placeholder="نام شما" style="padding:10px">
		<select name="rev_rating" style="padding:10px"><?php for ( $i = 5; $i >= 1; $i-- ) echo '<option value="' . $i . '">' . $i . ' ستاره</option>'; ?></select>
		<textarea name="rev_message" rows="3" required placeholder="تجربه شما از این برند…" style="padding:10px"></textarea>
		<button name="tk_review_submit" value="1" style="background:#0e3a40;color:#fff;border:none;border-radius:8px;padding:10px;cursor:pointer;font-weight:700">ارسال دیدگاه</button>
	</form>
</div>
</details>
<?php endforeach; ?>
</div>