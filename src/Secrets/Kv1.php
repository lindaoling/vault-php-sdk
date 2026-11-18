<?php

namespace Vault\Secrets;

use Vault\Http\HttpClient;

final class Kv1
{
    private $http;
    private $mount;

    public function __construct(HttpClient $http, $mount)
    {
        $this->http = $http;
        $this->mount = trim((string) $mount, '/');
        if ($this->mount === '') {
            throw new \InvalidArgumentException('KV mount path cannot be empty.');
        }
    }

    public function read($path)
    {
        $result = $this->http->get($this->secretPath($path));
        return isset($result['data']) && is_array($result['data']) ? $result['data'] : array();
    }

    public function write($path, array $data)
    {
        return $this->http->post($this->secretPath($path), $data);
    }

    public function delete($path)
    {
        return $this->http->delete($this->secretPath($path));
    }

    public function list($path = '')
    {
        $endpoint = $this->mount;
        $path = trim((string) $path, '/');
        if ($path !== '') {
            $endpoint .= '/' . $path;
        }
        $result = $this->http->list($endpoint);
        return isset($result['data']['keys']) && is_array($result['data']['keys'])
            ? $result['data']['keys'] : array();
    }

    private function secretPath($path)
    {
        $path = trim((string) $path, '/');
        if ($path === '' || strpos($path, '..') !== false) {
            throw new \InvalidArgumentException('Secret path must be a non-empty relative path.');
        }
        return $this->mount . '/' . $path;
    }
}
