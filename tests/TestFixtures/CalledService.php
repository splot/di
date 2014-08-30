<?php
namespace Splot\DependencyInjection\Tests\TestFixtures;

use Splot\DependencyInjection\Tests\TestFixtures\SimpleService;

class CalledService
{

    protected $name;

    protected $version;

    protected $simple;

    protected $optionallySimple;

    public function __construct($name, $version) {
        $this->name = $name;
        $this->version = $version;
    }

    public function setName($name) {
        $this->name = $name;
    }

    public function getName() {
        return $this->name;
    }

    public function setVersion($version) {
        $this->version = $version;
    }

    public function getVersion() {
        return $this->version;
    }

    public function setSimple(SimpleService $simple, SimpleService $optionallySimple = null) {
        $this->simple = $simple;
        $this->optionallySimple = $optionallySimple;
    }

    public function getSimple() {
        return $this->simple;
    }

    public function setOptionallySimple(SimpleService $optionallySimple = null) {
        $this->optionallySimple = $optionallySimple;
    }

    public function getOptionallySimple() {
        return $this->optionallySimple;
    }

}