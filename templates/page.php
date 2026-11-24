<?php
/**
 * Stand-alone page for one app policy, or the app's plain index of policies
 * (used when the app's landing page is turned off).
 *
 * Available: $app (array), $doc_key (string, '' for the index), $settings (array).
 * Copy this file and point the app_policy_pages_template filter at your copy to change it.
 *
 * @package App_Policy_Pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$types    = App_Policy_Pages::document_types();
$is_index = '' === $doc_key;
$heading  = $is_index ? $app['name'] : sprintf( '%s %s', $app['name'], $types[ $doc_key ]['label'] );
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $heading ); ?></title>
<link rel="canonical" href="<?php echo esc_url( App_Policy_Pages::app_url( $app, $doc_key ) ); ?>">
<?php
$icon = get_site_icon_url( 32 );
if ( $icon ) {
	echo '<link rel="icon" href="' . esc_url( $icon ) . '">' . "\n";
}
?>
<style><?php echo App_Policy_Pages::base_css( $settings['accent'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static CSS, accent escaped. ?></style>
</head>
<body>
<main>
<h1><?php echo esc_html( $heading ); ?></h1>
<?php if ( $is_index ) : ?>
	<p class="date"><?php echo esc_html( $app['developer'] ); ?></p>
	<ul class="doc-list">
	<?php foreach ( $types as $key => $type ) : ?>
		<?php if ( $app['docs'][ $key ]['enabled'] ) : ?>
			<li><a href="<?php echo esc_url( App_Policy_Pages::app_url( $app, $key ) ); ?>"><?php echo esc_html( $type['label'] ); ?></a></li>
		<?php endif; ?>
	<?php endforeach; ?>
	</ul>
<?php else : ?>
	<p class="date">
	<?php
	/* translators: %s: date. */
	printf( esc_html__( 'Last updated: %s', 'app-policy-pages' ), esc_html( App_Policy_Pages::format_date( $app['last_updated'] ) ) );
	?>
	</p>
	<?php echo App_Policy_Pages::render_document_body( $app, $doc_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered, placeholders escaped. ?>
<?php endif; ?>
<footer>
	<?php if ( ! $is_index && $app['landing']['enabled'] ) : ?>
		<a href="<?php echo esc_url( App_Policy_Pages::app_url( $app ) ); ?>"><?php echo esc_html( $app['name'] ); ?></a> ·
	<?php endif; ?>
	<?php echo App_Policy_Pages::footer_links( $app, $doc_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts. ?>
</footer>
</main>
</body>
</html>
