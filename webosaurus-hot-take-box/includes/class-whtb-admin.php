<?php
/**
 * Admin: menu, the Hot Take Desk approval queue, the editor panel and list column.
 *
 * @package WebosaurusHotTakeBox
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin screens.
 */
class WHTB_Admin {

	/** Hooks. */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		foreach ( WHTB_Settings::post_types() as $pt ) {
			add_filter( "manage_{$pt}_posts_columns", array( __CLASS__, 'column' ) );
			add_action( "manage_{$pt}_posts_custom_column", array( __CLASS__, 'column_value' ), 10, 2 );
		}
	}

	/** Menu with a pending count bubble. */
	public static function menu() {
		$pending = WHTB_Store::pending_count();
		$bubble  = $pending ? ' <span class="awaiting-mod count-' . (int) $pending . '"><span class="pending-count">' . (int) $pending . '</span></span>' : '';
		add_menu_page(
			__( 'Hot Take Desk', 'webosaurus-hot-take-box' ),
			__( 'Hot Takes', 'webosaurus-hot-take-box' ) . $bubble,
			'edit_posts',
			'whtb-desk',
			array( __CLASS__, 'render_desk' ),
			'dashicons-format-quote',
			26
		);
		add_submenu_page( 'whtb-desk', __( 'Hot Take Desk', 'webosaurus-hot-take-box' ), __( 'Desk', 'webosaurus-hot-take-box' ), 'edit_posts', 'whtb-desk', array( __CLASS__, 'render_desk' ) );
		add_submenu_page( 'whtb-desk', __( 'Hot Take Box settings', 'webosaurus-hot-take-box' ), __( 'Settings', 'webosaurus-hot-take-box' ), 'manage_options', 'whtb-settings', array( 'WHTB_Settings', 'render' ) );
	}

	/**
	 * Scripts and styles on our screens and the post editor.
	 *
	 * @param string $hook Screen hook.
	 */
	public static function assets( $hook ) {
		$screen  = get_current_screen();
		$is_edit = $screen && 'post' === $screen->base && in_array( $screen->post_type, WHTB_Settings::post_types(), true );
		$ours    = false !== strpos( $hook, 'whtb-' );
		if ( ! $is_edit && ! $ours ) {
			return;
		}
		wp_enqueue_style( 'whtb-admin', WHTB_URL . 'assets/css/admin.css', array(), WHTB_VERSION );
		if ( false !== strpos( $hook, 'whtb-settings' ) ) {
			return;
		}
		wp_register_script( 'whtb-lottie', WHTB_URL . 'assets/js/lottie_light.min.js', array(), '5.12.2', true );
		wp_enqueue_script( 'whtb-admin', WHTB_URL . 'assets/js/admin.js', array( 'wp-api-fetch', 'whtb-lottie' ), WHTB_VERSION, true );
		wp_add_inline_script(
			'whtb-admin',
			'window.WHTB = ' . wp_json_encode(
				array(
					'moods'    => whtb_moods(),
					'lottie'   => WHTB_URL . 'assets/lottie/',
					'rotate'   => (bool) WHTB_Settings::get( 'rotate' ),
					'mascot'   => WHTB_Settings::get( 'mascot' ),
					'settings' => current_user_can( 'manage_options' ) ? admin_url( 'admin.php?page=whtb-settings' ) : '',
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Data attribute for a desk component.
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $compact Desk row instead of editor panel.
	 * @return string
	 */
	protected static function component( $post_id, $compact ) {
		return sprintf(
			'<div class="whtb-desk%1$s" data-state="%2$s"><p class="whtb-loading">%3$s</p></div>',
			$compact ? ' whtb-desk--row' : '',
			esc_attr( wp_json_encode( WHTB_Store::state( $post_id ) ) ),
			esc_html__( 'Waking up the T rex…', 'webosaurus-hot-take-box' )
		);
	}

	/** Editor panel, shown in both the block editor and the classic editor. */
	public static function meta_box() {
		foreach ( WHTB_Settings::post_types() as $pt ) {
			add_meta_box(
				'whtb-panel',
				__( 'Hot Take Box', 'webosaurus-hot-take-box' ),
				static function ( $post ) {
					echo self::component( $post->ID, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				},
				$pt,
				'normal',
				'default'
			);
		}
	}

	/**
	 * List table column.
	 *
	 * @param array<string,string> $cols Columns.
	 * @return array<string,string>
	 */
	public static function column( $cols ) {
		$cols['whtb'] = __( 'Hot take', 'webosaurus-hot-take-box' );
		return $cols;
	}

	/**
	 * Column value.
	 *
	 * @param string $col     Column.
	 * @param int    $post_id Post ID.
	 */
	public static function column_value( $col, $post_id ) {
		if ( 'whtb' !== $col ) {
			return;
		}
		$status = (string) get_post_meta( $post_id, WHTB_Store::STATUS, true );
		$labels = self::status_labels();
		$status = isset( $labels[ $status ] ) ? $status : 'empty';
		printf( '<span class="whtb-pill whtb-pill--%1$s">%2$s</span>', esc_attr( $status ), esc_html( $labels[ $status ] ) );
	}

	/**
	 * Status labels.
	 *
	 * @return array<string,string>
	 */
	public static function status_labels() {
		return array(
			'pending' => __( 'Waiting for approval', 'webosaurus-hot-take-box' ),
			'live'    => __( 'Live', 'webosaurus-hot-take-box' ),
			'pulled'  => __( 'Pulled', 'webosaurus-hot-take-box' ),
			'empty'   => __( 'No take yet', 'webosaurus-hot-take-box' ),
		);
	}

	/**
	 * Query args for a desk tab.
	 *
	 * @param string $tab  Tab.
	 * @param int    $page Page number.
	 * @return array<string,mixed>
	 */
	protected static function tab_query( $tab, $page = 1 ) {
		$args = array(
			'post_type'      => WHTB_Settings::post_types(),
			'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'posts_per_page' => 20,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			$args['author'] = get_current_user_id();
		}
		if ( 'pending' === $tab || 'live' === $tab ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => WHTB_Store::STATUS,
					'value' => $tab,
				),
			);
		} elseif ( 'empty' === $tab ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'OR',
				array(
					'key'     => WHTB_Store::STATUS,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'   => WHTB_Store::STATUS,
					'value' => array( 'pulled', 'empty' ),
				),
			);
		}
		return $args;
	}

	/** The Hot Take Desk: every post, its drafts and one click approval. */
	public static function render_desk() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'pending';
		$page = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		// phpcs:enable
		$tabs = array(
			'pending' => __( 'Waiting for approval', 'webosaurus-hot-take-box' ),
			'live'    => __( 'Live', 'webosaurus-hot-take-box' ),
			'empty'   => __( 'No take yet', 'webosaurus-hot-take-box' ),
			'all'     => __( 'All', 'webosaurus-hot-take-box' ),
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'pending';
		}
		$q = new WP_Query( self::tab_query( $tab, $page ) );
		?>
		<div class="wrap whtb-wrap">
			<div class="whtb-hero">
				<div class="whtb-hero__mascot" data-whtb-lottie="hype" aria-hidden="true"></div>
				<div>
					<h1><?php esc_html_e( 'Hot Take Desk', 'webosaurus-hot-take-box' ); ?></h1>
					<p><?php esc_html_e( 'The AI drafts. You approve. Only approved takes reach your readers.', 'webosaurus-hot-take-box' ); ?></p>
				</div>
			</div>
			<nav class="nav-tab-wrapper">
				<?php
				foreach ( $tabs as $slug => $label ) :
					$count = 'all' === $slug ? null : ( new WP_Query( array_merge( self::tab_query( $slug ), array( 'fields' => 'ids', 'posts_per_page' => 1 ) ) ) )->found_posts;
					?>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'whtb-desk', 'tab' => $slug ), admin_url( 'admin.php' ) ) ); ?>" class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?><?php echo null !== $count ? ' <span class="whtb-count">' . (int) $count . '</span>' : ''; ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php if ( 'empty' === $tab && $q->have_posts() ) : ?>
				<p class="whtb-bulk"><button type="button" class="button button-primary" data-whtb-bulk-draft><?php esc_html_e( 'Draft takes for everything on this page', 'webosaurus-hot-take-box' ); ?></button> <span class="whtb-bulk__status" aria-live="polite"></span></p>
			<?php endif; ?>

			<div class="whtb-list">
				<?php
				if ( $q->have_posts() ) {
					foreach ( $q->posts as $p ) {
						echo self::component( $p->ID, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
				} else {
					$empty = array(
						'pending' => __( 'Inbox zero. Nothing is waiting on you. Go have a snack.', 'webosaurus-hot-take-box' ),
						'live'    => __( 'No takes are live yet. Approve one from the waiting tab.', 'webosaurus-hot-take-box' ),
						'empty'   => __( 'Every post has a take. Roarsome.', 'webosaurus-hot-take-box' ),
						'all'     => __( 'No posts yet.', 'webosaurus-hot-take-box' ),
					);
					echo '<p class="whtb-empty">' . esc_html( $empty[ $tab ] ) . '</p>';
				}
				?>
			</div>

			<?php
			if ( $q->max_num_pages > 1 ) {
				echo '<div class="tablenav"><div class="tablenav-pages">';
				echo wp_kses_post(
					paginate_links(
						array(
							'base'    => add_query_arg( 'paged', '%#%' ),
							'format'  => '',
							'current' => $page,
							'total'   => $q->max_num_pages,
						)
					)
				);
				echo '</div></div>';
			}
			?>
		</div>
		<?php
	}
}
