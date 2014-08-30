<?php
namespace Splot\DependencyInjection\Definition;

class Service
{

    protected $name;

    protected $class;

    protected $arguments = array();

    protected $instance;

    public function __construct($name) {
        $this->name = mb_strtolower($name);
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

    public function getInstance() {
        return $this->instance;
    }

    public function setInstance($instance) {
        $this->instance = $instance;
    }

}