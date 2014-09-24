<?php
namespace Splot\DependencyInjection\Definition;

use Splot\DependencyInjection\Definition\Service;
use Splot\DependencyInjection\Exceptions\InvalidServiceException;

class FactoryService extends Service
{

    protected $factoryService;

    protected $factoryMethod;

    protected $factoryArguments = array();

    public function __construct($name, $factoryService, $factoryMethod, array $factoryArguments = array()) {
        parent::__construct($name);
        $this->setFactoryService($factoryService);
        $this->setFactoryMethod($factoryMethod);
        $this->setFactoryArguments($factoryArguments);
    }

    public function setClass($class) {
        // noop
    }

    public function setAbstract($abstract) {
        if ($abstract) {
            throw new InvalidServiceException('Factory service "'. $this->getName() .'" cannot be defined as abstract.');
        }

        parent::setAbstract($abstract);
    }

    public function setExtends($extends) {
        if ($extends) {
            throw new InvalidServiceException('Factory service "'. $this->getName() .'" cannot extend another service.');
        }

        parent::setExtends($extends);
    }

    public function setFactoryService($factoryService) {
        $this->factoryService = ltrim($factoryService, '@');
    }

    public function getFactoryService() {
        return $this->factoryService;
    }

    public function setFactoryMethod($factoryMethod) {
        $this->factoryMethod = $factoryMethod;
    }

    public function getFactoryMethod() {
        return $this->factoryMethod;
    }

    public function setFactoryArguments(array $factoryArguments) {
        $this->factoryArguments = $factoryArguments;
    }

    public function getFactoryArguments() {
        return $this->factoryArguments;
    }

}
