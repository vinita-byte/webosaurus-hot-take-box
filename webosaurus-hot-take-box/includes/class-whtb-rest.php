<?php
/**
 * REST routes used by the editor panel and the Hot Take Desk.
 *
 * @package WebosaurusHotTakeBox
 */

defined( 'ABSPATH' ) || exit;

/**
 * Routes under whtb/v1.
 */
class WHTB_Rest {

	const NS = 'whtb/v1';

	/** Hooks. */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/** Register routes. */
	public static function routes() {
		$id = array(
			'id' => array(
				'type'     => 'integer',
				'required' => true,
			),
		);

		register_rest_route(
			self::NS,
			'/posts/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => static function ( $req ) {
					return rest_ensure_response( WHTB_Store::state( (int) $req['id'] ) );
				},
				'permission_callback' => array( __CLASS__, 'can_edit' ),
				'args'                => $id,
			)
		);

		register_rest_route(
			self::NS,
			'/posts/(?P<id>\d+)/draft',
			array(
				'methods'             => 'POST',
				'callback'            => static function ( $req ) {
					$override = array();
					foreach ( array( 'title', 'content' ) as $f ) {
						if ( is_string( $req->get_param( $f ) ) ) {
							$override[ $f ] = $req->get_param( $f );
						}
					}
					$state = WHTB_Store::draft( (int) $req['id'], $override );
					if ( is_wp_error( $state ) ) {
						$state->add_data( array( 'status' => 502 ) );
					}
					return rest_ensure_response( $state );
				},
				'permission_callback' => array( __CLASS__, 'can_edit' ),
				'args'                => $id,
			)
		);

		register_rest_route(
			self::NS,
			'/posts/(?P<id>\d+)/approve',
			array(
				'methods'             => 'POST',
				'callback'            => static function ( $req ) {
					$mode = 'add' === $req->get_param( 'mode' ) ? 'add' : 'replace';
					return rest_ensure_response( WHTB_Store::approve( (int) $req['id'], (array) $req->get_param( 'takes' ), $mode ) );
				},
				'permission_callback' => array( __CLASS__, 'can_approve' ),
				'args'                => array_merge(
					$id,
					array(
						'takes' => array(
							'type'     => 'array',
							'required' => true,
						),
						'mode'  => array(
							'type' => 'string',
							'enum' => array( 'replace', 'add' ),
						),
					)
				),
			)
		);

		register_rest_route(
			self::NS,
			'/posts/(?P<id>\d+)/pull',
			array(
				'methods'             => 'POST',
				'callback'            => static function ( $req ) {
					$index = $req->get_param( 'index' );
					if ( null !== $index && '' !== $index ) {
						return rest_ensure_response( WHTB_Store::remove_live( (int) $req['id'], (int) $index ) );
					}
					return rest_ensure_response( WHTB_Store::pull( (int) $req['id'] ) );
				},
				'permission_callback' => array( __CLASS__, 'can_approve' ),
				'args'                => $id,
			)
		);

		register_rest_route(
			self::NS,
			'/posts/(?P<id>\d+)/toss',
			array(
				'methods'             => 'POST',
				'callback'            => static function ( $req ) {
					return rest_ensure_response( WHTB_Store::toss( (int) $req['id'], (int) $req->get_param( 'index' ) ) );
				},
				'permission_callback' => array( __CLASS__, 'can_edit' ),
				'args'                => array_merge(
					$id,
					array(
						'index' => array(
							'type'     => 'integer',
							'required' => true,
						),
					)
				),
			)
		);
	}

	/**
	 * Post must exist, be a supported type, and be editable by the user.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return bool|WP_Error
	 */
	public static function can_edit( $req ) {
		$post = get_post( (int) $req['id'] );
		if ( ! $post || ! in_array( $post->post_type, WHTB_Settings::post_types(), true ) ) {
			return new WP_Error( 'whtb_no_post', __( 'Post not found.', 'webosaurus-hot-take-box' ), array( 'status' => 404 ) );
		}
		return current_user_can( 'edit_post', $post->ID );
	}

	/**
	 * Approving puts words on the live site, so it needs publish rights too.
	 *
	 * @param WP_REST_Request $req Request.
	 * @return bool|WP_Error
	 */
	public static function can_approve( $req ) {
		$ok = self::can_edit( $req );
		if ( true !== $ok ) {
			return $ok;
		}
		$pto = get_post_type_object( get_post_type( (int) $req['id'] ) );
		return $pto && current_user_can( $pto->cap->publish_posts );
	}
}
