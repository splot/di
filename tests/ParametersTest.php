<?php
namespace Splot\DependencyInjection\Tests;

use Splot\DependencyInjection\Container;

class ParametersTest extends \PHPUnit_Framework_TestCase
{

    public function testSettingAndGettingParameter() {
        $container = new Container();

        $container->setParameter('my_parameter', 'lorem ipsum');
        $this->assertTrue($container->hasParameter('my_parameter'));
        $this->assertEquals($container->getParameter('my_parameter'), 'lorem ipsum');
    }

    public function testHasParameter() {
        $container = new Container();
        $this->assertFalse($container->hasParameter('undefined'));
    }

    public function testSettingArrayAsParameter() {
        $container = new Container();
        $param = array('one', 'two', 'three');
        $container->setParameter('array_parameter', $param);
        $this->assertEquals($param, $container->getParameter('array_parameter'));
    }

    /**
     * @expectedException \Splot\DependencyInjection\Exceptions\ParameterNotFoundException
     */
    public function testGettingUndefinedParameter() {
        $container = new Container();
        $container->getParameter('undefined_param');
    }

    public function testResolvingParameters() {
        $container = new Container();

        $container->setParameter('debug', true);
        $container->setParameter('debug.alias', '%debug%');
        $container->setParameter('name', 'splot');
        $container->setParameter('version', 0.5);
        $container->setParameter('full_name', '%name% v.%version%');

        $this->assertEquals(true, $container->getParameter('debug.alias'));
        $this->assertEquals('splot v.0.5', $container->getParameter('full_name'));
    }

    public function testDeepResolvingParameters() {
        $container = new Container();

        $container->setParameter('prefix', 'di');
        $container->setParameter('deep', array(
            '%prefix% Salvatore',
            '%prefix% Rodriguez',
            array(
                '%prefix% Santa Cruz'
            )
        ));

        $this->assertEquals(array(
            'di Salvatore',
            'di Rodriguez',
            array(
                'di Santa Cruz'
            )
        ), $container->getParameter('deep'));
    }

    public function testResolvingUndefinedParameters() {
        $container = new Container();

        $container->setParameter('name', 'splot');
        $container->setParameter('version', 0.5);
        $container->setParameter('full_name', '%name% v.%version% - (%alpha%)');

        $this->assertEquals('splot v.0.5 - (%alpha%)', $container->getParameter('full_name'));
    }

    /**
     * @expectedException \Splot\DependencyInjection\Exceptions\InvalidParameterException
     */
    public function testReferencingNonScalarTypeInParameter() {
        $container = new Container();
        $container->setParameter('list', array('a', 'b', 'c', 'd', 'e'));
        $container->setParameter('with_list', 'This is the list: %list%.');

        $container->getParameter('with_list');
    }

    public function testLoadingFromYaml() {
        $container = new Container();
        $container->loadFromFile(__DIR__ .'/fixtures/parameters.yml');

        $expected = include __DIR__ .'/fixtures/parameters.php';
        foreach($expected as $name => $val) {
            $this->assertTrue($container->hasParameter($name));
            $this->assertEquals($container->getParameter($name), $val);
        }
    }

    public function testDumpParameters() {
        $container = new Container();
        $container->loadFromFile(__DIR__ .'/fixtures/parameters.yml');

        $expected = include __DIR__ .'/fixtures/parameters.php';
        $this->assertEquals($expected, $container->dumpParameters());
    }

    public function testEscapingParameterSign() {
        $container = new Container();
        $container->setParameter('lorem', 'ipsum');
        $container->setParameter('dolor', 'sit.amet');
        $container->setParameter('adipiscit.elit', '%lorem%%dolor%');
        $container->setParameter('lipsum', '%%lorem%%');
        $container->setParameter('lipsum.com', 'it.%dolor%%%lorem%%');

        $this->assertEquals('ipsumsit.amet', $container->getParameter('adipiscit.elit'));
        $this->assertEquals('%lorem%', $container->getParameter('lipsum'));
        $this->assertEquals('it.sit.amet%lorem%', $container->getParameter('lipsum.com'));
    }

}
