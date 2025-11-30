<?php
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Front-end renderer and assets.
 */
class SNP_Frontend
{

	public function __construct()
	{
		add_action('wp_enqueue_scripts', array($this, 'enqueue'));

		$hook = $this->get_render_hook();
		if ('wp_footer' === $hook) {
			add_action('wp_footer', array($this, 'render'), 1);
		} elseif ('header' === $hook) {
			// Server-side placement inside header via output buffering/injection.
			add_action('template_redirect', array($this, 'start_output_buffer'), 0);
		} else {
			// 'wp_body_open' → render early
			if (function_exists('wp_body_open')) {
				add_action('wp_body_open', array($this, 'render'), 1);
			} else {
				add_action('get_header', array($this, 'render'), 1); // legacy fallback
			}
		}

		add_action('wp_head', array($this, 'maybe_hide_wc_notice'), 99);
	}

	protected function get_render_hook()
	{
		$opts = wp_parse_args((array) get_option('snp_options'), snp_default_options());
		if ('wp_footer' === $opts['render_hook']) return 'wp_footer';
		if ('header' === $opts['render_hook']) return 'header';
		return 'wp_body_open';
	}

	/**
	 * True when a WooCommerce private share link should bypass Coming Soon suppression.
	 */
	protected function has_woo_private_bypass()
	{
		// WooCommerce preview/share links typically include a `woo_share` token.
		if (isset($_GET['woo-share']) && '' !== (string) $_GET['woo-share']) {
			return true;
		}
		// Some flows persist a cookie; honor it if present.
		if (isset($_COOKIE['woo-share']) && '' !== (string) $_COOKIE['woo-share']) {
			return true;
		}
		// Allow hosts/plugins to signal a bypass.
		return (bool) apply_filters('snp_woo_share_bypass', false);
	}

	/**
	 * Return true when the banner should be suppressed because the site
	 * is not publicly visible (WP maintenance or WC Coming Soon).
	 */
	protected function is_site_unavailable()
	{
		// 0) If visiting via a WooCommerce private share link, do not suppress.
		if ($this->has_woo_private_bypass()) {
			return false;
		}

		// 1) WordPress core maintenance (.maintenance present)
		if (function_exists('wp_is_maintenance_mode') && wp_is_maintenance_mode()) {
			return true; // site intentionally unavailable
		}

		// 2) WooCommerce Coming Soon (preferred, WC 9.1+)
		//    Use the official helper from the DI container when available.
		if (function_exists('wc_get_container') && class_exists('\Automattic\WooCommerce\Internal\ComingSoon\ComingSoonHelper')) {
			try {
				$helper = wc_get_container()->get(\Automattic\WooCommerce\Internal\ComingSoon\ComingSoonHelper::class);
				if (method_exists($helper, 'is_site_live') && ! $helper->is_site_live()) {
					return true; // site is NOT live => coming soon mode is active
				}
				// (Alternatively) if you prefer explicit:
				// if ( method_exists( $helper, 'is_site_coming_soon' ) && $helper->is_site_coming_soon() ) return true;
			} catch (\Throwable $e) {
				// fall through to option heuristic
			}
		}

		// 3) Fallback heuristic: check the option WC uses under the hood
		//    (Helper implements: return 'yes' !== get_option('woocommerce_coming_soon'))
		$raw = get_option('woocommerce_coming_soon', 'no');
		if (in_array($raw, array('yes', '1', 1, true), true)) {
			return true;
		}

		// 4) Allow overrides (e.g., hosts or coming-soon plugins)
		if (apply_filters('snp_hide_banner_for_coming_soon', false)) {
			return true;
		}

		return false;
	}


	/**
	 * True if banner should show (enabled + has messages + not dismissed via cookie).
	 */
	protected function should_show()
	{
		$opts = wp_parse_args((array) get_option('snp_options'), snp_default_options());
		// Suppress when site is in maintenance/coming-soon.
		if ($this->is_site_unavailable()) {
			return false;
		}
		if (empty($opts['enabled'])) {
			return false;
		}
		$lines = array_filter(array_map('trim', preg_split("/\r\n|\r|\n/", (string) $opts['messages'])));
		if (empty($lines)) {
			return false;
		}
		// If user dismissed and banner is closable, cookie will exist until expiry.
		if (! empty($opts['closable']) && isset($_COOKIE['snp_dismissed']) && '1' === $_COOKIE['snp_dismissed']) {
			return false;
		}
		return true;
	}

	public function enqueue()
	{
		if (! $this->should_show()) {
			return;
		}

		$opts = wp_parse_args((array) get_option('snp_options'), snp_default_options());

		// Styles with version from filemtime
		wp_register_style(
			'snp-frontend',
			SNP_URL . 'assets/css/frontend.css',
			array(),
			snp_asset_ver('assets/css/frontend.css')
		);
		wp_enqueue_style('snp-frontend');

		// Inject CSS variables
		$css = sprintf(
			':root{--snp-bg:%1$s;--snp-text:%2$s;--snp-link:%3$s;--snp-close:%4$s;}',
			esc_html($opts['bg_color']),
			esc_html($opts['text_color']),
			esc_html($opts['link_color']),
			esc_html($opts['close_color'])
		);
		wp_add_inline_style('snp-frontend', $css);

		// Script with version from filemtime
		wp_register_script(
			'snp-frontend',
			SNP_URL . 'assets/js/frontend.js',
			array(),
			snp_asset_ver('assets/js/frontend.js'),
			true
		);

		$render_hook = $this->get_render_hook();
		$js_position = ('wp_footer' === $render_hook) ? 'bottom' : 'top';
		$js_sticky   = ('header' === $render_hook || 'wp_footer' === $render_hook) ? 0 : (int) ! empty($opts['sticky']);

		wp_localize_script('snp-frontend', 'SNP_DATA', array(
			'interval'       => max(2, min(60, (int) $opts['interval'])),
			'dismissDays'    => max(1, min(365, (int) $opts['dismiss_days'])),
			'closable'       => (int) ! empty($opts['closable']),
			'position'       => $js_position,
			'sticky'         => $js_sticky,
			'renderHook'     => $render_hook,                   // 'header' | 'wp_body_open' | 'wp_footer'
			'headerSelector' => (string) $opts['header_selector'],
			'strings'        => array(
				'close'     => __('Close notice', 'store-notice-plus'),
				'announce'  => __('Store notice', 'store-notice-plus'),
			),
		));

		wp_enqueue_script('snp-frontend');
	}


	public function render()
	{
		if (! $this->should_show()) {
			return;
		}

		$opts  = wp_parse_args((array) get_option('snp_options'), snp_default_options());
		$lines = array_filter(array_map('trim', preg_split("/\r\n|\r|\n/", (string) $opts['messages'])));
		if (empty($lines)) {
			return;
		}
		echo $this->build_banner_html($opts, $lines, $this->get_render_hook());
	}

	/**
	 * Optionally hide WooCommerce default demo notice to prevent duplicate banners.
	 */
	public function maybe_hide_wc_notice()
	{
		$opts = wp_parse_args((array) get_option('snp_options'), snp_default_options());
		if (! empty($opts['hide_wc_notice'])) {
			// Hide native WooCommerce store notice if it's enabled.
			echo '<style>.woocommerce-store-notice{display:none !important;}</style>';
		}
	}

	/**
	 * Return banner HTML string for server-side rendering or injection.
	 */
	protected function build_banner_html($opts, $lines, $render_hook)
	{
		$allowed_tags = array(
			'a'     => array('href' => array(), 'title' => array(), 'target' => array(), 'rel' => array()),
			'strong' => array(),
			'em'    => array(),
			'br'    => array(),
			'span'  => array('class' => array(), 'style' => array()),
		);

		if ('wp_footer' === $render_hook) {
			$pos_class    = 'snp--pos-bottom';
			$sticky_class = '';
		} elseif ('header' === $render_hook) {
			$pos_class    = 'snp--pos-top';
			$sticky_class = '';
		} else {
			$pos_class    = 'snp--pos-top';
			$sticky_class = (! empty($opts['sticky']) && $pos_class === 'snp--pos-top') ? 'snp--sticky' : '';
		}

		$classes = esc_attr($pos_class . ' ' . $sticky_class);
		$aria_label = esc_attr__('Store notice', 'store-notice-plus');

		$html  = '';
		$html .= '<div id="snp-banner" class="snp-banner ' . $classes . '" role="region" aria-label="' . $aria_label . '">';
		$html .= '<div class="snp-inner">';
		$html .= '<div class="snp-messages" aria-live="polite">';

		$i = 0;
		foreach ($lines as $line) {
			$visible = ($i === 0) ? ' style="display:inline"' : ' style="display:none"';
			$html .= '<span class="snp-message"' . $visible . '>' . wp_kses($line, $allowed_tags) . '</span>';
			$i++;
		}

		$html .= '</div>';

		if (! empty($opts['closable'])) {
			$close_label = esc_attr__('Close notice', 'store-notice-plus');
			$close_title = esc_attr__('Close', 'store-notice-plus');
			$html .= '<button type="button" class="snp-close" aria-label="' . $close_label . '" title="' . $close_title . '">×</button>';
		}

		$html .= '</div></div>';

		return (string) $html;
	}

	/**
	 * Begin output buffering to allow server-side injection into the header element.
	 */
	public function start_output_buffer()
	{
		if (! $this->should_show()) {
			return;
		}
		ob_start(array($this, 'inject_into_header'));
	}

	/**
	 * OB callback: inject the banner as the first child of the first element
	 * matching the configured header selector. Falls back to after <body>.
	 */
	public function inject_into_header($html)
	{
		error_log('inject_into_header');
		try {
			$opts  = wp_parse_args((array) get_option('snp_options'), snp_default_options());
			$lines = array_filter(array_map('trim', preg_split("/\r\n|\r|\n/", (string) $opts['messages'])));
			if (empty($lines)) {
				return $html;
			}

			$banner = $this->build_banner_html($opts, $lines, 'header');
			$selectors = array_filter(array_map('trim', explode(',', (string) $opts['header_selector'])));

			// Try each selector in order: #id, .class, tag
			foreach ($selectors as $sel) {
				$pattern = '';
				if ('' === $sel) {
					continue;
				}
				if ('#' === $sel[0]) {
					$id = substr($sel, 1);
					if ('' === $id) {
						continue;
					}
					$pattern = '/<[^>]*\bid\s*=\s*(["\'])' . preg_quote($id, '/') . '\1[^>]*>/i';
				} elseif ('.' === $sel[0]) {
					$class = substr($sel, 1);
					if ('' === $class) {
						continue;
					}
					$pattern = '/<[^>]*\bclass\s*=\s*(["\'])[^"\']*\b' . preg_quote($class, '/') . '\b[^"\']*\1[^>]*>/i';
				} elseif (preg_match('/^[a-z][\w:-]*$/i', $sel)) {
					$pattern = '/<' . preg_quote($sel, '/') . '\b[^>]*>/i';
				} else {
					continue; // unsupported complex selector
				}

				if ($pattern && preg_match($pattern, $html, $m, PREG_OFFSET_CAPTURE)) {
					$open_tag      = $m[0][0];
					$open_pos      = $m[0][1];
					$insert_pos    = $open_pos + strlen($open_tag);
					return substr($html, 0, $insert_pos) . $banner . substr($html, $insert_pos);
				}
			}

			// Fallback: insert right after opening <body ...>
			if (preg_match('/<body\b[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
				$open_tag   = $m[0][0];
				$open_pos   = $m[0][1];
				$insert_pos = $open_pos + strlen($open_tag);
				return substr($html, 0, $insert_pos) . $banner . substr($html, $insert_pos);
			}
		} catch (\Throwable $e) {
			// Swallow and return original HTML on any failure.
		}
		return $html;
	}
}
