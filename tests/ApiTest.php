<?php

namespace Vault\Tests;

use Vault\Auth\AppRoleAuth;
use Vault\Auth\InMemoryTokenStorage;
use Vault\Client;
use Vault\Exception\AuthenticationException;
use Vault\Exception\TransportException;
use Vault\Http\Response;
use PHPUnit\Framework\TestCase;

final class ApiTest extends TestCase
{
    public function testRequestBuildsUrlHeadersAndJsonBody()
    {
        $transport = new QueueTransport(array(
            new Response(200, array(), '{"ok":true}'),
        ));
        $client = new Client(array(
            'address' => 'https://vault.example.test:8200/',
            'token' => 'token-123',
            'namespace' => 'team-a',
            'headers' => array('X-Trace-Id' => 'trace-1'),
        ), $transport);

        self::assertSame(
            array('ok' => true),
            $client->request('post', 'sys/test', array('name' => '测试'), array('filter' => 'a b'))
        );

        $request = $transport->requests[0];
        self::assertSame('POST', $request['method']);
        self::assertSame(
            'https://vault.example.test:8200/v1/sys/test?filter=a%20b',
            $request['url']
        );
        self::assertSame('{"name":"测试"}', $request['body']);
        self::assertContains('X-Vault-Token: token-123', $request['headers']);
        self::assertContains('X-Vault-Namespace: team-a', $request['headers']);
        self::assertContains('X-Trace-Id: trace-1', $request['headers']);
        self::assertContains('Content-Type: application/json', $request['headers']);
    }

    public function testKv2OperationsUseExpectedEndpointsAndPayloads()
    {
        $transport = new QueueTransport(array(
            new Response(200, array(), '{}'),
            new Response(200, array(), '{"data":{"data":{"key":"value"},"metadata":{"version":2}}}'),
            new Response(200, array(), '{}'),
            new Response(204, array(), ''),
            new Response(200, array(), '{}'),
            new Response(200, array(), '{"data":{"keys":["app/"]}}'),
        ));
        $client = new Client(array('address' => 'https://vault.example.test'), $transport);
        $kv = $client->kv2('secret');

        $kv->write('app/config', array('key' => 'value'), array('cas' => 0));
        $result = $kv->read('app/config', 2);
        $kv->patch('app/config', array('key' => 'updated'));
        $kv->deleteVersions('app/config', array(1, 2));
        $kv->delete('app/config');
        self::assertSame(array('app/'), $kv->list('app'));

        self::assertSame('POST', $transport->requests[0]['method']);
        self::assertSame('https://vault.example.test/v1/secret/data/app/config', $transport->requests[0]['url']);
        self::assertSame(
            '{"data":{"key":"value"},"options":{"cas":0}}',
            $transport->requests[0]['body']
        );
        self::assertSame(
            'https://vault.example.test/v1/secret/data/app/config?version=2',
            $transport->requests[1]['url']
        );
        self::assertSame(array('key' => 'value'), $result['data']);
        self::assertSame('PATCH', $transport->requests[2]['method']);
        self::assertSame('POST', $transport->requests[3]['method']);
        self::assertSame(
            'https://vault.example.test/v1/secret/delete/app/config',
            $transport->requests[3]['url']
        );
        self::assertSame('https://vault.example.test/v1/secret/data/app/config', $transport->requests[4]['url']);
        self::assertSame('LIST', $transport->requests[5]['method']);
    }

    public function testAppRoleLoginStoresReturnedToken()
    {
        $transport = new QueueTransport(array(
            new Response(200, array(), '{"auth":{"client_token":"app-token","lease_duration":3600}}'),
        ));
        $client = new Client(array('address' => 'https://vault.example.test'), $transport);

        $auth = new AppRoleAuth($client);
        $result = $auth->login('role-id', 'secret-id', array('role_name' => 'web'));

        self::assertSame('app-token', $client->getToken());
        self::assertSame(3600, $result['lease_duration']);
        self::assertSame(
            'https://vault.example.test/v1/approle/login',
            $transport->requests[0]['url']
        );
        self::assertSame(
            '{"role_id":"role-id","secret_id":"secret-id","role_name":"web"}',
            $transport->requests[0]['body']
        );
    }

    public function testAppRoleLoginWithoutTokenThrowsAuthenticationException()
    {
        $transport = new QueueTransport(array(new Response(200, array(), '{"auth":{}}')));
        $client = new Client(array('address' => 'https://vault.example.test'), $transport);

        $this->expectException(AuthenticationException::class);
        (new AppRoleAuth($client))->login('role-id', 'secret-id');
    }

    public function testInvalidJsonSuccessResponseThrowsTransportException()
    {
        $transport = new QueueTransport(array(new Response(200, array(), '{invalid')));
        $client = new Client(array('address' => 'https://vault.example.test'), $transport);

        $this->expectException(TransportException::class);
        $client->request('GET', 'sys/health');
    }

    public function testAuthSystemAndTokenStorageApis()
    {
        $transport = new QueueTransport(array(
            new Response(200, array(), '{"data":{"id":"token"}}'),
            new Response(200, array(), '{"auth":{"client_token":"renewed"}}'),
            new Response(204, array(), ''),
            new Response(200, array(), '{"initialized":true}'),
            new Response(200, array(), '{"data":{"secret/":{"type":"kv"}}}'),
        ));
        $client = new Client(array('address' => 'https://vault.example.test', 'token' => 'initial'), $transport);
        $storage = new InMemoryTokenStorage('shared');
        $client->setTokenStorage($storage);

        self::assertSame(array('id' => 'token'), $client->auth()->lookupSelf());
        $client->auth()->renewSelf(600);
        $client->auth()->revokeSelf();
        self::assertNull($client->getToken());
        self::assertSame(array('initialized' => true), $client->sys()->health());
        self::assertSame(array('secret/' => array('type' => 'kv')), $client->sys()->mounts());
        self::assertNull($storage->getToken());
        self::assertSame(
            'https://vault.example.test/v1/auth/token/lookup-self',
            $transport->requests[0]['url']
        );
        self::assertSame('{"increment":600}', $transport->requests[1]['body']);
    }
}
