<?php

namespace Vault\Http;

interface TransportInterface
{
    /**
     * @param string $method HTTP method
     * @param string $url Full URL
     * @param array $headers Header lines
     * @param string|null $body Encoded request body
     * @param array $options Transport options
     * @return Response
     */
    public function send($method, $url, array $headers, $body, array $options);
}
