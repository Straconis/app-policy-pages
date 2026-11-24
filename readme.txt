=== App Policy Pages ===
Contributors: straconis
Tags: privacy policy, app, terms, google play, data deletion
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Host a landing page, privacy policy, terms, data deletion page and private test builds for each of your mobile apps, at URLs such as /your-app/ and /your-app/privacy/.

== Description ==

Add each of your apps under App Policies, fill in its details and documents, and publish. Every app gets /app-slug/privacy/, /app-slug/terms/ and /app-slug/delete-data/ pages, ready to paste into Google Play Console or App Store Connect. Placeholders like {app_name} and {email_link} keep the details consistent.

== Changelog ==

= 1.2.0 =
* Optional tester password: testers can unlock the test builds page by typing a password instead of using the secret link. Stored hashed, 5 wrong tries then a 15-minute wait, remembered for 30 days, and changing it signs everyone out.
* Optional "Beta testers" link on the landing page that opens the password screen.

= 1.1.0 =
* Landing page per app: tagline, icon, screenshots, description and store button.
* Private test builds page: upload APKs with version and notes, tester-only download link that you can replace at any time.

= 1.0.0 =
* First release.
