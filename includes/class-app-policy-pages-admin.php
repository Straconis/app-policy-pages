<?php
/**
 * wp-admin screens: the app editor, the settings page and import/export.
 *
 * @package App_Policy_Pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class App_Policy_Pages_Admin {

	const NONCE = 'app_policy_pages_save';

	/** @var App_Policy_Pages_Admin|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$pt = App_Policy_Pages::POST_TYPE;
		add_action( 'add_meta_boxes_' . $pt, array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . $pt, array( $this, 'save_app' ), 10, 2 );
		add_action( 'transition_post_status', array( $this, 'status_changed' ), 10, 3 );
		add_action( 'deleted_post', array( $this, 'post_deleted' ), 10, 2 );
		add_filter( 'enter_title_here', array( $this, 'title_placeholder' ), 10, 2 );
		add_filter( 'manage_' . $pt . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . $pt . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_app_policy_export', array( $this, 'export_app' ) );
		add_action( 'admin_post_app_policy_import', array( $this, 'import_app' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
		add_action( 'admin_head', array( $this, 'admin_css' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( APP_POLICY_PAGES_FILE ), array( $this, 'plugin_links' ) );
	}

	/* ------------------------------------------------------------------
	 * App editor
	 * ------------------------------------------------------------------ */

	public function title_placeholder( $text, $post ) {
		return App_Policy_Pages::POST_TYPE === $post->post_type ? __( 'App name, e.g. Smoke Buddy', 'app-policy-pages' ) : $text;
	}

	public function add_meta_boxes( $post ) {
		add_meta_box( 'app-policy-details', __( 'App details', 'app-policy-pages' ), array( $this, 'box_details' ), null, 'normal', 'high' );
		foreach ( App_Policy_Pages::document_types() as $key => $type ) {
			add_meta_box(
				'app-policy-doc-' . $key,
				$type['label'],
				array( $this, 'box_document' ),
				null,
				'normal',
				'default',
				array( 'key' => $key )
			);
		}
		add_meta_box( 'app-policy-links', __( 'Page links', 'app-policy-pages' ), array( $this, 'box_links' ), null, 'side', 'high' );
		add_meta_box( 'app-policy-placeholders', __( 'Placeholders', 'app-policy-pages' ), array( $this, 'box_placeholders' ), null, 'side', 'default' );
	}

	/**
	 * Data for the editor. A brand-new app starts with the default templates.
	 */
	private function editor_data( $post ) {
		$app = App_Policy_Pages::get_app( $post );
		$raw = get_post_meta( $post->ID, App_Policy_Pages::META_KEY, true );
		if ( ! is_array( $raw ) || ! $raw ) {
			// Show blanks (so defaults apply) and the starter templates.
			foreach ( array( 'developer', 'email', 'website', 'effective_date', 'last_updated' ) as $f ) {
				$app[ $f ] = '';
			}
			$app['slug'] = 'auto-draft' === $post->post_status ? '' : $app['slug'];
			foreach ( App_Policy_Pages::document_types() as $key => $type ) {
				$app['docs'][ $key ] = array(
					'enabled' => true,
					'content' => self::default_template( $key ),
				);
			}
		} else {
			// Show what's stored, not the fallbacks, so blanks stay blank.
			foreach ( array( 'developer', 'email', 'website', 'effective_date', 'last_updated' ) as $f ) {
				$app[ $f ] = isset( $raw[ $f ] ) ? $raw[ $f ] : '';
			}
		}
		return $app;
	}

	public static function default_template( $key ) {
		$file = APP_POLICY_PAGES_DIR . 'templates/defaults/' . sanitize_file_name( $key ) . '.html';
		return is_readable( $file ) ? (string) file_get_contents( $file ) : '';
	}

	public function box_details( $post ) {
		$app      = $this->editor_data( $post );
		$settings = App_Policy_Pages::settings();
		wp_nonce_field( self::NONCE, 'app_policy_nonce' );
		$base = home_url( '/' . App_Policy_Pages::prefix() );
		?>
		<table class="form-table app-policy-form" role="presentation">
			<tr>
				<th><label for="apppol-slug"><?php esc_html_e( 'URL slug', 'app-policy-pages' ); ?></label></th>
				<td>
					<code><?php echo esc_html( $base ); ?></code><input type="text" id="apppol-slug" name="app_policy[slug]" value="<?php echo esc_attr( $app['slug'] ); ?>" class="regular-text code" style="width:16em" placeholder="<?php esc_attr_e( 'made from the app name', 'app-policy-pages' ); ?>"><code>/privacy/</code>
					<p class="description"><?php esc_html_e( 'Lowercase letters, numbers and dashes. Leave blank to make it from the app name.', 'app-policy-pages' ); ?></p>
				</td>
			</tr>
			<?php
			$fields = array(
				'developer'  => array( __( 'Developer name', 'app-policy-pages' ), 'text', $settings['developer'] ),
				'email'      => array( __( 'Contact email', 'app-policy-pages' ), 'email', $settings['email'] ),
				'website'    => array( __( 'Website', 'app-policy-pages' ), 'url', $settings['website'] ),
				'package_id' => array( __( 'Package / bundle ID', 'app-policy-pages' ), 'text', 'com.example.app' ),
				'store_url'  => array( __( 'Store listing URL', 'app-policy-pages' ), 'url', 'https://play.google.com/store/apps/details?id=…' ),
			);
			foreach ( $fields as $name => $f ) :
				?>
				<tr>
					<th><label for="apppol-<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $f[0] ); ?></label></th>
					<td><input type="<?php echo esc_attr( $f[1] ); ?>" id="apppol-<?php echo esc_attr( $name ); ?>" name="app_policy[<?php echo esc_attr( $name ); ?>]" value="<?php echo esc_attr( $app[ $name ] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( $f[2] ); ?>"></td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<th><label for="apppol-effective"><?php esc_html_e( 'Effective date', 'app-policy-pages' ); ?></label></th>
				<td><input type="date" id="apppol-effective" name="app_policy[effective_date]" value="<?php echo esc_attr( $app['effective_date'] ); ?>"></td>
			</tr>
			<tr>
				<th><label for="apppol-updated"><?php esc_html_e( 'Last updated', 'app-policy-pages' ); ?></label></th>
				<td>
					<input type="date" id="apppol-updated" name="app_policy[last_updated]" value="<?php echo esc_attr( $app['last_updated'] ); ?>">
					<label style="margin-left:1em"><input type="checkbox" name="app_policy[touch_updated]" value="1"> <?php esc_html_e( 'Set to today when I save', 'app-policy-pages' ); ?></label>
				</td>
			</tr>
		</table>
		<p class="description"><?php esc_html_e( 'Blank developer, email and website fields use the defaults from App Policies → Settings. A blank date uses the date this app was last saved.', 'app-policy-pages' ); ?></p>
		<?php
	}

	public function box_document( $post, $box ) {
		$key  = $box['args']['key'];
		$app  = $this->editor_data( $post );
		$doc  = $app['docs'][ $key ];
		$type = App_Policy_Pages::document_types()[ $key ];
		?>
		<p>
			<label><input type="checkbox" name="app_policy[docs][<?php echo esc_attr( $key ); ?>][enabled]" value="1" <?php checked( $doc['enabled'] ); ?>>
			<?php
			/* translators: %s: URL path such as "privacy". */
			printf( esc_html__( 'Publish this page at …/%s/', 'app-policy-pages' ), esc_html( $type['path'] ) );
			?>
			</label>
		</p>
		<?php
		wp_editor(
			$doc['content'],
			'apppol_doc_' . $key,
			array(
				'textarea_name' => 'app_policy[docs][' . $key . '][content]',
				'textarea_rows' => 18,
				'media_buttons' => false,
			)
		);
	}

	public function box_links( $post ) {
		$app = App_Policy_Pages::get_app( $post );
		if ( 'publish' !== $post->post_status ) {
			echo '<p>' . esc_html__( 'Publish the app to make its pages live.', 'app-policy-pages' ) . '</p>';
			return;
		}
		echo '<ul class="app-policy-linklist">';
		foreach ( App_Policy_Pages::document_types() as $key => $type ) {
			$url = App_Policy_Pages::app_url( $app, $key );
			echo '<li><strong>' . esc_html( $type['label'] ) . '</strong>';
			if ( $app['docs'][ $key ]['enabled'] ) {
				echo '<br><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $url ) . '</a>';
			} else {
				echo '<br><em>' . esc_html__( 'Not published', 'app-policy-pages' ) . '</em>';
			}
			echo '</li>';
		}
		echo '</ul>';
		echo '<p class="description">' . esc_html__( 'To show a policy inside a normal page of your theme instead, use a shortcode:', 'app-policy-pages' ) . '</p>';
		echo '<p><code>[app_policy app="' . esc_html( $app['slug'] ) . '" doc="privacy"]</code></p>';
		echo '<p><a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=app_policy_export&post=' . $post->ID ), 'app_policy_export_' . $post->ID ) ) . '">' . esc_html__( 'Export this app as JSON', 'app-policy-pages' ) . '</a></p>';
	}

	public function box_placeholders() {
		echo '<p>' . esc_html__( 'Type these anywhere in a document and they are filled in on the live page:', 'app-policy-pages' ) . '</p><ul class="app-policy-placeholders">';
		foreach ( App_Policy_Pages::placeholders() as $tag => $label ) {
			echo '<li><code>' . esc_html( $tag ) . '</code> ' . esc_html( $label ) . '</li>';
		}
		echo '</ul>';
	}

	/**
	 * Clean an array of submitted or imported app data.
	 */
	public static function sanitize_app_data( $in, $post_id, $title ) {
		$in  = is_array( $in ) ? $in : array();
		$out = array();

		$slug = sanitize_title( isset( $in['slug'] ) ? $in['slug'] : '' );
		if ( '' === $slug ) {
			$slug = sanitize_title( $title );
		}
		$out['slug'] = self::unique_slug( $slug, $post_id );

		$out['developer']  = sanitize_text_field( isset( $in['developer'] ) ? $in['developer'] : '' );
		$out['email']      = sanitize_email( isset( $in['email'] ) ? $in['email'] : '' );
		$out['website']    = esc_url_raw( isset( $in['website'] ) ? $in['website'] : '' );
		$out['package_id'] = sanitize_text_field( isset( $in['package_id'] ) ? $in['package_id'] : '' );
		$out['store_url']  = esc_url_raw( isset( $in['store_url'] ) ? $in['store_url'] : '' );
		foreach ( array( 'effective_date', 'last_updated' ) as $f ) {
			$v         = isset( $in[ $f ] ) ? trim( (string) $in[ $f ] ) : '';
			$out[ $f ] = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : '';
		}
		if ( ! empty( $in['touch_updated'] ) ) {
			$out['last_updated'] = current_time( 'Y-m-d' );
		}

		$out['docs'] = array();
		foreach ( App_Policy_Pages::document_types() as $key => $type ) {
			$d                   = isset( $in['docs'][ $key ] ) && is_array( $in['docs'][ $key ] ) ? $in['docs'][ $key ] : array();
			$out['docs'][ $key ] = array(
				'enabled' => ! empty( $d['enabled'] ),
				'content' => wp_kses_post( isset( $d['content'] ) ? (string) $d['content'] : '' ),
			);
		}
		return $out;
	}

	/**
	 * Make sure no two apps share a URL slug.
	 */
	private static function unique_slug( $slug, $post_id ) {
		if ( '' === $slug ) {
			return 'app-' . (int) $post_id;
		}
		$taken = array();
		$posts = get_posts(
			array(
				'post_type'      => App_Policy_Pages::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'post__not_in'   => array( (int) $post_id ),
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		foreach ( $posts as $id ) {
			$m = get_post_meta( $id, App_Policy_Pages::META_KEY, true );
			if ( is_array( $m ) && ! empty( $m['slug'] ) ) {
				$taken[ $m['slug'] ] = true;
			}
		}
		$base = $slug;
		$n    = 2;
		while ( isset( $taken[ $slug ] ) ) {
			$slug = $base . '-' . $n++;
		}
		return $slug;
	}

	public function save_app( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || ! isset( $_POST['app_policy_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['app_policy_nonce'] ) ), self::NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized field by field below.
		$in   = isset( $_POST['app_policy'] ) ? wp_unslash( $_POST['app_policy'] ) : array();
		$data = self::sanitize_app_data( $in, $post_id, $post->post_title );
		update_post_meta( $post_id, App_Policy_Pages::META_KEY, $data );
		App_Policy_Pages::rebuild_routes();

		$page = get_page_by_path( App_Policy_Pages::prefix() . $data['slug'] );
		if ( $page ) {
			set_transient( 'app_policy_conflict_' . get_current_user_id(), $data['slug'], 60 );
		}
	}

	public function status_changed( $new, $old, $post ) {
		if ( App_Policy_Pages::POST_TYPE === $post->post_type && $new !== $old ) {
			App_Policy_Pages::rebuild_routes();
		}
	}

	public function post_deleted( $post_id, $post = null ) {
		if ( $post && App_Policy_Pages::POST_TYPE === $post->post_type ) {
			App_Policy_Pages::rebuild_routes();
		}
	}

	/* ------------------------------------------------------------------
	 * App list
	 * ------------------------------------------------------------------ */

	public function columns( $cols ) {
		$new = array();
		foreach ( $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'title' === $k ) {
				$new['app_policy_urls'] = __( 'Pages', 'app-policy-pages' );
			}
		}
		return $new;
	}

	public function column_content( $col, $post_id ) {
		if ( 'app_policy_urls' !== $col ) {
			return;
		}
		$app = App_Policy_Pages::get_app( $post_id );
		if ( 'publish' !== $app['status'] ) {
			echo '<em>' . esc_html__( 'Not live until published', 'app-policy-pages' ) . '</em>';
			return;
		}
		$links = array();
		foreach ( App_Policy_Pages::document_types() as $key => $type ) {
			if ( $app['docs'][ $key ]['enabled'] ) {
				$links[] = '<a href="' . esc_url( App_Policy_Pages::app_url( $app, $key ) ) . '" target="_blank" rel="noopener">' . esc_html( $type['label'] ) . '</a>';
			}
		}
		echo '<code>/' . esc_html( App_Policy_Pages::prefix() . $app['slug'] ) . '/</code><br>' . implode( ' · ', $links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
	}

	public function row_actions( $actions, $post ) {
		if ( App_Policy_Pages::POST_TYPE === $post->post_type ) {
			unset( $actions['inline hide-if-no-js'] );
		}
		return $actions;
	}

	/* ------------------------------------------------------------------
	 * Settings page
	 * ------------------------------------------------------------------ */

	public function admin_menu() {
		add_submenu_page(
			'edit.php?post_type=' . App_Policy_Pages::POST_TYPE,
			__( 'App Policies Settings', 'app-policy-pages' ),
			__( 'Settings', 'app-policy-pages' ),
			'manage_options',
			'app-policy-pages-settings',
			array( $this, 'settings_page' )
		);
	}

	public function plugin_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'edit.php?post_type=' . App_Policy_Pages::POST_TYPE . '&page=app-policy-pages-settings' ) ) . '">' . esc_html__( 'Settings', 'app-policy-pages' ) . '</a>' );
		return $links;
	}

	public function register_settings() {
		register_setting(
			'app_policy_pages',
			App_Policy_Pages::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	public function sanitize_settings( $in ) {
		$in     = is_array( $in ) ? $in : array();
		$old    = App_Policy_Pages::settings();
		$prefix = isset( $in['prefix'] ) ? (string) $in['prefix'] : '';
		$prefix = implode( '/', array_filter( array_map( 'sanitize_title', explode( '/', $prefix ) ) ) );
		$accent = sanitize_hex_color( isset( $in['accent'] ) ? $in['accent'] : '' );
		$out    = array(
			'developer' => sanitize_text_field( isset( $in['developer'] ) ? $in['developer'] : '' ),
			'email'     => sanitize_email( isset( $in['email'] ) ? $in['email'] : '' ),
			'website'   => esc_url_raw( isset( $in['website'] ) ? $in['website'] : '' ),
			'prefix'    => $prefix,
			'accent'    => $accent ? $accent : '#5f8f52',
		);
		if ( $out['prefix'] !== trim( (string) $old['prefix'], '/' ) ) {
			update_option( App_Policy_Pages::FLUSH_FLAG, 1, true );
		}
		return $out;
	}

	public function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s   = App_Policy_Pages::settings();
		$opt = App_Policy_Pages::OPTION;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'App Policies Settings', 'app-policy-pages' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'app_policy_pages' ); ?>
				<h2><?php esc_html_e( 'Defaults for every app', 'app-policy-pages' ); ?></h2>
				<p><?php esc_html_e( 'An app uses these when its own field is left blank.', 'app-policy-pages' ); ?></p>
				<table class="form-table" role="presentation">
					<tr><th><label for="apppol-s-dev"><?php esc_html_e( 'Developer name', 'app-policy-pages' ); ?></label></th>
						<td><input type="text" id="apppol-s-dev" name="<?php echo esc_attr( $opt ); ?>[developer]" value="<?php echo esc_attr( $s['developer'] ); ?>" class="regular-text"></td></tr>
					<tr><th><label for="apppol-s-email"><?php esc_html_e( 'Contact email', 'app-policy-pages' ); ?></label></th>
						<td><input type="email" id="apppol-s-email" name="<?php echo esc_attr( $opt ); ?>[email]" value="<?php echo esc_attr( $s['email'] ); ?>" class="regular-text"></td></tr>
					<tr><th><label for="apppol-s-web"><?php esc_html_e( 'Website', 'app-policy-pages' ); ?></label></th>
						<td><input type="url" id="apppol-s-web" name="<?php echo esc_attr( $opt ); ?>[website]" value="<?php echo esc_attr( $s['website'] ); ?>" class="regular-text"></td></tr>
				</table>
				<h2><?php esc_html_e( 'Pages', 'app-policy-pages' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><label for="apppol-s-prefix"><?php esc_html_e( 'URL prefix', 'app-policy-pages' ); ?></label></th>
						<td><code><?php echo esc_html( home_url( '/' ) ); ?></code><input type="text" id="apppol-s-prefix" name="<?php echo esc_attr( $opt ); ?>[prefix]" value="<?php echo esc_attr( $s['prefix'] ); ?>" class="code" style="width:10em" placeholder="<?php esc_attr_e( '(none)', 'app-policy-pages' ); ?>"><code>/app-slug/privacy/</code>
						<p class="description"><?php esc_html_e( 'Optional. Leave blank for /smoke-buddy/privacy/, or enter "apps" for /apps/smoke-buddy/privacy/.', 'app-policy-pages' ); ?></p></td></tr>
					<tr><th><label for="apppol-s-accent"><?php esc_html_e( 'Link colour', 'app-policy-pages' ); ?></label></th>
						<td><input type="color" id="apppol-s-accent" name="<?php echo esc_attr( $opt ); ?>[accent]" value="<?php echo esc_attr( $s['accent'] ); ?>"></td></tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Import an app', 'app-policy-pages' ); ?></h2>
			<p><?php esc_html_e( 'Upload a JSON file exported from this plugin (each app has an "Export this app as JSON" link). The app is added as a draft so you can review it before publishing.', 'app-policy-pages' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="app_policy_import">
				<?php wp_nonce_field( 'app_policy_import' ); ?>
				<input type="file" name="app_policy_file" accept=".json,application/json" required>
				<?php submit_button( __( 'Import', 'app-policy-pages' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Import / export
	 * ------------------------------------------------------------------ */

	public function export_app() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'app_policy_export_' . $post_id );
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'app-policy-pages' ) );
		}
		$post = get_post( $post_id );
		$raw  = get_post_meta( $post_id, App_Policy_Pages::META_KEY, true );
		if ( ! $post || App_Policy_Pages::POST_TYPE !== $post->post_type || ! is_array( $raw ) ) {
			wp_die( esc_html__( 'App not found.', 'app-policy-pages' ) );
		}
		$export = array_merge(
			array(
				'format' => 'app-policy-pages/1',
				'name'   => $post->post_title,
			),
			$raw
		);
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $raw['slug'] . '.json' ) . '"' );
		echo wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	public function import_app() {
		check_admin_referer( 'app_policy_import' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'app-policy-pages' ) );
		}
		$back = admin_url( 'edit.php?post_type=' . App_Policy_Pages::POST_TYPE . '&page=app-policy-pages-settings' );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- only the temp path is used, and its contents are validated.
		$tmp  = isset( $_FILES['app_policy_file']['tmp_name'] ) ? $_FILES['app_policy_file']['tmp_name'] : '';
		$json = ( $tmp && is_uploaded_file( $tmp ) ) ? json_decode( (string) file_get_contents( $tmp ), true ) : null;
		if ( ! is_array( $json ) || empty( $json['name'] ) ) {
			wp_safe_redirect( add_query_arg( 'app_policy_import', 'bad', $back ) );
			exit;
		}
		$title   = sanitize_text_field( $json['name'] );
		$post_id = wp_insert_post(
			array(
				'post_type'   => App_Policy_Pages::POST_TYPE,
				'post_status' => 'draft',
				'post_title'  => $title,
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			wp_safe_redirect( add_query_arg( 'app_policy_import', 'bad', $back ) );
			exit;
		}
		update_post_meta( $post_id, App_Policy_Pages::META_KEY, self::sanitize_app_data( $json, $post_id, $title ) );
		wp_safe_redirect( add_query_arg( 'app_policy_imported', 1, get_edit_post_link( $post_id, 'raw' ) ) );
		exit;
	}

	/* ------------------------------------------------------------------
	 * Notices and styling
	 * ------------------------------------------------------------------ */

	public function notices() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		if ( isset( $_GET['app_policy_import'] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'That file is not an App Policy Pages export.', 'app-policy-pages' ) . '</p></div>';
		}
		if ( isset( $_GET['app_policy_imported'] ) ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'App imported as a draft. Check it over, then press Publish to make its pages live.', 'app-policy-pages' ) . '</p></div>';
		}
		// phpcs:enable
		$key  = 'app_policy_conflict_' . get_current_user_id();
		$slug = get_transient( $key );
		if ( $slug ) {
			delete_transient( $key );
			/* translators: %s: URL path. */
			echo '<div class="notice notice-warning"><p>' . sprintf( esc_html__( 'A normal page already lives at /%s/. The app\'s policy pages still work, but its app index page replaces that page. Pick a different URL slug if that is not what you want.', 'app-policy-pages' ), esc_html( App_Policy_Pages::prefix() . $slug ) ) . '</p></div>';
		}
	}

	public function admin_css() {
		$screen = get_current_screen();
		if ( ! $screen || App_Policy_Pages::POST_TYPE !== $screen->post_type ) {
			return;
		}
		echo '<style>.app-policy-form th{width:170px}.app-policy-linklist li{margin-bottom:.8em;word-break:break-all}.app-policy-placeholders li{margin-bottom:.3em}.app-policy-placeholders code{font-size:12px}</style>';
	}
}
