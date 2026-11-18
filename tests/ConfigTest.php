<?php

namespace Vault\Tests;

use InvalidArgumentException;
use Vault\Client;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testAddressIsNormalizedAndDefaultsAreApplied()
    {
        $transport = new ConfigTransport();
        $client = new Client(array('address' => 'https://vault.example.test///'), $transport);

        self::assertNull($client->getToken());
        $client->request('GET', 'sys/health');
        self::assertSame('https://vault.example.test/v1/sys/health', $transport->lastRequest['url']);
        self::assertSame(10, $transport->lastRequest['options']['timeout']);
        self::assertSame(3, $transport->lastRequest['options']['connect_timeout']);
    }

    /**
     * @dataProvider invalidConfigurationProvider
     */
    public function testInvalidConfigurationIsRejected(array $configuration)
    {
        $this->expectException(InvalidArgumentException::class);
        new Client($configuration);
    }

    public static function invalidConfigurationProvider()
    {
        return array(
            array(array()),
            array(array('address' => 'ftp://vault.example.test')),
            array(array('address' => 'https://vault.example.test', 'timeout' => 0)),
            array(array('address' => 'https://vault.example.test', 'connect_timeout' => -1)),
        );
    }

    public function testPathAndHeaderInjectionAreRejected()
    {
        $client = new Client(array('address' => 'https://vault.example.test'));

        $this->expectException(InvalidArgumentException::class);
        $client->request('GET', 'sys/../secret');
    }

    public function testHeaderCarriageReturnIsRejected()
    {
        $client = new Client(array(
            'address' => 'https://vault.example.test',
            'headers' => array('X-Trace' => "safe\r\nInjected: true"),
        ));

        $this->expectException(InvalidArgumentException::class);
        $client->request('GET', 'sys/health');
    }
}
