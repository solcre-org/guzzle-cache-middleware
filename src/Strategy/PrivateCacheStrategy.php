<?php

namespace Kevinrob\GuzzleCache\Strategy;

use Doctrine\Common\Cache\ArrayCache;
use Kevinrob\GuzzleCache\CacheEntry;
use Kevinrob\GuzzleCache\KeyValueHttpHeader;
use Kevinrob\GuzzleCache\Storage\CacheStorageInterface;
use Kevinrob\GuzzleCache\Storage\DoctrineCacheWrapper;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * This strategy represent a "private" HTTP client.
 * Pay attention to share storage between application with caution!
 *
 * For example, a response with cache-control header "private, max-age=60"
 * will be cached by this strategy.
 *
 * The rules applied are from RFC 7234.
 *
 * @see https://tools.ietf.org/html/rfc7234
 */
class PrivateCacheStrategy implements CacheStrategyInterface
{
    /**
     * @var CacheStorageInterface
     */
    protected $storage;

    /**
     * @var int[]
     */
    protected $statusAccepted = [
        200 => 200,
        203 => 203,
        204 => 204,
        300 => 300,
        301 => 301,
        404 => 404,
        405 => 405,
        410 => 410,
        414 => 414,
        418 => 418,
        501 => 501,
    ];

    /**
     * @var string[]
     */
    protected $ageKey = [
        'max-age',
    ];

    public function __construct(CacheStorageInterface $cache = null)
    {
        $this->storage = $cache !== null ? $cache : new DoctrineCacheWrapper(new ArrayCache());
    }

    /**
     * @param ResponseInterface $response
     *
     * @return CacheEntry|null entry to save, null if can't cache it
     */
    protected function getCacheObject(ResponseInterface $response)
    {
        if (!isset($this->statusAccepted[$response->getStatusCode()])) {
            // Don't cache it
            return;
        }

        $cacheControl = new KeyValueHttpHeader($response->getHeader('Cache-Control'));

        if ($cacheControl->has('no-store')) {
            // No store allowed (maybe some sensitives data...)
            return;
        }

        if ($cacheControl->has('no-cache')) {
            // Stale response see RFC7234 section 5.2.1.4
            $entry = new CacheEntry($response, new \DateTime('-1 seconds'));

            return $entry->hasValidationInformation() ? $entry : null;
        }

        foreach ($this->ageKey as $key) {
            if ($cacheControl->has($key)) {
                return new CacheEntry(
                    $response,
                    new \DateTime('+'.(int) $cacheControl->get($key).'seconds')
                );
            }
        }

        if ($response->hasHeader('Expires')) {
            $expireAt = \DateTime::createFromFormat(\DateTime::RFC1123, $response->getHeaderLine('Expires'));
            if ($expireAt !== false) {
                return new CacheEntry(
                    $response,
                    $expireAt
                );
            }
        }

        return new CacheEntry($response, new \DateTime('-1 seconds'));
    }

    /**
     * @param RequestInterface $request
     *
     * @return string
     */
    protected function getCacheKey(RequestInterface $request)
    {
        return sha1(
            $request->getMethod().$request->getUri()
        );
    }

    /**
     * Return a CacheEntry or null if no cache.
     *
     * @param RequestInterface $request
     *
     * @return CacheEntry|null
     */
    public function fetch(RequestInterface $request)
    {
        return $this->storage->fetch($this->getCacheKey($request));
    }

    /**
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     *
     * @return bool true if success
     */
    public function cache(RequestInterface $request, ResponseInterface $response)
    {
        $cacheObject = $this->getCacheObject($response);
        if ($cacheObject !== null) {
            return $this->storage->save(
                $this->getCacheKey($request),
                $cacheObject
            );
        }

        return false;
    }
}