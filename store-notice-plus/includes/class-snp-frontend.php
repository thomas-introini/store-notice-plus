<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front-end renderer and assets.
 */
class SNP_Frontend {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );

		$hook = $this->get_render_hook();
		if ( 'wp_footer' === $hook ) {
			add_action( 'wp_footer', array( $this, 'render' ), 1 );
		} else {
			// 'header' or 'wp_body_open' → render early, then JS can move into header if needed
			if ( function_exists( 'wp_body_open' ) ) {
				add_action( 'wp_body_open', array( $this, 'render' ), 1 );
			} else {
				add_action( 'get_header', array( $this, 'render' ), 1 ); // legacy fallback
			}
		}

		add_action( 'wp_head', array( $this, 'maybe_hide_wc_notice' ), 99 );
	}

	protected function get_render_hook() {
		$opts = wp_parse_args( (array) get_option( 'snp_options' ), snp_default_options() );
		if ( 'wp_footer' === $opts['render_hook'] ) return 'wp_footer';
		if ( 'header' === $opts['render_hook'] ) return 'header';
		return 'wp_body_open';
	}

	/**
	 * Return true when the banner should be suppressed because the site
	 * is not publicly visible (WP maintenance or WC Coming Soon).
	 */
	protected function is_site_unavailable() {
		// 1) WordPress core maintenance (.maintenance present)
		if ( function_exists( 'wp_is_maintenance_mode' ) && wp_is_maintenance_mode() ) {
			return true; // site intentionally unavailable
		}

		// 2) WooCommerce Coming Soon (preferred, WC 9.1+)
		//    Use the official helper from the DI container when available.
		if ( function_exists( 'wc_get_container' ) && class_exists( '\Automattic\WooCommerce\Internal\ComingSoon\ComingSoonHelper' ) ) {
			try {
				$helper = wc_get_container()->get( \Automattic\WooCommerce\Internal\ComingSoon\ComingSoonHelper::class );
				if ( method_exists( $helper, 'is_site_live' ) && ! $helper->is_site_live() ) {
					return true; // site is NOT live => coming soon mode is active
				}
				// (Alternatively) if you prefer explicit:
				// if ( method_exists( $helper, 'is_site_coming_soon' ) && $helper->is_site_coming_soon() ) return true;
			} catch ( \Throwable $e ) {
				// fall through to option heuristic
			}
		}

		// 3) Fallback heuristic: check the option WC uses under the hood
		//    (Helper implements: return 'yes' !== get_option('woocommerce_coming_soon'))
		$raw = get_option( 'woocommerce_coming_soon', 'no' );
		if ( in_array( $raw, array( 'yes', '1', 1, true ), true ) ) {
			return true;
		}

		// 4) Allow overrides (e.g., hosts or coming-soon plugins)
		if ( apply_filters( 'snp_hide_banner_for_coming_soon', false ) ) {
			return true;
		}

		return false;
	}


	/**
	 * True if banner should show (enabled + has messages + not dismissed via cookie).
	 */
	protected function should_show() {
		$opts = wp_parse_args( (array) get_option( 'snp_options' ), snp_default_options() );
		// Suppress when site is in maintenance/coming-soon.
		if ( $this->is_site_unavailable() ) {
			return false;
		}
		if ( empty( $opts['enabled'] ) ) {
			return false;
		}
		$lines = array_filter( array_map( 'trim', preg_split( "/\r\n|\r|\n/", (string) $opts['messages'] ) ) );
		if ( empty( $lines ) ) {
			return false;
		}
		// If user dismissed and banner is closable, cookie will exist until expiry.
		if ( ! empty( $opts['closable'] ) && isset( $_COOKIE['snp_dismissed'] ) && '1' === $_COOKIE['snp_dismissed'] ) {
			return false;
		}
		return true;
	}

	public function enqueue() {
		if ( ! $this->should_show() ) {
			return;
		}

		$opts = wp_parse_args( (array) get_option( 'snp_options' ), snp_default_options() );

		// Styles with version from filemtime
		wp_register_style(
			'snp-frontend',
			SNP_URL . 'assets/css/frontend.css',
			array(),
			snp_asset_ver( 'assets/css/frontend.css' )
		);
		wp_enqueue_style( 'snp-frontend' );

		// Inject CSS variables
		$css = sprintf(
			':root{--snp-bg:%1$s;--snp-text:%2$s;--snp-link:%3$s;--snp-close:%4$s;}',
			esc_html( $opts['bg_color'] ),
			esc_html( $opts['text_color'] ),
			esc_html( $opts['link_color'] ),
			esc_html( $opts['close_color'] )
		);
		wp_add_inline_style( 'snp-frontend', $css );

		// Script with version from filemtime
		wp_register_script(
			'snp-frontend',
			SNP_URL . 'assets/js/frontend.js',
			array(),
			snp_asset_ver( 'assets/js/frontend.js' ),
			true
		);

		$render_hook = $this->get_render_hook();
		$js_position = ( 'wp_footer' === $render_hook ) ? 'bottom' : 'top';
		$js_sticky   = ( 'header' === $render_hook || 'wp_footer' === $render_hook ) ? 0 : (int) ! empty( $opts['sticky'] );

		wp_localize_script( 'snp-frontend', 'SNP_DATA', array(
			'interval'       => max( 2, min( 60, (int) $opts['interval'] ) ),
			'dismissDays'    => max( 1, min( 365, (int) $opts['dismiss_days'] ) ),
			'closable'       => (int) ! empty( $opts['closable'] ),
			'position'       => $js_position,
			'sticky'         => $js_sticky,
			'renderHook'     => $render_hook,                   // 'header' | 'wp_body_open' | 'wp_footer'
			'headerSelector' => (string) $opts['header_selector'],
			'strings'        => array(
				'close'     => __( 'Close notice', 'store-notice-plus' ),
				'announce'  => __( 'Store notice', 'store-notice-plus' ),
			),
		) );

		wp_enqueue_script( 'snp-frontend' );
	}


	public function render() {
		if ( ! $this->should_show() ) {
			return;
		}

		$opts    = wp_parse_args( (array) get_option( 'snp_options' ), snp_default_options() );
		$lines   = array_filter( array_map( 'trim', preg_split( "/\r\n|\r|\n/", (string) $opts['messages'] ) ) );
		if ( empty( $lines ) ) {
			return;
		}

		$allowed_tags = array(
			'a'     => array( 'href' => array(), 'title' => array(), 'target' => array(), 'rel' => array() ),
			'strong'=> array(),
			'em'    => array(),
			'br'    => array(),
			'span'  => array( 'class' => array(), 'style' => array() ),
		);

		$render_hook = $this->get_render_hook();

		if ( 'wp_footer' === $render_hook ) {
			$pos_class    = 'snp--pos-bottom';
			$sticky_class = '';
		} elseif ( 'header' === $render_hook ) {
			// Inside header: top, non-sticky (let the theme header manage stickiness)
			$pos_class    = 'snp--pos-top';
			$sticky_class = '';
		} else {
			$pos_class    = 'snp--pos-top';
			$sticky_class = ( ! empty( $opts['sticky'] ) && $pos_class === 'snp--pos-top' ) ? 'snp--sticky' : '';
		}


		?>
		<div id="snp-banner" class="snp-banner <?php echo esc_attr( $pos_class . ' ' . $sticky_class ); ?>" role="region" aria-label="<?php esc_attr_e( 'Store notice', 'store-notice-plus' ); ?>">
			<div class="snp-inner">
				<div class="snp-messages" aria-live="polite">
					<?php
					$i = 0;
					foreach ( $lines as $line ) :
						$visible = ( $i === 0 ) ? ' style="display:inline"' : ' style="display:none"';
						echo '<span class="snp-message"' . $visible . '>' . wp_kses( $line, $allowed_tags ) . '</span>';
						$i++;
					endforeach;
					?>
				</div>
				<?php if ( ! empty( $opts['closable'] ) ) : ?>
					<button type="button" class="snp-close" aria-label="<?php esc_attr_e( 'Close notice', 'store-notice-plus' ); ?>" title="<?php esc_attr_e( 'Close', 'store-notice-plus' ); ?>">×</button>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Optionally hide WooCommerce default demo notice to prevent duplicate banners.
	 */
	public function maybe_hide_wc_notice() {
		$opts = wp_parse_args( (array) get_option( 'snp_options' ), snp_default_options() );
		if ( ! empty( $opts['hide_wc_notice'] ) ) {
			// Hide native WooCommerce store notice if it's enabled.
			echo '<style>.woocommerce-store-notice{display:none !important;}</style>';
		}
	}
}

