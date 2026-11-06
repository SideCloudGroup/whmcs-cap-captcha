<?php
/**
 * WHMCS Captcha Hook (Cap / Turnstile / hCaptcha)
 *
 * LICENSE: Licensed under the Apache License, Version 2.0 (the "License")
 * and the Commons Clause Restriction; you may not use this file except in
 * compliance with the License.
 *
 * @category   whmcs
 * @package    whmcs-captcha
 * @author     Hybula Development <development@hybula.com>
 * @author     SideCloud
 * @copyright  2023 Hybula B.V., 2025-2026 SideCloud
 * @license    https://github.com/SideCloudGroup/whmcs-cap-captcha/blob/main/LICENSE.md
 * @link       https://github.com/SideCloudGroup/whmcs-cap-captcha
 */

declare(strict_types=1);

// Active driver: cap | turnstile | hcaptcha
const hybulaCaptchaDriver = 'cap';

const hybulaCapEnabled = true;
const hybulaCapExcludeLogin = true;
const hybulaCapCheckoutLoggedIn = true;
const hybulaCapFailOpen = false;
const hybulaCapCredits = false;
const hybulaCapTheme = 'auto';

// Cap.js — used when hybulaCaptchaDriver = 'cap'
const hybulaCapUrl = '';
const hybulaCapSite = '';
const hybulaCapSecret = '';
const hybulaCapWidgetScript = 'https://cdn.jsdelivr.net/npm/@cap.js/widget';

// Cloudflare Turnstile — used when hybulaCaptchaDriver = 'turnstile'
const hybulaTurnstileSite = '';
const hybulaTurnstileSecret = '';
const hybulaTurnstileScript = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
const hybulaTurnstileVerifyUrl = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

// hCaptcha — used when hybulaCaptchaDriver = 'hcaptcha'
const hybulaHcaptchaSite = '';
const hybulaHcaptchaSecret = '';
const hybulaHcaptchaScript = 'https://js.hcaptcha.com/1/api.js';
const hybulaHcaptchaVerifyUrl = 'https://api.hcaptcha.com/siteverify';

const hybulaCapErrorTitle = 'Captcha verification failed';
const hybulaCapErrorText = 'Please complete the captcha challenge to continue.';
const hybulaCapErrorButton = 'Back';
const hybulaCapLocations = ['login', 'register', 'checkout', 'ticket', 'contact', 'reset'];
const hybulaCapSelectors = [
    'login' => '#login,form input[name=password]',
    'register' => '#frmCheckout,form input[name=password2]',
    'contact' => 'form textarea[name=message]',
    'ticket' => '#openTicketSubmit,form input[name=subject]',
    'checkout' => '#btnCompleteOrder,#frmCheckout',
    'reset' => 'form input[name=email]',
];

if (! defined('WHMCS')) {
    die('This file cannot be accessed directly!');
}

interface HybulaCaptchaDriver
{
    public function tokenField(): string;

    public function isConfigured(): bool;

    public function widgetHtml(): string;

    public function widgetSelector(): string;

    public function scriptSrc(): string;

    public function scriptAsync(): bool;

    public function verify(string $token): bool;
}

final class HybulaCaptchaCapDriver implements HybulaCaptchaDriver
{
    public function tokenField(): string
    {
        return 'cap-token';
    }

    public function isConfigured(): bool
    {
        return hybulaCapUrl !== '' && hybulaCapSite !== '' && hybulaCapSecret !== '';
    }

    public function widgetHtml(): string
    {
        return '<cap-widget data-cap-api-endpoint="' . hybula_captcha_escape(hybula_captcha_cap_endpoint()) . '"></cap-widget>';
    }

    public function widgetSelector(): string
    {
        return 'cap-widget';
    }

    public function scriptSrc(): string
    {
        return hybulaCapWidgetScript;
    }

    public function scriptAsync(): bool
    {
        return false;
    }

    public function verify(string $token): bool
    {
        $result = hybula_captcha_http_post(
            hybula_captcha_cap_endpoint() . 'siteverify',
            json_encode([
                'secret' => hybulaCapSecret,
                'response' => $token,
            ]),
            ['Content-Type: application/json']
        );

        return hybula_captcha_parse_success($result['body'], $result['error'], 'Cap captcha');
    }
}

final class HybulaCaptchaTurnstileDriver implements HybulaCaptchaDriver
{
    public function tokenField(): string
    {
        return 'cf-turnstile-response';
    }

    public function isConfigured(): bool
    {
        return hybulaTurnstileSite !== '' && hybulaTurnstileSecret !== '';
    }

    public function widgetHtml(): string
    {
        return '<div class="cf-turnstile" data-sitekey="' . hybula_captcha_escape(hybulaTurnstileSite)
            . '" data-theme="' . hybula_captcha_escape(hybulaCapTheme) . '"></div>';
    }

    public function widgetSelector(): string
    {
        return '.cf-turnstile';
    }

    public function scriptSrc(): string
    {
        return hybulaTurnstileScript;
    }

    public function scriptAsync(): bool
    {
        return true;
    }

    public function verify(string $token): bool
    {
        $result = hybula_captcha_http_post(
            hybulaTurnstileVerifyUrl,
            http_build_query([
                'secret' => hybulaTurnstileSecret,
                'response' => $token,
                'remoteip' => hybula_captcha_remote_ip(),
            ]),
            ['Content-Type: application/x-www-form-urlencoded']
        );

        return hybula_captcha_parse_success($result['body'], $result['error'], 'Turnstile captcha');
    }
}

final class HybulaCaptchaHcaptchaDriver implements HybulaCaptchaDriver
{
    public function tokenField(): string
    {
        return 'h-captcha-response';
    }

    public function isConfigured(): bool
    {
        return hybulaHcaptchaSite !== '' && hybulaHcaptchaSecret !== '';
    }

    public function widgetHtml(): string
    {
        $theme = hybulaCapTheme === 'dark' ? 'dark' : 'light';

        return '<div class="h-captcha" data-sitekey="' . hybula_captcha_escape(hybulaHcaptchaSite)
            . '" data-theme="' . hybula_captcha_escape($theme) . '"></div>';
    }

    public function widgetSelector(): string
    {
        return '.h-captcha';
    }

    public function scriptSrc(): string
    {
        return hybulaHcaptchaScript;
    }

    public function scriptAsync(): bool
    {
        return true;
    }

    public function verify(string $token): bool
    {
        $result = hybula_captcha_http_post(
            hybulaHcaptchaVerifyUrl,
            http_build_query([
                'secret' => hybulaHcaptchaSecret,
                'response' => $token,
                'remoteip' => hybula_captcha_remote_ip(),
                'sitekey' => hybulaHcaptchaSite,
            ]),
            ['Content-Type: application/x-www-form-urlencoded']
        );

        return hybula_captcha_parse_success($result['body'], $result['error'], 'hCaptcha');
    }
}

function hybula_captcha_driver()
{
    static $driver = null;
    if ($driver !== null) {
        return $driver;
    }

    if (hybulaCaptchaDriver === 'turnstile') {
        $driver = new HybulaCaptchaTurnstileDriver();
    } elseif (hybulaCaptchaDriver === 'hcaptcha') {
        $driver = new HybulaCaptchaHcaptchaDriver();
    } elseif (hybulaCaptchaDriver === 'cap') {
        $driver = new HybulaCaptchaCapDriver();
    } else {
        $driver = false;
    }

    return $driver;
}

function hybula_captcha_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES);
}

function hybula_captcha_cap_endpoint(): string
{
    return rtrim(hybulaCapUrl, '/') . '/' . trim(hybulaCapSite, '/') . '/';
}

function hybula_captcha_remote_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    return is_string($ip) ? $ip : '';
}

function hybula_captcha_http_post(string $url, string $body, array $headers): array
{
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
    ]);
    $result = curl_exec($curl);
    $error = curl_error($curl);
    if (function_exists('curl_close')) {
        curl_close($curl);
    }

    return [
        'body' => is_string($result) ? $result : '',
        'error' => is_string($error) ? $error : '',
    ];
}

function hybula_captcha_parse_success(string $body, string $error, string $label): bool
{
    $json = $body !== '' ? json_decode($body, true) : null;
    if (! is_array($json) || ! array_key_exists('success', $json)) {
        if (function_exists('logActivity')) {
            logActivity($label . ': siteverify request failed. ' . ($error !== '' ? $error : 'Invalid response.'));
        }

        return hybulaCapFailOpen;
    }

    return $json['success'] === true;
}

function hybula_captcha_location_active(string $location): bool
{
    if (! in_array($location, hybulaCapLocations, true)) {
        return false;
    }
    if (isset($_SESSION['uid']) && hybulaCapExcludeLogin) {
        return $location === 'checkout' && hybulaCapCheckoutLoggedIn;
    }

    return true;
}

function hybula_captcha_match_location(array $checks)
{
    foreach ($checks as $location => $matched) {
        if ($matched && hybula_captcha_location_active($location)) {
            return $location;
        }
    }

    return null;
}

function hybula_captcha_page_file(): string
{
    return basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '.php');
}

/**
 * Route path under any WHMCS friendly URL mode:
 * index.php?rp=/route, index.php/route or a fully rewritten /route.
 */
function hybula_captcha_route(): string
{
    $route = $_GET['rp'] ?? '';
    if (is_string($route) && $route !== '') {
        return $route;
    }
    $pathInfo = $_SERVER['PATH_INFO'] ?? '';
    if (is_string($pathInfo) && $pathInfo !== '') {
        return $pathInfo;
    }

    return (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
}

function hybula_captcha_is_reset_route(): bool
{
    return hybula_captcha_page_file() === 'pwreset'
        || (hybula_captcha_page_file() === 'index' && strpos(hybula_captcha_route(), '/password/reset') !== false);
}

function hybula_captcha_is_invite_page(array $vars = []): bool
{
    $template = isset($vars['templatefile']) ? (string) $vars['templatefile'] : '';
    if ($template !== '' && stripos($template, 'invite') !== false) {
        return true;
    }

    return strpos(hybula_captcha_route(), '/invite/') !== false;
}

function hybula_captcha_is_checkout(): bool
{
    return hybula_captcha_page_file() === 'cart' && ($_GET['a'] ?? '') === 'checkout';
}

function hybula_captcha_credits_html(): string
{
    if (! hybulaCapCredits) {
        return '<!-- Captcha integration by Hybula / SideCloud -->';
    }

    return '<a href="https://github.com/SideCloudGroup/whmcs-cap-captcha" target="_blank"><small class="text-muted text-uppercase">Captcha integration by Hybula / SideCloud</small></a>';
}

function hybula_captcha_fail(): void
{
    if (isset($_SESSION['uid'])) {
        unset($_SESSION['uid']);
    }

    $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
    $title = json_encode(hybulaCapErrorTitle, $flags);
    $text = json_encode(hybulaCapErrorText, $flags);
    $button = json_encode(hybulaCapErrorButton, $flags);

    die(<<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function () {
    var back = function () { history.back(); };
    if (window.Swal) {
        Swal.fire({
            icon: 'error',
            title: $title,
            text: $text,
            confirmButtonText: $button,
            allowOutsideClick: false,
            allowEscapeKey: false
        }).then(back);
    } else {
        alert($title + '\\n' + $text);
        back();
    }
})();
</script>
</body>
</html>
HTML
    );
}

// Use WHMCS's server-side admin context, not an authenticated admin session:
// the login form has no adminid yet.
// Do not infer this from a URL, submitted field or directory name: those can be
// supplied by a client and custom admin directory names are supported by WHMCS.
$hybulaCapAdminArea = defined('ADMINAREA') && ADMINAREA;
$hybulaCaptcha = hybula_captcha_driver();

if (hybulaCapEnabled && $hybulaCaptcha instanceof HybulaCaptchaDriver && $hybulaCaptcha->isConfigured() && ! $hybulaCapAdminArea && ! isset($_SESSION['adminid'])) {
    if (! empty($_POST)) {
        $pageFile = hybula_captcha_page_file();
        $location = hybula_captcha_match_location([
            'login' => in_array($pageFile, ['index', 'dologin'], true) && isset($_POST['username'], $_POST['password']),
            'register' => $pageFile === 'register',
            'contact' => $pageFile === 'contact',
            'ticket' => $pageFile === 'submitticket' && isset($_POST['subject']),
            'checkout' => hybula_captcha_is_checkout(),
            'reset' => hybula_captcha_is_reset_route() && isset($_POST['email']),
        ]);

        if ($location !== null) {
            $token = $_POST[$hybulaCaptcha->tokenField()] ?? '';
            if (! is_string($token) || $token === '' || ! $hybulaCaptcha->verify($token)) {
                hybula_captcha_fail();
            }
        }
    }

    add_hook('ClientAreaFooterOutput', 1, function ($vars) use ($hybulaCaptcha) {
        $pageFile = hybula_captcha_page_file();
        $template = $vars['templatefile'] ?? '';
        $pageTitle = $vars['pagetitle'] ?? null;
        $invitePage = hybula_captcha_is_invite_page(is_array($vars) ? $vars : []);
        $location = hybula_captcha_match_location([
            'login' => $invitePage
                || $template === 'login'
                || ($pageTitle !== null && $pageTitle === ($vars['LANG']['login'] ?? null)),
            'register' => $invitePage || $pageFile === 'register',
            'contact' => $pageFile === 'contact',
            'ticket' => $pageFile === 'submitticket',
            'checkout' => hybula_captcha_is_checkout(),
            'reset' => hybula_captcha_is_reset_route()
                || in_array($template, ['password-reset-container', 'pwreset'], true)
                || ($pageTitle !== null && $pageTitle === ($vars['LANG']['pwreset'] ?? null)),
        ]);

        if ($location === null) {
            return '';
        }

        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
        $selector = json_encode($invitePage ? 'form' : hybulaCapSelectors[$location], $flags);
        $widgetHtml = json_encode($hybulaCaptcha->widgetHtml() . hybula_captcha_credits_html(), $flags);
        $widgetSelector = json_encode($hybulaCaptcha->widgetSelector(), $flags);
        $scriptSrc = json_encode($hybulaCaptcha->scriptSrc(), $flags);
        $scriptAsync = $hybulaCaptcha->scriptAsync() ? 'true' : 'false';
        $inviteEmbed = $invitePage ? 'true' : 'false';

        return <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function () {
    var invite = $inviteEmbed;
    var selector = $selector;
    var widgetHtml = $widgetHtml;
    var widgetSelector = $widgetSelector;
    var targets = [];
    if (invite) {
        document.querySelectorAll('form').forEach(function (form) {
            if (form.querySelector('input[name=password],input[name=password2],input[name=username],input[name=email]')) {
                targets.push(form);
            }
        });
    } else {
        var target = document.querySelector(selector);
        if (target) {
            targets.push(target);
        }
    }
    targets.forEach(function (target) {
        var form = target.tagName === 'FORM' ? target : (target.closest && target.closest('form'));
        if (!form || form.querySelector(widgetSelector)) {
            return;
        }
        var box = document.createElement('div');
        box.style.margin = '1rem 0';
        box.innerHTML = widgetHtml;
        var submitBtn = (target.matches && target.matches('[type=submit]')) ? target : form.querySelector('[type=submit]');
        if (submitBtn) {
            submitBtn.parentNode.insertBefore(box, submitBtn);
        } else {
            form.appendChild(box);
        }
        window.addEventListener('pageshow', function (event) {
            if (!event.persisted) {
                return;
            }
            box.innerHTML = widgetHtml;
            var ts = box.querySelector('.cf-turnstile');
            if (ts && window.turnstile && window.turnstile.render) {
                window.turnstile.render(ts);
            }
            var hc = box.querySelector('.h-captcha');
            if (hc && window.hcaptcha && window.hcaptcha.render) {
                window.hcaptcha.render(hc);
            }
        });
    });
    if (!targets.length) {
        return;
    }
    var script = document.createElement('script');
    script.src = $scriptSrc;
    if ($scriptAsync) {
        script.async = true;
        script.defer = true;
    }
    document.body.appendChild(script);
});
</script>
HTML;
    });
}
