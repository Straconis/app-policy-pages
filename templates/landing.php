<?php
/**
 * An app's landing page, at /app-slug/.
 *
 * Available: $app (array), $doc_key (''), $settings (array).
 *
 * @package App_Policy_Pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$landing  = $app['landing'];
$icon_url = $landing['icon_id'] ? wp_get_attachment_image_url( $landing['icon_id'], 'medium' ) : '';
$shots    = array();
foreach ( $landing['screenshot_ids'] as $shot_id ) {
	$src = wp_get_attachment_image_url( $shot_id, 'large' );
	if ( $src ) {
		$shots[] = array(
			'src' => $src,
			'alt' => (string) get_post_meta( $shot_id, '_wp_attachment_image_alt', true ),
		);
	}
}
$description = $landing['description'] ? App_Policy_Pages::fill( wpautop( wp_kses_post( $landing['description'] ) ), $app ) : '';
$summary     = $landing['tagline'] ? $landing['tagline'] : wp_trim_words( wp_strip_all_tags( $description ), 30 );
$canonical   = App_Policy_Pages::app_url( $app );
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $app['name'] . ( $landing['tagline'] ? ' – ' . $landing['tagline'] : '' ) ); ?></title>
<link rel="canonical" href="<?php echo esc_url( $canonical ); ?>">
<meta name="description" content="<?php echo esc_attr( $summary ); ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?php echo esc_attr( $app['name'] ); ?>">
<meta property="og:description" content="<?php echo esc_attr( $summary ); ?>">
<meta property="og:url" content="<?php echo esc_url( $canonical ); ?>">
<?php if ( $icon_url ) : ?>
<meta property="og:image" content="<?php echo esc_url( $icon_url ); ?>">
<link rel="icon" href="<?php echo esc_url( $icon_url ); ?>">
<?php endif; ?>
<style>
<?php echo App_Policy_Pages::base_css( $settings['accent'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static CSS, accent escaped. ?>
.hero{display:flex;gap:20px;align-items:center;margin-bottom:1.5rem}
.hero img{width:96px;height:96px;border-radius:22px;flex:none;box-shadow:0 2px 10px rgba(0,0,0,.15)}
.hero h1{margin:0}
.tagline{margin:.2rem 0 0;font-size:1.1rem}
.by{color:var(--muted);margin:.2rem 0 0;font-size:.95rem}
.cta{margin:1.5rem 0}
.shots{display:flex;gap:12px;overflow-x:auto;padding:4px 0 12px;margin:1.5rem 0;scroll-snap-type:x mandatory}
.shots img{height:420px;width:auto;max-width:none;border-radius:14px;flex:none;scroll-snap-align:start;border:1px solid var(--rule)}
@media (max-width:480px){.hero{gap:14px}.hero img{width:72px;height:72px;border-radius:16px}.shots img{height:360px}}
</style>
</head>
<body>
<main>
<div class="hero">
	<?php if ( $icon_url ) : ?>
		<img src="<?php echo esc_url( $icon_url ); ?>" alt="">
	<?php endif; ?>
	<div>
		<h1><?php echo esc_html( $app['name'] ); ?></h1>
		<?php if ( $landing['tagline'] ) : ?>
			<p class="tagline"><?php echo esc_html( $landing['tagline'] ); ?></p>
		<?php endif; ?>
		<p class="by"><?php echo esc_html( $app['developer'] ); ?></p>
	</div>
</div>

<?php if ( $app['store_url'] ) : ?>
	<p class="cta"><a class="button" href="<?php echo esc_url( $app['store_url'] ); ?>"><?php echo esc_html( false !== strpos( $app['store_url'], 'play.google.com' ) ? __( 'Get it on Google Play', 'app-policy-pages' ) : __( 'Get the app', 'app-policy-pages' ) ); ?></a></p>
<?php endif; ?>

<?php if ( $shots ) : ?>
	<div class="shots">
		<?php foreach ( $shots as $shot ) : ?>
			<img src="<?php echo esc_url( $shot['src'] ); ?>" alt="<?php echo esc_attr( $shot['alt'] ); ?>" loading="lazy">
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<?php echo $description; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered, placeholders escaped. ?>

<footer><?php echo App_Policy_Pages::footer_links( $app ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts. ?></footer>
</main>
</body>
</html>
