<?php
namespace Splot\DependencyInjection\Resolver;

class ServiceLink
{

    protected $name;

    protected $optional;

    public function __construct($name, $optional = false) {
        $this->name = $name;
        $this->optional = $optional;
    }

    public function getName() {
        return $this->name;
    }

    public function getOptional() {
        return $this->optional;
    }

    public function isOptional() {
        return $this->getOptional();
    }

}
