<?php
namespace Splot\DependencyInjection;

use Splot\DependencyInjection\Definition\ClosureService;
use Splot\DependencyInjection\Definition\ObjectService;
use Splot\DependencyInjection\Exceptions\CacheDataNotFoundException;
use Splot\DependencyInjection\Exceptions\NotCacheableException;
use Splot\DependencyInjection\Container;

class CachedContainer extends Container
{

    /**
     * Cache in which the container data is stored.
     * 
     * @var ContainerCacheInterface
     */
    protected $cache;

    /**
     * Services that have been instantiated before reading from cache
     * and their notifications will need to be delivered right after
     * cache has been loaded.
     * 
     * @var array
     */
    protected $preCacheServices = array();

    /**
     * Has data been loaded from cache already?
     * 
     * @var boolean
     */
    protected $loadedFromCache = false;

    /**
     * Constructor.
     * 
     * @param ContainerCacheInterface $cache ContainerCacheInterface
     */
    public function __construct(ContainerCacheInterface $cache) {
        parent::__construct();

        $this->cache = $cache;
    }

    public function get($name) {
        $instance = parent::get($name);
        // if already loaded from cache, then don't do anything
        if ($this->loadedFromCache) {
            return $instance;
        }

        // if cache hasn't been loaded yet, then keep reference to this
        // resolved service so that on cache load we can deliver all
        // notifications to it
        $realName = $this->resolveServiceName($name);
        $this->preCacheServices[$realName] = array(
            'name' => $realName,
            'instance' => $instance
        );

        return $instance;
    }

    /**
     * Load container data from cache.
     *
     * It will try to not overwrite any service (or param) that has already been
     * registered in the container.
     * 
     * @throws CacheDataNotFoundException When there wasn't any data in the cache.
     */
    public function loadFromCache() {
        $cachedData = $this->cache->load();

        if (
            !is_array($cachedData)
            || !isset($cachedData['parameters']) || !is_array($cachedData['parameters'])
            || !isset($cachedData['services']) || !is_array($cachedData['services'])
            || !isset($cachedData['aliases']) || !is_array($cachedData['aliases'])
            || !isset($cachedData['notifications']) || !is_array($cachedData['notifications'])
            || !isset($cachedData['loaded_files']) || !is_array($cachedData['loaded_files'])
        ) {
            throw new CacheDataNotFoundException('Container cache has returned invalid data.');
        }

        $this->parameters = array_merge($cachedData['parameters'], $this->parameters);
        $this->services = array_merge($cachedData['services'], $this->services);
        $this->aliases = array_merge($cachedData['aliases'], $this->aliases);
        $this->notificationsResolver->notifications = array_merge($cachedData['notifications'], $this->notificationsResolver->notifications);
        $this->loadedFiles = array_merge($cachedData['loaded_files'], $this->loadedFiles);

        // deliver notifications to any services that have already been instantiated
        $this->notificationsResolver->queue = array_merge($this->preCacheServices, $this->notificationsResolver->queue);
        $this->preCacheServices = array(); // clear it

        // mark that the container has now been loaded from cache
        $this->loadedFromCache = true;

        $this->notificationsResolver->resolveQueue();
    }

    /**
     * Cache the current container configuration state.
     *
     * Container cannot be cached if there are closue or object services registered in it.
     *
     * @throws NotCacheableException When the container data cannot be cached for some reason.
     */
    public function cacheCurrentState() {
        foreach($this->services as $name => $definition) {
            if ($definition instanceof ClosureService) {
                throw new NotCacheableException('Cannot cache the container with a closure service registered ("'. $definition->name .'"). If you want to use a closure service set it after storing in cache or after reading from cache.');
            }

            // "container" is an exception case, because it's set in the container constructor anyway
            if ($definition instanceof ObjectService && $definition->name !== 'container') {
                throw new NotCacheableException('Cannot cache the container with an object service registered ("'. $definition->name .'"). If you want to use an object service set it after storing in cache or after reading from cache.');
            }
        }

        // merge back the delivered notifications to the notifications list
        $notifications = $this->notificationsResolver->notifications;
        foreach($this->notificationsResolver->deliveredNotifications as $target => $items) {
            if (!isset($notifications[$target])) {
                $notifications[$target] = array();
            }
            $notifications[$target] = array_merge($items, $notifications[$target]);
        }

        $this->cache->save(array(
            'parameters' => $this->parameters,
            'services' => $this->services,
            'aliases' => $this->aliases,
            'notifications' => $notifications,
            'loaded_files' => $this->loadedFiles
        ));
    }

    /**
     * Clear the cache.
     */
    public function clearCache() {
        $this->cache->flush();
    }

}
