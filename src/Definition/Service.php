<?php
namespace Splot\DependencyInjection\Definition;

class Service
{

    protected $name;

    protected $class;

    protected $arguments = array();

    protected $singleton = true;

    protected $readOnly = false;

    protected $instance;

    public function __construct($name) {
        $this->name = $name;
    }

    public function getName() {
        return $this->name;
    }

    public function setClass($class) {
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

    public function setSingleton($singleton) {
        $this->singleton = $singleton;
    }

    public function getSingleton() {
        return $this->singleton;
    }

    public function isSingleton() {
        return $this->getSingleton();
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

}