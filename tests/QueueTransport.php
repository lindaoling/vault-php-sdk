<?php

namespace Vault\Tests;

use Vault\Http\Response;
use Vault\Http\TransportInterface;

final class QueueTransport implements TransportInterface
{
    public $requests = array();
    private $responses;

    public function __construct(array $responses)
    {
        $this->responses = $responses;
    }

    public function send($method, $url, array $headers, $body, array $options)
    {
        $this->requests[] = compact('method', 'url', 'headers', 'body', 'options');

        if (empty($this->responses)) {
            throw new \RuntimeException('QueueTransport has no response left.');
        }

        return array_shift($this->responses);
    }
}
