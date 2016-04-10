<?php

namespace Caxy\HtmlDiff;

use Doctrine\Common\Cache\Cache;

/**
 * Class DiffCache.
 */
class DiffCache
{
    /**
     * @var Cache
     */
    protected $cacheProvider;

    /**
     * DiffCache constructor.
     *
     * @param Cache $cacheProvider
     */
    public function __construct(Cache $cacheProvider)
    {
        $this->cacheProvider = $cacheProvider;
    }

    /**
     * @return Cache
     */
    public function getCacheProvider()
    {
        return $this->cacheProvider;
    }

    /**
     * @param Cache $cacheProvider
     *
     * @return DiffCache
     */
    public function setCacheProvider($cacheProvider)
    {
        $this->cacheProvider = $cacheProvider;

        return $this;
    }

    /**
     * @param string $oldText
     * @param string $newText
     *
     * @return bool
     */
    public function contains($oldText, $newText)
    {
        return $this->cacheProvider->contains($this->getHashKey($oldText, $newText));
    }

    /**
     * @param string $oldText
     * @param string $newText
     *
     * @return string
     */
    public function fetch($oldText, $newText)
    {
        return $this->cacheProvider->fetch($this->getHashKey($oldText, $newText));
    }

    /**
     * @param string $oldText
     * @param string $newText
     * @param string $data
     * @param int    $lifeTime
     *
     * @return bool
     */
    public function save($oldText, $newText, $data, $lifeTime = 0)
    {
        return $this->cacheProvider->save($this->getHashKey($oldText, $newText), $data, $lifeTime);
    }

    /**
     * @param string $oldText
     * @param string $newText
     *
     * @return bool
     */
    public function delete($oldText, $newText)
    {
        return $this->cacheProvider->delete($this->getHashKey($oldText, $newText));
    }

    /**
     * @return array|null
     */
    public function getStats()
    {
        return $this->cacheProvider->getStats();
    }

    /**
     * @param string $oldText
     * @param string $newText
     *
     * @return string
     */
    protected function getHashKey($oldText, $newText)
    {
        return sprintf('%s_%s', md5($oldText), md5($newText));
    }
}
