<?php
namespace Splot\DependencyInjection\Tests\TestFixtures;

class NamedProduct
{

    protected $name;

    public function __construct($name) {
        $this->name = $name;
    }

    public function getName() {
        return $this->name;
    }

}