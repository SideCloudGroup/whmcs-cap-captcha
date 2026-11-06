<?php
// Isolated request harness. No WHMCS installation, database or network needed.
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$case = json_decode(base64_decode($argv[1]), true);
define('WHMCS', true);
if (array_key_exists('admin_area', $case)) {
    define('ADMINAREA', $case['admin_area']);
}
$_SERVER = array_merge([
    'SCRIPT_NAME' => '/index.php',
    'REQUEST_URI' => '/index.php',
], $case['server'] ?? []);
$_SESSION = $case['session'] ?? [];
$_GET = $case['get'] ?? [];
$_POST = $case['post'] ?? [];
$hooks = [];
$verifications = 0;
$verifyUrl = '';
$verifyBody = '';

function add_hook($name, $priority, $callback)
{
    $GLOBALS['hooks'][$name] = $callback;
}

// Run PHP with -n so these fakes cannot conflict with the curl extension.
foreach (['CURLOPT_POST', 'CURLOPT_HTTPHEADER', 'CURLOPT_POSTFIELDS', 'CURLOPT_RETURNTRANSFER', 'CURLOPT_CONNECTTIMEOUT', 'CURLOPT_TIMEOUT'] as $i => $option) {
    define($option, $i + 1);
}
function curl_init($url)
{
    $GLOBALS['verifyUrl'] = $url;
    return $url;
}
function curl_setopt_array($handle, $options)
{
    if (isset($options[CURLOPT_POSTFIELDS])) {
        $GLOBALS['verifyBody'] = $options[CURLOPT_POSTFIELDS];
    }
    return true;
}
function curl_exec($handle)
{
    $GLOBALS['verifications']++;
    return json_encode(['success' => $GLOBALS['case']['verify_success'] ?? false]);
}
function curl_error($handle)
{
    return '';
}
function curl_close($handle)
{
}

require $argv[2];
$footer = isset($hooks['ClientAreaFooterOutput'])
    ? $hooks['ClientAreaFooterOutput']($case['vars'] ?? []) : '';
echo json_encode([
    'hooks' => count($hooks),
    'verifications' => $verifications,
    'widget' => strpos($footer, 'cap-widget') !== false
        || strpos($footer, 'cf-turnstile') !== false
        || strpos($footer, 'h-captcha') !== false,
    'widget_cap' => strpos($footer, 'cap-widget') !== false,
    'widget_turnstile' => strpos($footer, 'cf-turnstile') !== false,
    'widget_hcaptcha' => strpos($footer, 'h-captcha') !== false,
    'verify_url' => $verifyUrl,
    'verify_body' => $verifyBody,
]);
