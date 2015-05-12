<?php
namespace Splot\DependencyInjection\Tests;

use Splot\DependencyInjection\Container;

class NotifyTest extends \PHPUnit_Framework_TestCase
{

    protected $container;

    public function setUp() {
        $this->container = new Container();

        $this->container->register('collection_service', 'Splot\DependencyInjection\Tests\TestFixtures\CollectionService');
        $this->container->register('subscriber_service', array(
            'class' => 'Splot\DependencyInjection\Tests\TestFixtures\SubscriberService',
            'arguments' => array('@container', '@collection_service')
        ));
    }

    public function testNotifyWithLaterDefinedDependency() {
        $collectionService = $this->container->get('collection_service');

        $this->container->loadFromFile(__DIR__ .'/fixtures/notify/later_defined.yml');

        $subscriberService = $this->container->get('subscriber_service');
        $subscriberService->trigger('event');
    }

}
