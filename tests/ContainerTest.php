<?php
namespace Splot\DependencyInjection\Tests;

use Splot\DependencyInjection\Container;

use Splot\DependencyInjection\Tests\TestFixtures\ArgumentedService;
use Splot\DependencyInjection\Tests\TestFixtures\CalledService;
use Splot\DependencyInjection\Tests\TestFixtures\ExtendedService;
use Splot\DependencyInjection\Tests\TestFixtures\ParametrizedService;
use Splot\DependencyInjection\Tests\TestFixtures\SimpleFactory;
use Splot\DependencyInjection\Tests\TestFixtures\SimpleService;

/**
 * @coversDefaultClass Splot\DependencyInjection\Container
 */
class ContainerTest extends \PHPUnit_Framework_TestCase
{

    private $simpleServiceClass = 'Splot\DependencyInjection\Tests\TestFixtures\SimpleService';
    private $abstractServiceClass = 'Splot\DependencyInjection\Tests\TestFixtures\AbstractService';
    private $argumentedServiceClass = 'Splot\DependencyInjection\Tests\TestFixtures\ArgumentedService';
    private $parametrizedServiceClass = 'Splot\DependencyInjection\Tests\TestFixtures\ParametrizedService';
    private $calledServiceClass = 'Splot\DependencyInjection\Tests\TestFixtures\CalledService';
    private $extendedServiceClass = 'Splot\DependencyInjection\Tests\TestFixtures\ExtendedService';
    private $simpleFactoryClass = 'Splot\DependencyInjection\Tests\TestFixtures\SimpleFactory';
    private $namedFactoryClass = 'Splot\DependencyInjection\Tests\TestFixtures\NamedFactory';
    private $namedProductClass = 'Splot\DependencyInjection\Tests\TestFixtures\NamedProduct';
    private $collectionServiceClass = 'Splot\DependencyInjection\Tests\TestFixtures\CollectionService';

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

    /**
     * @expectedException \Splot\DependencyInjection\Exceptions\ServiceNotFoundException
     */
    public function testGettingUnregisteredService() {
        $container = new Container();
        $container->get('undefined');
    }

    /**
     * @expectedException \Splot\DependencyInjection\Exceptions\InvalidServiceException
     */
    public function testRegisteringServiceWithoutClassName() {
        $container = new Container();
        $container->register('simple', array(
            'arguments' => array('nope')
        ));
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

    /**
     * @expectedException \Splot\DependencyInjection\Exceptions\InvalidServiceException
     */
    public function testRegisteringServiceByUndefinedParameter() {
        $container = new Container();
        $container->register('simple', '%simple.class%');
        $container->get('simple');
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
        $container->register('simple', $this->simpleServiceClass);
        $this->assertNotSame($simple, $container->get('simple'));
    }

    public function testGettingItself() {
        $container = new Container();
        $this->assertSame($container, $container->get('container'));
    }

    public function testGettingItselfByAliases() {
        $container = new Container();
        $this->assertSame($container, $container->get('service_container'));
        $this->assertSame($container, $container->get('services_container'));
        $this->assertSame($container, $container->get('di_container'));
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

    public function testLoadingTwiceFromFile() {
        $container = new Container();
        $success = $container->loadFromFile(__DIR__ .'/fixtures/parameters.yml');
        $this->assertTrue($success);

        $success2 = $container->loadFromFile(__DIR__ .'/fixtures/parameters.yml');
        $this->assertTrue($success2);
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\InvalidFileException
     */
    public function testLoadingUnsupportedFile() {
        $container = new Container();
        $container->loadFromFile(__DIR__ .'/fixtures/parameters.ini');
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

    /**
     * @expectedException \Splot\DependencyInjection\Exceptions\InvalidServiceException
     */
    public function testRegisteringInvalidSetterInjectionDefinition() {
        $container = new Container();
        $container->setParameter('name', 'di');
        $container->setParameter('version', 4);
        $container->register('called', array(
            'class' => $this->calledServiceClass,
            'arguments' => array('splot', 2),
            'call' => array(
                array('method' => 'setName', 'arguments' => array('%name%.overwritten')),
                array('method' => 'setVersion', 'arguments' => array('%version%'))
            )
        ));
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

    /**
     * @expectedException \Splot\DependencyInjection\Exceptions\InvalidServiceException
     */
    public function testRegisteringWithUndefinedServiceInSetterInjection() {
        $container = new Container();
        $container->register('simple', $this->simpleServiceClass);
        $container->register('called', array(
            'class' => $this->calledServiceClass,
            'arguments' => array('splot', 2),
            'call' => array(
                array('setSimple', array('@simple', '@simple_nope'))
            )
        ));

        $calledService = $container->get('called');
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

    public function testRegisteringWithAliases() {
        $container = new Container();
        $container->register('simple', array(
            'class' => $this->simpleServiceClass,
            'aliases' => array(
                'simple_service',
                'simplicity',
                'justit'
            )
        ));

        $simple = $container->get('simple');
        $this->assertSame($simple, $container->get('simple_service'));
        $this->assertSame($simple, $container->get('simplicity'));
        $this->assertSame($simple, $container->get('justit'));
    }

    public function testRegisteringWithOneAlias() {
        $container = new Container();
        $container->register('simple', array(
            'class' => $this->simpleServiceClass,
            'aliases' => 'simple_service'
        ));

        $this->assertSame($container->get('simple'), $container->get('simple_service'));
    }

    public function testRegisteringAliasService() {
        $container = new Container();
        $container->register('simple', $this->simpleServiceClass);
        $container->register('simple_service', array(
            'alias' => 'simple'
        ));

        $this->assertSame($container->get('simple'), $container->get('simple_service'));
    }

    /**
     * @expectedException \Splot\DependencyInjection\Exceptions\PrivateServiceException
     */
    public function testRegisteringPrivateService() {
        $container = new Container();
        $container->register('simple', array(
            'class' => $this->simpleServiceClass,
            'private' => true
        ));

        $container->get('simple');
    }

    public function testRegisteringServiceWithPrivateDependency() {
        $container = new Container();
        $container->register('simple.private', array(
            'class' => $this->simpleServiceClass,
            'private' => true
        ));
        $container->register('parametrized', array(
            'class' => $this->parametrizedServiceClass,
            'arguments' => array(
                '@simple.private',
                'public',
                1,
                true
            )
        ));

        $this->assertInstanceOf($this->parametrizedServiceClass, $container->get('parametrized'));
    }

    /**
     * @expectedException \Splot\DependencyInjection\Exceptions\InvalidServiceException
     */
    public function testRegisteringInvalidNotification() {
        $container = new Container();
        $container->register('simple', array(
            'class' => $this->simpleServiceClass,
            'notify' => array(
                array()
            )
        ));
    }

    /**
     * @expectedException \Splot\DependencyInjection\Exceptions\InvalidServiceException
     */
    public function testRegisteringInvalidNotificationMethodName() {
        $container = new Container();
        $container->register('simple', array(
            'class' => $this->simpleServiceClass,
            'notify' => array(
                array('container')
            )
        ));
    }

    /**
     * @expectedException \Splot\DependencyInjection\Exceptions\InvalidServiceException
     */
    public function testOverwritingByAlias() {
        $container = new Container();
        $container->register('simple', array(
            'class' => $this->simpleServiceClass,
            'aliases' => array('container')
        ));
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

    public function testExtendingService() {
        $container = new Container();
        $container->register('simple', $this->simpleServiceClass);
        $container->register('called', array(
            'class' => $this->calledServiceClass,
            'arguments' => array('splot', 2),
            'call' => array(
                array('setName', array('splot.overwritten')),
                array('setVersion', 3)
            )
        ));
        $container->register('extended', array(
            'class' => $this->extendedServiceClass,
            'extends' => 'called',
            'arguments' => array('splot.ext', 18, 'extended'),
            'call' => array(
                array('setSimple', array('@simple')),
                array('setExtended', array(true))
            )
        ));

        $simpleService = $container->get('simple');
        $calledService = $container->get('called');
        $extendedService = $container->get('extended');
        $this->assertInstanceOf($this->extendedServiceClass, $extendedService);
        $this->assertNotSame($extendedService, $calledService);

        $this->assertEquals('splot.overwritten', $extendedService->getName());
        $this->assertEquals(3, $extendedService->getVersion());
        $this->assertSame($simpleService, $extendedService->getSimple());
        $this->assertTrue($extendedService->getExtended());
    }

    public function testChainExtendingService() {
        $container = new Container();
        $container->register('root', array(
            'class' => $this->calledServiceClass,
            'arguments' => array('root', 1)
        ));
        $container->register('first', array(
            'extends' => 'root'
        ));
        $container->register('second', array(
            'extends' => 'first',
            'arguments' => array('second', 2)
        ));
        $container->register('third', array(
            'extends' => 'second',
            'call' => array(
                array('setVersion', 3)
            )
        ));
        $container->register('fourth', array(
            'extends' => 'third',
            'call' => array(
                array('setName', 'fourth')
            )
        ));

        $root = $container->get('root');
        $this->assertInstanceOf($this->calledServiceClass, $root);

        $first = $container->get('first');
        $this->assertInstanceOf($this->calledServiceClass, $first);
        $this->assertNotSame($first, $root);

        $second = $container->get('second');
        $this->assertInstanceOf($this->calledServiceClass, $second);
        $this->assertNotSame($second, $first);
        $this->assertEquals('second', $second->getName());
        $this->assertEquals(2, $second->getVersion());

        $third = $container->get('third');
        $this->assertInstanceOf($this->calledServiceClass, $third);
        $this->assertNotSame($third, $second);
        $this->assertEquals('second', $third->getName());
        $this->assertEquals(3, $third->getVersion());

        $fourth = $container->get('fourth');
        $this->assertInstanceOf($this->calledServiceClass, $fourth);
        $this->assertNotSame($fourth, $third);
        $this->assertEquals('fourth', $fourth->getName());
        $this->assertEquals(3, $fourth->getVersion());
    }

    /**
     * @expectedException \Splot\DependencyInjection\Exceptions\InvalidServiceException
     */
    public function testExtendingObjectService() {
        $container = new Container();
        $container->set('simple', new SimpleService());
        $container->register('simple.extended', array(
            'extends' => 'simple'
        ));
        $container->get('simple.extended');
    }

    /**
     * @expectedException \Splot\DependencyInjection\Exceptions\InvalidServiceException
     */
    public function testExtendingUndefinedService() {
        $container = new Container();
        $container->register('simple.extending', array(
            'class' => $this->simpleServiceClass,
            'extends' => 'simple.undefined'
        ));
        $container->get('simple.extending');
    }

    public function testExtendingServiceBeforeRegisteringIt() {
        $container = new Container();
        $container->register('simple.extending', array(
            'class' => $this->simpleServiceClass,
            'extends' => 'simple.lazy'
        ));
        $container->register('simple.lazy', $this->simpleServiceClass);

        $extendingService = $container->get('simple.extending');
        $this->assertInstanceOf($this->simpleServiceClass, $extendingService);
    }

    public function testExtendingServiceWithoutClass() {
        $container = new Container();
        $container->register('simple', $this->simpleServiceClass);
        $container->register('called', array(
            'class' => $this->calledServiceClass,
            'arguments' => array('splot', 2),
            'call' => array(
                array('setName', array('splot.overwritten')),
                array('setVersion', 3)
            )
        ));
        $container->register('extended', array(
            'extends' => 'called',
            'call' => array(
                array('setSimple', array('@simple'))
            )
        ));

        $simpleService = $container->get('simple');
        $calledService = $container->get('called');
        $extendedService = $container->get('extended');
        $this->assertInstanceOf($this->calledServiceClass, $extendedService);
        $this->assertNotSame($calledService, $extendedService);

        $this->assertEquals('splot.overwritten', $extendedService->getName());
        $this->assertEquals(3, $extendedService->getVersion());
        $this->assertNull($calledService->getSimple());
        $this->assertSame($simpleService, $extendedService->getSimple());
    }

    /**
     * @expectedException \Splot\DependencyInjection\Exceptions\AbstractServiceException
     */
    public function testGettingAbstractService() {
        $container = new Container();
        $container->register('simple', array(
            'class' => $this->simpleServiceClass,
            'abstract' => true
        ));

        $container->get('simple');
    }

    public function testExtendingAbstractService() {
        $container = new Container();
        $container->register('simple', $this->simpleServiceClass);
        $container->register('called', array(
            'class' => $this->calledServiceClass,
            'arguments' => array('splot', 2),
            'abstract' => true,
            'call' => array(
                array('setName', array('splot.overwritten')),
                array('setVersion', 3)
            )
        ));
        $container->register('extended', array(
            'class' => $this->extendedServiceClass,
            'extends' => 'called',
            'arguments' => array('splot.ext', 18, 'extended'),
            'call' => array(
                array('setSimple', array('@simple')),
                array('setExtended', array(true))
            )
        ));

        $extendedService = $container->get('extended');
        $this->assertInstanceOf($this->extendedServiceClass, $extendedService);
    }

    public function testExtendingParametrizedService() {
        $container = new Container();
        $container->setParameter('extend_service', 'simple');
        $container->register('simple', $this->simpleServiceClass);
        $container->register('extended', array(
            'extends' => '%extend_service%'
        ));

        $this->assertInstanceOf($this->simpleServiceClass, $container->get('extended'));
    }

    public function testRegisteringFactoryService() {
        $container = new Container();
        $container->register('factory', $this->simpleFactoryClass);
        $container->register('factory.product', array(
            'factory_service' => 'factory',
            'factory_method' => 'get'
        ));

        $this->assertInstanceOf($this->simpleServiceClass, $container->get('factory.product'));
    }

    /**
     * @expectedException \Splot\DependencyInjection\Exceptions\InvalidServiceException
     */
    public function testRegisteringInvalidFactoryServiceDefinition() {
        $container = new Container();
        $container->register('factory', $this->simpleFactoryClass);
        $container->register('factory.product', array(
            'factory_service' => 'factory'
        ));
    }

    public function testRegisteringCompactFactoryService() {
        $container = new Container();
        $container->register('factory', $this->simpleFactoryClass);
        $container->register('factory.product', array(
            'factory' => array('factory', 'get')
        ));

        $this->assertInstanceOf($this->simpleServiceClass, $container->get('factory.product'));
    }

    /**
     * @expectedException \Splot\DependencyInjection\Exceptions\InvalidServiceException
     */
    public function testRegisteringInvalidCompactFactoryService() {
        $container = new Container();
        $container = new Container();
        $container->register('factory', $this->simpleFactoryClass);
        $container->register('factory.product', array(
            'factory' => array('factory')
        ));
    }

    public function testRegisteringSuperCompactFactoryService() {
        $container = new Container();
        $container->register('factory', $this->simpleFactoryClass);
        $container->register('factory.product', array('factory', 'get'));

        $this->assertInstanceOf($this->simpleServiceClass, $container->get('factory.product'));
    }

    /**
     * @expectedException \Splot\DependencyInjection\Exceptions\InvalidServiceException
     */
    public function testRegisteringAbstractFactoryService() {
        $container = new Container();
        $container->register('factory', $this->simpleFactoryClass);
        $container->register('factory.product', array(
            'factory_service' => 'factory',
            'factory_method' => 'get',
            'abstract' => true
        ));
    }

    /**
     * @expectedException \Splot\DependencyInjection\Exceptions\InvalidServiceException
     */
    public function testRegisteringExtendingFactoryService() {
        $container = new Container();
        $container->register('factory', $this->simpleFactoryClass);
        $container->register('simple', $this->simpleServiceClass);
        $container->register('factory.product', array(
            'factory_service' => 'factory',
            'factory_method' => 'get',
            'extends' => 'simple'
        ));
    }

    public function testRegisteringSingletonFactoryService() {
        $container = new Container();
        $container->register('factory', $this->simpleFactoryClass);
        $container->register('factory.product', array(
            'factory_service' => 'factory',
            'factory_method' => 'get'
        ));

        $first = $container->get('factory.product');
        $second = $container->get('factory.product');
        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame($first, $second);
    }

    public function testRegisteringNotSingletonFactoryService() {
        $container = new Container();
        $container->register('factory', $this->simpleFactoryClass);
        $container->register('factory.product', array(
            'factory_service' => 'factory',
            'factory_method' => 'get',
            'singleton' => false
        ));

        $first = $container->get('factory.product');
        $second = $container->get('factory.product');
        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertNotSame($first, $second);
    }

    public function testRegisteringFactoryServiceWithArguments() {
        $container = new Container();
        $container->register('named_factory', $this->namedFactoryClass);
        $container->register('something', array(
            'factory' => array('named_factory', 'provide', array('something'))
        ));

        $service = $container->get('something');
        $this->assertInstanceOf($this->namedProductClass, $service);
        $this->assertEquals('something', $service->getName());
    }

    public function testRegisteringFactoryServiceWithParametrizedArguments() {
        $container = new Container();
        $container->setParameter('named_factory', 'foreverandever');
        $container->register('named_factory', $this->namedFactoryClass);
        $container->register('something', array(
            'factory' => array('named_factory', 'provide', array('%named_factory%.something'))
        ));

        $service = $container->get('something');
        $this->assertInstanceOf($this->namedProductClass, $service);
        $this->assertEquals('foreverandever.something', $service->getName());
    }

    /**
     * @expectedException \Splot\DependencyInjection\Exceptions\InvalidServiceException
     */
    public function testRegisteringInvalidFactoryService() {
        $container = new Container();
        $container->register('factory', $this->abstractServiceClass);
        $container->register('factory_product', array(
            'factory_service' => 'factory',
            'factory_method' => 'provide'
        ));
        $container->get('factory_product');
    }

    public function testNotifyingAService() {
        $container = new Container();
        $container->register('collection', $this->collectionServiceClass);

        foreach(array(
            'one',
            'two',
            'three'
        ) as $name) {
            $container->register($name, array(
                'class' => $this->simpleServiceClass,
                'notify' => array(
                    array('collection', 'addService', array('@', $name))
                )
            ));
        }

        $collection = $container->get('collection');
        $this->assertCount(3, $collection->getServices());
        $this->assertArrayHasKey('one', $collection->getServices());
        $this->assertArrayHasKey('two', $collection->getServices());
        $this->assertArrayHasKey('three', $collection->getServices());

        $this->assertSame($collection->getService('one'), $container->get('one'));
        $this->assertSame($collection->getService('two'), $container->get('two'));
        $this->assertSame($collection->getService('three'), $container->get('three'));
    }

    public function testNotifyingNonExistentService() {
        $container = new Container();
        $container->register('collection', $this->collectionServiceClass);
        $container->register('one', array(
            'class' => $this->simpleServiceClass,
            'notify' => array(
                array('undefined', 'add', array('@')),
                array('collection', 'addService', array('@', 'one'))
            )
        ));

        $collection = $container->get('collection');
        $this->assertCount(1, $collection->getServices());
    }

    public function testNotifyingServiceRegisteredLater() {
        $container = new Container();
        $container->register('one', array(
            'class' => $this->simpleServiceClass,
            'notify' => array(
                array('lazy_collection', 'addService', array('@', 'one'))
            )
        ));

        $oneService = $container->get('one');
        $this->assertInstanceOf($this->simpleServiceClass, $oneService);

        $container->register('lazy_collection', $this->collectionServiceClass);
        $lazyCollection = $container->get('lazy_collection');
        $this->assertCount(1, $lazyCollection->getServices());
        $this->assertSame($oneService, $lazyCollection->getService('one'));
    }

    public function testNotifyExtraLazy() {
        $container = new Container();
        $container->register('collection', $this->collectionServiceClass);

        $collection = $container->get('collection');
        $this->assertCount(0, $collection->getServices());

        $container->register('one', array(
            'class' => $this->simpleServiceClass,
            'notify' => array(
                array('collection', 'addService', array('@', 'one'))
            )
        ));

        $this->assertCount(1, $collection->getServices());
        $this->assertSame($container->get('one'), $collection->getService('one'));
    }

    public function testNotifyingPreviouslyInstantiatedService() {
        $container = new Container();
        $collectionServiceClass = $this->collectionServiceClass;
        $container->set('collection', new $collectionServiceClass);

        $collection = $container->get('collection');
        $this->assertCount(0, $collection->getServices());

        $container->register('one', array(
            'class' => $this->simpleServiceClass,
            'notify' => array(
                array('collection', 'addService', array('@', 'one'))
            )
        ));

        $this->assertCount(1, $collection->getServices());
        $this->assertSame($container->get('one'), $collection->getService('one'));
    }

    public function testNotifyingServiceByAlias() {
        $container = new Container();
        $container->register('collection', array(
            'class' => $this->collectionServiceClass,
            'aliases' => array('list', 'simple_services')
        ));

        $container->register('lipsum', array(
            'class' => $this->simpleServiceClass,
            'notify' => array(
                array('collection', 'addService', array('@', '@=')),
                array('list', 'addService', array('@', 'lipsum.list')),
                array('simple_services', 'addService', array('@', 'lipsum.simple_services')),
                array('set_of_services', 'addService', array('@', 'lipsum.set_of_services'))
            )
        ));

        $container->register('set_of_services', array(
            'alias' => 'list'
        ));

        $collection = $container->get('collection');
        $this->assertCount(4, $collection->getServices());
    }

    public function testInjectArrayFromParameter() {
        $container = new Container();
        $container->setParameter('names', array('name1', 'name2', 'name3', 'name4'));
        $container->register('arrayed', array(
            'class' => $this->namedProductClass,
            'arguments' => array('%names%')
        ));

        $this->assertNotEmpty($container->get('arrayed'));
    }

}
