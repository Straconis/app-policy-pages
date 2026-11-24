<?php
/**
 * Plugin Name:       App Policy Pages
 * Plugin URI:        https://github.com/straconis/app-policy-pages
 * Description:       Hosts a landing page, privacy policy, terms, data deletion page and private test builds for each of your mobile apps, at URLs such as /your-app/ and /your-app/privacy/.
 * Version:           1.2.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Straconis Software
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       app-policy-pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'APP_POLICY_PAGES_VERSION', '1.2.0' );
define( 'APP_POLICY_PAGES_FILE', __FILE__ );
define( 'APP_POLICY_PAGES_DIR', plugin_dir_path( __FILE__ ) );

require_once APP_POLICY_PAGES_DIR . 'includes/class-app-policy-pages.php';
require_once APP_POLICY_PAGES_DIR . 'includes/class-app-policy-pages-admin.php';

App_Policy_Pages::instance();
if ( is_admin() ) {
	App_Policy_Pages_Admin::instance();
}

register_activation_hook( __FILE__, array( 'App_Policy_Pages', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'App_Policy_Pages', 'deactivate' ) );
