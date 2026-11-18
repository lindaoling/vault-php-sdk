<?php

namespace Vault\Tests;

use Vault\Http\Response;
use Vault\Http\TransportInterface;

final class FakeTransport implements TransportInterface
{
    public $lastRequest;
    private $response;

    public function __construct(Response $response)
    {
        $this->response = $response;
    }

    public function send($method, $url, array $headers, $body, array $options)
    {
        $this->lastRequest = compact('method', 'url', 'headers', 'body', 'options');
        return $this->response;
    }
}
