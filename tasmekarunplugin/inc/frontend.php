<?php
defined( 'ABSPATH' ) || exit;

/* کاربرد: [tk_card sku="B70" brand="rhino"] */
add_shortcode( 'tk_card', 'tk_render_card' );
function tk_render_card( $atts ) {
	$a = shortcode_atts( array( 'sku' => '', 'brand' => '' ), $atts );
	global $wpdb; $p = $wpdb->prefix;

	$b = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM {$p}tk_brands WHERE slug=%s OR LOWER(name_en)=LOWER(%s) OR name_fa=%s",
		$a['brand'], $a['brand'], $a['brand'] ) );
	if ( ! $b ) { return '<div class="tk-card tk-error">برند پیدا نشد: ' . esc_html( $a['brand'] ) . '</div>'; }

	$parsed = TK_Engine::parse_sku( $a['sku'] );
	if ( ! $parsed ) { return '<div class="tk-card tk-error">مدل ناشناخته: ' . esc_html( $a['sku'] ) . '</div>'; }

	$sec   = TK_Engine::section_by_slug( $parsed['section'] );
	$price = TK_Engine::price( $b->id, $parsed );
	$stock = $sec ? TK_Engine::in_stock( $b->id, (int) $sec->id, $parsed['size'] ) : false;
	$set   = get_option( 'tk_settings', array() );
	$phone = isset( $set['phone'] ) ? $set['phone'] : '';

	ob_start(); ?>
	<div class="tk-card" dir="rtl" style="border:1px solid #ccc;padding:16px;margin:10px 0;max-width:340px;font-family:inherit">
		<h3 style="margin:0 0 8px"><?php echo esc_html( strtoupper( str_replace( ' ', '', $a['sku'] ) ) . ' — ' . $b->name_fa ); ?></h3>
		<div style="margin-bottom:8px"><strong>قیمت:</strong> <?php echo $price ? esc_html( TK_Engine::fmt( $price ) ) : '— (ضریب/فرمول تعریف نشده)'; ?></div>
		<div style="margin-bottom:12px"><strong>وضعیت:</strong> <?php echo $stock ? '✅ موجود در انبار' : '⚠️ ناموجود — تأمین از همکاران'; ?></div>
		<?php if ( $stock ) : ?>
			<button class="tk-add" style="width:100%;padding:10px;cursor:pointer">افزودن به سبد خرید</button>
		<?php else : ?>
			<a class="tk-call" style="display:block;text-align:center;padding:10px;background:#0e3a40;color:#fff;text-decoration:none" href="tel:<?php echo esc_attr( $phone ); ?>">برای خرید تماس بگیرید: <?php echo esc_html( $phone ); ?></a>
		<?php endif; ?>
	</div>
	<?php return ob_get_clean();
}