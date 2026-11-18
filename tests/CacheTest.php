<?php

namespace Vault\Tests;

use InvalidArgumentException;
use Vault\Auth\Psr16TokenStorage;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

final class CacheTest extends TestCase
{
    public function testPsr16StorageDelegatesTokenOperationsToCache()
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())
            ->method('set')
            ->with('vault.token', 'token-123', 3600);
        $cache->expects(self::once())
            ->method('get')
            ->with('vault.token')
            ->willReturn('token-123');
        $cache->expects(self::once())
            ->method('delete')
            ->with('vault.token');

        $storage = new Psr16TokenStorage($cache, 'vault.token', 3600);
        $storage->setToken('token-123');

        self::assertSame('token-123', $storage->getToken());
        $storage->clear();
    }

    public function testNullOrEmptyTokenRemovesCachedToken()
    {
        $deletes = 0;
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('delete')
            ->willReturnCallback(function ($key) use (&$deletes) {
                self::assertSame('vault.token', $key);
                $deletes++;
                return true;
            });

        $storage = new Psr16TokenStorage($cache);
        $storage->setToken(null);
        $storage->setToken('');

        self::assertSame(2, $deletes);
    }

    /**
     * @dataProvider invalidConfigurationProvider
     */
    public function testInvalidCacheConfigurationIsRejected($key, $ttl)
    {
        $cache = $this->createMock(CacheInterface::class);

        $this->expectException(InvalidArgumentException::class);
        new Psr16TokenStorage($cache, $key, $ttl);
    }

    public static function invalidConfigurationProvider()
    {
        return array(
            array('', null),
            array('vault.token', -1),
            array('vault.token', '3600'),
        );
    }
}
