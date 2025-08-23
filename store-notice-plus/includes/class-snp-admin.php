<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin settings page (Settings API).
 */
class SNP_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );

	}

	public function add_menu() {
		add_menu_page(
			__( 'Store Notice+', 'store-notice-plus' ),
			__( 'Store Notice+', 'store-notice-plus' ),
			'manage_options',
			'store-notice-plus',
			array( $this, 'render_page' ),
			'dashicons-megaphone',
			56
		);
	}

	public function register_settings() {
		register_setting(
			'snp_options_group',
			'snp_options',
			array( $this, 'sanitize' )
		);

		add_settings_section(
			'snp_main',
			__( 'Banner Settings', 'store-notice-plus' ),
			function () {
				echo '<p>' . esc_html__( 'Configure the rotating, dismissible store notice banner. One message per line. Limited HTML allowed: <a>, <strong>, <em>, <br>, <span>.', 'store-notice-plus' ) . '</p>';
			},
			'store-notice-plus'
		);

		$fields = array(
			array( 'enabled', 'checkbox', __( 'Enable banner', 'store-notice-plus' ) ),
			array( 'messages', 'textarea', __( 'Messages (one per line)', 'store-notice-plus' ) ),
			array( 'interval', 'number', __( 'Rotation interval (seconds)', 'store-notice-plus' ) ),
			array( 'dismiss_days', 'number', __( 'Dismiss duration (days)', 'store-notice-plus' ) ),
			array( 'position', 'select', __( 'Position', 'store-notice-plus' ), array( 'top' => __( 'Top', 'store-notice-plus' ), 'bottom' => __( 'Bottom', 'store-notice-plus' ) ) ),
			array( 'sticky', 'checkbox', __( 'Stick on scroll (top only)', 'store-notice-plus' ) ),
			array( 'bg_color', 'color', __( 'Background color', 'store-notice-plus' ) ),
			array( 'text_color', 'color', __( 'Text color', 'store-notice-plus' ) ),
			array( 'link_color', 'color', __( 'Link color', 'store-notice-plus' ) ),
			array( 'close_color', 'color', __( 'Close “X” color', 'store-notice-plus' ) ),
			array( 'close_color_hover', 'color', __( 'Close “X” color hover', 'store-notice-plus' ) ),
			array( 'hide_wc_notice', 'checkbox', __( 'Hide WooCommerce default Store Notice (if enabled)', 'store-notice-plus' ) ),
		);

		foreach ( $fields as $field ) {
			add_settings_field(
				'field_' . $field[0],
				esc_html( $field[2] ),
				array( $this, 'render_field' ),
				'store-notice-plus',
				'snp_main',
				array(
					'key'     => $field[0],
					'type'    => $field[1],
					'choices' => isset( $field[3] ) ? $field[3] : array(),
				)
			);
		}
	}

	/**
	 * Sanitize and validate options.
	 */
	public function sanitize( $input ) {
		$defaults = snp_default_options();
		$out      = wp_parse_args( (array) $input, $defaults );

		$out['enabled']      = empty( $input['enabled'] ) ? 0 : 1;
		$out['sticky']       = empty( $input['sticky'] ) ? 0 : 1;
		$out['hide_wc_notice'] = empty( $input['hide_wc_notice'] ) ? 0 : 1;

		// Interval: clamp 2..60 seconds.
		$interval            = isset( $input['interval'] ) ? absint( $input['interval'] ) : $defaults['interval'];
		$out['interval']     = max( 2, min( 60, $interval ) );

		// Dismiss days: clamp 1..365.
		$dismiss_days        = isset( $input['dismiss_days'] ) ? absint( $input['dismiss_days'] ) : $defaults['dismiss_days'];
		$out['dismiss_days'] = max( 1, min( 365, $dismiss_days ) );

		// Position enum.
		$pos = isset( $input['position'] ) ? sanitize_text_field( $input['position'] ) : 'top';
		$out['position'] = in_array( $pos, array( 'top', 'bottom' ), true ) ? $pos : 'top';

		// Colors.
		foreach ( array( 'bg_color', 'text_color', 'link_color', 'close_color', 'close_color_hover' ) as $key ) {
			$color        = isset( $input[ $key ] ) ? sanitize_hex_color( $input[ $key ] ) : $defaults[ $key ];
			$out[ $key ]  = $color ? $color : $defaults[ $key ];
		}

		// Messages: split by newline, trim, remove empties. Allow safe HTML.
		$allowed_tags = array(
			'a'     => array( 'href' => array(), 'title' => array(), 'target' => array(), 'rel' => array() ),
			'strong'=> array(),
			'em'    => array(),
			'br'    => array(),
			'span'  => array( 'class' => array(), 'style' => array() ),
		);

		$raw = isset( $input['messages'] ) ? (string) $input['messages'] : '';
		$lines = array_filter( array_map( 'trim', preg_split( "/\r\n|\r|\n/", $raw ) ) );
		$lines = array_map( function( $line ) use ( $allowed_tags ) {
			return wp_kses( $line, $allowed_tags );
		}, $lines );

		$out['messages'] = implode( "\n", $lines );

		return $out;
	}

	/**
	 * Field renderer.
	 */
	public function render_field( $args ) {
		$key     = $args['key'];
		$type    = $args['type'];
		$choices = $args['choices'];

		$opts = wp_parse_args( (array) get_option( 'snp_options' ), snp_default_options() );
		$val  = isset( $opts[ $key ] ) ? $opts[ $key ] : '';

		switch ( $type ) {
			case 'checkbox':
				printf(
					'<label><input type="checkbox" name="snp_options[%1$s]" value="1" %2$s /> %3$s</label>',
					esc_attr( $key ),
					checked( $val, 1, false ),
					''
				);
				break;

			case 'textarea':
				printf(
					'<textarea name="snp_options[%1$s]" rows="6" cols="60" class="large-text code">%2$s</textarea>',
					esc_attr( $key ),
					esc_textarea( $val )
				);
				echo '<p class="description">' . esc_html__( 'One message per line. HTML allowed: <a>, <strong>, <em>, <br>, <span>.', 'store-notice-plus' ) . '</p>';
				break;

			case 'number':
				printf(
					'<input type="number" name="snp_options[%1$s]" value="%2$s" class="small-text" />',
					esc_attr( $key ),
					esc_attr( $val )
				);
				break;

			case 'color':
				$defaults = snp_default_options();
				$default  = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '#000000';
				printf(
					'<input type="text" name="snp_options[%1$s]" value="%2$s" class="snp-color-field" data-default-color="%3$s" />',
					esc_attr( $key ),
					esc_attr( $val ),
					esc_attr( $default )
				);
				break;

			case 'select':
				echo '<select name="snp_options[' . esc_attr( $key ) . ']">';
				foreach ( $choices as $c_key => $label ) {
					printf(
						'<option value="%1$s" %3$s>%2$s</option>',
						esc_attr( $c_key ),
						esc_html( $label ),
						selected( $val, $c_key, false )
					);
				}
				echo '</select>';
				break;
		}
	}

	/**
	 * Page renderer.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Store Notice+', 'store-notice-plus' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'snp_options_group' );
				do_settings_sections( 'store-notice-plus' );
				submit_button( __( 'Save Changes', 'store-notice-plus' ) );
				?>
			</form>
			<p>
				<em><?php esc_html_e( 'Tip: Disable WooCommerce > Settings > Advanced > Store Notice to avoid duplicates, or check the “Hide WooCommerce default Store Notice” option above.', 'store-notice-plus' ); ?></em>
			</p>
		</div>
		<?php
	}

	/**
	 * Enqueue admin assets only on our settings page.
	 */
	public function enqueue_admin( $hook_suffix ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || $screen->id !== 'toplevel_page_store-notice-plus' ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		wp_enqueue_script(
			'snp-admin',
			SNP_URL . 'assets/js/admin.js',
			array( 'wp-color-picker', 'jquery' ),
			snp_asset_ver( 'assets/js/admin.js' ),
			true
		);

		wp_enqueue_style(
			'snp-admin',
			SNP_URL . 'assets/css/admin.css',
			array(),
			snp_asset_ver( 'assets/css/admin.css' )
		);
	}


}

