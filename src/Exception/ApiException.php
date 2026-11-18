<?php

namespace Vault\Exception;

class ApiException extends VaultException
{
    private $statusCode;
    private $response;
    private $rawBody;

    public function __construct($message, $statusCode, array $response = array(), $rawBody = '')
    {
        parent::__construct($message, (int) $statusCode);
        $this->statusCode = (int) $statusCode;
        $this->response = $response;
        $this->rawBody = (string) $rawBody;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function getResponse()
    {
        return $this->response;
    }

    /** Raw response body; handle carefully because it may contain sensitive data. */
    public function getRawBody()
    {
        return $this->rawBody;
    }
}
