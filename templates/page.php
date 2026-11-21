<?php
/**
 * Stand-alone page for one app policy, or the app's index of policies.
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
$accent   = $settings['accent'];
$is_index = '' === $doc_key;
$heading  = $is_index ? $app['name'] : sprintf( '%s %s', $app['name'], $types[ $doc_key ]['label'] );
$canon    = App_Policy_Pages::app_url( $app, $doc_key );
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $heading ); ?></title>
<link rel="canonical" href="<?php echo esc_url( $canon ); ?>">
<?php
$icon = get_site_icon_url( 32 );
if ( $icon ) {
	echo '<link rel="icon" href="' . esc_url( $icon ) . '">' . "\n";
}
?>
<style>
:root { --bg:#faf8fc; --fg:#2a1a14; --muted:#6b5c66; --accent:<?php echo esc_html( $accent ); ?>; --rule:#e4dde8; }
@media (prefers-color-scheme: dark) { :root { --bg:#1b1720; --fg:#f1ecf4; --muted:#b4a9bb; --rule:#3a3240; } }
body { background:var(--bg); color:var(--fg); font:17px/1.6 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; margin:0; padding:32px 16px 64px; }
main { max-width:680px; margin:0 auto; }
h1 { font-size:1.8rem; line-height:1.2; margin:0 0 4px; }
h2 { font-size:1.15rem; margin:2rem 0 .4rem; }
h3 { font-size:1rem; margin:1.5rem 0 .3rem; }
.date { color:var(--muted); margin-top:0; }
a { color:var(--accent); }
@media (prefers-color-scheme: dark) { a { filter:brightness(1.35); } }
ul { padding-left:1.3em; }
footer { margin-top:3rem; padding-top:1rem; border-top:1px solid var(--rule); color:var(--muted); font-size:.9rem; }
footer a { color:inherit; }
.doc-list { list-style:none; padding:0; }
.doc-list li { margin:.6rem 0; font-size:1.1rem; }
</style>
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
	<?php
	$parts = array();
	foreach ( $types as $key => $type ) {
		if ( $key !== $doc_key && $app['docs'][ $key ]['enabled'] ) {
			$parts[] = '<a href="' . esc_url( App_Policy_Pages::app_url( $app, $key ) ) . '">' . esc_html( $type['label'] ) . '</a>';
		}
	}
	if ( $app['website'] ) {
		$parts[] = '<a href="' . esc_url( $app['website'] ) . '">' . esc_html( $app['developer'] ) . '</a>';
	} else {
		$parts[] = esc_html( $app['developer'] );
	}
	echo implode( ' · ', $parts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
	?>
</footer>
</main>
</body>
</html>
