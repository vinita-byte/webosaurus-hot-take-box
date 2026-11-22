<?php
/**
 * Clean up on uninstall: settings, keys and every take.
 *
 * @package WebosaurusHotTakeBox
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'whtb_settings' );
delete_option( 'whtb_keys' );
foreach ( array( '_whtb_drafts', '_whtb_takes', '_whtb_status', '_whtb_meta' ) as $whtb_key ) {
	delete_post_meta_by_key( $whtb_key );
}
wp_clear_scheduled_hook( 'whtb_draft_event' );
