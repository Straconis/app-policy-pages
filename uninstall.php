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
// Test builds live in a private uploads folder.
$app_policy_uploads = wp_upload_dir( null, false );
$app_policy_dir     = trailingslashit( $app_policy_uploads['basedir'] ) . 'app-policy-builds';
if ( is_dir( $app_policy_dir ) ) {
	$app_policy_files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $app_policy_dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $app_policy_files as $app_policy_file ) {
		$app_policy_file->isDir() ? rmdir( $app_policy_file->getPathname() ) : unlink( $app_policy_file->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	rmdir( $app_policy_dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions
}
delete_option( 'app_policy_pages_settings' );
delete_option( 'app_policy_pages_routes' );
delete_option( 'app_policy_pages_flush' );
delete_option( 'app_policy_pages_version' );
flush_rewrite_rules( false );
