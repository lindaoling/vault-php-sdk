<?php

namespace Vault\Http;

use Vault\Config;
use Vault\Auth\InMemoryTokenStorage;
use Vault\Auth\TokenStorageInterface;
use Vault\Exception\ApiException;
use Vault\Exception\TransportException;

final class HttpClient
{
    private $config;
    private $transport;
    private $tokenStorage;

    public function __construct(Config $config, TransportInterface $transport = null)
    {
        $this->config = $config;
        $this->transport = $transport ?: new CurlTransport();
        $this->tokenStorage = new InMemoryTokenStorage($config->get('token'));
    }

    public function setToken($token)
    {
        $this->tokenStorage->setToken($token);
    }

    public function getToken()
    {
        return $this->tokenStorage->getToken();
    }

    public function setTokenStorage(TokenStorageInterface $storage)
    {
        $this->tokenStorage = $storage;
        return $this;
    }

    public function getTokenStorage()
    {
        return $this->tokenStorage;
    }

    public function request($method, $path, array $data = array(), array $query = array())
    {
        $method = strtoupper($method);
        $path = ltrim($path, '/');

        if ($path === '' || strpos($path, '..') !== false) {
            throw new \InvalidArgumentException('API path must be a non-empty relative path.');
        }

        $url = $this->config->get('address') . '/v1/' . $path;
        if (!empty($query)) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $headers = array(
            'Accept: application/json',
            'X-Vault-Request: true',
        );

        $token = $this->tokenStorage->getToken();
        if ($token !== null && $token !== '') {
            $headers[] = 'X-Vault-Token: ' . $this->safeHeaderValue($token, 'token');
        }

        $namespace = $this->config->get('namespace');
        if ($namespace !== null && $namespace !== '') {
            $headers[] = 'X-Vault-Namespace: ' . $this->safeHeaderValue($namespace, 'namespace');
        }

        foreach ($this->config->get('headers') as $name => $value) {
            $headers[] = $this->safeHeaderValue($name, 'header name') . ': '
                . $this->safeHeaderValue($value, 'header value');
        }

        $body = null;
        if (!empty($data) || in_array($method, array('POST', 'PUT', 'PATCH'), true)) {
            $encoded = json_encode($data, JSON_UNESCAPED_UNICODE);
            if ($encoded === false) {
                throw new \InvalidArgumentException('Unable to JSON-encode request payload: ' . json_last_error_msg());
            }
            $body = $encoded;
            $headers[] = 'Content-Type: application/json';
        }

        $tls = $this->config->get('tls');
        $options = array(
            'timeout' => $this->config->get('timeout'),
            'connect_timeout' => $this->config->get('connect_timeout'),
            'verify_peer' => $tls['verify_peer'],
            'verify_host' => $tls['verify_host'],
            'ca_file' => $tls['ca_file'],
        );

        $response = $this->transport->send($method, $url, $headers, $body, $options);
        $status = $response->getStatusCode();
        $raw = $response->getBody();

        if ($raw === '') {
            $decoded = array();
        } else {
            $decoded = json_decode($raw, true);
        }

        if ($status < 200 || $status >= 300) {
            $payload = is_array($decoded) ? $decoded : array();
            $message = isset($payload['errors']) && is_array($payload['errors'])
                ? implode('; ', $payload['errors'])
                : 'Vault API request failed with HTTP ' . $status;

            if ($status === 401) {
                $message = 'Vault authentication failed: ' . $message;
            }

            throw new ApiException($message, $status, $payload, $raw);
        }

        if ($raw !== '' && json_last_error() !== JSON_ERROR_NONE) {
            throw new TransportException('Vault returned invalid JSON: ' . json_last_error_msg());
        }

        return is_array($decoded) ? $decoded : array();
    }

    public function get($path, array $query = array())
    {
        return $this->request('GET', $path, array(), $query);
    }

    public function post($path, array $data = array())
    {
        return $this->request('POST', $path, $data);
    }

    public function put($path, array $data = array())
    {
        return $this->request('PUT', $path, $data);
    }

    public function patch($path, array $data = array())
    {
        return $this->request('PATCH', $path, $data);
    }

    public function delete($path, array $query = array())
    {
        return $this->request('DELETE', $path, array(), $query);
    }

    public function list($path, array $query = array())
    {
        return $this->request('LIST', $path, array(), $query);
    }

    private function safeHeaderValue($value, $label)
    {
        $value = (string) $value;
        if (strpos($value, "\r") !== false || strpos($value, "\n") !== false) {
            throw new \InvalidArgumentException('Invalid CR/LF in ' . $label . '.');
        }
        return $value;
    }
}
