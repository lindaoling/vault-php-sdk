<?php

namespace Vault;

use Vault\Auth\AuthApi;
use Vault\Auth\TokenStorageInterface;
use Vault\Http\HttpClient;
use Vault\Http\TransportInterface;
use Vault\Secrets\Kv1;
use Vault\Secrets\Kv2;
use Vault\System\SysApi;

final class Client
{
    private $http;
    private $authApi;
    private $sysApi;

    public function __construct(array $config, TransportInterface $transport = null)
    {
        $this->http = new HttpClient(new Config($config), $transport);
    }

    public function setToken($token)
    {
        $this->http->setToken($token);
        return $this;
    }

    public function getToken()
    {
        return $this->http->getToken();
    }

    public function setTokenStorage(TokenStorageInterface $storage)
    {
        $this->http->setTokenStorage($storage);
        return $this;
    }

    public function kv1($mount = 'secret')
    {
        return new Kv1($this->http, $mount);
    }

    public function kv2($mount = 'secret')
    {
        return new Kv2($this->http, $mount);
    }

    public function auth()
    {
        if ($this->authApi === null) {
            $this->authApi = new AuthApi($this->http);
        }
        return $this->authApi;
    }

    public function sys()
    {
        if ($this->sysApi === null) {
            $this->sysApi = new SysApi($this->http);
        }
        return $this->sysApi;
    }

    public function request($method, $path, array $data = array(), array $query = array())
    {
        return $this->http->request($method, $path, $data, $query);
    }
}
