<?php
namespace Splot\DependencyInjection\Definition;

use MD\Foundation\Debug\Debugger;

use Splot\DependencyInjection\Definition\Service;

class ObjectService extends Service
{

    public function __construct($name, $object) {
        parent::__construct($name);
        $this->instance = $object;
        $this->class = Debugger::getType($object);
    }

}