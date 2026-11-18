<?php

namespace Vault\System;

use Vault\Http\HttpClient;

final class SysApi
{
    private $http;

    public function __construct(HttpClient $http)
    {
        $this->http = $http;
    }

    public function health(array $query = array())
    {
        return $this->http->get('sys/health', $query);
    }

    public function mounts()
    {
        $result = $this->http->get('sys/mounts');
        return isset($result['data']) ? $result['data'] : $result;
    }
}
