<?php

namespace Vault\Secrets;

use Vault\Http\HttpClient;

final class Kv2
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

    public function read($path, $version = null)
    {
        $query = $version === null ? array() : array('version' => (int) $version);
        $result = $this->http->get($this->dataPath($path), $query);
        return isset($result['data']) && is_array($result['data']) ? $result['data'] : array();
    }

    public function write($path, array $data, array $options = array())
    {
        $payload = array('data' => $data);
        if (!empty($options)) {
            $payload['options'] = $options;
        }
        return $this->http->post($this->dataPath($path), $payload);
    }

    public function patch($path, array $data)
    {
        return $this->http->patch($this->dataPath($path), array('data' => $data));
    }

    public function delete($path)
    {
        return $this->http->delete($this->dataPath($path));
    }

    public function deleteVersions($path, array $versions)
    {
        return $this->http->post($this->versionPath('delete', $path), array('versions' => array_values($versions)));
    }

    public function undelete($path, array $versions)
    {
        return $this->http->post($this->versionPath('undelete', $path), array('versions' => array_values($versions)));
    }

    public function destroy($path, array $versions)
    {
        return $this->http->post($this->versionPath('destroy', $path), array('versions' => array_values($versions)));
    }

    public function readMetadata($path)
    {
        $result = $this->http->get($this->metadataPath($path));
        return isset($result['data']) && is_array($result['data']) ? $result['data'] : array();
    }

    public function writeMetadata($path, array $metadata)
    {
        return $this->http->post($this->metadataPath($path), $metadata);
    }

    public function deleteMetadata($path)
    {
        return $this->http->delete($this->metadataPath($path));
    }

    public function list($path = '')
    {
        $endpoint = $this->mount . '/metadata';
        $path = trim((string) $path, '/');
        if ($path !== '') {
            $endpoint .= '/' . $path;
        }
        $result = $this->http->list($endpoint);
        return isset($result['data']['keys']) && is_array($result['data']['keys'])
            ? $result['data']['keys'] : array();
    }

    private function dataPath($path)
    {
        return $this->mount . '/data/' . $this->cleanPath($path);
    }

    private function metadataPath($path)
    {
        return $this->mount . '/metadata/' . $this->cleanPath($path);
    }

    private function versionPath($operation, $path)
    {
        return $this->mount . '/' . $operation . '/' . $this->cleanPath($path);
    }

    private function cleanPath($path)
    {
        $path = trim((string) $path, '/');
        if ($path === '' || strpos($path, '..') !== false) {
            throw new \InvalidArgumentException('Secret path must be a non-empty relative path.');
        }
        return $path;
    }
}
