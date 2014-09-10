<?php
namespace Splot\DependencyInjection\Tests;

use Splot\DependencyInjection\Container;

use Splot\DependencyInjection\Tests\TestFixtures\CalledService;
use Splot\DependencyInjection\Tests\TestFixtures\CollectionService;
use Splot\DependencyInjection\Tests\TestFixtures\ExtendedService;
use Splot\DependencyInjection\Tests\TestFixtures\ParametrizedService;
use Splot\DependencyInjection\Tests\TestFixtures\NamedFactory;
use Splot\DependencyInjection\Tests\TestFixtures\NamedProduct;
use Splot\DependencyInjection\Tests\TestFixtures\SimpleService;
use Splot\DependencyInjection\Tests\TestFixtures\SimpleFactory;

class CoverallTest extends \PHPUnit_Framework_TestCase
{

    protected $container;

    private $simpleServiceClass = 'Splot\DependencyInjection\Tests\TestFixtures\SimpleService';
    private $extendedServiceClass = 'Splot\DependencyInjection\Tests\TestFixtures\ExtendedService';
    private $parametrizedServiceClass = 'Splot\DependencyInjection\Tests\TestFixtures\ParametrizedService';
    private $namedProductClass = 'Splot\DependencyInjection\Tests\TestFixtures\NamedProduct';

    public function setUp() {
        $this->container = new Container();
        $this->container->loadFromFile(__DIR__ .'/fixtures/coverall.yml');
    }

    public function testParametersDefinition() {
        // validate parameters
        $this->assertEquals(array(
            'debug' => true,
            'debug.relative' => true,
            'name' => 'di',
            'name.prefixed' => 'lib.di',
            'vendor' => 'splot',
            'full_name' => 'splot.lib.di.lib',
            'version' => 2,
            'authors' => array(
                'Michał Dudek',
                'John Doe',
                'di Salvatore'
            ),
            'authors.compact' => array(
                'Michał Dudek',
                'John Doe',
                'di Salvatore'
            ),
            'simple_service.class' => 'Splot\DependencyInjection\Tests\TestFixtures\SimpleService',
            'parametrized_service.class' => 'Splot\DependencyInjection\Tests\TestFixtures\ParametrizedService',
            'called_service.class' => 'Splot\DependencyInjection\Tests\TestFixtures\CalledService',
            'extended_service.class' => 'Splot\DependencyInjection\Tests\TestFixtures\ExtendedService',
            'collection_service.class' => 'Splot\DependencyInjection\Tests\TestFixtures\CollectionService',
            'simple_factory.class' => 'Splot\DependencyInjection\Tests\TestFixtures\SimpleFactory',
            'named_factory.class' => 'Splot\DependencyInjection\Tests\TestFixtures\NamedFactory',
            'named_factory.product.class' => 'Splot\DependencyInjection\Tests\TestFixtures\NamedProduct'
        ), $this->container->dumpParameters());
    }

    public function testSimpleService() {
        $this->assertInstanceOf($this->simpleServiceClass, $this->container->get('simple_service'));
        $this->assertInstanceOf($this->simpleServiceClass, $this->container->get('simple_service.full'));
        $this->assertInstanceOf($this->simpleServiceClass, $this->container->get('simple_service.dynamic'));
        $this->assertInstanceOf($this->simpleServiceClass, $this->container->get('simple_service.dynamic.full'));
    }

    public function testParametrizedService() {
        // parametrized via constructor injector
        // test both full and compact arguments
        foreach(array(
            'parametrized_service',
            'parametrized_service.compact'
        ) as $name) {
            $parametrizedService = $this->container->get($name);
            $this->assertInstanceOf($this->parametrizedServiceClass, $parametrizedService);
            $this->assertSame($this->container->get('simple_service'), $parametrizedService->simple);
            $this->assertEquals('di.parametrized', $parametrizedService->name);
            $this->assertEquals(2, $parametrizedService->version);
            $this->assertEquals(true, $parametrizedService->debug);
            $this->assertNull($parametrizedService->not_existent);
        }
    }

    public function testCalledService() {
        $calledService = $this->container->get('called_service');
        $simpleService = $this->container->get('simple_service');
        $this->assertTrue($calledService instanceof CalledService);
        $this->assertEquals('di.overwritten', $calledService->getName());
        $this->assertEquals(3, $calledService->getVersion());
        $this->assertSame($simpleService, $calledService->getSimple());
        $this->assertNull($calledService->getOptionallySimple());
    }

    public function testExtendedService() {
        $extendedService = $this->container->get('extended_service');

        $this->assertInstanceOf($this->extendedServiceClass, $extendedService);
        $this->assertNotSame($this->container->get('called_service'), $extendedService);

        $this->assertEquals('di.overwritten', $extendedService->getName());
        $this->assertEquals('extended', $extendedService->getSubname());
        $this->assertEquals(3, $extendedService->getVersion());
        $this->assertSame($this->container->get('simple_service'), $extendedService->getOptionallySimple());
        $this->assertTrue($extendedService->getExtended());
    }

    public function testAliasedService() {
        $this->markTestIncomplete();
        
        $this->assertSame($this->container->get('aliased_service'), $this->container->get('aliased_service.alias'));

        $multiAliasService = $this->container->get('aliased_service.multi');
        $this->assertSame($multiAliasService, $this->container->get('aliased_service.multi.one'));
        $this->assertSame($multiAliasService, $this->container->get('aliased_service.multi.two'));
        $this->assertSame($multiAliasService, $this->container->get('aliased_service.multi.three'));

        $this->assertSame($this->container->get('aliased_service.link'), $this->container->get('simple_service'));
    }

    public function testNotSingleton() {
        $this->assertNotSame($this->container->get('simple_service.not_singleton'), $this->container->get('simple_service.not_singleton'));
    }

    /**
     * @expectedException \Splot\DependencyInjection\Exceptions\ReadOnlyException
     */
    public function testReadOnlyService() {
        $this->container->register('simple_service.read_only', 'Splot\DependencyInjection\Tests\TestFixtures\SimpleService');
    }

    /**
     * @expectedException \Splot\DependencyInjection\Exceptions\AbstractServiceException
     */
    public function testAbstractService() {
        $this->container->get('simple_service.abstract');
    }

    /**
     * @expectedException \Splot\DependencyInjection\Exceptions\PrivateServiceException
     */
    public function testPrivateService() {
        $this->markTestIncomplete();
        
        $this->container->get('simple_service.private');
    }

    public function testPrivateDependency() {
        $this->markTestIncomplete();
        
        $service = $this->container->get('parametrized_service.private_dependency');
        $this->assertTrue($service instanceof ParametrizedService);
        $this->assertTrue($service->simple instanceof SimpleService);
    }

    public function testCollectionService() {
        $this->markTestIncomplete();
        
        $service = $this->get('collection_service');
        $this->assertTrue($service instanceof CollectionService);
        $collection = $service->getServices();
        $this->assertCount(4, $collection);
        foreach(array(
            'item_one',
            'item_one.alias',
            'item_two',
            'factory_product'
        ) as $name) {
            $this->assertArrayHasKey($name, $collection);
            $this->assertTrue($collection[$name] instanceof SimpleService);
        }
    }

    /**
     * @expectedException \Splot\DependencyInjection\Exceptions\PrivateServiceException
     */
    public function testCollectionPrivateService() {
        $this->markTestIncomplete();
        
        $this->container->get('collection_service.item_two');
    }

    public function testSimpleFactoryService() {
        $this->assertTrue($this->container->get('simple_factory') instanceof SimpleFactory);
        $this->assertTrue($this->container->get('simple_factory.product.one') instanceof SimpleService);
        $this->assertTrue($this->container->get('simple_factory.product.two') instanceof SimpleService);
        $this->assertTrue($this->container->get('simple_factory.product.three') instanceof SimpleService);
    }

    public function testFactorySingleton() {
        $this->assertSame($this->container->get('simple_factory.product.two'), $this->container->get('simple_factory.product.two'));
    }

    public function testFactoryNotSingleton() {
        $this->assertNotSame($this->container->get('simple_factory.product.not_singleton'), $this->container->get('simple_factory.product.not_singleton'));
    }

    public function testVerboseFactory() {
        $service = $this->container->get('named_factory.verbose_product');
        $this->assertInstanceOf($this->namedProductClass, $service);
        $this->assertEquals('verbose', $service->getName());
    }

    public function testCompactFactory() {
        $service = $this->container->get('named_factory.product.compact');
        $this->assertInstanceOf($this->namedProductClass, $service);
        $this->assertEquals('compact', $service->getName());
    }

}
