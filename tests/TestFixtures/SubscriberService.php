<?php
namespace Splot\DependencyInjection\Tests\TestFixtures;

use Splot\DependencyInjection\ContainerInterface;
use Splot\DependencyInjection\Tests\TestFixtures\CollectionService;

class SubscriberService
{

    protected $container;

    protected $collection;

    protected $listeners = array();

    public function __construct(ContainerInterface $container, CollectionService $collection) {
        $this->container = $container;
        $this->collection = $collection;
    }

    public function subscribeService($event, $serviceName, $method) {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = array();
        }

        $this->listeners[$event][] = array($serviceName, $method);
    }

    public function trigger($event) {
        if (!isset($this->listeners[$event])) {
            return;
        }

        foreach($this->listeners[$event] as $info) {
            $service = $this->container->get($info[0]);
            $method = $info[1];
            $service->$method($event);
        }
    }

}