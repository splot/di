<?php
namespace Splot\DependencyInjection\Definition;

use MD\Foundation\Debug\Debugger;

use Splot\DependencyInjection\Definition\Service;

class ObjectService extends Service
{

    private $resolved = false;

    public function __construct($name, $object) {
        parent::__construct($name);
        $this->instance = $object;
        $this->class = Debugger::getType($object);
    }

    public function setClass($class) {
        // noop
    }

    public function isInstantiated() {
        return $this->resolved;
    }

    public function getInstance() {
        $this->resolved = true;
        return $this->instance;
    }

    public function getSingleton() {
        // object service is not a singleton until it has been resolved,
        // so that setter injection can happen once and only once
        return $this->resolved;
    }

}
