<?php
/**
 * Removes everything the plugin stored when it is deleted from wp-admin.
 *
 * @package App_Policy_Pages
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$app_policy_ids = get_posts(
	array(
		'post_type'      => 'app_policy_app',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
foreach ( $app_policy_ids as $app_policy_id ) {
	wp_delete_post( $app_policy_id, true );
}
delete_option( 'app_policy_pages_settings' );
delete_option( 'app_policy_pages_routes' );
delete_option( 'app_policy_pages_flush' );
flush_rewrite_rules( false );
