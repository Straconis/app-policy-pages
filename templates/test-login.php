<?php
/**
 * Tester password screen, shown at /app-slug/test/ when the app has a tester
 * password and the visitor hasn't entered it yet.
 *
 * Available: $app (array), $settings (array), $login_error (string).
 *
 * @package App_Policy_Pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$heading = sprintf(
	/* translators: %s: app name. */
	__( '%s tester access', 'app-policy-pages' ),
	$app['name']
);
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( $heading ); ?></title>
<style>
<?php echo App_Policy_Pages::base_css( $settings['accent'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static CSS, accent escaped. ?>
form{background:var(--card);border:1px solid var(--rule);border-radius:14px;padding:20px;margin:1.5rem 0;max-width:420px}
label{display:block;font-weight:600;margin-bottom:.4rem}
input[type=password]{width:100%;box-sizing:border-box;font:inherit;padding:.6em .8em;border:1px solid var(--rule);border-radius:10px;background:var(--bg);color:var(--fg);margin-bottom:1rem}
button.button{border:0;cursor:pointer;font:inherit;font-weight:600}
.error{color:#c0392b;font-weight:600}
@media (prefers-color-scheme:dark){.error{color:#ff8a7a}}
</style>
</head>
<body>
<main>
<h1><?php echo esc_html( $heading ); ?></h1>
<p class="date"><?php esc_html_e( 'Test builds of the app, downloaded directly rather than from Google Play. Enter the tester password you were given.', 'app-policy-pages' ); ?></p>
<form method="post" action="<?php echo esc_url( App_Policy_Pages::tester_login_url( $app ) ); ?>">
	<?php if ( $login_error ) : ?>
		<p class="error" role="alert"><?php echo esc_html( $login_error ); ?></p>
	<?php endif; ?>
	<label for="app-policy-password"><?php esc_html_e( 'Tester password', 'app-policy-pages' ); ?></label>
	<input type="password" id="app-policy-password" name="app_policy_password" autocomplete="current-password" required autofocus>
	<button type="submit" class="button"><?php esc_html_e( 'Show test builds', 'app-policy-pages' ); ?></button>
</form>
<footer>
	<?php if ( $app['landing']['enabled'] ) : ?>
		<a href="<?php echo esc_url( App_Policy_Pages::app_url( $app ) ); ?>"><?php echo esc_html( $app['name'] ); ?></a> ·
	<?php endif; ?>
	<?php echo App_Policy_Pages::footer_links( $app ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts. ?>
</footer>
</main>
</body>
</html>
