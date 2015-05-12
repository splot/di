<?php
namespace Splot\DependencyInjection;

use Splot\DependencyInjection\Exceptions\CacheDataNotFoundException;

interface ContainerCacheInterface
{

    /**
     * Loads container data from the cache.
     *
     * Returns whatever data was previously stored in the cache.
     * 
     * @return mixed
     *
     * @throws CacheDataNotFoundException When could not find or load any data from cache.
     */
    function load();

    /**
     * Stores given container data in the cache.
     * 
     * @param  mixed $data Whatever data the container wants to cache.
     */
    function save($data);

    /**
     * Clears the container cache.
     */
    function clear();

}
