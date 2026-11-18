# Vault PHP SDK

[English](README.md) | [简体中文](README.zh-CN.md)

`dylanlim/vault-php-sdk` 是一个通用、无框架依赖的 PHP Vault 兼容 API
客户端，支持 `/v1/` 接口。默认使用 PHP cURL 发起请求，同时支持注入自定义
传输层，便于接入现有框架、代理和测试替身。

PHP 公共命名空间为 `Vault\`，Composer 包名为 `dylanlim/vault-php-sdk`。

## 特性

- 支持 PHP 7.2+，不依赖 Laravel、Symfony、ThinkPHP、Guzzle 或容器
- Token 和 AppRole 认证
- KV v1、KV v2 读写、版本管理和 metadata 操作
- Token 生命周期、健康检查和挂载点接口
- 可注入自定义 HTTP 传输层
- 提供 PSR-16 Token 存储适配器，可接入自定义缓存
- 默认开启 TLS 证书和主机名校验
- 提供带 HTTP 状态码和响应数据的异常类型

## 环境要求

- PHP 7.2 或更高版本
- `ext-curl`
- `ext-json`
- 可访问的 Vault 兼容服务

## 安装

```bash
composer require dylanlim/vault-php-sdk
```

本地开发：

```bash
composer install
composer test
composer lint
```

## 快速开始

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

`address` 是服务端基础 URL，SDK 会自动拼接 `/v1/`，并对查询参数进行
URL 编码。API 路径必须是相对路径，空路径和包含 `..` 的路径会被拒绝。

## KV v2

挂载点是 secrets engine 的 mount point，不是 secret 路径的一部分。

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

## 认证

### Token 认证

可以在客户端配置中传入 Token，也可以在运行时更新：

```php
$vault->setToken($token);
$currentToken = $vault->getToken();
```

### AppRole 登录

```php
use Vault\Auth\AppRoleAuth;

$auth = new AppRoleAuth($vault);
$authData = $auth->login($roleId, $secretId);

// 后续请求会使用 auth.client_token。
$secret = $vault->kv2('secret')->read('application/database');
```

SDK 不会自动持久化 Token。如果需要跨进程共享或外部存储，请实现
`Vault\Auth\TokenStorageInterface` 并通过 `setTokenStorage()` 注入安全的
实现。

如果已有 PSR-16 缓存实现，可以直接使用 SDK 提供的适配器：

```php
use Vault\Auth\Psr16TokenStorage;
use Psr\SimpleCache\CacheInterface;

/** @var CacheInterface $cache */
$vault->setTokenStorage(new Psr16TokenStorage($cache, 'vault.token', 3600));
```

该适配器只保存客户端 Token，SDK 不会缓存密文响应。传入 `null` 或空字符串
Token 会删除对应的缓存项。

## 配置项

| 配置项 | 默认值 | 说明 |
| --- | --- | --- |
| `address` | 必填 | HTTP(S) 基础 URL，例如 `https://host:8200` |
| `token` | `null` | 初始客户端 Token |
| `namespace` | `null` | `X-Vault-Namespace` 请求头 |
| `timeout` | `10` | 请求总超时时间（秒） |
| `connect_timeout` | `3` | 建立连接超时时间（秒） |
| `tls.verify_peer` | `true` | 校验 TLS 证书 |
| `tls.verify_host` | `true` | 校验 TLS 主机名 |
| `tls.ca_file` | `null` | 可选 CA 证书文件路径 |
| `headers` | `[]` | 额外的静态 HTTP 请求头 |

默认开启 TLS 校验。仅应在受控的开发环境中关闭校验。

## 异常

所有 SDK 异常都继承自 `Vault\Exception\VaultException`：

- `ApiException`：服务端返回非 2xx 状态，可获取状态码、解码后的响应和原始响应体
- `TransportException`：cURL、DNS、超时、TLS 或响应 JSON 解析失败
- `AuthenticationException`：AppRole 响应中没有客户端 Token

## 自定义传输层

实现 `Vault\Http\TransportInterface`，并作为 `new Client($config,
$transport)` 的第二个参数传入。传输层会收到 HTTP 方法、完整 URL、请求头、
可选 JSON 请求体以及超时/TLS 选项，并必须返回 `Vault\Http\Response`。

## 安全建议

- 使用最小权限策略，不要把 root Token 或 Secret ID 提交到代码仓库。
- 优先使用 AppRole 等工作负载身份，并使用短期 Token。
- 保持 TLS 校验开启，必要时配置受信任的 CA。
- 不要记录 Token、Secret ID、密文或原始 API 响应体。
- SDK 不会自动重试请求；写请求重试可能创建重复版本或其他副作用。
- `destroy()` 会永久删除 KV v2 版本，请谨慎使用。
- 自定义 Token 存储必须像 Token 本身一样受到保护。

## 兼容性

本包支持 PHP 7.2+，不绑定任何框架，遵循 Vault `/v1/` HTTP 约定，也兼容
提供这些接口的 Vault 服务。服务端策略和 secrets engine 配置由业务应用负责。

## 许可证

MIT，详见 [LICENSE](LICENSE)。
