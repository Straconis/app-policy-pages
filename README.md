# App Policy Pages

A small WordPress plugin that gives each of your mobile apps a home on your site: a landing page, the privacy policy, terms of use and data deletion pages that app stores ask for, and a private page of test builds for your testers. Add each app once in wp-admin and it gets its own URLs:

```
https://your-site.example/smoke-buddy/              landing page (or a plain list of the policies)
https://your-site.example/smoke-buddy/privacy/
https://your-site.example/smoke-buddy/terms/
https://your-site.example/smoke-buddy/delete-data/
https://your-site.example/smoke-buddy/test/?key=…   private test builds
```

## Install

1. Download `app-policy-pages.zip` from the [latest release](../../releases) (or zip this folder).
2. In wp-admin go to **Plugins → Add New Plugin → Upload Plugin**, choose the zip, then **Activate**.
3. Your site must use pretty permalinks (**Settings → Permalinks**, anything except "Plain").

## Use

1. **App Policies → Settings**: enter your default developer name, contact email and website. Optionally set a URL prefix (e.g. `apps` gives `/apps/smoke-buddy/privacy/`).
2. **App Policies → Add App**: type the app name, check the URL slug, fill in the details, and edit the three documents. New apps start with starter templates that you must edit to match what your app does. Untick a document to leave it unpublished.
3. Press **Publish**. The **Page links** box shows the live URLs to paste into Google Play Console or App Store Connect.

### Landing page

Tick **Show a landing page** in the app's Landing page box, then add a tagline, the app icon, screenshots and a description. The "Get it on Google Play" button links to the app's Store listing URL. With the landing page off, the app's main URL shows a plain list of its policy pages.

### Test builds

Tick **Turn on the private test builds page** in the Test builds box, choose an APK, enter its version and what's new, and press Update. Testers open the tester link shown in the box, see every build (newest first) with its size and SHA-256, and download it.

- Only people with the link (or logged-in editors) can see the page or download builds. A wrong or missing key gets a normal 404.
- Tick **Make a new link** to cut off the old link.
- APKs are stored with random names in `wp-content/uploads/app-policy-builds/`, which has an `.htaccess` that blocks direct access on Apache and LiteSpeed. On Nginx add `location ^~ /wp-content/uploads/app-policy-builds/ { deny all; }`.
- Pages are sent with `noindex` and no-cache headers.
- The upload size limit is your server's PHP `upload_max_filesize` and `post_max_size`; the box shows the current limit.
- Don't use this to give away a paid app publicly. Keep the link to your testers.

Placeholders such as `{app_name}`, `{developer}`, `{email_link}`, `{last_updated}` and `{deletion_url}` are filled in on the live page, so one change in the details updates every page. The full list is in the editor's sidebar.

Pages are standalone (no theme header or footer), light and dark mode aware, and readable on phones. To show a policy inside a normal page of your theme instead, use a shortcode:

```
[app_policy app="smoke-buddy" doc="privacy"]   doc = privacy | terms | deletion
[app_policy_links app="smoke-buddy"]
```

### Import and export

Each app has an **Export this app as JSON** link. **App Policies → Settings → Import an app** loads such a file as a draft. `examples/smoke-buddy.json` is a complete example.

### Customising the page

Copy `templates/page.php` into your theme or a small plugin and point the `app_policy_pages_template` filter at it.

## Uninstall

Deleting the plugin from wp-admin removes its apps, settings and uploaded test builds.

## Licence

GPL-2.0-or-later.
