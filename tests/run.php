<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Configure a temporary copy only; never use real Cap keys or make HTTP calls.
$source = file_get_contents($argv[1] ?? __DIR__ . '/../includes/hooks/hybula_cap.php');
foreach (['hybulaCapUrl' => 'https://captcha.example.test', 'hybulaCapSite' => 'test-site', 'hybulaCapSecret' => 'test-secret'] as $name => $value) {
    $source = str_replace("const $name = '';", "const $name = '$value';", $source);
}
$hook = tempnam(sys_get_temp_dir(), 'cap-hook-');
file_put_contents($hook, $source);
register_shutdown_function(function () use ($hook) { unlink($hook); });

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
$cases['client login valid token'] = [array_merge($login, ['post' => $login['post'] + ['cap-token' => 'valid'], 'verify_success' => true]), ['hooks' => 1, 'verifications' => 1, 'widget' => true]];
$cases['client login GET still has widget'] = [['vars' => ['templatefile' => 'login']], ['hooks' => 1, 'verifications' => 0, 'widget' => true]];
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

$failures = 0;
foreach ($cases as $name => $test) {
    list($case, $expected) = $test;
    $command = escapeshellarg(PHP_BINARY) . ' -n ' . escapeshellarg(__DIR__ . '/request.php') . ' ' . escapeshellarg(base64_encode(json_encode($case))) . ' ' . escapeshellarg($hook);
    $lines = [];
    exec($command . ' 2>&1', $lines, $status);
    $output = implode("\n", $lines);
    $actual = json_decode($output, true);
    $ok = $status === 0 && ($expected === 'blocked'
        ? strpos($output, '<!DOCTYPE html>') === 0 && strpos($output, 'Captcha verification failed') !== false
        : $actual === $expected);
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . "\n";
    if (! $ok) {
        $failures++;
        fwrite(STDERR, $output . "\n");
    }
}
echo count($cases) . " cases, $failures failures\n";
exit($failures ? 1 : 0);
