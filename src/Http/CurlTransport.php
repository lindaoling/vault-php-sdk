<?php

namespace Vault\Http;

use Vault\Exception\TransportException;

final class CurlTransport implements TransportInterface
{
    public function send($method, $url, array $headers, $body, array $options)
    {
        if (!function_exists('curl_init')) {
            throw new TransportException('The PHP cURL extension is required.');
        }

        $responseHeaders = array();
        $handle = curl_init($url);

        $curlOptions = array(
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => $options['connect_timeout'],
            CURLOPT_TIMEOUT => $options['timeout'],
            CURLOPT_SSL_VERIFYPEER => $options['verify_peer'],
            CURLOPT_SSL_VERIFYHOST => $options['verify_host'] ? 2 : 0,
            CURLOPT_HEADERFUNCTION => function ($curl, $headerLine) use (&$responseHeaders) {
                $length = strlen($headerLine);
                $parts = explode(':', $headerLine, 2);
                if (count($parts) === 2) {
                    $name = strtolower(trim($parts[0]));
                    $responseHeaders[$name][] = trim($parts[1]);
                }
                return $length;
            },
        );

        if (!empty($options['ca_file'])) {
            $curlOptions[CURLOPT_CAINFO] = $options['ca_file'];
        }

        if ($body !== null) {
            $curlOptions[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($handle, $curlOptions);
        $responseBody = curl_exec($handle);

        if ($responseBody === false) {
            $message = curl_error($handle);
            $number = curl_errno($handle);
            curl_close($handle);
            throw new TransportException(
                'Vault HTTP transport failed (' . $number . '): ' . $message,
                $number
            );
        }

        $statusCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        return new Response($statusCode, $responseHeaders, $responseBody);
    }
}
