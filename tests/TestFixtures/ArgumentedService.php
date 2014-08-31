<?php
namespace Splot\DependencyInjection\Tests\TestFixtures;

class ArgumentedService
{

    public $name;

    public $version;

    public $stability;

    public function __construct($name, $version, $stability) {
        $this->name = $name;
        $this->version = $version;
        $this->stability = $stability;
    }

}