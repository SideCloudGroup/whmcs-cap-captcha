<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function hybula_test_write_hook(array $consts)
{
    $source = file_get_contents($GLOBALS['argv'][1] ?? __DIR__ . '/../includes/hooks/hybula_cap.php');
    foreach ($consts as $name => $value) {
        $source = preg_replace(
            '/const ' . preg_quote($name, '/') . ' = \'(?:\\\\\'|[^\'])*\';/',
            'const ' . $name . ' = \'' . $value . '\';',
            $source,
            1
        );
    }
    $hook = tempnam(sys_get_temp_dir(), 'cap-hook-');
    file_put_contents($hook, $source);
    register_shutdown_function(function () use ($hook) {
        if (is_file($hook)) {
            unlink($hook);
        }
    });

    return $hook;
}

function hybula_test_expectations_match($expected, $actual)
{
    if (! is_array($actual)) {
        return false;
    }
    foreach ($expected as $key => $value) {
        if (! array_key_exists($key, $actual) || $actual[$key] !== $value) {
            return false;
        }
    }

    return true;
}

// Configure a temporary copy only; never use real keys or make HTTP calls.
$driverHooks = [
    'cap' => hybula_test_write_hook([
        'hybulaCaptchaDriver' => 'cap',
        'hybulaCapUrl' => 'https://captcha.example.test',
        'hybulaCapSite' => 'test-site',
        'hybulaCapSecret' => 'test-secret',
        'hybulaTurnstileSite' => 'test-turnstile-site',
        'hybulaTurnstileSecret' => 'test-turnstile-secret',
        'hybulaHcaptchaSite' => 'test-hcaptcha-site',
        'hybulaHcaptchaSecret' => 'test-hcaptcha-secret',
    ]),
    'turnstile' => hybula_test_write_hook([
        'hybulaCaptchaDriver' => 'turnstile',
        'hybulaCapUrl' => 'https://captcha.example.test',
        'hybulaCapSite' => 'test-site',
        'hybulaCapSecret' => 'test-secret',
        'hybulaTurnstileSite' => 'test-turnstile-site',
        'hybulaTurnstileSecret' => 'test-turnstile-secret',
        'hybulaHcaptchaSite' => 'test-hcaptcha-site',
        'hybulaHcaptchaSecret' => 'test-hcaptcha-secret',
    ]),
    'hcaptcha' => hybula_test_write_hook([
        'hybulaCaptchaDriver' => 'hcaptcha',
        'hybulaCapUrl' => 'https://captcha.example.test',
        'hybulaCapSite' => 'test-site',
        'hybulaCapSecret' => 'test-secret',
        'hybulaTurnstileSite' => 'test-turnstile-site',
        'hybulaTurnstileSecret' => 'test-turnstile-secret',
        'hybulaHcaptchaSite' => 'test-hcaptcha-site',
        'hybulaHcaptchaSecret' => 'test-hcaptcha-secret',
    ]),
];

$drivers = [
    'cap' => [
        'token' => 'cap-token',
        'wrong_token' => 'cf-turnstile-response',
        'widget_key' => 'widget_cap',
        'verify_url' => 'https://captcha.example.test/test-site/siteverify',
        'verify_body' => '{"secret":"test-secret","response":"valid"}',
    ],
    'turnstile' => [
        'token' => 'cf-turnstile-response',
        'wrong_token' => 'h-captcha-response',
        'widget_key' => 'widget_turnstile',
        'verify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        'verify_body' => 'secret=test-turnstile-secret&response=valid&remoteip=',
    ],
    'hcaptcha' => [
        'token' => 'h-captcha-response',
        'wrong_token' => 'cap-token',
        'widget_key' => 'widget_hcaptcha',
        'verify_url' => 'https://api.hcaptcha.com/siteverify',
        'verify_body' => 'secret=test-hcaptcha-secret&response=valid&remoteip=&sitekey=test-hcaptcha-site',
    ],
];

$login = ['post' => ['username' => 'test@example.test', 'password' => 'not-a-real-password'], 'vars' => ['templatefile' => 'login']];
$cases = [];
foreach (['/admin/index.php', '/private-office/index.php', '/billing/secret-admin/dologin.php'] as $path) {
    $cases['admin before login: ' . $path] = [array_merge($login, ['admin_area' => true, 'server' => ['SCRIPT_NAME' => $path, 'REQUEST_URI' => $path]]), ['hooks' => 0, 'verifications' => 0, 'widget' => false]];
    $cases['admin invalid token: ' . $path] = [array_merge($login, ['admin_area' => true, 'server' => ['SCRIPT_NAME' => $path], 'post' => $login['post'] + ['cap-token' => 'invalid']]), ['hooks' => 0, 'verifications' => 0, 'widget' => false]];
}
$cases['admin password reset'] = [['admin_area' => true, 'server' => ['SCRIPT_NAME' => '/private/index.php'], 'get' => ['rp' => '/password/reset'], 'post' => ['email' => 'admin@example.test']], ['hooks' => 0, 'verifications' => 0, 'widget' => false]];
$cases['admin GET has no client widget'] = [['admin_area' => true, 'vars' => ['templatefile' => 'login']], ['hooks' => 0, 'verifications' => 0, 'widget' => false]];
$cases['active admin session unchanged'] = [array_merge($login, ['session' => ['adminid' => 1]]), ['hooks' => 0, 'verifications' => 0, 'widget' => false]];
$cases['client login missing token'] = [$login, 'blocked'];
$cases['false admin constant stays protected'] = [$login + ['admin_area' => false], 'blocked'];
$cases['client login invalid token'] = [array_merge($login, ['post' => $login['post'] + ['cap-token' => 'invalid']]), 'blocked'];
$cases['client array token rejected'] = [array_merge($login, ['post' => $login['post'] + ['cap-token' => ['invalid']]]), 'blocked'];
$cases['client login valid token'] = [array_merge($login, ['post' => $login['post'] + ['cap-token' => 'valid'], 'verify_success' => true]), ['hooks' => 1, 'verifications' => 1, 'widget' => true, 'widget_cap' => true, 'verify_url' => 'https://captcha.example.test/test-site/siteverify', 'verify_body' => '{"secret":"test-secret","response":"valid"}']];
$cases['client login GET still has widget'] = [['vars' => ['templatefile' => 'login']], ['hooks' => 1, 'verifications' => 0, 'widget' => true, 'widget_cap' => true]];
$cases['legacy dologin protected'] = [$login + ['server' => ['SCRIPT_NAME' => '/dologin.php']], 'blocked'];
$cases['admin-like URL is not authority'] = [$login + ['server' => ['SCRIPT_NAME' => '/index.php', 'REQUEST_URI' => '/admin/index.php', 'PATH_INFO' => '/admin/index.php']], 'blocked'];
$cases['admin-like script name is not authority'] = [$login + ['server' => ['SCRIPT_NAME' => '/billing/admin/index.php']], 'blocked'];
$cases['submitted admin flags cannot bypass'] = [array_merge($login, ['post' => $login['post'] + ['adminid' => 1, 'ADMINAREA' => true], 'get' => ['adminid' => 1, 'ADMINAREA' => true, 'rp' => '/admin/index.php']]), 'blocked'];
foreach (['register', 'contact', 'submitticket', 'cart', 'pwreset'] as $page) {
    $post = ['email' => 'test@example.test', 'subject' => 'test', 'message' => 'test'];
    $cases['client ' . $page . ' protected'] = [['server' => ['SCRIPT_NAME' => '/' . $page . '.php'], 'get' => ['a' => 'checkout'], 'post' => $post], 'blocked'];
}
$cases['logged-in checkout remains protected'] = [['session' => ['uid' => 42], 'server' => ['SCRIPT_NAME' => '/cart.php'], 'get' => ['a' => 'checkout'], 'post' => ['submit' => '1']], 'blocked'];
foreach (['get' => ['rp' => '/password/reset'], 'path' => ['PATH_INFO' => '/password/reset'], 'rewrite' => ['REQUEST_URI' => '/billing/password/reset']] as $mode => $route) {
    $case = ['post' => ['email' => 'test@example.test']];
    $case[$mode === 'get' ? 'get' : 'server'] = $route;
    $cases['client reset ' . $mode . ' protected'] = [$case, 'blocked'];
}
$cases['invite GET renders widget'] = [
    ['server' => ['REQUEST_URI' => '/invite/abc123'], 'vars' => ['templatefile' => 'user-invite']],
    ['hooks' => 1, 'verifications' => 0, 'widget' => true, 'widget_cap' => true],
];
$cases['invite login still requires token'] = [
    array_merge($login, ['server' => ['REQUEST_URI' => '/invite/abc123']]),
    'blocked',
];
$cases['invite referer cannot bypass'] = [
    array_merge($login, ['server' => ['HTTP_REFERER' => 'https://example.test/invite/abc123']]),
    'blocked',
];

foreach (['turnstile', 'hcaptcha'] as $driverName) {
    $meta = $drivers[$driverName];
    $cases[$driverName . ' admin excluded'] = [
        array_merge($login, ['admin_area' => true, 'driver' => $driverName]),
        ['hooks' => 0, 'verifications' => 0, 'widget' => false],
    ];
    $cases[$driverName . ' login missing token'] = [
        $login + ['driver' => $driverName],
        'blocked',
    ];
    $cases[$driverName . ' login invalid token'] = [
        array_merge($login, ['driver' => $driverName, 'post' => $login['post'] + [$meta['token'] => 'invalid']]),
        'blocked',
    ];
    $cases[$driverName . ' wrong field rejected'] = [
        array_merge($login, ['driver' => $driverName, 'post' => $login['post'] + [$meta['wrong_token'] => 'valid'], 'verify_success' => true]),
        'blocked',
    ];
    $cases[$driverName . ' login valid token'] = [
        array_merge($login, ['driver' => $driverName, 'post' => $login['post'] + [$meta['token'] => 'valid'], 'verify_success' => true]),
        ['hooks' => 1, 'verifications' => 1, 'widget' => true, $meta['widget_key'] => true, 'verify_url' => $meta['verify_url'], 'verify_body' => $meta['verify_body']],
    ];
    $cases[$driverName . ' login GET has widget'] = [
        ['driver' => $driverName, 'vars' => ['templatefile' => 'login']],
        ['hooks' => 1, 'verifications' => 0, 'widget' => true, $meta['widget_key'] => true],
    ];
    $cases[$driverName . ' reset protected'] = [
        ['driver' => $driverName, 'post' => ['email' => 'test@example.test'], 'get' => ['rp' => '/password/reset']],
        'blocked',
    ];
    $cases[$driverName . ' logged-in checkout protected'] = [
        ['driver' => $driverName, 'session' => ['uid' => 42], 'server' => ['SCRIPT_NAME' => '/cart.php'], 'get' => ['a' => 'checkout'], 'post' => ['submit' => '1']],
        'blocked',
    ];
    $cases[$driverName . ' invite GET renders widget'] = [
        ['driver' => $driverName, 'server' => ['REQUEST_URI' => '/invite/abc123'], 'vars' => ['templatefile' => 'user-invite']],
        ['hooks' => 1, 'verifications' => 0, 'widget' => true, $meta['widget_key'] => true],
    ];
}

$unknownDriverHook = hybula_test_write_hook([
    'hybulaCaptchaDriver' => 'unknown',
    'hybulaCapUrl' => 'https://captcha.example.test',
    'hybulaCapSite' => 'test-site',
    'hybulaCapSecret' => 'test-secret',
]);
$cases['unknown driver stays inactive'] = [
    $login + ['hook' => $unknownDriverHook],
    ['hooks' => 0, 'verifications' => 0, 'widget' => false],
];

$failures = 0;
foreach ($cases as $name => $test) {
    list($case, $expected) = $test;
    $driverName = $case['driver'] ?? 'cap';
    $hook = $case['hook'] ?? $driverHooks[$driverName];
    unset($case['driver'], $case['hook']);
    $command = escapeshellarg(PHP_BINARY) . ' -n ' . escapeshellarg(__DIR__ . '/request.php') . ' ' . escapeshellarg(base64_encode(json_encode($case))) . ' ' . escapeshellarg($hook);
    $lines = [];
    exec($command . ' 2>&1', $lines, $status);
    $output = implode("\n", $lines);
    $actual = json_decode($output, true);
    $ok = $status === 0 && ($expected === 'blocked'
        ? strpos($output, '<!DOCTYPE html>') === 0 && strpos($output, 'Captcha verification failed') !== false
        : hybula_test_expectations_match($expected, $actual));
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . "\n";
    if (! $ok) {
        $failures++;
        fwrite(STDERR, $output . "\n");
    }
}
echo count($cases) . " cases, $failures failures\n";
exit($failures ? 1 : 0);
