<?php
namespace Splot\DependencyInjection\Tests\TestFixtures;

use Splot\DependencyInjection\Tests\TestFixtures\SimpleService;

class CollectionService
{

    protected $services = array();

    public function addService(SimpleService $service, $name) {
        $this->services[$name] = $service;
    }

    public function getService($name) {
        return $this->services[$name];
    }

    public function getServices() {
        return $this->services;
    }
    
}