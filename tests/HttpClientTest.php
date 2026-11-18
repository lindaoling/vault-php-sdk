<?php

namespace Vault\Tests;

use Vault\Client;
use Vault\Http\Response;
use PHPUnit\Framework\TestCase;

final class HttpClientTest extends TestCase
{
    public function testKv2ReadUsesExpectedEndpointAndTokenHeader()
    {
        $transport = new FakeTransport(new Response(
            200,
            array(),
            '{"data":{"data":{"username":"app"},"metadata":{"version":3}}}'
        ));

        $client = new Client(array(
            'address' => 'https://vault.example.test:8200/',
            'token' => 'test-token',
        ), $transport);

        $result = $client->kv2('secret')->read('app/database');

        self::assertSame(array('username' => 'app'), $result['data']);
        self::assertSame('GET', $transport->lastRequest['method']);
        self::assertSame(
            'https://vault.example.test:8200/v1/secret/data/app/database',
            $transport->lastRequest['url']
        );
        self::assertContains('X-Vault-Token: test-token', $transport->lastRequest['headers']);
    }

    public function testApiErrorsExposeStatusCode()
    {
        $transport = new FakeTransport(new Response(403, array(), '{"errors":["permission denied"]}'));
        $client = new Client(array('address' => 'https://vault.example.test'), $transport);

        try {
            $client->kv1('secret')->read('private');
            self::fail('Expected API exception.');
        } catch (\Vault\Exception\ApiException $exception) {
            self::assertSame(403, $exception->getStatusCode());
            self::assertSame(array('permission denied'), $exception->getResponse()['errors']);
        }
    }
}
