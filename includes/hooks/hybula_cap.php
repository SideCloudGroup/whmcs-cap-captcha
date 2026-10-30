<?php
/**
 * WHMCS Cap Hook
 *
 * LICENSE: Licensed under the Apache License, Version 2.0 (the "License")
 * and the Commons Clause Restriction; you may not use this file except in
 * compliance with the License.
 *
 * @category   whmcs
 * @package    whmcs-cap
 * @author     Hybula Development <development@hybula.com>
 * @author     SideCloud
 * @copyright  2023 Hybula B.V., 2025 SideCloud
 * @license    https://github.com/sidecloud/whmcs-cap-captcha/blob/main/LICENSE.md
 * @link       https://github.com/sidecloud/whmcs-cap-captcha
 */

declare(strict_types=1);

const hybulaCapEnabled = true;
const hybulaCapExcludeLogin = true;
const hybulaCapCheckoutLoggedIn = true;
const hybulaCapFailOpen = false;
const hybulaCapUrl = '';
const hybulaCapSite = '';
const hybulaCapSecret = '';
const hybulaCapWidgetScript = 'https://cdn.jsdelivr.net/npm/@cap.js/widget';
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

function hybula_cap_endpoint(): string
{
    return rtrim(hybulaCapUrl, '/') . '/' . trim(hybulaCapSite, '/') . '/';
}

function hybula_cap_location_active(string $location): bool
{
    if (! in_array($location, hybulaCapLocations, true)) {
        return false;
    }
    if (isset($_SESSION['uid']) && hybulaCapExcludeLogin) {
        return $location === 'checkout' && hybulaCapCheckoutLoggedIn;
    }

    return true;
}

function hybula_cap_match_location(array $checks): ?string
{
    foreach ($checks as $location => $matched) {
        if ($matched && hybula_cap_location_active($location)) {
            return $location;
        }
    }

    return null;
}

function hybula_cap_page_file(): string
{
    return basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '.php');
}

/**
 * Route path under any WHMCS friendly URL mode:
 * index.php?rp=/route, index.php/route or a fully rewritten /route.
 */
function hybula_cap_route(): string
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

function hybula_cap_is_reset_route(): bool
{
    return hybula_cap_page_file() === 'pwreset'
        || (hybula_cap_page_file() === 'index' && strpos(hybula_cap_route(), '/password/reset') !== false);
}

function hybula_cap_is_checkout(): bool
{
    return hybula_cap_page_file() === 'cart' && ($_GET['a'] ?? '') === 'checkout';
}

function hybula_cap_verify(string $token): bool
{
    $curl = curl_init(hybula_cap_endpoint() . 'siteverify');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'secret' => hybulaCapSecret,
            'response' => $token,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
    ]);
    $result = curl_exec($curl);
    $error = curl_error($curl);

    $json = is_string($result) ? json_decode($result, true) : null;
    if (! is_array($json) || ! array_key_exists('success', $json)) {
        if (function_exists('logActivity')) {
            logActivity('Cap captcha: siteverify request failed. ' . ($error !== '' ? $error : 'Invalid response.'));
        }

        return hybulaCapFailOpen;
    }

    return $json['success'] === true;
}

function hybula_cap_fail(): void
{
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

if (hybulaCapEnabled && hybulaCapUrl !== '' && hybulaCapSite !== '' && hybulaCapSecret !== '' && ! isset($_SESSION['adminid'])) {
    if (! empty($_POST)) {
        $pageFile = hybula_cap_page_file();
        $location = hybula_cap_match_location([
            'login' => in_array($pageFile, ['index', 'dologin'], true) && isset($_POST['username'], $_POST['password']),
            'register' => $pageFile === 'register',
            'contact' => $pageFile === 'contact',
            'ticket' => $pageFile === 'submitticket' && isset($_POST['subject']),
            'checkout' => hybula_cap_is_checkout(),
            'reset' => hybula_cap_is_reset_route() && isset($_POST['email']),
        ]);

        if ($location !== null) {
            $token = $_POST['cap-token'] ?? '';
            if (! is_string($token) || $token === '' || ! hybula_cap_verify($token)) {
                hybula_cap_fail();
            }
        }
    }

    add_hook('ClientAreaFooterOutput', 1, function ($vars) {
        $pageFile = hybula_cap_page_file();
        $template = $vars['templatefile'] ?? '';
        $pageTitle = $vars['pagetitle'] ?? null;
        $location = hybula_cap_match_location([
            'login' => $template === 'login' || ($pageTitle !== null && $pageTitle === ($vars['LANG']['login'] ?? null)),
            'register' => $pageFile === 'register',
            'contact' => $pageFile === 'contact',
            'ticket' => $pageFile === 'submitticket',
            'checkout' => hybula_cap_is_checkout(),
            'reset' => hybula_cap_is_reset_route()
                || in_array($template, ['password-reset-container', 'pwreset'], true)
                || ($pageTitle !== null && $pageTitle === ($vars['LANG']['pwreset'] ?? null)),
        ]);

        if ($location === null) {
            return '';
        }

        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
        $selector = json_encode(hybulaCapSelectors[$location], $flags);
        $widgetHtml = json_encode(
            '<cap-widget data-cap-api-endpoint="' . htmlspecialchars(hybula_cap_endpoint(), ENT_QUOTES) . '"></cap-widget>',
            $flags
        );
        $scriptSrc = json_encode(hybulaCapWidgetScript, $flags);

        return <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function () {
    var target = document.querySelector($selector);
    if (!target) {
        return;
    }
    var form = target.tagName === 'FORM' ? target : (target.closest && target.closest('form'));
    if (!form || form.querySelector('cap-widget')) {
        return;
    }
    var capDiv = document.createElement('div');
    capDiv.style.margin = '1rem 0';
    capDiv.innerHTML = $widgetHtml;
    var submitBtn = (target.matches && target.matches('[type=submit]')) ? target : form.querySelector('[type=submit]');
    if (submitBtn) {
        submitBtn.parentNode.insertBefore(capDiv, submitBtn);
    } else {
        form.appendChild(capDiv);
    }
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            capDiv.innerHTML = $widgetHtml;
        }
    });
    var script = document.createElement('script');
    script.src = $scriptSrc;
    document.body.appendChild(script);
});
</script>
HTML;
    });
}
