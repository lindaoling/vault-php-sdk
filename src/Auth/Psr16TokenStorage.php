<?php

namespace Vault\Auth;

use InvalidArgumentException;
use Psr\SimpleCache\CacheInterface;

/**
 * Stores the client token through a PSR-16 compatible cache implementation.
 */
final class Psr16TokenStorage implements TokenStorageInterface
{
    private $cache;
    private $key;
    private $ttl;

    /**
     * @param CacheInterface $cache PSR-16 cache implementation
     * @param string $key Cache key used for the token
     * @param int|\DateInterval|null $ttl Optional cache lifetime
     */
    public function __construct(CacheInterface $cache, $key = 'vault.token', $ttl = null)
    {
        $key = (string) $key;
        if ($key === '') {
            throw new InvalidArgumentException('Token cache key cannot be empty.');
        }

        if ($ttl !== null && !is_int($ttl) && !($ttl instanceof \DateInterval)) {
            throw new InvalidArgumentException('Token cache TTL must be an integer, DateInterval, or null.');
        }

        if (is_int($ttl) && $ttl < 0) {
            throw new InvalidArgumentException('Token cache TTL cannot be negative.');
        }

        $this->cache = $cache;
        $this->key = $key;
        $this->ttl = $ttl;
    }

    public function getToken()
    {
        return $this->cache->get($this->key);
    }

    public function setToken($token)
    {
        if ($token === null || $token === '') {
            $this->clear();
            return;
        }

        $this->cache->set($this->key, $token, $this->ttl);
    }

    public function clear()
    {
        $this->cache->delete($this->key);
    }
}
