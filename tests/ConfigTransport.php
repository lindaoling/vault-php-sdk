<?php

namespace Vault\Tests;

use Vault\Http\Response;
use Vault\Http\TransportInterface;

final class ConfigTransport implements TransportInterface
{
    public $lastRequest;

    public function send($method, $url, array $headers, $body, array $options)
    {
        $this->lastRequest = compact('method', 'url', 'headers', 'body', 'options');
        return new Response(200, array(), '{}');
    }
}
