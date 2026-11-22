<?php
/**
 * Post meta storage, the approval state machine and background drafting.
 *
 * Status flow, Amul style: empty -> pending (AI drafted, waiting on a human)
 * -> live (approved and showing) -> pulled (approved take taken down).
 *
 * @package WebosaurusHotTakeBox
 */

defined( 'ABSPATH' ) || exit;

/**
 * Meta helpers.
 */
class WHTB_Store {

	const DRAFTS   = '_whtb_drafts';
	const TAKES    = '_whtb_takes';
	const STATUS   = '_whtb_status';
	const META     = '_whtb_meta';
	const MAX_LEN  = 220;
	const MAX_KEEP = 8;

	/** Hooks. */
	public static function init() {
		add_action( 'transition_post_status', array( __CLASS__, 'on_publish' ), 10, 3 );
		add_action( 'whtb_draft_event', array( __CLASS__, 'run_background_draft' ) );
	}

	/**
	 * Clean one take: plain text, one line, capped, valid mood.
	 *
	 * @param mixed $take Raw take (array with text and mood, or a string).
	 * @return array{text:string,mood:string}|null
	 */
	public static function clean_take( $take ) {
		if ( is_string( $take ) ) {
			$take = array( 'text' => $take );
		}
		if ( ! is_array( $take ) || ! isset( $take['text'] ) ) {
			return null;
		}
		$text = wp_strip_all_tags( (string) $take['text'] );
		$text = preg_replace( '/\s+/u', ' ', $text );
		$text = trim( $text, " \t\n\r\0\x0B\"'“”‘’" );
		if ( WHTB_Settings::get( 'strip_dashes' ) ) {
			$text = self::strip_dashes( $text );
		}
		if ( '' === $text ) {
			return null;
		}
		if ( function_exists( 'mb_substr' ) && mb_strlen( $text ) > self::MAX_LEN ) {
			$text = rtrim( mb_substr( $text, 0, self::MAX_LEN - 1 ) ) . '…';
		}
		$mood  = isset( $take['mood'] ) ? sanitize_key( $take['mood'] ) : 'smug';
		$moods = whtb_moods();
		if ( ! isset( $moods[ $mood ] ) ) {
			$mood = 'smug';
		}
		return array(
			'text' => $text,
			'mood' => $mood,
		);
	}

	/**
	 * Remove em dashes, en dashes and hyphens inside words.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function strip_dashes( $text ) {
		$text = preg_replace( '/\s*[—–]\s*/u', ', ', $text );
		$text = preg_replace( '/(\p{L})-(?=\p{L})/u', '$1 ', $text );
		$text = preg_replace( '/\s+,/u', ',', $text );
		$text = preg_replace( '/,\s*([.!?])/u', '$1', $text );
		return trim( preg_replace( '/^,\s*|,\s*$/u', '', $text ) );
	}

	/**
	 * Clean a list of takes, drop blanks and duplicates.
	 *
	 * @param mixed $takes Raw list.
	 * @return array<int,array{text:string,mood:string}>
	 */
	public static function clean_list( $takes ) {
		$out  = array();
		$seen = array();
		foreach ( (array) $takes as $t ) {
			$c = self::clean_take( $t );
			if ( ! $c ) {
				continue;
			}
			$k = strtolower( $c['text'] );
			if ( isset( $seen[ $k ] ) ) {
				continue;
			}
			$seen[ $k ] = true;
			$out[]      = $c;
			if ( count( $out ) >= self::MAX_KEEP ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Full state for the editor and the desk.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string,mixed>
	 */
	public static function state( $post_id ) {
		$post   = get_post( $post_id );
		$meta   = (array) get_post_meta( $post_id, self::META, true );
		$status = (string) get_post_meta( $post_id, self::STATUS, true );
		$pto    = $post ? get_post_type_object( $post->post_type ) : null;
		return array(
			'id'          => (int) $post_id,
			'title'       => $post ? html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ) : '',
			'post_status' => $post ? $post->post_status : '',
			'edit_link'   => $post ? get_edit_post_link( $post, 'raw' ) : '',
			'view_link'   => $post ? get_permalink( $post ) : '',
			'status'      => $status ? $status : 'empty',
			'takes'       => self::clean_list( get_post_meta( $post_id, self::TAKES, true ) ),
			'drafts'      => self::clean_list( get_post_meta( $post_id, self::DRAFTS, true ) ),
			'engine'      => isset( $meta['engine'] ) ? $meta['engine'] : '',
			'note'        => isset( $meta['note'] ) ? $meta['note'] : '',
			'error'       => isset( $meta['error'] ) ? $meta['error'] : '',
			'drafted_at'  => isset( $meta['drafted_at'] ) ? (int) $meta['drafted_at'] : 0,
			'approved_by' => isset( $meta['approved_by'] ) ? $meta['approved_by'] : '',
			'approved_at' => isset( $meta['approved_at'] ) ? (int) $meta['approved_at'] : 0,
			'stale'       => self::is_stale( $post_id, $meta ),
			'can_approve' => $pto ? current_user_can( $pto->cap->publish_posts ) && current_user_can( 'edit_post', $post_id ) : false,
		);
	}

	/**
	 * True when the post changed after the drafts were written.
	 *
	 * @param int                 $post_id Post ID.
	 * @param array<string,mixed> $meta    Stored meta.
	 * @return bool
	 */
	public static function is_stale( $post_id, $meta ) {
		if ( empty( $meta['hash'] ) ) {
			return false;
		}
		return self::content_hash( $post_id ) !== $meta['hash'];
	}

	/**
	 * Hash of title plus content, to spot edits after drafting.
	 *
	 * @param int                  $post_id  Post ID.
	 * @param array<string,string> $override Unsaved editor text, if any.
	 * @return string
	 */
	public static function content_hash( $post_id, $override = array() ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}
		$post = WHTB_AI::with_override( $post, $override );
		return md5( trim( $post->post_title ) . "\n" . trim( WHTB_AI::article_text( $post ) ) );
	}

	/**
	 * Patch the meta blob.
	 *
	 * @param int                 $post_id Post ID.
	 * @param array<string,mixed> $patch   Fields.
	 */
	public static function patch_meta( $post_id, $patch ) {
		$meta = (array) get_post_meta( $post_id, self::META, true );
		update_post_meta( $post_id, self::META, array_merge( $meta, $patch ) );
	}

	/**
	 * Draft fresh takes for a post and park them for approval.
	 *
	 * @param int                  $post_id  Post ID.
	 * @param array<string,string> $override Unsaved title and content from the editor.
	 * @return array<string,mixed>|WP_Error New state, or an error when nothing usable came back.
	 */
	public static function draft( $post_id, $override = array() ) {
		$result = WHTB_AI::generate( $post_id, $override );
		if ( is_wp_error( $result ) ) {
			self::patch_meta( $post_id, array( 'error' => $result->get_error_message() ) );
			return $result;
		}
		$drafts = self::clean_list( $result['takes'] );
		if ( ! $drafts ) {
			$err = new WP_Error( 'whtb_empty', __( 'The writer came back with nothing usable. Try again.', 'webosaurus-hot-take-box' ) );
			self::patch_meta( $post_id, array( 'error' => $err->get_error_message() ) );
			return $err;
		}
		update_post_meta( $post_id, self::DRAFTS, $drafts );
		self::patch_meta(
			$post_id,
			array(
				'engine'     => $result['engine'],
				'note'       => isset( $result['note'] ) ? $result['note'] : '',
				'error'      => '',
				'drafted_at' => time(),
				'hash'       => self::content_hash( $post_id, $override ),
			)
		);
		$status = (string) get_post_meta( $post_id, self::STATUS, true );
		if ( 'live' !== $status ) {
			update_post_meta( $post_id, self::STATUS, 'pending' );
		}
		return self::state( $post_id );
	}

	/**
	 * Approve takes. Replace swaps out whatever is live; add stacks them for rotation.
	 *
	 * @param int    $post_id Post ID.
	 * @param mixed  $takes   Takes to approve.
	 * @param string $mode    replace or add.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function approve( $post_id, $takes, $mode = 'replace' ) {
		$takes = self::clean_list( $takes );
		if ( ! $takes ) {
			return new WP_Error( 'whtb_empty', __( 'Write something first. Even a T rex needs words.', 'webosaurus-hot-take-box' ), array( 'status' => 400 ) );
		}
		if ( 'add' === $mode ) {
			$takes = self::clean_list( array_merge( self::clean_list( get_post_meta( $post_id, self::TAKES, true ) ), $takes ) );
		}
		update_post_meta( $post_id, self::TAKES, $takes );
		update_post_meta( $post_id, self::STATUS, 'live' );

		// Approved drafts leave the tray.
		$approved = array_map( 'strtolower', wp_list_pluck( $takes, 'text' ) );
		$left     = array();
		foreach ( self::clean_list( get_post_meta( $post_id, self::DRAFTS, true ) ) as $d ) {
			if ( ! in_array( strtolower( $d['text'] ), $approved, true ) ) {
				$left[] = $d;
			}
		}
		update_post_meta( $post_id, self::DRAFTS, $left );

		$user = wp_get_current_user();
		self::patch_meta(
			$post_id,
			array(
				'approved_by' => $user && $user->exists() ? $user->display_name : '',
				'approved_at' => time(),
			)
		);
		return self::state( $post_id );
	}

	/**
	 * Remove one approved take from the live set.
	 *
	 * @param int $post_id Post ID.
	 * @param int $index   Index in the live list.
	 * @return array<string,mixed>
	 */
	public static function remove_live( $post_id, $index ) {
		$takes = self::clean_list( get_post_meta( $post_id, self::TAKES, true ) );
		if ( isset( $takes[ $index ] ) ) {
			array_splice( $takes, $index, 1 );
		}
		update_post_meta( $post_id, self::TAKES, $takes );
		if ( ! $takes ) {
			update_post_meta( $post_id, self::STATUS, self::clean_list( get_post_meta( $post_id, self::DRAFTS, true ) ) ? 'pending' : 'pulled' );
		}
		return self::state( $post_id );
	}

	/**
	 * Take the box down without deleting anything.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string,mixed>
	 */
	public static function pull( $post_id ) {
		update_post_meta( $post_id, self::TAKES, array() );
		update_post_meta( $post_id, self::STATUS, self::clean_list( get_post_meta( $post_id, self::DRAFTS, true ) ) ? 'pending' : 'pulled' );
		return self::state( $post_id );
	}

	/**
	 * Toss one draft.
	 *
	 * @param int $post_id Post ID.
	 * @param int $index   Draft index.
	 * @return array<string,mixed>
	 */
	public static function toss( $post_id, $index ) {
		$drafts = self::clean_list( get_post_meta( $post_id, self::DRAFTS, true ) );
		if ( isset( $drafts[ $index ] ) ) {
			array_splice( $drafts, $index, 1 );
		}
		update_post_meta( $post_id, self::DRAFTS, $drafts );
		if ( ! $drafts && 'pending' === get_post_meta( $post_id, self::STATUS, true ) ) {
			delete_post_meta( $post_id, self::STATUS );
		}
		return self::state( $post_id );
	}

	/**
	 * When a post goes live with no take yet, draft in the background.
	 *
	 * @param string  $new  New status.
	 * @param string  $old  Old status.
	 * @param WP_Post $post Post.
	 */
	public static function on_publish( $new, $old, $post ) {
		if ( 'publish' !== $new || 'publish' === $old ) {
			return;
		}
		if ( ! WHTB_Settings::get( 'auto_draft' ) || ! in_array( $post->post_type, WHTB_Settings::post_types(), true ) ) {
			return;
		}
		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}
		if ( get_post_meta( $post->ID, self::TAKES, true ) || get_post_meta( $post->ID, self::DRAFTS, true ) ) {
			return;
		}
		if ( ! wp_next_scheduled( 'whtb_draft_event', array( (int) $post->ID ) ) ) {
			wp_schedule_single_event( time() + 5, 'whtb_draft_event', array( (int) $post->ID ) );
		}
	}

	/**
	 * Cron callback.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function run_background_draft( $post_id ) {
		$post_id = (int) $post_id;
		if ( get_post( $post_id ) && ! get_post_meta( $post_id, self::TAKES, true ) ) {
			self::draft( $post_id );
		}
	}

	/**
	 * Count posts waiting on approval.
	 *
	 * @return int
	 */
	public static function pending_count() {
		$q = new WP_Query(
			array(
				'post_type'              => WHTB_Settings::post_types(),
				'post_status'            => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'meta_key'               => self::STATUS, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'             => 'pending', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'                 => 'ids',
				'posts_per_page'         => 1,
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		return (int) $q->found_posts;
	}
}
