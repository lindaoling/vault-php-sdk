<?php

namespace Vault;

use InvalidArgumentException;

final class Config
{
    private $values;

    public function __construct(array $config)
    {
        if (empty($config['address']) || !filter_var($config['address'], FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('A valid Vault address is required.');
        }

        $scheme = strtolower((string) parse_url($config['address'], PHP_URL_SCHEME));
        if (!in_array($scheme, array('http', 'https'), true)) {
            throw new InvalidArgumentException('Vault address must use HTTP or HTTPS.');
        }

        $tls = isset($config['tls']) && is_array($config['tls']) ? $config['tls'] : array();
        $timeout = isset($config['timeout']) ? (int) $config['timeout'] : 10;
        $connectTimeout = isset($config['connect_timeout']) ? (int) $config['connect_timeout'] : 3;

        if ($timeout < 1 || $connectTimeout < 1) {
            throw new InvalidArgumentException('HTTP timeouts must be positive integers.');
        }

        $this->values = array(
            'address' => rtrim($config['address'], '/'),
            'token' => isset($config['token']) ? $config['token'] : null,
            'namespace' => isset($config['namespace']) ? $config['namespace'] : null,
            'timeout' => $timeout,
            'connect_timeout' => $connectTimeout,
            'tls' => array(
                'verify_peer' => isset($tls['verify_peer']) ? (bool) $tls['verify_peer'] : true,
                'verify_host' => isset($tls['verify_host']) ? (bool) $tls['verify_host'] : true,
                'ca_file' => isset($tls['ca_file']) ? $tls['ca_file'] : null,
            ),
            'headers' => isset($config['headers']) && is_array($config['headers'])
                ? $config['headers'] : array(),
        );
    }

    public function get($key)
    {
        return array_key_exists($key, $this->values) ? $this->values[$key] : null;
    }
}
