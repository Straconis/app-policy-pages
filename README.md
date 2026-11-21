# App Policy Pages

A small WordPress plugin that hosts the privacy policy, terms of use and data deletion pages that app stores ask for. Add each app once in wp-admin and it gets its own URLs:

```
https://your-site.example/smoke-buddy/            (index of the app's pages)
https://your-site.example/smoke-buddy/privacy/
https://your-site.example/smoke-buddy/terms/
https://your-site.example/smoke-buddy/delete-data/
```

## Install

1. Download `app-policy-pages.zip` from the [latest release](../../releases) (or zip this folder).
2. In wp-admin go to **Plugins → Add New Plugin → Upload Plugin**, choose the zip, then **Activate**.
3. Your site must use pretty permalinks (**Settings → Permalinks**, anything except "Plain").

## Use

1. **App Policies → Settings**: enter your default developer name, contact email and website. Optionally set a URL prefix (e.g. `apps` gives `/apps/smoke-buddy/privacy/`).
2. **App Policies → Add App**: type the app name, check the URL slug, fill in the details, and edit the three documents. New apps start with starter templates that you must edit to match what your app does. Untick a document to leave it unpublished.
3. Press **Publish**. The **Page links** box shows the live URLs to paste into Google Play Console or App Store Connect.

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

Deleting the plugin from wp-admin removes its apps and settings.

## Licence

GPL-2.0-or-later.
