# Vault PHP SDK

[English](README.md) | [简体中文](README.zh-CN.md)

`latch/vault-php-sdk` is a small, framework-independent PHP client for
Vault-compatible HTTP APIs (`/v1/`). The SDK uses PHP cURL by default, but
accepts an injected transport for framework integrations and deterministic
tests.

The public PHP namespace is `Vault\`, and the Composer package name is
`latch/vault-php-sdk`.

## Highlights

- PHP 8.0+ on the default branch; no framework, Guzzle, or PSR container
  dependency
- Token and AppRole authentication
- KV v1 and KV v2 secret operations
- Token lifecycle, health, and mount APIs
- Custom HTTP transport support
- PSR-16 token storage adapter for custom caches
- TLS certificate and hostname verification enabled by default
- Dedicated SDK exceptions with HTTP status and decoded response access

## Requirements

- PHP 8.0+
- `ext-curl`
- `ext-json`
- A reachable Vault-compatible server

The default branch supports PHP 8.0 and later. Projects that must stay on
PHP 7 can use the `1.x-dev` branch.

## Installation

```bash
composer require latch/vault-php-sdk
```

For PHP 7:

```bash
composer require latch/vault-php-sdk:"1.x-dev"
```

The `1.x-dev` branch supports PHP 7.2+ and uses `psr/simple-cache` 1.x. The
default branch uses `psr/simple-cache` 3.x and requires PHP 8.0+.

For local development:

```bash
composer install
composer test
composer lint
```

## Quick start

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Vault\Client;

$vault = new Client(array(
    'address' => 'https://vault.example.com:8200',
    'token' => getenv('VAULT_TOKEN'),
    'timeout' => 10,
    'connect_timeout' => 3,
    'tls' => array(
        'verify_peer' => true,
        'verify_host' => true,
        // 'ca_file' => '/etc/ssl/certs/vault-ca.pem',
    ),
));

$result = $vault->kv2('secret')->read('application/database');
$database = $result['data'];
```

`address` is the server base URL. The SDK appends `/v1/` and URL-encodes query
parameters. Paths are relative API paths; empty paths and paths containing
`..` are rejected.

## KV v2

The mount is the secrets-engine mount point, not part of the secret path.

```php
$kv = $vault->kv2('secret');

$kv->write('application/database', array(
    'host' => '127.0.0.1',
    'username' => 'app',
    'password' => 'change-me',
));

$current = $kv->read('application/database');
$config = $current['data'];
$version = $current['metadata']['version'];

$old = $kv->read('application/database', 2);
$kv->patch('application/database', array('password' => 'rotated'));
$kv->delete('application/database');

$kv->deleteVersions('application/database', array(2, 3));
$kv->undelete('application/database', array(2));
$kv->destroy('application/database', array(1));

$metadata = $kv->readMetadata('application/database');
$kv->writeMetadata('application/database', array('max_versions' => 10));
$kv->deleteMetadata('application/database');

$keys = $kv->list('application');
```

## KV v1

```php
$kv = $vault->kv1('legacy');

$kv->write('application/config', array('feature_enabled' => true));
$data = $kv->read('application/config');
$kv->delete('application/config');
$keys = $kv->list('application');
```

## Authentication

### Token authentication

Pass a token in the client configuration or update it later:

```php
$vault->setToken($token);
$currentToken = $vault->getToken();
```

### AppRole login

```php
use Vault\Auth\AppRoleAuth;

$auth = new AppRoleAuth($vault);
$authData = $auth->login($roleId, $secretId);

// Subsequent requests use auth.client_token.
$secret = $vault->kv2('secret')->read('application/database');
```

The SDK does not persist tokens automatically. Provide a secure implementation
of `Vault\Auth\TokenStorageInterface` when tokens must be shared across
workers or stored outside the process.

For any PSR-16 cache implementation, use the built-in adapter instead of
writing a cache-specific token storage class:

```php
use Vault\Auth\Psr16TokenStorage;
use Psr\SimpleCache\CacheInterface;

/** @var CacheInterface $cache */
$vault->setTokenStorage(new Psr16TokenStorage($cache, 'vault.token', 3600));
```

The adapter stores only the client token. Secret responses are never cached by
the SDK. Passing `null` or an empty token removes the cache entry.

## Configuration

| Option | Default | Description |
| --- | --- | --- |
| `address` | required | HTTP(S) base URL, for example `https://host:8200` |
| `token` | `null` | Initial client token |
| `namespace` | `null` | Value for `X-Vault-Namespace` |
| `timeout` | `10` | Total request timeout in seconds |
| `connect_timeout` | `3` | Connection timeout in seconds |
| `tls.verify_peer` | `true` | Verify the TLS certificate |
| `tls.verify_host` | `true` | Verify the TLS hostname |
| `tls.ca_file` | `null` | Optional CA bundle path |
| `headers` | `[]` | Additional static HTTP headers |

TLS verification is enabled by default. Disabling it should be limited to
controlled development environments.

## Exceptions

All SDK exceptions extend `Vault\Exception\VaultException`.

- `ApiException`: non-2xx response; exposes `getStatusCode()`,
  `getResponse()`, and `getRawBody()`.
- `TransportException`: cURL, DNS, timeout, TLS, or invalid JSON failure.
- `AuthenticationException`: AppRole response did not contain a client token.

## Custom transport

Implement `Vault\Http\TransportInterface` and pass it as the second
constructor argument. This supports framework clients, proxies,
instrumentation, and unit tests. The transport receives the HTTP method, full
URL, headers, optional JSON body, and timeout/TLS options, and must return an
`Vault\Http\Response`.

## Security guidance

- Use least-privilege policies and never commit root tokens or Secret IDs.
- Prefer AppRole or another workload identity with short-lived tokens.
- Keep TLS verification enabled and configure a trusted CA when required.
- Never log tokens, Secret IDs, secret values, or raw API response bodies.
- The SDK does not retry requests automatically. Retrying writes can create
  duplicate secret versions or other unintended effects.
- `destroy()` permanently removes KV v2 versions; use it deliberately.
- Protect any custom token storage with the same care as the token itself.

## Compatibility and support

The default branch targets PHP 8.0+. PHP 7.2+ projects should install the
`1.x-dev` branch. Neither branch uses framework-specific dependencies. The API
follows Vault's `/v1/` HTTP conventions and is compatible with Vault servers
exposing those endpoints. Server-side policy and engine configuration remain
the responsibility of the application.

## License

MIT. See [LICENSE](LICENSE).
