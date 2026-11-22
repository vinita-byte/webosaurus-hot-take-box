<?php
/**
 * Plugin Name:       Webosaurus Hot Take Box
 * Plugin URI:        https://github.com/vinita-byte/webosaurus-hot-take-box
 * Description:       At the end of every post, WebSaurus the T rex drops a one line punny reaction. AI drafts a handful of takes, you approve the one that goes live.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            WhatTheWeb
 * Author URI:        https://whattheweb.co.in
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       webosaurus-hot-take-box
 *
 * @package WebosaurusHotTakeBox
 */

defined( 'ABSPATH' ) || exit;

define( 'WHTB_VERSION', '1.0.0' );
define( 'WHTB_FILE', __FILE__ );
define( 'WHTB_DIR', plugin_dir_path( __FILE__ ) );
define( 'WHTB_URL', plugin_dir_url( __FILE__ ) );

require_once WHTB_DIR . 'includes/class-whtb-settings.php';
require_once WHTB_DIR . 'includes/class-whtb-store.php';
require_once WHTB_DIR . 'includes/class-whtb-puns.php';
require_once WHTB_DIR . 'includes/class-whtb-ai.php';
require_once WHTB_DIR . 'includes/class-whtb-rest.php';
require_once WHTB_DIR . 'includes/class-whtb-admin.php';
require_once WHTB_DIR . 'includes/class-whtb-frontend.php';

/**
 * Moods map one to one onto the WebSaurus Lottie files in assets/lottie.
 *
 * @return array<string,string> mood slug => label.
 */
function whtb_moods() {
	return array(
		'smug'     => __( 'Smug (the classic hot take)', 'webosaurus-hot-take-box' ),
		'hype'     => __( 'Hype (jumps for joy)', 'webosaurus-hot-take-box' ),
		'cackling' => __( 'Cackling (cannot stop laughing)', 'webosaurus-hot-take-box' ),
		'shocked'  => __( 'Shocked (wait, what?)', 'webosaurus-hot-take-box' ),
		'thinking' => __( 'Thinking (big brain moment)', 'webosaurus-hot-take-box' ),
		'talk'     => __( 'Talk (just chatting)', 'webosaurus-hot-take-box' ),
		'idle'     => __( 'Idle (chill and smug)', 'webosaurus-hot-take-box' ),
	);
}

/**
 * URL of the Lottie JSON for a mood.
 *
 * @param string $mood Mood slug.
 * @return string
 */
function whtb_lottie_url( $mood ) {
	$moods = whtb_moods();
	if ( ! isset( $moods[ $mood ] ) ) {
		$mood = 'smug';
	}
	return WHTB_URL . 'assets/lottie/websaurus-' . $mood . '.json';
}

register_activation_hook( __FILE__, array( 'WHTB_Settings', 'activate' ) );
register_deactivation_hook(
	__FILE__,
	static function () {
		wp_clear_scheduled_hook( 'whtb_draft_event' );
	}
);

add_action(
	'plugins_loaded',
	static function () {
		WHTB_Settings::init();
		WHTB_Rest::init();
		WHTB_Admin::init();
		WHTB_Frontend::init();
		WHTB_Store::init();
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	static function ( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=whtb-settings' ) ) . '">' . esc_html__( 'Settings', 'webosaurus-hot-take-box' ) . '</a>'
		);
		return $links;
	}
);
