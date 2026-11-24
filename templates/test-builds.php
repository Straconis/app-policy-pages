<?php
/**
 * Private test builds page, at /app-slug/test/?key=…
 *
 * Available: $app (array), $doc_key ('test'), $settings (array).
 *
 * @package App_Policy_Pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$builds  = App_Policy_Pages::get_builds( $app['id'] );
$heading = sprintf(
	/* translators: %s: app name. */
	__( '%s test builds', 'app-policy-pages' ),
	$app['name']
);
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title><?php echo esc_html( $heading ); ?></title>
<style>
<?php echo App_Policy_Pages::base_css( $settings['accent'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static CSS, accent escaped. ?>
.build{background:var(--card);border:1px solid var(--rule);border-radius:14px;padding:16px 18px;margin:14px 0}
.build h2{margin:0;font-size:1.2rem}
.meta{color:var(--muted);font-size:.9rem;margin:.1rem 0 .6rem}
.latest{display:inline-block;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;background:var(--accent);color:#fff;border-radius:999px;padding:.1em .6em;margin-left:.4em;vertical-align:middle}
.sha{font:12px/1.4 ui-monospace,Menlo,Consolas,monospace;color:var(--muted);word-break:break-all;margin:.6rem 0 0}
.howto{font-size:.95rem}
</style>
</head>
<body>
<main>
<h1><?php echo esc_html( $heading ); ?></h1>
<p class="date"><?php esc_html_e( 'Private page for testers. Please don\'t share this link.', 'app-policy-pages' ); ?></p>

<?php if ( $app['test']['notes'] ) : ?>
	<?php echo App_Policy_Pages::fill( wpautop( wp_kses_post( $app['test']['notes'] ) ), $app ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered, placeholders escaped. ?>
<?php endif; ?>

<?php if ( ! $builds ) : ?>
	<p><?php esc_html_e( 'No builds have been uploaded yet.', 'app-policy-pages' ); ?></p>
<?php endif; ?>

<?php foreach ( $builds as $i => $build ) : ?>
	<section class="build">
		<h2>
			<?php
			/* translators: %s: version number. */
			echo esc_html( sprintf( __( 'Version %s', 'app-policy-pages' ), $build['version'] ) );
			?>
			<?php if ( 0 === $i ) : ?>
				<span class="latest"><?php esc_html_e( 'Latest', 'app-policy-pages' ); ?></span>
			<?php endif; ?>
		</h2>
		<p class="meta"><?php echo esc_html( wp_date( get_option( 'date_format' ), (int) $build['uploaded'] ) . ' · ' . App_Policy_Pages::format_size( $build['size'] ) ); ?></p>
		<?php if ( '' !== $build['notes'] ) : ?>
			<p><?php echo nl2br( esc_html( $build['notes'] ) ); ?></p>
		<?php endif; ?>
		<p><a class="button" href="<?php echo esc_url( App_Policy_Pages::test_url( $app, $build['id'] ) ); ?>" rel="nofollow"><?php esc_html_e( 'Download APK', 'app-policy-pages' ); ?></a></p>
		<?php if ( ! empty( $build['sha256'] ) ) : ?>
			<p class="sha">SHA-256 <?php echo esc_html( $build['sha256'] ); ?></p>
		<?php endif; ?>
	</section>
<?php endforeach; ?>

<?php if ( $builds ) : ?>
	<h2><?php esc_html_e( 'Installing', 'app-policy-pages' ); ?></h2>
	<ol class="howto">
		<li><?php esc_html_e( 'Open this page on your Android phone and tap Download APK.', 'app-policy-pages' ); ?></li>
		<li><?php esc_html_e( 'Open the downloaded file. If Android asks, allow your browser or Files app to install unknown apps.', 'app-policy-pages' ); ?></li>
		<li><?php esc_html_e( 'If installing fails because a version from Google Play is already installed, uninstall that one first. Test builds and store builds are signed differently and can\'t update each other.', 'app-policy-pages' ); ?></li>
	</ol>
<?php endif; ?>

<footer><?php echo App_Policy_Pages::footer_links( $app ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts. ?></footer>
</main>
</body>
</html>
