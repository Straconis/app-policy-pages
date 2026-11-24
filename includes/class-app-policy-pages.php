<?php
/**
 * Front end: data access, URLs, rewrite rules, rendering and shortcodes.
 *
 * @package App_Policy_Pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class App_Policy_Pages {

	const POST_TYPE    = 'app_policy_app';
	const META_KEY     = '_app_policy_data';
	const OPTION       = 'app_policy_pages_settings';
	const ROUTES       = 'app_policy_pages_routes';
	const FLUSH_FLAG   = 'app_policy_pages_flush';
	const QV_APP       = 'app_policy_app';
	const QV_DOC       = 'app_policy_doc';
	const QV_BUILD     = 'app_policy_build';
	const BUILDS_META  = '_app_policy_builds';
	const BUILDS_DIR   = 'app-policy-builds';

	/** @var App_Policy_Pages|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_action( 'init', array( $this, 'maybe_flush' ), 99 );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'template_redirect' ) );
		add_shortcode( 'app_policy', array( $this, 'shortcode_document' ) );
		add_shortcode( 'app_policy_links', array( $this, 'shortcode_links' ) );
	}

	/* ------------------------------------------------------------------
	 * Documents and settings
	 * ------------------------------------------------------------------ */

	/**
	 * The document types every app can have. The key is stored; the path is
	 * the last part of the public URL.
	 */
	public static function document_types() {
		return array(
			'privacy'  => array(
				'label' => __( 'Privacy Policy', 'app-policy-pages' ),
				'path'  => 'privacy',
			),
			'terms'    => array(
				'label' => __( 'Terms of Use', 'app-policy-pages' ),
				'path'  => 'terms',
			),
			'deletion' => array(
				'label' => __( 'Data Deletion', 'app-policy-pages' ),
				'path'  => 'delete-data',
			),
		);
	}

	public static function default_settings() {
		return array(
			'developer' => get_bloginfo( 'name' ),
			'email'     => get_option( 'admin_email' ),
			'website'   => home_url( '/' ),
			'prefix'    => '',
			'accent'    => '#5f8f52',
		);
	}

	public static function settings() {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::default_settings() );
	}

	/**
	 * Path segment that comes before every app slug, e.g. "apps/" or "".
	 */
	public static function prefix() {
		$prefix = trim( (string) self::settings()['prefix'], '/' );
		return '' === $prefix ? '' : $prefix . '/';
	}

	/**
	 * All the data for one app, with defaults filled in.
	 *
	 * @param int|WP_Post $post App post.
	 * @return array|null
	 */
	public static function get_app( $post ) {
		$post = get_post( $post );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}
		$settings = self::settings();
		$data     = get_post_meta( $post->ID, self::META_KEY, true );
		$data     = is_array( $data ) ? $data : array();

		$app = wp_parse_args(
			$data,
			array(
				'slug'           => $post->post_name ? $post->post_name : sanitize_title( $post->post_title ),
				'developer'      => '',
				'email'          => '',
				'website'        => '',
				'package_id'     => '',
				'store_url'      => '',
				'effective_date' => '',
				'last_updated'   => '',
				'docs'           => array(),
				'landing'        => array(),
				'test'           => array(),
			)
		);

		$app['id']       = $post->ID;
		$app['name']     = get_the_title( $post );
		$app['status']   = $post->post_status;
		// Blank fields fall back to the site-wide defaults.
		$app['developer'] = '' !== $app['developer'] ? $app['developer'] : $settings['developer'];
		$app['email']     = '' !== $app['email'] ? $app['email'] : $settings['email'];
		$app['website']   = '' !== $app['website'] ? $app['website'] : $settings['website'];
		if ( '' === $app['last_updated'] ) {
			$app['last_updated'] = get_post_modified_time( 'Y-m-d', false, $post );
		}
		if ( '' === $app['effective_date'] ) {
			$app['effective_date'] = $app['last_updated'];
		}

		foreach ( self::document_types() as $key => $type ) {
			$doc                 = isset( $app['docs'][ $key ] ) && is_array( $app['docs'][ $key ] ) ? $app['docs'][ $key ] : array();
			$app['docs'][ $key ] = array(
				'enabled' => ! empty( $doc['enabled'] ),
				'content' => isset( $doc['content'] ) ? (string) $doc['content'] : '',
			);
		}
		$landing        = is_array( $app['landing'] ) ? $app['landing'] : array();
		$app['landing'] = array(
			'enabled'        => ! empty( $landing['enabled'] ),
			'tagline'        => isset( $landing['tagline'] ) ? (string) $landing['tagline'] : '',
			'description'    => isset( $landing['description'] ) ? (string) $landing['description'] : '',
			'icon_id'        => isset( $landing['icon_id'] ) ? absint( $landing['icon_id'] ) : 0,
			'screenshot_ids' => isset( $landing['screenshot_ids'] ) ? array_values( array_filter( array_map( 'absint', (array) $landing['screenshot_ids'] ) ) ) : array(),
		);
		$test        = is_array( $app['test'] ) ? $app['test'] : array();
		$app['test'] = array(
			'enabled' => ! empty( $test['enabled'] ),
			'key'     => isset( $test['key'] ) ? (string) $test['key'] : '',
			'notes'   => isset( $test['notes'] ) ? (string) $test['notes'] : '',
		);
		return $app;
	}

	/* ------------------------------------------------------------------
	 * Test builds
	 * ------------------------------------------------------------------ */

	/**
	 * Builds for an app, newest first.
	 */
	public static function get_builds( $app_id ) {
		$builds = get_post_meta( $app_id, self::BUILDS_META, true );
		$builds = is_array( $builds ) ? array_values( $builds ) : array();
		foreach ( $builds as $i => $build ) {
			$builds[ $i ]['_order'] = $i; // Later in the list = uploaded later.
		}
		usort(
			$builds,
			function ( $a, $b ) {
				$by_time = (int) $b['uploaded'] - (int) $a['uploaded'];
				return 0 !== $by_time ? $by_time : $b['_order'] - $a['_order'];
			}
		);
		return $builds;
	}

	/**
	 * Private folder that holds the uploaded builds. Web access to it is
	 * denied; files are only sent through serve_build().
	 */
	public static function builds_dir( $app_id = 0 ) {
		$uploads = wp_upload_dir( null, false );
		$dir     = trailingslashit( $uploads['basedir'] ) . self::BUILDS_DIR;
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			// Apache / LiteSpeed. Nginx needs its own rule (see README).
			file_put_contents( $dir . '/.htaccess', "# App Policy Pages: test builds are only served through WordPress.\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder deny,allow\n\tDeny from all\n</IfModule>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		if ( $app_id ) {
			$dir .= '/' . (int) $app_id;
			if ( ! is_dir( $dir ) ) {
				wp_mkdir_p( $dir );
				file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}
		}
		return $dir;
	}

	public static function test_url( $app, $build_id = '' ) {
		$path = self::prefix() . $app['slug'] . '/test/';
		if ( $build_id ) {
			$path .= 'download/' . $build_id . '/';
		}
		return add_query_arg( 'key', rawurlencode( $app['test']['key'] ), home_url( user_trailingslashit( $path ) ) );
	}

	/**
	 * Testers get in with the key; people who can edit the app always can.
	 */
	public static function can_see_tests( $app ) {
		if ( ! $app['test']['enabled'] ) {
			return false;
		}
		if ( current_user_can( 'edit_post', $app['id'] ) ) {
			return true;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the key is the access check.
		$given = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		return '' !== $app['test']['key'] && '' !== $given && hash_equals( $app['test']['key'], $given );
	}

	private static function no_cache_private() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		do_action( 'litespeed_control_set_nocache', 'app policy pages test builds' );
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'Referrer-Policy: no-referrer', true );
	}

	private static function serve_build( $app, $build_id ) {
		foreach ( self::get_builds( $app['id'] ) as $build ) {
			if ( $build['id'] !== $build_id ) {
				continue;
			}
			$file = self::builds_dir( $app['id'] ) . '/' . basename( $build['file'] );
			if ( ! is_readable( $file ) ) {
				break;
			}
			$name = sanitize_file_name( $app['slug'] . '-' . $build['version'] . '.apk' );
			while ( ob_get_level() ) {
				ob_end_clean();
			}
			self::no_cache_private();
			status_header( 200 );
			header( 'Content-Type: application/vnd.android.package-archive' );
			header( 'Content-Disposition: attachment; filename="' . $name . '"' );
			header( 'Content-Length: ' . filesize( $file ) );
			header( 'X-Content-Type-Options: nosniff' );
			readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			exit;
		}
		return false;
	}

	public static function format_size( $bytes ) {
		return size_format( (int) $bytes, 1 );
	}

	/**
	 * Find a published app by its URL slug.
	 */
	public static function find_app( $slug ) {
		$routes = get_option( self::ROUTES, array() );
		if ( is_array( $routes ) && isset( $routes[ $slug ] ) ) {
			$app = self::get_app( (int) $routes[ $slug ] );
			if ( $app && 'publish' === $app['status'] ) {
				return $app;
			}
		}
		return null;
	}

	public static function app_url( $app, $doc_key = '' ) {
		$path = self::prefix() . $app['slug'] . '/';
		if ( $doc_key ) {
			$types = self::document_types();
			$path .= $types[ $doc_key ]['path'] . '/';
		}
		return home_url( user_trailingslashit( $path ) );
	}

	/* ------------------------------------------------------------------
	 * Placeholders
	 * ------------------------------------------------------------------ */

	public static function placeholders() {
		return array(
			'{app_name}'       => __( 'App name', 'app-policy-pages' ),
			'{developer}'      => __( 'Developer name', 'app-policy-pages' ),
			'{email}'          => __( 'Contact email (plain text)', 'app-policy-pages' ),
			'{email_link}'     => __( 'Contact email as a mailto link', 'app-policy-pages' ),
			'{website}'        => __( 'Website URL', 'app-policy-pages' ),
			'{package_id}'     => __( 'Package / bundle ID', 'app-policy-pages' ),
			'{store_url}'      => __( 'Store listing URL', 'app-policy-pages' ),
			'{effective_date}' => __( 'Effective date', 'app-policy-pages' ),
			'{last_updated}'   => __( 'Last updated date', 'app-policy-pages' ),
			'{privacy_url}'    => __( "This app's privacy policy URL", 'app-policy-pages' ),
			'{terms_url}'      => __( "This app's terms URL", 'app-policy-pages' ),
			'{deletion_url}'   => __( "This app's data deletion URL", 'app-policy-pages' ),
		);
	}

	/**
	 * Shared CSS for the stand-alone pages.
	 */
	public static function base_css( $accent ) {
		return ':root{--bg:#faf8fc;--fg:#2a1a14;--muted:#6b5c66;--accent:' . esc_html( $accent ) . ';--rule:#e4dde8;--card:#ffffff}'
			. '@media (prefers-color-scheme:dark){:root{--bg:#1b1720;--fg:#f1ecf4;--muted:#b4a9bb;--rule:#3a3240;--card:#25202b}a{filter:brightness(1.35)}}'
			. 'body{background:var(--bg);color:var(--fg);font:17px/1.6 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;margin:0;padding:32px 16px 64px}'
			. 'main{max-width:680px;margin:0 auto}h1{font-size:1.8rem;line-height:1.2;margin:0 0 4px}h2{font-size:1.15rem;margin:2rem 0 .4rem}h3{font-size:1rem;margin:1.5rem 0 .3rem}'
			. '.date{color:var(--muted);margin-top:0}a{color:var(--accent)}ul{padding-left:1.3em}img{max-width:100%;height:auto}'
			. 'footer{margin-top:3rem;padding-top:1rem;border-top:1px solid var(--rule);color:var(--muted);font-size:.9rem}footer a{color:inherit}'
			. '.doc-list{list-style:none;padding:0}.doc-list li{margin:.6rem 0;font-size:1.1rem}'
			. '.button{display:inline-block;background:var(--accent);color:#fff;text-decoration:none;font-weight:600;padding:.6em 1.2em;border-radius:999px}'
			. '@media (prefers-color-scheme:dark){.button{filter:none;color:#111}}';
	}

	/**
	 * Footer links shared by every stand-alone page.
	 */
	public static function footer_links( $app, $current = '' ) {
		$parts = array();
		foreach ( self::document_types() as $key => $type ) {
			if ( $key !== $current && $app['docs'][ $key ]['enabled'] ) {
				$parts[] = '<a href="' . esc_url( self::app_url( $app, $key ) ) . '">' . esc_html( $type['label'] ) . '</a>';
			}
		}
		if ( $app['website'] ) {
			$parts[] = '<a href="' . esc_url( $app['website'] ) . '">' . esc_html( $app['developer'] ) . '</a>';
		} else {
			$parts[] = esc_html( $app['developer'] );
		}
		return implode( ' · ', $parts );
	}

	public static function format_date( $ymd ) {
		$ts = strtotime( (string) $ymd );
		return $ts ? date_i18n( get_option( 'date_format' ), $ts ) : (string) $ymd;
	}

	/**
	 * Replace {placeholders} in already-sanitized HTML with escaped values.
	 */
	public static function fill( $html, $app ) {
		$email = sanitize_email( $app['email'] );
		$map   = array(
			'{app_name}'       => esc_html( $app['name'] ),
			'{developer}'      => esc_html( $app['developer'] ),
			'{email}'          => esc_html( $email ),
			'{email_link}'     => $email ? '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>' : '',
			'{website}'        => esc_url( $app['website'] ),
			'{package_id}'     => esc_html( $app['package_id'] ),
			'{store_url}'      => esc_url( $app['store_url'] ),
			'{effective_date}' => esc_html( self::format_date( $app['effective_date'] ) ),
			'{last_updated}'   => esc_html( self::format_date( $app['last_updated'] ) ),
			'{privacy_url}'    => esc_url( self::app_url( $app, 'privacy' ) ),
			'{terms_url}'      => esc_url( self::app_url( $app, 'terms' ) ),
			'{deletion_url}'   => esc_url( self::app_url( $app, 'deletion' ) ),
		);
		return strtr( $html, $map );
	}

	/**
	 * The finished HTML body of one document.
	 */
	public static function render_document_body( $app, $doc_key ) {
		$content = $app['docs'][ $doc_key ]['content'];
		$content = wpautop( wp_kses_post( $content ) );
		return self::fill( $content, $app );
	}

	/* ------------------------------------------------------------------
	 * Routing
	 * ------------------------------------------------------------------ */

	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'               => __( 'App Policies', 'app-policy-pages' ),
					'singular_name'      => __( 'App', 'app-policy-pages' ),
					'menu_name'          => __( 'App Policies', 'app-policy-pages' ),
					'all_items'          => __( 'All Apps', 'app-policy-pages' ),
					'add_new'            => __( 'Add App', 'app-policy-pages' ),
					'add_new_item'       => __( 'Add App', 'app-policy-pages' ),
					'edit_item'          => __( 'Edit App', 'app-policy-pages' ),
					'new_item'           => __( 'New App', 'app-policy-pages' ),
					'search_items'       => __( 'Search Apps', 'app-policy-pages' ),
					'not_found'          => __( 'No apps yet.', 'app-policy-pages' ),
					'not_found_in_trash' => __( 'No apps in the trash.', 'app-policy-pages' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'menu_icon'           => 'dashicons-shield',
				'menu_position'       => 25,
				'supports'            => array( 'title', 'revisions' ),
				'capability_type'     => 'page',
				'map_meta_cap'        => true,
				'rewrite'             => false,
				'query_var'           => false,
				'exclude_from_search' => true,
			)
		);
	}

	public function query_vars( $vars ) {
		$vars[] = self::QV_APP;
		$vars[] = self::QV_DOC;
		$vars[] = self::QV_BUILD;
		return $vars;
	}

	/**
	 * One rule per published app, so the plugin never claims URLs that
	 * belong to ordinary pages.
	 */
	public function add_rewrite_rules() {
		$routes = get_option( self::ROUTES, array() );
		if ( ! is_array( $routes ) || ! $routes ) {
			return;
		}
		$prefix = preg_quote( self::prefix(), '#' );
		$paths  = array();
		foreach ( self::document_types() as $key => $type ) {
			$paths[ $type['path'] ] = $key;
		}
		foreach ( array_keys( $routes ) as $slug ) {
			$s = preg_quote( $slug, '#' );
			foreach ( $paths as $path => $key ) {
				add_rewrite_rule( '^' . $prefix . $s . '/' . preg_quote( $path, '#' ) . '/?$', 'index.php?' . self::QV_APP . '=' . rawurlencode( $slug ) . '&' . self::QV_DOC . '=' . $key, 'top' );
			}
			add_rewrite_rule( '^' . $prefix . $s . '/test/download/([a-z0-9]+)/?$', 'index.php?' . self::QV_APP . '=' . rawurlencode( $slug ) . '&' . self::QV_DOC . '=test&' . self::QV_BUILD . '=$matches[1]', 'top' );
			add_rewrite_rule( '^' . $prefix . $s . '/test/?$', 'index.php?' . self::QV_APP . '=' . rawurlencode( $slug ) . '&' . self::QV_DOC . '=test', 'top' );
			add_rewrite_rule( '^' . $prefix . $s . '/?$', 'index.php?' . self::QV_APP . '=' . rawurlencode( $slug ), 'top' );
		}
	}

	/**
	 * Rebuild the slug => post ID map of published apps, and flush rewrite
	 * rules on the next request.
	 */
	public static function rebuild_routes() {
		$routes = array();
		$posts  = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		foreach ( $posts as $post ) {
			$app = self::get_app( $post );
			if ( $app && '' !== $app['slug'] && ! isset( $routes[ $app['slug'] ] ) ) {
				$routes[ $app['slug'] ] = $post->ID;
			}
		}
		update_option( self::ROUTES, $routes, true );
		update_option( self::FLUSH_FLAG, 1, true );
	}

	public function maybe_flush() {
		// New versions may add URLs, so rebuild the rules once after an update.
		if ( get_option( 'app_policy_pages_version' ) !== APP_POLICY_PAGES_VERSION ) {
			update_option( 'app_policy_pages_version', APP_POLICY_PAGES_VERSION, true );
			self::rebuild_routes();
		}
		if ( get_option( self::FLUSH_FLAG ) ) {
			delete_option( self::FLUSH_FLAG );
			flush_rewrite_rules( false );
		}
	}

	public static function activate() {
		self::instance()->register_post_type();
		self::rebuild_routes();
	}

	public static function deactivate() {
		delete_option( self::FLUSH_FLAG );
		flush_rewrite_rules( false );
	}

	public function template_redirect() {
		$slug = get_query_var( self::QV_APP );
		if ( '' === $slug || null === $slug ) {
			return;
		}
		$app     = self::find_app( $slug );
		$doc_key = (string) get_query_var( self::QV_DOC );
		$types   = self::document_types();

		$is_test = 'test' === $doc_key;
		if ( $app && $is_test && ! self::can_see_tests( $app ) ) {
			$app = null; // A wrong key looks the same as no page at all.
		}
		if ( ! $app || ( '' !== $doc_key && ! $is_test && ( ! isset( $types[ $doc_key ] ) || ! $app['docs'][ $doc_key ]['enabled'] ) ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			if ( $is_test ) {
				self::no_cache_private();
			}
			return; // Let the theme show its normal 404 page.
		}

		$settings = self::settings();
		if ( $is_test ) {
			$build_id = sanitize_key( (string) get_query_var( self::QV_BUILD ) );
			if ( $build_id ) {
				self::serve_build( $app, $build_id );
				global $wp_query;
				$wp_query->set_404();
				status_header( 404 );
				return;
			}
			self::no_cache_private();
			$template = APP_POLICY_PAGES_DIR . 'templates/test-builds.php';
		} elseif ( '' === $doc_key && $app['landing']['enabled'] ) {
			nocache_headers();
			$template = APP_POLICY_PAGES_DIR . 'templates/landing.php';
		} else {
			nocache_headers();
			$template = APP_POLICY_PAGES_DIR . 'templates/page.php';
		}
		status_header( 200 );
		/**
		 * Filter the template file used for a policy page.
		 *
		 * @param string $template Absolute path.
		 * @param array  $app      App data.
		 * @param string $doc_key  Document key, 'test' for test builds, or '' for the app's index or landing page.
		 */
		$template = apply_filters( 'app_policy_pages_template', $template, $app, $doc_key );
		include $template;
		exit;
	}

	/* ------------------------------------------------------------------
	 * Shortcodes, for putting a policy inside a normal page of your theme.
	 * ------------------------------------------------------------------ */

	/**
	 * [app_policy app="smoke-buddy" doc="privacy"]
	 */
	public function shortcode_document( $atts ) {
		$atts  = shortcode_atts( array( 'app' => '', 'doc' => 'privacy' ), $atts, 'app_policy' );
		$app   = self::find_app( sanitize_title( $atts['app'] ) );
		$types = self::document_types();
		$key   = sanitize_key( $atts['doc'] );
		if ( ! $app || ! isset( $types[ $key ] ) || ! $app['docs'][ $key ]['enabled'] ) {
			return '';
		}
		return '<div class="app-policy app-policy-' . esc_attr( $key ) . '">' . self::render_document_body( $app, $key ) . '</div>';
	}

	/**
	 * [app_policy_links app="smoke-buddy"]
	 */
	public function shortcode_links( $atts ) {
		$atts = shortcode_atts( array( 'app' => '' ), $atts, 'app_policy_links' );
		$app  = self::find_app( sanitize_title( $atts['app'] ) );
		if ( ! $app ) {
			return '';
		}
		$items = '';
		foreach ( self::document_types() as $key => $type ) {
			if ( $app['docs'][ $key ]['enabled'] ) {
				$items .= '<li><a href="' . esc_url( self::app_url( $app, $key ) ) . '">' . esc_html( $type['label'] ) . '</a></li>';
			}
		}
		return $items ? '<ul class="app-policy-links">' . $items . '</ul>' : '';
	}
}
