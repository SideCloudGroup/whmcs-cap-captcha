# WHMCS Captcha Hook

A WHMCS client-area hook that adds a selectable captcha driver — [Cap](https://capjs.js.org/) (proof-of-work), [Cloudflare Turnstile](https://www.cloudflare.com/products/turnstile/), or [hCaptcha](https://www.hcaptcha.com/) — without using WHMCS's built-in captcha types.

This repository is a [SideCloud](https://github.com/SideCloudGroup) fork of [Hybula's WHMCS Turnstile hook](https://github.com/hybula/whmcs-turnstile). Shared page detection, admin-area exclusion and the email / password-reset flow stay in one place; only the widget embed, token field and siteverify call change per driver.

### Introduction

By default WHMCS offers two types of captchas, the built-in-easily-cracked GD based captcha and the
easily-cracked-privacy-violating reCAPTCHA by Google.

### Features

- One active driver, selected in the hook file: Cap, Turnstile or hCaptcha.
- Enable captcha on login, register, checkout, ticket, contact and password-reset pages.
- Password-reset / email forms are detected under all WHMCS friendly URL modes (`pwreset`, `?rp=`, `PATH_INFO`, rewritten paths).
- Account-invite pages render the widget on both login and register forms and still require a valid token on POST.
- Support for themes (auto/dark/light) on Turnstile and hCaptcha.
- Ability to disable credits and have it fully white labeled.
- Ability to exclude captcha when clients are logged in, with an option to keep checkout protected.
- Administrator area is excluded using WHMCS's `ADMINAREA` context, including admin login before a session exists.

### Requirements

- PHP 8.x (tested on 8.1.27); hook syntax remains PHP 7.2-compatible
- WHMCS 8.x (tested on 8.9.0)
- The PHP cURL extension, for server-side siteverify calls

### Installation

1. Download the latest release and unzip it in the root of your WHMCS installation. The hook file must be placed in `includes/hooks`. If you previously installed upstream `hybula_turnstile.php`, remove that file so only one captcha hook runs.
2. Edit `includes/hooks/hybula_cap.php` and set `hybulaCaptchaDriver` to `cap`, `turnstile` or `hcaptcha`.
3. Fill the fields for that driver only (see below). Unused driver keys can stay empty.
4. Leave `hybulaCapEnabled` set to `true`. Open a private window to test (an active admin session hides the widget).

Do not copy these constants into `configuration.php`. Settings live in the hook file. Defining the same names in `configuration.php` can trigger "Constant already defined" during WHMCS updates.

### Driver configuration

Shared options (apply to every driver):

- `hybulaCaptchaDriver`: `cap` (default), `turnstile` or `hcaptcha`
- `hybulaCapEnabled`: master on/off switch
- `hybulaCapExcludeLogin`: skip captcha for logged-in clients (except checkout when enabled below)
- `hybulaCapCheckoutLoggedIn`: still require captcha on checkout when a client is logged in
- `hybulaCapFailOpen`: if siteverify fails to return JSON, allow the request (`true`) or block it (`false`)
- `hybulaCapCredits`: show a small Hybula / SideCloud credit next to the widget
- `hybulaCapTheme`: `auto`, `dark` or `light` (Turnstile uses the value as-is; hCaptcha maps `auto` to `light`; Cap is styled with CSS)
- `hybulaCapLocations` / `hybulaCapSelectors`: which pages are protected and where the widget is inserted
- `hybulaCapErrorTitle` / `hybulaCapErrorText` / `hybulaCapErrorButton`: client-visible failure page

#### Cap (`hybulaCaptchaDriver = 'cap'`)

1. Create a site on your Cap server.
2. Set:
   - `hybulaCapUrl`: Cap server URL, without a trailing slash
   - `hybulaCapSite`: site key
   - `hybulaCapSecret`: secret key
3. Optional: `hybulaCapWidgetScript` if you self-host the widget instead of the default jsDelivr URL.

Token field: `cap-token`. Verify URL: `{hybulaCapUrl}/{hybulaCapSite}/siteverify`.

#### Turnstile (`hybulaCaptchaDriver = 'turnstile'`)

1. Create a widget in the Cloudflare dashboard and copy the site key and secret key.
2. Set:
   - `hybulaTurnstileSite`
   - `hybulaTurnstileSecret`

Token field: `cf-turnstile-response`. Verify URL: Cloudflare `siteverify` (form-encoded, includes `remoteip` when present).

#### hCaptcha (`hybulaCaptchaDriver = 'hcaptcha'`)

1. Create a site in the hCaptcha dashboard and copy the site key and secret key.
2. Set:
   - `hybulaHcaptchaSite`
   - `hybulaHcaptchaSecret`

Token field: `h-captcha-response`. Verify URL: hCaptcha `siteverify` (form-encoded, includes `remoteip` and `sitekey`).

### Migrating from a Cap-only install

Existing Cap deployments keep working with almost no change:

1. Keep your current `hybulaCapUrl`, `hybulaCapSite` and `hybulaCapSecret` values.
2. Add (or leave at default) `const hybulaCaptchaDriver = 'cap';`.
3. New shared options (`hybulaCapCredits`, `hybulaCapTheme`, Turnstile / hCaptcha keys) can stay at their shipped defaults.
4. Replace `includes/hooks/hybula_cap.php` and re-apply the settings above if you overwrite a customized copy.

To switch later, change `hybulaCaptchaDriver` and fill that driver's site/secret keys. Cap URL/keys can remain in the file unused.

### Administrator area

This hook protects the client area, not administrator authentication. Requests where WHMCS defines
`ADMINAREA` as true are excluded, including administrator login before an admin session exists and
custom administrator directory names. WHMCS's own administrator authentication and security checks
remain in place. URL paths, query parameters and submitted `adminid` fields do not grant an exemption.

When upgrading, preserve your existing driver, Cap URL, site keys, secrets and other settings in the hook file.
After deployment, check administrator login in a private window and confirm that client login,
registration, password reset and checkout still require a valid challenge. These checks require
your actual WHMCS installation; the standalone tests below use simulated WHMCS context.

### Tests

Run `php -n tests/run.php` for isolated request regressions and `php -l includes/hooks/hybula_cap.php`
for syntax validation. The tests use synthetic configuration and stub HTTP calls; they do not contact
WHMCS or a captcha provider. Each request runs in a separate PHP process so admin constants cannot leak into
client-area cases. Cases cover Cap, Turnstile and hCaptcha on the critical login, reset, checkout,
admin-exclusion and invite paths. PHP 7.2-compatible syntax is retained.

### Contribute

Contributions are welcome with pull requests (PR).

### Sponsored

This project was originally developed by [Hybula B.V.](https://www.hybula.com/) and modified
by [SideCloud](https://github.com/SideCloudGroup).

### License

```Apache License, Version 2.0 and the Commons Clause Restriction```
