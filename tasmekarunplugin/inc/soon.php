<?php
defined( 'ABSPATH' ) || exit;

/* ---------- صفحهٔ «به‌زودی» برای بخش‌های معوق ---------- */
add_shortcode( 'tk_soon', 'tk_render_soon' );
function tk_render_soon( $atts ) {
	$a = shortcode_atts( array( 'title' => 'این بخش' ), $atts, 'tk_soon' );
	ob_start();
	?>
	<style>
	.tk-soon{direction:rtl;min-height:70vh;display:flex;flex-direction:column;justify-content:center;gap:18px;padding:60px 6vw}
	.tk-soon .gear{width:110px;height:110px;animation:tkspin 9s linear infinite}
	.tk-soon h1{font-size:clamp(2.4rem,7vw,5rem);font-weight:900;color:#0e3a40;margin:0}
	.tk-soon p{color:#585d61;font-size:1.05rem;margin:0;max-width:52ch}
	.tk-soon .marq{overflow:hidden;border-block:1px solid #0e3a4022;padding:10px 0}
	.tk-soon .marq div{display:flex;gap:40px;width:max-content;animation:tkmarq 18s linear infinite;font-weight:800;color:#0e3a40;white-space:nowrap}
	@keyframes tkspin{to{transform:rotate(360deg)}}
	@keyframes tkmarq{to{transform:translateX(50%)}}
	@media (prefers-reduced-motion:reduce){.tk-soon .gear,.tk-soon .marq div{animation:none}}
	</style>
	<div class="tk-soon">
	<svg class="gear" viewBox="0 0 100 100" fill="none" aria-hidden="true">
	  <circle cx="50" cy="50" r="18" stroke="#0e3a40" stroke-width="6"/>
	  <g stroke="#0e3a40" stroke-width="6" stroke-linecap="round">
	    <path d="M50 8v10M50 82v10M8 50h10M82 50h10M20 20l7 7M73 73l7 7M80 20l-7 7M27 73l-7 7"/>
	  </g>
	  <circle cx="50" cy="50" r="34" stroke="#7a8c2e" stroke-width="4" stroke-dasharray="10 8"/>
	</svg>
	<h1><?php echo esc_html( $a['title'] ); ?></h1>
	<p>این بخش به‌زودی افزوده خواهد شد؛ داریم چیزی می‌سازیم که ارزش انتظارش را داشته باشد.</p>
	<div class="marq"><div>
	  <span>به‌زودی</span><span>•</span><span>تسمه کارون</span><span>•</span><span>به‌زودی</span><span>•</span><span>تسمه کارون</span><span>•</span>
	  <span>به‌زودی</span><span>•</span><span>تسمه کارون</span><span>•</span><span>به‌زودی</span><span>•</span><span>تسمه کارون</span><span>•</span>
	</div></div>
	</div>
	<?php
	return ob_get_clean();
}