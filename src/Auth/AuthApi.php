<?php

namespace Vault\Auth;

use Vault\Http\HttpClient;

final class AuthApi
{
    private $http;

    public function __construct(HttpClient $http)
    {
        $this->http = $http;
    }

    public function lookupSelf()
    {
        $result = $this->http->get('auth/token/lookup-self');
        return isset($result['data']) ? $result['data'] : array();
    }

    public function renewSelf($increment = null)
    {
        $payload = $increment === null ? array() : array('increment' => (int) $increment);
        return $this->http->post('auth/token/renew-self', $payload);
    }

    public function revokeSelf()
    {
        $result = $this->http->post('auth/token/revoke-self', array());
        $this->http->setToken(null);
        return $result;
    }
}
