<?php

namespace Vault\Auth;

interface TokenStorageInterface
{
    public function getToken();

    public function setToken($token);

    public function clear();
}
