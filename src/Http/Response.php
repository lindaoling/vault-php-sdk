<?php

namespace Vault\Http;

final class Response
{
    private $statusCode;
    private $headers;
    private $body;

    public function __construct($statusCode, array $headers, $body)
    {
        $this->statusCode = (int) $statusCode;
        $this->headers = $headers;
        $this->body = (string) $body;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getBody()
    {
        return $this->body;
    }
}
