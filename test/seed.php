<?php
/** Seed a Playground site for the smoke test. */
require_once '/wordpress/wp-load.php';

if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
	mkdir( WPMU_PLUGIN_DIR, 0777, true );
}
file_put_contents( WPMU_PLUGIN_DIR . '/whtb-mock-ai.php', "<?php require '/seed/mock-ai.php';\n" );

$s             = get_option( 'whtb_settings' );
$s['provider'] = 'anthropic';
update_option( 'whtb_settings', $s );
update_option( 'whtb_keys', array( 'anthropic' => 'sk-test-not-real' ), false );
update_option( 'blogname', 'Test Blog' );
global $wp_rewrite;
$wp_rewrite->set_permalink_structure( '/%postname%/' );

$p1 = wp_insert_post( array(
	'post_title'   => 'Why every website needs a sitemap',
	'post_name'    => 'sitemap',
	'post_status'  => 'draft',
	'post_content' => '<!-- wp:paragraph --><p>A sitemap is a list of every page you want search engines to find. Without one, Google crawls your links and hopes for the best.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>WordPress ships a sitemap at /wp-sitemap.xml. Submit it in Search Console and your new posts get found in hours, not weeks.</p><!-- /wp:paragraph -->',
) );
$p2 = wp_insert_post( array(
	'post_title'   => 'Chai, code and burnout',
	'post_name'    => 'chai-code-burnout',
	'post_status'  => 'publish',
	'post_content' => '<p>Late night coding with endless chai feels productive until your brain stops compiling. Burnout creeps in slowly. Sleep, breaks and boundaries keep developers shipping for years, not weeks.</p>',
) );
$p3 = wp_insert_post( array(
	'post_title'   => 'Dark mode is not a feature',
	'post_name'    => 'dark-mode',
	'post_status'  => 'publish',
	'post_content' => '<p>Dark mode is table stakes now. Users expect it, designers must plan for it, and contrast ratios still matter in the dark.</p>',
) );
update_post_meta( $p3, '_whtb_takes', array( array( 'text' => 'Dark mode: because even your website deserves a night shift.', 'mood' => 'smug' ) ) );
update_post_meta( $p3, '_whtb_status', 'live' );
update_option( 'whtb_test_ids', array( $p1, $p2, $p3 ) );
flush_rewrite_rules();
