<?php
namespace Splot\DependencyInjection\Tests;

use Splot\DependencyInjection\Container;

use Splot\DependencyInjection\Tests\TestFixtures\ArgumentedService;
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
    private $argumentedServiceClass = 'Splot\DependencyInjection\Tests\TestFixtures\ArgumentedService';
    private $parametrizedServiceClass = 'Splot\DependencyInjection\Tests\TestFixtures\ParametrizedService';
    private $calledServiceClass = 'Splot\DependencyInjection\Tests\TestFixtures\CalledService';

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

        $this->assertInstanceOf($this->simpleServiceClass, $container->get('simple.closure'));
    }

    public function testRegisterByClass() {
        $container = new Container();
        $container->register('simple', $this->simpleServiceClass);
        $this->assertInstanceOf($this->simpleServiceClass, $container->get('simple'));
    }

    public function testRegisteringService() {
        $container = new Container();
        $container->register('simple', array(
            'class' => 'Splot\DependencyInjection\Tests\TestFixtures\SimpleService'
        ));
        $this->assertInstanceOf($this->simpleServiceClass, $container->get('simple'));
    }

    public function testRegisterByClassParameter() {
        $container = new Container();
        $container->setParameter('simple.class', $this->simpleServiceClass);
        $container->register('simple', '%simple.class%');
        $this->assertInstanceOf($this->simpleServiceClass, $container->get('simple'));
    }

    public function testRegisteringServiceByParameter() {
        $container = new Container();
        $container->setParameter('simple.class', $this->simpleServiceClass);
        $container->register('simple', array(
            'class' => '%simple.class%'
        ));
        $this->assertInstanceOf($this->simpleServiceClass, $container->get('simple'));
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

    public function testRegisteringWithConstructorInjection() {
        $container = new Container();
        $container->register('argumented', array(
            'class' => $this->argumentedServiceClass,
            'arguments' => array(
                'splot',
                2,
                'alpha'
            )
        ));
        $argumented = $container->get('argumented');
        $this->assertInstanceOf($this->argumentedServiceClass, $argumented);
        $this->assertEquals('splot', $argumented->name);
        $this->assertEquals(2, $argumented->version);
        $this->assertEquals('alpha', $argumented->stability);
    }

    public function testRegisteringWithParametersInConstructorInjection() {
        $container = new Container();
        $container->setParameter('name', 'splot');
        $container->setParameter('version', 3);
        $container->setParameter('stability', 'unstable');
        $container->register('argumented.parameters', array(
            'class' => $this->argumentedServiceClass,
            'arguments' => array(
                '%name%.argumented',
                '%version%',
                '%stability%'
            )
        ));

        $argumented = $container->get('argumented.parameters');
        $this->assertInstanceOf($this->argumentedServiceClass, $argumented);
        $this->assertEquals('splot.argumented', $argumented->name);
        $this->assertEquals(3, $argumented->version);
        $this->assertEquals('unstable', $argumented->stability);
    }

    public function testRegisteringWithServiceAndParametersConstructorInjection() {
        $container = new Container();
        $container->setParameter('name', 'splot');
        $container->setParameter('version', 1);
        $container->setParameter('debug', true);
        $container->register('simple', $this->simpleServiceClass);
        $container->register('parametrized_service', array(
            'class' => $this->parametrizedServiceClass,
            'arguments' => array(
                '@simple',
                '%name%.parametrized',
                '%version%',
                '%debug%'
            )
        ));

        $parametrized = $container->get('parametrized_service');
        $this->assertInstanceOf($this->parametrizedServiceClass, $parametrized);
        $this->assertInstanceOf($this->simpleServiceClass, $parametrized->simple);
        $this->assertSame($parametrized->simple, $container->get('simple'));
        $this->assertEquals('splot.parametrized', $parametrized->name);
        $this->assertEquals(1, $parametrized->version);
        $this->assertEquals(true, $parametrized->debug);
    }

    public function testRegisteringWithOptionalUndefinedServiceConstructorInjection() {
        $container = new Container();
        $container->setParameter('name', 'splot');
        $container->setParameter('version', 4);
        $container->setParameter('debug', true);
        $container->register('simple', $this->simpleServiceClass);
        $container->register('parametrized_service', array(
            'class' => $this->parametrizedServiceClass,
            'arguments' => array(
                '@simple',
                '%name%.parametrized_optional',
                '%version%',
                '%debug%',
                '@simple_service.undefined?'
            )
        ));

        $this->assertNull($container->get('parametrized_service')->not_existent);
    }

    public function testRegisteringWithOptionalDefinedServiceConstructorInjection() {
        $container = new Container();
        $container->setParameter('name', 'splot');
        $container->setParameter('version', 4);
        $container->setParameter('debug', true);
        $container->register('simple', $this->simpleServiceClass);
        $container->register('simple_service.defined', $this->simpleServiceClass);
        $container->register('parametrized_service', array(
            'class' => $this->parametrizedServiceClass,
            'arguments' => array(
                '@simple',
                '%name%.parametrized_optional',
                '%version%',
                '%debug%',
                '@simple_service.defined?'
            )
        ));

        $parametrized = $container->get('parametrized_service');
        $this->assertNotNull($parametrized->not_existent);
        $this->assertInstanceOf($this->simpleServiceClass, $parametrized->not_existent);
    }

    public function testRegisteringWithConstructorInjectionAndNotSingleton() {
        $container = new Container();
        $container->setParameter('name', 'splot');
        $container->setParameter('version', 4);
        $container->setParameter('debug', true);
        $container->register('simple', $this->simpleServiceClass);
        $container->register('parametrized_service', array(
            'class' => $this->parametrizedServiceClass,
            'arguments' => array(
                '@simple',
                '%name%',
                '%version%',
                '%debug%',
                '@simple_service.defined?'
            ),
            'singleton' => false
        ));

        $parametrizedOne = $container->get('parametrized_service');

        // alter parameters and define undefined service
        $container->setParameter('name', 'split');
        $container->setParameter('version', 5);
        $container->register('simple_service.defined', $this->simpleServiceClass);
        
        $parametrizedTwo = $container->get('parametrized_service');

        $this->assertNotSame($parametrizedOne, $parametrizedTwo);

        $this->assertInstanceOf($this->simpleServiceClass, $parametrizedOne->simple);
        $this->assertInstanceOf($this->simpleServiceClass, $parametrizedTwo->simple);
        $this->assertSame($parametrizedOne->simple, $parametrizedTwo->simple);
        $this->assertEquals('splot', $parametrizedOne->name);
        $this->assertEquals('split', $parametrizedTwo->name);
        $this->assertEquals(4, $parametrizedOne->version);
        $this->assertEquals(5, $parametrizedTwo->version);
        $this->assertNull($parametrizedOne->not_existent);
        $this->assertNotNull($parametrizedTwo->not_existent);
    }

    public function testRegisteringWithSetterInjection() {
        $container = new Container();
        $container->register('called', array(
            'class' => $this->calledServiceClass,
            'arguments' => array('splot', 2),
            'call' => array(
                array('setName', array('splot.called')),
                array('setVersion', array(3))
            )
        ));

        $calledService = $container->get('called');
        $this->assertEquals('splot.called', $calledService->getName());
        $this->assertEquals(3, $calledService->getVersion());
    }

    public function testRegisteringWithParametersInSetterInjection() {
        $container = new Container();
        $container->setParameter('name', 'di');
        $container->setParameter('version', 4);
        $container->register('called', array(
            'class' => $this->calledServiceClass,
            'arguments' => array('splot', 2),
            'call' => array(
                array('setName', array('%name%.overwritten')),
                array('setVersion', array('%version%'))
            )
        ));

        $calledService = $container->get('called');
        $this->assertEquals('di.overwritten', $calledService->getName());
        $this->assertEquals(4, $calledService->getVersion());
    }

    public function testRegisteringWithServiceAndParametersSetterInjection() {
        $container = new Container();
        $container->setParameter('name', 'called_simple');
        $container->setParameter('version', 5);
        $container->register('simple', $this->simpleServiceClass);
        $container->register('called', array(
            'class' => $this->calledServiceClass,
            'arguments' => array('splot', 2),
            'call' => array(
                array('setName', array('%name%')),
                array('setVersion', array('%version%')),
                array('setSimple', array('@simple'))
            )
        ));

        $calledService = $container->get('called');
        $this->assertEquals('called_simple', $calledService->getName());
        $this->assertEquals(5, $calledService->getVersion());
        $this->assertInstanceOf($this->simpleServiceClass, $calledService->getSimple());
    }

    public function testRegisteringWithOptionalUndefinedServiceInSetterInjection() {
        $container = new Container();
        $container->register('simple', $this->simpleServiceClass);
        $container->register('called', array(
            'class' => $this->calledServiceClass,
            'arguments' => array('splot', 2),
            'call' => array(
                array('setSimple', array('@simple', '@simple_nope?'))
            )
        ));

        $calledService = $container->get('called');
        $this->assertInstanceOf($this->simpleServiceClass, $calledService->getSimple());
        $this->assertNull($calledService->getOptionallySimple());
    }

    public function testRegisteringWithOptionalDefinedServiceInSetterInjection() {
        $container = new Container();
        $container->register('simple', $this->simpleServiceClass);
        $container->register('simple_nope', $this->simpleServiceClass);
        $container->register('called', array(
            'class' => $this->calledServiceClass,
            'arguments' => array('splot', 2),
            'call' => array(
                array('setSimple', array('@simple', '@simple_nope?'))
            )
        ));

        $calledService = $container->get('called');
        $this->assertInstanceOf($this->simpleServiceClass, $calledService->getSimple());
        $this->assertInstanceOf($this->simpleServiceClass, $calledService->getOptionallySimple());
    }

    public function testRegisteringWithSetterInjectionAndNotSingletion() {
        $container = new Container();
        $container->setParameter('name', 'setter');
        $container->setParameter('version', 1);
        $container->register('simple', $this->simpleServiceClass);
        $container->register('called', array(
            'class' => $this->calledServiceClass,
            'arguments' => array('splot', 2),
            'call' => array(
                array('setName', array('%name%')),
                array('setVersion', array('%version%')),
                array('setSimple', array('@simple', '@simple_nope?'))
            ),
            'singleton' => false
        ));

        $calledOne = $container->get('called');

        // alter the container a bit
        $container->setParameter('name', 'di');
        $container->setParameter('version', 3);
        $container->register('simple_nope', $this->simpleServiceClass);

        $calledTwo = $container->get('called');

        $this->assertNotSame($calledOne, $calledTwo);
        $this->assertInstanceOf($this->calledServiceClass, $calledOne);
        $this->assertInstanceOf($this->calledServiceClass, $calledTwo);
        $this->assertEquals('setter', $calledOne->getName());
        $this->assertEquals('di', $calledTwo->getName());
        $this->assertEquals(1, $calledOne->getVersion());
        $this->assertEquals(3, $calledTwo->getVersion());
        $this->assertInstanceOf($this->simpleServiceClass, $calledOne->getSimple());
        $this->assertInstanceOf($this->simpleServiceClass, $calledTwo->getSimple());
        $this->assertSame($calledOne->getSimple(), $calledTwo->getSimple());
        $this->assertNull($calledOne->getOptionallySimple());
        $this->assertNotNull($calledTwo->getOptionallySimple());
    }

    public function testClosureServiceWithSetterInjection() {
        $container = new Container();
        $container->set('called', function($c) {
            return new CalledService('splot', 2);
        }, array(
            'call' => array(
                array('setName', array('di')),
                array('setVersion', array(3))
            ),
        ));

        $calledService = $container->get('called');
        $this->assertEquals('di', $calledService->getName());
        $this->assertEquals(3, $calledService->getVersion());
    }

    public function testObjectServiceWithSetterInjection() {
        $container = new Container();
        $called = new CalledService('splot', 2);
        $container->set('called', $called, array(
            'call' => array(
                array('setName', array('di')),
                array('setVersion', array(3))
            ),
        ));

        $calledService = $container->get('called');
        $this->assertSame($called, $calledService);
        $this->assertEquals('di', $calledService->getName());
        $this->assertEquals(3, $calledService->getVersion());
    }

    public function testSetterInjectionInvalidArgumentsFormat() {
        $container = new Container();
        $container->register('called', array(
            'class' => $this->calledServiceClass,
            'arguments' => array('splot', 2),
            'call' => array(
                array('setName', 'di') // make argument 2 an array if not an array
            )
        ));

        $calledService = $container->get('called');
        $this->assertEquals('di', $calledService->getName());
    }

    /**
     * @expectedException \Splot\DependencyInjection\Exceptions\CircularReferenceException
     */
    public function testDetectingCircularReference() {
        $container = new Container();
        $container->register('argumented', array(
            'class' => $this->argumentedServiceClass,
            'arguments' => array(
                '@argumented.sub1',
                '@argumented.sub2'
            )
        ));
        $container->register('argumented.sub1', array(
            'class' => $this->argumentedServiceClass,
            'arguments' => array(
                '@argumented.sub1.1',
                '@argumented.sub2'
            )
        ));

        $container->register('argumented.sub2', array(
            'class' => $this->argumentedServiceClass,
            'arguments' => array(
                '@argumented.sub1.1'
            )
        ));

        $container->register('argumented.sub1.1', array(
            'class' => $this->argumentedServiceClass,
            'arguments' => array(
                '@argumented'
            )
        ));

        $container->get('argumented');
    }

    /**
     * @expectedException \Splot\DependencyInjection\Exceptions\CircularReferenceException
     */
    public function testDetectingCircularReferenceInClosures() {
        $container = new Container();
        $container->set('argumented', function($c) {
            return new ArgumentedService($c->get('argumented.sub1'), $c->get('argumented.sub2'));
        });
        $container->set('argumented.sub1', function($c) {
            return new ArgumentedService($c->get('argumented.sub1.1'), $c->get('argumented.sub2'));
        });
        $container->set('argumented.sub2', function($c) {
            return new ArgumentedService($c->get('argumented.sub1.1'));
        });
        $container->set('argumented.sub1.1', function($c) {
            return new ArgumentedService($c->get('argumented'));
        });

        $container->get('argumented');
    }

    /**
     * @expectedException \Splot\DependencyInjection\Exceptions\CircularReferenceException
     */
    public function testCircularReferenceInSetterInjection() {
        $container = new Container();
        $container->register('setter', array(
            'class' => $this->calledServiceClass,
            'arguments' => array('splot', 0),
            'call' => array(
                array('setCalledService', array('@setter.sub1'))
            )
        ));
        $container->register('setter.sub1', array(
            'class' => $this->calledServiceClass,
            'arguments' => array('splot', 1),
            'call' => array(
                array('setCalledService', array('@setter.sub2'))
            )
        ));

        $container->register('setter.sub2', array(
            'class' => $this->calledServiceClass,
            'arguments' => array('splot', 2),
            'call' => array(
                array('setCalledService', array('@setter'))
            )
        ));

        $container->get('setter');
    }

}
