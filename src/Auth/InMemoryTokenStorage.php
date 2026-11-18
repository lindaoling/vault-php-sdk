<?php

namespace Vault\Auth;

final class InMemoryTokenStorage implements TokenStorageInterface
{
    private $token;

    public function __construct($token = null)
    {
        $this->token = $token;
    }

    public function getToken()
    {
        return $this->token;
    }

    public function setToken($token)
    {
        $this->token = $token;
    }

    public function clear()
    {
        $this->token = null;
    }
}
