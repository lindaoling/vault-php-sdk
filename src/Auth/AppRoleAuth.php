<?php

namespace Vault\Auth;

use Vault\Client;
use Vault\Exception\AuthenticationException;

final class AppRoleAuth
{
    private $client;
    private $mount;

    public function __construct(Client $client, $mount = 'approle')
    {
        $this->client = $client;
        $this->mount = trim((string) $mount, '/');
    }

    public function login($roleId, $secretId, array $options = array())
    {
        $payload = array(
            'role_id' => $roleId,
            'secret_id' => $secretId,
        );

        $payload = array_merge($payload, $options);
        $result = $this->client->request('POST', $this->mount . '/login', $payload);
        $auth = isset($result['auth']) && is_array($result['auth']) ? $result['auth'] : array();
        $token = isset($auth['client_token']) ? $auth['client_token'] : null;

        if (!$token) {
            throw new AuthenticationException('Vault AppRole response did not contain a client token.');
        }

        $this->client->setToken($token);
        return $auth;
    }
}
