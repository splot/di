<?php
namespace Splot\DependencyInjection\Definition;

use Splot\DependencyInjection\Exceptions\InvalidServiceException;

class Service
{

    public $name;

    public $class;

    public $arguments = array();

    public $methodCalls = array();

    public $extends = null;

    public $singleton = true;

    public $abstract = false;

    public $readOnly = false;

    public $private = false;

    public $instance;

    public function __construct($name) {
        $this->name = $name;
    }

    public function getName() {
        return $this->name;
    }

    public function setClass($class) {
        if (empty($class) && !$this->isExtending()) {
            throw new InvalidServiceException('Cannot define service "'. $this->getName() .'" without specifying its class.');
        }
        
        $this->class = $class;
    }

    public function getClass() {
        return $this->class;
    }

    public function setArguments(array $arguments = array()) {
        $this->arguments = $arguments;
    }

    public function getArguments() {
        return $this->arguments;
    }

    public function addMethodCall($method, array $arguments = array()) {
        $this->methodCalls[] = array(
            'method' => $method,
            'arguments' => $arguments
        );
    }

    public function getMethodCalls() {
        return $this->methodCalls;
    }

    public function setExtends($extends) {
        $this->extends = $extends;
    }

    public function getExtends() {
        return $this->extends;
    }

    public function isExtending() {
        return $this->getExtends();
    }

    public function setSingleton($singleton) {
        $this->singleton = $singleton;
    }

    public function getSingleton() {
        return $this->singleton;
    }

    public function isSingleton() {
        return $this->getSingleton();
    }

    public function setAbstract($abstract) {
        $this->abstract = $abstract;
    }

    public function getAbstract() {
        return $this->abstract;
    }

    public function isAbstract() {
        return $this->getAbstract();
    }

    public function setReadOnly($readOnly) {
        $this->readOnly = $readOnly;
    }

    public function getReadOnly() {
        return $this->readOnly;
    }

    public function isReadOnly() {
        return $this->getReadOnly();
    }

    public function setPrivate($private) {
        $this->private = $private;
    }

    public function getPrivate() {
        return $this->private;
    }

    public function isPrivate() {
        return $this->getPrivate();
    }

    public function getInstance() {
        return $this->instance;
    }

    public function setInstance($instance) {
        // if not singleton then no point in keeping reference
        if (!$this->isSingleton()) {
            return;
        }
        $this->instance = $instance;
    }

    public function isInstantiated() {
        return isset($this->instance);
    }

    public function applyParent(Service $parent) {
        if (!$this->getClass()) {
            $this->setClass($parent->getClass());
        }

        $arguments = $this->getArguments();
        if (empty($arguments)) {
            $this->setArguments($parent->getArguments());
        }

        // prepend parent method calls
        foreach($parent->getMethodCalls() as $methodCall) {
            array_unshift($this->methodCalls, $methodCall);
        }
    }

    public function __clone() {
        $this->instance = null;
    }

}
