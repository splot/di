<?php
namespace Splot\DependencyInjection\Tests;

use Splot\DependencyInjection\Container;

use Splot\DependencyInjection\Tests\TestFixtures\CalledService;
use Splot\DependencyInjection\Tests\TestFixtures\ExtendedService;
use Splot\DependencyInjection\Tests\TestFixtures\ParametrizedService;
use Splot\DependencyInjection\Tests\TestFixtures\SimpleService;

/**
 * @coversDefaultClass Splot\DependencyInjection\Container
 */
class ContainerTest extends \PHPUnit_Framework_TestCase
{

    private $simpleServiceClass = 'Splot\DependencyInjection\Tests\TestFixtures\SimpleService';

    public function testSettingInstanceService() {
        $container = new Container();
        $service = new SimpleService();
        $container->set('simple', $service);

        $this->assertSame($service, $container->get('simple'));
    }

    public function testSettingServiceByClosure() {
        $container = new Container();
        $container->set('simple.closure', function($c) {
            return new SimpleService();
        });

        $this->assertTrue($container->get('simple.closure') instanceof SimpleService);
    }

    public function testRegisterByClass() {
        $container = new Container();
        $container->register('simple', $this->simpleServiceClass);
        $this->assertTrue($container->get('simple') instanceof SimpleService);
    }

    public function testRegisteringService() {
        $container = new Container();
        $container->register('simple', array(
            'class' => 'Splot\DependencyInjection\Tests\TestFixtures\SimpleService'
        ));
        $this->assertTrue($container->get('simple') instanceof SimpleService);
    }

    public function testRegisterByClassParameter() {
        $container = new Container();
        $container->setParameter('simple.class', $this->simpleServiceClass);
        $container->register('simple', '%simple.class%');
        $this->assertTrue($container->get('simple') instanceof SimpleService);
    }

    public function testRegisteringServiceByParameter() {
        $container = new Container();
        $container->setParameter('simple.class', $this->simpleServiceClass);
        $container->register('simple', array(
            'class' => '%simple.class%'
        ));
        $this->assertTrue($container->get('simple') instanceof SimpleService);
    }

    public function testRegisteringSingletonService() {
        $container = new Container();
        $container->register('simple', $this->simpleServiceClass);
        $this->assertSame($container->get('simple'), $container->get('simple'));
    }

    public function testRegisteringNotSingletonService() {
        $container = new Container();
        $container->register('simple', array(
            'class' => $this->simpleServiceClass,
            'singleton' => false
        ));
        $this->assertNotSame($container->get('simple'), $container->get('simple'));
    }

    public function testOverwritingServiceDefinition() {
        $container = new Container();
        $simple = new SimpleService();
        $container->set('simple', $simple);
        // overwrite
        $container->set('simple', $this->simpleServiceClass);
        $this->assertNotSame($simple, $container->get('simple'));
    }

    public function testGettingItself() {
        $container = new Container();
        $this->assertSame($container, $container->get('container'));
    }

    /**
     * @expectedException \Splot\DependencyInjection\Exceptions\ReadOnlyException
     */
    public function testOverwritingItself() {
        $container = new Container();
        $container->set('container', $this->simpleServiceClass);
    }

    /**
     * @expectedException \Splot\DependencyInjection\Exceptions\ReadOnlyException
     */
    public function testReadOnlyServiceDefinition() {
        $container = new Container();
        $simple = new SimpleService();
        $container->set('simple', $simple, array(
            'read_only' => true
        ));
        // overwrite
        $container->set('simple', $this->simpleServiceClass);
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\NotFoundException
     */
    public function testLoadingFromInexistentFile() {
        $container = new Container();
        $container->loadFromFile(__DIR__ .'/fixtures/parameters.inexistent.yml');
    }

    /*
    public function testRegisteringServiceWithConstructorInjection() {
        $container = new Container();
        $container->register('parametrized_service', array(
            'class' => 'Splot\DependencyInjection\Tests\TestFixtures\ParametrizedService',
            'arguments' => array(
                '@simple',
                '%name%.parametrized',
                '%version',
                '%debug',
                '@simple_service.undefined?'
            )
        ));
    }
    */

}
