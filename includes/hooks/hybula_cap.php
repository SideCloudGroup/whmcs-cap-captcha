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
const hybulaCapUrl = '';
const hybulaCapSite = '';
const hybulaCapSecret = '';
const hybulaCapError = <<<HTML
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
Swal.fire({
    icon: 'error',
    title: 'Captcha verification failed',
    text: 'Please complete the captcha challenge to continue.',
    confirmButtonText: 'Back',
    allowOutsideClick: false,
    allowEscapeKey: false
}).then(() => {
    history.back();
});
</script>
HTML;
const hybulaCapLocations = ['login', 'register', 'checkout', 'ticket', 'contact', 'reset'];

if (! defined('WHMCS')) {
    die('This file cannot be accessed directly!');
}

if (! isset($_SESSION['adminid'])) {
    if (! empty($_POST) && (! isset($_SESSION['uid']) && hybulaCapExcludeLogin)) {
        $pageFile = basename($_SERVER['SCRIPT_NAME'], '.php');
        $onLoginPage = $pageFile == 'index' && isset($_POST['username']) && isset($_POST['password']) && in_array('login', hybulaCapLocations);
        $onRegisterPage = $pageFile == 'register' && in_array('register', hybulaCapLocations);
        $onContactPage = $pageFile == 'contact' && in_array('contact', hybulaCapLocations);
        $onTicketPage = $pageFile == 'submitticket' && isset($_POST['subject']) && in_array('ticket', hybulaCapLocations);
        $onCheckoutPage = $pageFile == 'cart' && isset($_GET['a']) && $_GET['a'] == 'checkout' && in_array('checkout', hybulaCapLocations);
        $onResetPage = $pageFile == 'index' && isset($_POST['email']) && in_array('reset', hybulaCapLocations);

        if (hybulaCapEnabled && ($onLoginPage || $onRegisterPage || $onContactPage || $onTicketPage || $onCheckoutPage || $onResetPage)) {
            if (! isset($_POST['cap-token'])) {
                unset($_SESSION['uid']);
                die('Missing captcha response in POST data!');
            }
            $siteURL = hybulaCapUrl;
            $siteKey = hybulaCapSite;
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_FOLLOWLOCATION => 1,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'secret' => hybulaCapSecret,
                    'response' => $_POST['cap-token'],
                ]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_URL => "$siteURL/$siteKey/siteverify",
            ]);
            $result = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
            if ($json = json_decode($result)) {
                if (! $json->success) {
                    unset($_SESSION['uid']);
                    die(hybulaCapError);
                }
            }
        }
    }

    add_hook('ClientAreaFooterOutput', 1, function ($vars) {
        if (! hybulaCapEnabled || (isset($_SESSION['uid']) && hybulaCapExcludeLogin)) {
            return '';
        }
        $pageFile = basename($_SERVER['SCRIPT_NAME'], '.php');
        $isLoginPage = in_array('login', hybulaCapLocations) && $vars['pagetitle'] == $vars['LANG']['login'];
        $isRegisterPage = in_array('register', hybulaCapLocations) && $pageFile == 'register';
        $isContactPage = in_array('contact', hybulaCapLocations) && $pageFile == 'contact';
        $isTicketPage = in_array('ticket', hybulaCapLocations) && $pageFile == 'submitticket';
        $isCheckoutPage = in_array('checkout', hybulaCapLocations) && $pageFile == 'cart' && isset($_GET['a']) && $_GET['a'] == 'checkout';
        $isResetPage = in_array('reset', hybulaCapLocations) && $vars['pagetitle'] == $vars['LANG']['pwreset'];

        if ($isLoginPage || $isRegisterPage || $isContactPage || $isTicketPage || $isCheckoutPage || $isResetPage) {
            $siteURL = hybulaCapUrl;
            $siteKey = hybulaCapSite;
            return <<<HTML
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var form = document.querySelector('input[type=submit],#login,div.text-center > button[type=submit],#openTicketSubmit').parentNode;
                    if (form) {
                        var capDiv = document.createElement('div');
                        capDiv.innerHTML = '<cap-widget id="cap" data-cap-api-endpoint="$siteURL/$siteKey/"></cap-widget><br><br>';
                        var submitBtn = form.querySelector('input[type=submit], button[type=submit]');
                        if (submitBtn) {
                            if (submitBtn.parentNode === form) {
                                form.insertBefore(capDiv, submitBtn);
                            } else {
                                submitBtn.parentNode.insertBefore(capDiv, submitBtn);
                            }
                        } else {
                            form.appendChild(capDiv);
                        }
                        var script = document.createElement('script');
                        script.src = 'https://cdn.jsdelivr.net/npm/@cap.js/widget';
                        document.body.appendChild(script);
                    }
                });
                </script>
                HTML;
        }
        return '';
    });
}