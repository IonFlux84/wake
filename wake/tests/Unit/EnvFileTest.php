<?php

use Wake\Support\EnvFile;

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/wake-env-'.bin2hex(random_bytes(4));
    mkdir($this->root);
    file_put_contents($this->root.'/.env.example', "APP_NAME=Wake\nAPP_KEY=\nAPP_URL=http://localhost:8000\n");
});

afterEach(function () {
    @unlink($this->root.'/.env');
    @unlink($this->root.'/.env.example');
    @rmdir($this->root);
});

it('writes .env with a key and the address of the first request', function () {
    expect(EnvFile::create($this->root, ['HTTP_HOST' => 'Verein.example.org', 'HTTPS' => 'on']))->toBeTrue();

    $env = file_get_contents($this->root.'/.env');

    expect($env)->toMatch('/^APP_KEY=base64:.{44}$/m')->toContain("APP_URL=https://verein.example.org\n")
        ->and(EnvFile::create($this->root, ['HTTP_HOST' => 'other.example']))->toBeFalse();
});

it('keeps the example address for the command line and odd host names', function (array $server) {
    EnvFile::create($this->root, $server);

    expect(file_get_contents($this->root.'/.env'))->toContain('APP_URL=http://localhost:8000');
})->with([
    'command line' => [[]],
    'injection' => [['HTTP_HOST' => "evil.example\nAPP_DEBUG=true"]],
    'path' => [['HTTP_HOST' => 'evil.example/x']],
]);

it('reads the scheme behind a proxy and keeps ports', function () {
    expect(EnvFile::address(['HTTP_HOST' => 'localhost:8200']))->toBe('http://localhost:8200')
        ->and(EnvFile::address(['HTTP_HOST' => 'example.org', 'HTTP_X_FORWARDED_PROTO' => 'https']))->toBe('https://example.org')
        ->and(EnvFile::address(['HTTP_HOST' => '[::1]:8080']))->toBe('http://[::1]:8080');
});
