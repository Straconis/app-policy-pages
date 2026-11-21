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
		return $app;
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

		if ( ! $app || ( '' !== $doc_key && ( ! isset( $types[ $doc_key ] ) || ! $app['docs'][ $doc_key ]['enabled'] ) ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			return; // Let the theme show its normal 404 page.
		}

		status_header( 200 );
		nocache_headers();
		$settings = self::settings();
		$template = APP_POLICY_PAGES_DIR . 'templates/page.php';
		/**
		 * Filter the template file used for a policy page.
		 *
		 * @param string $template Absolute path.
		 * @param array  $app      App data.
		 * @param string $doc_key  Document key, or '' for the app's index page.
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
