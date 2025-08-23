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

		// Render in a layout-safe hook (never overlaps header).
		if ( function_exists( 'wp_body_open' ) ) {
			add_action( 'wp_body_open', array( $this, 'render' ), 1 );
		} else {
			// Fallback: render before main content via get_header (very old themes).
			add_action( 'get_header', array( $this, 'render' ), 1 );
		}

		// Optionally hide WooCommerce default demo notice if requested.
		add_action( 'wp_head', array( $this, 'maybe_hide_wc_notice' ), 99 );
	}

	/**
	 * True if banner should show (enabled + has messages + not dismissed via cookie).
	 */
	protected function should_show() {
		$opts = wp_parse_args( (array) get_option( 'snp_options' ), snp_default_options() );
		if ( empty( $opts['enabled'] ) ) {
			return false;
		}
		$lines = array_filter( array_map( 'trim', preg_split( "/\r\n|\r|\n/", (string) $opts['messages'] ) ) );
		if ( empty( $lines ) ) {
			return false;
		}
		// If user dismissed, cookie will exist until expiry.
		if ( isset( $_COOKIE['snp_dismissed'] ) && '1' === $_COOKIE['snp_dismissed'] ) {
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

		// (localize as you already do)
		wp_localize_script( 'snp-frontend', 'SNP_DATA', array(
			'interval'     => max( 2, min( 60, (int) $opts['interval'] ) ),
			'dismissDays'  => max( 1, min( 365, (int) $opts['dismiss_days'] ) ),
			'position'     => ( $opts['position'] === 'bottom' ? 'bottom' : 'top' ),
			'sticky'       => (int) ! empty( $opts['sticky'] ),
			'strings'      => array(
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

		$pos_class    = ( $opts['position'] === 'bottom' ) ? 'snp--pos-bottom' : 'snp--pos-top';
		$sticky_class = ( ! empty( $opts['sticky'] ) && $opts['position'] === 'top' ) ? 'snp--sticky' : '';

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
				<button type="button" class="snp-close" aria-label="<?php esc_attr_e( 'Close notice', 'store-notice-plus' ); ?>" title="<?php esc_attr_e( 'Close', 'store-notice-plus' ); ?>">×</button>
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

