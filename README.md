# WHMCS Cap Captcha

[Cap](https://capjs.js.org/) is a lightweight, modern open-source CAPTCHA alternative using proof-of-work

### Introduction

By default WHMCS offers two types of captchas, the built-in-easily-cracked GD based captcha and the
easily-cracked-privacy-violating reCAPTCHA by Google.

### Features

- Enable Cap captcha on login, register, checkout, ticket, contact pages.
- Support for themes (auto/dark/light).
- Ability to disable credits and have it fully white labeled.
- Ability to exclude captcha when clients are logged in.

### Requirements

- PHP 8.x (tested on 8.1.27)
- WHMCS 8.x (tested on 8.9.0)

### Installation

1. Download the latest release and unzip it in the root of your WHMCS installation, make sure the hook file is placed in
   `includes/hooks`.
2. Create site key in your own Cap server.
3. Edit `includes/hooks/hybula_cap` and fill following fields:
    - `hybulaCapUrl`: Your Cap server URL, without ending slash.
    - `hybulaCapSite`: Your Cap site key.
    - `hybulaCapSecret`: Your Cap secret key.

Final notes: Due to some limitations, the captcha will **NOT** be shown when there is an active admin session. If you
want to test it, open up your WHMCS in a private window.

### Contribute

Contributions are welcome with pull requests (PR).

### Sponsored

This project was originally developed by [Hybula B.V.](https://www.hybula.com/) and modified
by [SideCloud](https://github.com/SideCloudGroup).

### License

```Apache License, Version 2.0 and the Commons Clause Restriction```
