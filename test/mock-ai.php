<?php
/**
 * Test only mu-plugin: fakes the Anthropic API so the real request path runs
 * without a key or network. Records the last request for the smoke test.
 */
add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		if ( 0 !== strpos( $url, 'https://api.anthropic.com/' ) ) {
			return $pre;
		}
		update_option( 'whtb_test_last_request', array( 'url' => $url, 'headers' => $args['headers'], 'body' => json_decode( $args['body'], true ) ), false );
		$takes = array(
			array( 'text' => 'Sitemap? More like site-map to my heart — Google approved.', 'mood' => 'smug' ),
			array( 'text' => 'Lost in your own website? Even a T rex reads the map first.', 'mood' => 'thinking' ),
			array( 'text' => 'No sitemap, no party. Crawlers RSVP only with directions.', 'mood' => 'cackling' ),
			array( 'text' => 'Kal ho na ho, sitemap zaroor ho.', 'mood' => 'hype' ),
			array( 'text' => 'Google can find anything, except your unlisted pages.', 'mood' => 'shocked' ),
		);
		return array(
			'headers'  => array(),
			'body'     => wp_json_encode(
				array(
					'type'        => 'message',
					'role'        => 'assistant',
					'stop_reason' => 'end_turn',
					'content'     => array(
						array( 'type' => 'thinking', 'thinking' => '' ),
						array( 'type' => 'text', 'text' => "```json\n" . wp_json_encode( array( 'takes' => $takes ) ) . "\n```" ),
					),
				)
			),
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'cookies'  => array(),
		);
	},
	10,
	3
);

// GET /?whtb_peek=1 as an admin prints the last faked request, key redacted.
add_action(
	'init',
	static function () {
		if ( empty( $_GET['whtb_peek'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$r                         = get_option( 'whtb_test_last_request' );
		$r['headers']['x-api-key'] = '(redacted)';
		header( 'Content-Type: application/json' );
		echo wp_json_encode( $r, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}
);
