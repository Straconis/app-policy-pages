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
		add_action( 'post_edit_form_tag', array( $this, 'form_enctype' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
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
		add_meta_box( 'app-policy-landing', __( 'Landing page', 'app-policy-pages' ), array( $this, 'box_landing' ), null, 'normal', 'high' );
		add_meta_box( 'app-policy-tests', __( 'Test builds', 'app-policy-pages' ), array( $this, 'box_tests' ), null, 'normal', 'default' );
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
		$home = App_Policy_Pages::app_url( $app );
		echo '<li><strong>' . esc_html( $app['landing']['enabled'] ? __( 'Landing page', 'app-policy-pages' ) : __( 'Index of policies', 'app-policy-pages' ) ) . '</strong><br><a href="' . esc_url( $home ) . '" target="_blank" rel="noopener">' . esc_html( $home ) . '</a></li>';
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
		if ( $app['test']['enabled'] && $app['test']['key'] ) {
			$turl = App_Policy_Pages::test_url( $app );
			echo '<li><strong>' . esc_html__( 'Test builds (private)', 'app-policy-pages' ) . '</strong><br><a href="' . esc_url( $turl ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $turl ) . '</a></li>';
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
		$l              = isset( $in['landing'] ) && is_array( $in['landing'] ) ? $in['landing'] : array();
		$shots          = isset( $l['screenshot_ids'] ) ? $l['screenshot_ids'] : array();
		$shots          = is_array( $shots ) ? $shots : explode( ',', (string) $shots );
		$out['landing'] = array(
			'enabled'        => ! empty( $l['enabled'] ),
			'tagline'        => sanitize_text_field( isset( $l['tagline'] ) ? $l['tagline'] : '' ),
			'description'    => wp_kses_post( isset( $l['description'] ) ? (string) $l['description'] : '' ),
			'icon_id'        => isset( $l['icon_id'] ) ? absint( $l['icon_id'] ) : 0,
			'screenshot_ids' => array_values( array_filter( array_map( 'absint', $shots ) ) ),
		);

		$t        = isset( $in['test'] ) && is_array( $in['test'] ) ? $in['test'] : array();
		$existing = get_post_meta( $post_id, App_Policy_Pages::META_KEY, true );
		$key      = is_array( $existing ) && ! empty( $existing['test']['key'] ) ? (string) $existing['test']['key'] : '';
		if ( '' === $key || ! empty( $t['new_key'] ) ) {
			$key = self::new_key();
		}
		$hash = is_array( $existing ) && ! empty( $existing['test']['password_hash'] ) ? (string) $existing['test']['password_hash'] : '';
		$new  = isset( $t['new_password'] ) ? trim( (string) $t['new_password'] ) : '';
		if ( '' !== $new ) {
			$hash = wp_hash_password( $new );
		} elseif ( ! empty( $t['clear_password'] ) ) {
			$hash = '';
		}
		$out['test'] = array(
			'enabled'       => ! empty( $t['enabled'] ),
			'key'           => $key,
			'notes'         => wp_kses_post( isset( $t['notes'] ) ? (string) $t['notes'] : '' ),
			'password_hash' => $hash,
			'show_link'     => ! empty( $t['show_link'] ),
		);
		return $out;
	}

	private static function new_key() {
		return strtolower( wp_generate_password( 24, false, false ) );
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
		$this->save_builds( $post_id );

		$page = get_page_by_path( App_Policy_Pages::prefix() . $data['slug'] );
		if ( $page ) {
			set_transient( 'app_policy_conflict_' . get_current_user_id(), $data['slug'], 60 );
		}
	}

	/**
	 * Delete ticked builds and store a newly uploaded one.
	 */
	private function save_builds( $post_id ) {
		$builds  = get_post_meta( $post_id, App_Policy_Pages::BUILDS_META, true );
		$builds  = is_array( $builds ) ? $builds : array();
		$changed = false;
		$dir     = App_Policy_Pages::builds_dir( $post_id );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked in save_app().
		$delete = isset( $_POST['app_policy_delete_builds'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['app_policy_delete_builds'] ) ) : array();
		foreach ( $builds as $i => $build ) {
			if ( in_array( $build['id'], $delete, true ) ) {
				wp_delete_file( $dir . '/' . basename( $build['file'] ) );
				unset( $builds[ $i ] );
				$changed = true;
			}
		}

		$upload = isset( $_FILES['app_policy_build_file'] ) ? $_FILES['app_policy_build_file'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated below.
		if ( $upload && isset( $upload['error'] ) && UPLOAD_ERR_NO_FILE !== (int) $upload['error'] ) {
			$version = isset( $_POST['app_policy_build_version'] ) ? sanitize_text_field( wp_unslash( $_POST['app_policy_build_version'] ) ) : '';
			$notes   = isset( $_POST['app_policy_build_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['app_policy_build_notes'] ) ) : '';
			$error   = '';
			$name    = isset( $upload['name'] ) ? (string) $upload['name'] : '';
			$tmp     = isset( $upload['tmp_name'] ) ? (string) $upload['tmp_name'] : '';
			if ( UPLOAD_ERR_OK !== (int) $upload['error'] ) {
				/* translators: %s: maximum upload size. */
				$error = in_array( (int) $upload['error'], array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) ? sprintf( __( 'The APK is larger than this server allows (%s).', 'app-policy-pages' ), size_format( wp_max_upload_size() ) ) : __( 'The upload failed. Please try again.', 'app-policy-pages' );
			} elseif ( '' === $version ) {
				$error = __( 'Enter a version number for the build.', 'app-policy-pages' );
			} elseif ( 'apk' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) || ! is_uploaded_file( $tmp ) ) {
				$error = __( 'Only .apk files can be uploaded as test builds.', 'app-policy-pages' );
			} else {
				$fh    = fopen( $tmp, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				$magic = $fh ? fread( $fh, 4 ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
				if ( $fh ) {
					fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				}
				if ( "PK\x03\x04" !== $magic ) {
					$error = __( 'That file is not a valid APK.', 'app-policy-pages' );
				}
			}
			if ( ! $error ) {
				$id   = strtolower( wp_generate_password( 12, false, false ) );
				$file = $id . '.apk';
				if ( move_uploaded_file( $tmp, $dir . '/' . $file ) ) {
					$builds[] = array(
						'id'       => $id,
						'file'     => $file,
						'version'  => $version,
						'notes'    => $notes,
						'size'     => filesize( $dir . '/' . $file ),
						'sha256'   => hash_file( 'sha256', $dir . '/' . $file ),
						'original' => sanitize_file_name( $name ),
						'uploaded' => time(),
					);
					$changed  = true;
					/* translators: %s: version number. */
					$this->notice( 'success', sprintf( __( 'Test build %s uploaded.', 'app-policy-pages' ), $version ) );
				} else {
					$error = __( 'Could not save the APK on the server.', 'app-policy-pages' );
				}
			}
			if ( $error ) {
				$this->notice( 'error', $error );
			}
		}
		// phpcs:enable
		if ( $changed ) {
			update_post_meta( $post_id, App_Policy_Pages::BUILDS_META, array_values( $builds ) );
		}
	}

	private function notice( $type, $message ) {
		set_transient( 'app_policy_notice_' . get_current_user_id(), array( $type, $message ), 120 );
	}

	public function form_enctype( $post ) {
		if ( App_Policy_Pages::POST_TYPE === $post->post_type ) {
			echo ' enctype="multipart/form-data"';
		}
	}

	public function enqueue( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || App_Policy_Pages::POST_TYPE !== $screen->post_type || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'app-policy-pages-admin', plugins_url( 'assets/admin.js', APP_POLICY_PAGES_FILE ), array( 'jquery' ), APP_POLICY_PAGES_VERSION, true );
		wp_localize_script(
			'app-policy-pages-admin',
			'appPolicyPages',
			array(
				'chooseIcon'  => __( 'Choose app icon', 'app-policy-pages' ),
				'chooseShots' => __( 'Choose screenshots', 'app-policy-pages' ),
				'use'         => __( 'Use these', 'app-policy-pages' ),
				'copied'      => __( 'Copied', 'app-policy-pages' ),
			)
		);
	}

	public function box_landing( $post ) {
		$app = $this->editor_data( $post );
		$l   = $app['landing'];
		?>
		<p><label><input type="checkbox" name="app_policy[landing][enabled]" value="1" <?php checked( $l['enabled'] ); ?>> <?php esc_html_e( 'Show a landing page at the app\'s main URL (otherwise it shows a plain list of the policy pages)', 'app-policy-pages' ); ?></label></p>
		<table class="form-table app-policy-form" role="presentation">
			<tr>
				<th><label for="apppol-tagline"><?php esc_html_e( 'Tagline', 'app-policy-pages' ); ?></label></th>
				<td><input type="text" id="apppol-tagline" name="app_policy[landing][tagline]" value="<?php echo esc_attr( $l['tagline'] ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'One short line about the app', 'app-policy-pages' ); ?>"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'App icon', 'app-policy-pages' ); ?></th>
				<td class="app-policy-media" data-multiple="0">
					<input type="hidden" name="app_policy[landing][icon_id]" value="<?php echo esc_attr( $l['icon_id'] ? $l['icon_id'] : '' ); ?>">
					<div class="app-policy-previews"><?php echo $l['icon_id'] ? wp_get_attachment_image( $l['icon_id'], 'thumbnail' ) : ''; ?></div>
					<button type="button" class="button app-policy-pick"><?php esc_html_e( 'Choose icon', 'app-policy-pages' ); ?></button>
					<button type="button" class="button-link app-policy-clear"><?php esc_html_e( 'Remove', 'app-policy-pages' ); ?></button>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Screenshots', 'app-policy-pages' ); ?></th>
				<td class="app-policy-media" data-multiple="1">
					<input type="hidden" name="app_policy[landing][screenshot_ids]" value="<?php echo esc_attr( implode( ',', $l['screenshot_ids'] ) ); ?>">
					<div class="app-policy-previews">
					<?php
					foreach ( $l['screenshot_ids'] as $sid ) {
						echo wp_get_attachment_image( $sid, 'thumbnail' );
					}
					?>
					</div>
					<button type="button" class="button app-policy-pick"><?php esc_html_e( 'Choose screenshots', 'app-policy-pages' ); ?></button>
					<button type="button" class="button-link app-policy-clear"><?php esc_html_e( 'Remove all', 'app-policy-pages' ); ?></button>
					<p class="description"><?php esc_html_e( 'Select several at once. They show in the order you pick them.', 'app-policy-pages' ); ?></p>
				</td>
			</tr>
		</table>
		<p><strong><?php esc_html_e( 'Description', 'app-policy-pages' ); ?></strong> <span class="description"><?php esc_html_e( '(a few paragraphs or a feature list; placeholders work here too)', 'app-policy-pages' ); ?></span></p>
		<?php
		wp_editor(
			$l['description'],
			'apppol_landing_description',
			array(
				'textarea_name' => 'app_policy[landing][description]',
				'textarea_rows' => 10,
				'media_buttons' => true,
			)
		);
		echo '<p class="description">' . esc_html__( 'The "Get it on Google Play" button uses the Store listing URL above.', 'app-policy-pages' ) . '</p>';
	}

	public function box_tests( $post ) {
		$app    = App_Policy_Pages::get_app( $post );
		$t      = $app['test'];
		$builds = App_Policy_Pages::get_builds( $post->ID );
		?>
		<p><label><input type="checkbox" name="app_policy[test][enabled]" value="1" <?php checked( $t['enabled'] ); ?>> <?php esc_html_e( 'Turn on the private test builds page', 'app-policy-pages' ); ?></label></p>
		<?php if ( $t['key'] && 'publish' === $post->post_status ) : ?>
			<p><?php esc_html_e( 'Tester link (only people with this link can see and download builds):', 'app-policy-pages' ); ?><br>
				<input type="text" readonly class="large-text code app-policy-copy" value="<?php echo esc_attr( App_Policy_Pages::test_url( $app ) ); ?>">
			</p>
			<p><label><input type="checkbox" name="app_policy[test][new_key]" value="1"> <?php esc_html_e( 'Make a new link when I save (the old link stops working)', 'app-policy-pages' ); ?></label></p>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'Publish the app to get its tester link.', 'app-policy-pages' ); ?></p>
		<?php endif; ?>
		<table class="form-table app-policy-form" role="presentation">
			<tr><th><label for="apppol-test-password"><?php esc_html_e( 'Tester password', 'app-policy-pages' ); ?></label></th>
				<td>
					<input type="password" id="apppol-test-password" name="app_policy[test][new_password]" class="regular-text" autocomplete="new-password" placeholder="<?php echo esc_attr( App_Policy_Pages::has_password( $app ) ? __( 'Set. Type to change', 'app-policy-pages' ) : __( 'Not set', 'app-policy-pages' ) ); ?>">
					<?php if ( App_Policy_Pages::has_password( $app ) ) : ?>
						<label style="margin-left:1em"><input type="checkbox" name="app_policy[test][clear_password]" value="1"> <?php esc_html_e( 'Remove the password', 'app-policy-pages' ); ?></label>
					<?php endif; ?>
					<p class="description">
					<?php
					/* translators: 1: number of tries, 2: tester page URL. */
					printf( esc_html__( 'Optional. With a password, testers can also get in at %2$s by typing it, instead of using the secret link. It is stored hashed, and after %1$d wrong tries a visitor has to wait 15 minutes. Changing it signs everyone out.', 'app-policy-pages' ), (int) App_Policy_Pages::PASSWORD_TRIES, '<code>' . esc_html( App_Policy_Pages::tester_login_url( $app ) ) . '</code>' );
					?>
					</p>
				</td></tr>
			<tr><th><?php esc_html_e( 'Landing page link', 'app-policy-pages' ); ?></th>
				<td><label><input type="checkbox" name="app_policy[test][show_link]" value="1" <?php checked( $app['test']['show_link'] ); ?>> <?php esc_html_e( 'Show a "Beta testers: download the APK directly (outside Google Play)" link on the landing page (only when a password is set)', 'app-policy-pages' ); ?></label></td></tr>
		</table>
		<p><label for="apppol-test-notes"><strong><?php esc_html_e( 'Message for testers', 'app-policy-pages' ); ?></strong></label> <span class="description"><?php esc_html_e( '(shown above the builds, optional)', 'app-policy-pages' ); ?></span><br>
			<textarea id="apppol-test-notes" name="app_policy[test][notes]" rows="3" class="large-text"><?php echo esc_textarea( $t['notes'] ); ?></textarea></p>

		<h4><?php esc_html_e( 'Upload a build', 'app-policy-pages' ); ?></h4>
		<table class="form-table app-policy-form" role="presentation">
			<tr><th><label for="apppol-build-file"><?php esc_html_e( 'APK file', 'app-policy-pages' ); ?></label></th>
				<td><input type="file" id="apppol-build-file" name="app_policy_build_file" accept=".apk,application/vnd.android.package-archive">
				<p class="description">
				<?php
				/* translators: %s: size such as 64 MB. */
				printf( esc_html__( 'Largest file this server accepts: %s. The file is uploaded when you press Update or Publish.', 'app-policy-pages' ), esc_html( size_format( wp_max_upload_size() ) ) );
				?>
				</p></td></tr>
			<tr><th><label for="apppol-build-version"><?php esc_html_e( 'Version', 'app-policy-pages' ); ?></label></th>
				<td><input type="text" id="apppol-build-version" name="app_policy_build_version" class="regular-text" placeholder="0.9.2"></td></tr>
			<tr><th><label for="apppol-build-notes"><?php esc_html_e( 'What\'s new', 'app-policy-pages' ); ?></label></th>
				<td><textarea id="apppol-build-notes" name="app_policy_build_notes" rows="3" class="large-text"></textarea></td></tr>
		</table>

		<h4><?php esc_html_e( 'Uploaded builds', 'app-policy-pages' ); ?></h4>
		<?php if ( ! $builds ) : ?>
			<p class="description"><?php esc_html_e( 'None yet.', 'app-policy-pages' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Version', 'app-policy-pages' ); ?></th><th><?php esc_html_e( 'Uploaded', 'app-policy-pages' ); ?></th><th><?php esc_html_e( 'Size', 'app-policy-pages' ); ?></th><th><?php esc_html_e( 'What\'s new', 'app-policy-pages' ); ?></th><th><?php esc_html_e( 'Delete', 'app-policy-pages' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $builds as $b ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $b['version'] ); ?></strong><br><small><?php echo esc_html( $b['original'] ); ?></small></td>
						<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $b['uploaded'] ) ); ?></td>
						<td><?php echo esc_html( App_Policy_Pages::format_size( $b['size'] ) ); ?></td>
						<td><?php echo nl2br( esc_html( $b['notes'] ) ); ?></td>
						<td><input type="checkbox" name="app_policy_delete_builds[]" value="<?php echo esc_attr( $b['id'] ); ?>" aria-label="<?php esc_attr_e( 'Delete this build', 'app-policy-pages' ); ?>"></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description"><?php esc_html_e( 'Ticked builds are deleted when you press Update.', 'app-policy-pages' ); ?></p>
		<?php endif; ?>
		<?php
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
		unset( $raw['test']['key'], $raw['test']['password_hash'] ); // Never put tester secrets in an export.
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
		$nkey = 'app_policy_notice_' . get_current_user_id();
		$note = get_transient( $nkey );
		if ( is_array( $note ) ) {
			delete_transient( $nkey );
			echo '<div class="notice notice-' . esc_attr( 'error' === $note[0] ? 'error' : 'success' ) . ' is-dismissible"><p>' . esc_html( $note[1] ) . '</p></div>';
		}
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
		echo '<style>.app-policy-form th{width:170px}.app-policy-linklist li{margin-bottom:.8em;word-break:break-all}.app-policy-placeholders li{margin-bottom:.3em}.app-policy-placeholders code{font-size:12px}.app-policy-previews{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px}.app-policy-previews img{width:80px;height:80px;object-fit:cover;border-radius:8px;border:1px solid #dcdcde}</style>';
	}
}
