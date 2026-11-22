<?php
/**
 * Front end: the box at the end of each post.
 *
 * The take is plain HTML in the page, so readers without JavaScript, search
 * engines and AI crawlers all see it. JavaScript only adds the animated mascot.
 *
 * @package WebosaurusHotTakeBox
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rendering.
 */
class WHTB_Frontend {

	/** Hooks. */
	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'append' ), 20 );
		add_shortcode( 'webosaurus_hot_take', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	/** Register assets; they are only enqueued when a box renders. */
	public static function register_assets() {
		wp_register_style( 'whtb', WHTB_URL . 'assets/css/front.css', array(), WHTB_VERSION );
		wp_register_script( 'whtb-lottie', WHTB_URL . 'assets/js/lottie_light.min.js', array(), '5.12.2', true );
		wp_register_script( 'whtb', WHTB_URL . 'assets/js/front.js', array( 'whtb-lottie' ), WHTB_VERSION, true );
		if ( function_exists( 'wp_script_add_data' ) ) {
			wp_script_add_data( 'whtb', 'strategy', 'defer' );
			wp_script_add_data( 'whtb-lottie', 'strategy', 'defer' );
		}
	}

	/**
	 * Add the box after the content of a single supported post.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function append( $content ) {
		if ( ! WHTB_Settings::get( 'auto_append' ) || is_admin() || is_feed() || ! is_singular( WHTB_Settings::post_types() ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( has_shortcode( $content, 'webosaurus_hot_take' ) ) {
			return $content;
		}
		return $content . self::render( get_the_ID() );
	}

	/**
	 * Shortcode: [webosaurus_hot_take id="123"]. Defaults to the current post.
	 *
	 * @param array<string,string>|string $atts Attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'webosaurus_hot_take' );
		$id   = $atts['id'] ? (int) $atts['id'] : get_the_ID();
		return $id ? self::render( $id ) : '';
	}

	/**
	 * Box HTML for a post, or an empty string when nothing is approved.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function render( $post_id ) {
		if ( 'live' !== get_post_meta( $post_id, WHTB_Store::STATUS, true ) ) {
			return '';
		}
		$takes = WHTB_Store::clean_list( get_post_meta( $post_id, WHTB_Store::TAKES, true ) );
		if ( ! $takes ) {
			return '';
		}
		/**
		 * Filter the takes shown for a post, after approval.
		 *
		 * @param array $takes   Approved takes.
		 * @param int   $post_id Post ID.
		 */
		$takes = WHTB_Store::clean_list( apply_filters( 'whtb_takes', $takes, $post_id ) );
		if ( ! $takes ) {
			return '';
		}
		$s      = WHTB_Settings::get();
		$rotate = $s['rotate'] && count( $takes ) > 1;
		$first  = $takes[0];

		wp_enqueue_style( 'whtb' );
		wp_add_inline_style( 'whtb', '.whtb{--whtb-accent:' . sanitize_hex_color( $s['accent'] ) . '}' );
		wp_enqueue_script( 'whtb' );

		$moods = array();
		foreach ( array_keys( whtb_moods() ) as $m ) {
			$moods[ $m ] = whtb_lottie_url( $m );
		}

		ob_start();
		?>
		<aside class="whtb whtb--nojs" data-mood="<?php echo esc_attr( $first['mood'] ); ?>" data-lottie="<?php echo esc_attr( wp_json_encode( $moods ) ); ?>"<?php echo $rotate ? ' data-takes="' . esc_attr( wp_json_encode( $takes ) ) . '"' : ''; ?> aria-label="<?php echo esc_attr( $s['label'] ); ?>">
			<div class="whtb__mascot" aria-hidden="true"></div>
			<figure class="whtb__bubble">
				<figcaption class="whtb__label"><?php echo esc_html( $s['label'] ); ?></figcaption>
				<blockquote class="whtb__text"><p><?php echo esc_html( $first['text'] ); ?></p></blockquote>
			</figure>
		</aside>
		<?php
		return trim( ob_get_clean() );
	}
}
