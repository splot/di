<?php
namespace Splot\DependencyInjection\Tests;

use MD\Foundation\Exceptions\NotImplementedException;

use Splot\DependencyInjection\Tests\TestFixtures\ContainerCache;
use Splot\DependencyInjection\Tests\CoverallTest;

use Splot\DependencyInjection\Exceptions\CacheDataNotFoundException;
use Splot\DependencyInjection\CachedContainer;

// by extending CoverallTest we're actually gonna test full functionality
// again on a cached container (see `::setUp()` method).
class CachedContainerTest extends CoverallTest
{

    protected $globalCache;

    public function setUp() {
        $this->globalCache = new ContainerCache();

        // a container is created on one request
        $container = new CachedContainer($this->globalCache);
        $container->loadFromFile(__DIR__ .'/fixtures/coverall.yml');
        $container->cacheCurrentState();

        // different container instance is created on another request, but is loaded from cache
        // all tests from `CoverallTest` class depend on `$this->container`
        $this->container = new CachedContainer($this->globalCache);
        $this->container->loadFromCache();
    }

    public function testIntegrationWithCache() {
        $cache = $this->getMockBuilder('Splot\DependencyInjection\ContainerCacheInterface')->getMock();
        $cache->expects($this->once())
            ->method('load')
            ->willReturn(array(
                'parameters' => array(),
                'services' => array(),
                'aliases' => array(),
                'notifications' => array(),
                'loaded_files' => array()
            ));
        $cache->expects($this->once())
            ->method('save');
        $cache->expects($this->once())
            ->method('clear');

        $container = new CachedContainer($cache);
        $container->loadFromFile(__DIR__ .'/fixtures/coverall.yml');

        $container->cacheCurrentState();
        $container->loadFromCache();
        $container->clearCache();
    }

    /**
     * @expectedException \Splot\DependencyInjection\Exceptions\CacheDataNotFoundException
     */
    public function testNotHavingCachedData() {
        $container = new CachedContainer(new ContainerCache());
        $container->loadFromCache();
    }

    /**
     * @expectedException \Splot\DependencyInjection\Exceptions\CacheDataNotFoundException
     */
    public function testHavingInvalidCachedData() {
        $cache = $this->getMockBuilder('Splot\DependencyInjection\ContainerCacheInterface')->getMock();
        $cache->method('load')->willReturn(array());

        $container = new CachedContainer($cache);
        $container->loadFromCache();
    }

    public function testReadingFromCacheAfterSettingSomeData() {
        $container = new CachedContainer($this->globalCache);

        $container->setParameter('version', 5); // coverall.yml = 2
        $container->setParameter('name', 'cached.di'); // coverall.yml = di

        $container->loadFromCache();

        $this->assertEquals(5, $container->getParameter('version'));
        $this->assertEquals('cached.di', $container->getParameter('name'));
        $this->assertEquals('splot.lib.cached.di.lib', $container->getParameter('full_name'));
        $this->assertEquals('splot', $container->getParameter(('vendor')));

        $this->assertInstanceOf('Splot\DependencyInjection\Tests\TestFixtures\SimpleService', $container->get('simple_service'));
    }

    public function testStoringTheCacheAfterResolvingServices() {
        $container = new CachedContainer(new ContainerCache());
        $container->loadFromFile(__DIR__ .'/fixtures/coverall.yml');

        $extendedService = $container->get('extended_service');
        $calledService = $container->get('called_service');

        // just make sure this doesn't throw any exception / error
        $container->cacheCurrentState();
    }

    public function testStoringAndReadingTheCacheAfterResolvingServices() {
        $container = new CachedContainer(new ContainerCache());
        $container->loadFromFile(__DIR__ .'/fixtures/coverall.yml');

        $extendedService = $container->get('extended_service');
        $calledService = $container->get('called_service');

        $this->assertSame($extendedService, $container->get('extended_service'));

        $container->cacheCurrentState();
        $container->loadFromCache();

        $this->assertSame($extendedService, $container->get('extended_service'));
        $this->assertSame($calledService, $container->get('called_service'));
    }

    /**
     * @expectedException \Splot\DependencyInjection\Exceptions\NotCacheableException
     */
    public function testCachingContainerWithClosureService() {
        $container = new CachedContainer(new ContainerCache());
        $container->set('cache', function($c) {
            return new ContainerCache();
        });

        $container->cacheCurrentState();
    }

    /**
     * @expectedException \Splot\DependencyInjection\Exceptions\NotCacheableException
     */
    public function testCachingContainerWithObjectService() {
        $container = new CachedContainer(new ContainerCache());
        $container->set('standard', new \stdClass());
        $container->cacheCurrentState();
    }

    public function testContainerServiceAfterReadingFromCache() {
        $this->assertSame($this->container, $this->container->get('container'));
    }

    public function testNotificationsOnServicesThatHaveBeenConfiguredOutsideOfCache() {
        $cache = new ContainerCache();

        $originalContainer = new CachedContainer($cache);
        $originalContainer->loadFromFile(__DIR__ .'/fixtures/coverall.yml');
        $originalContainer->cacheCurrentState();

        $container = new CachedContainer($cache);

        $container->register('collection_service', 'Splot\DependencyInjection\Tests\TestFixtures\CollectionService');
        $collection = $container->get('collection_service');
        $this->assertCount(0, $collection->getServices());

        // this should automatically deliver any possible notifications
        $container->loadFromCache();

        $this->assertCount(6, $collection->getServices());
    }

}
