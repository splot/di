<?php
namespace Splot\DependencyInjection\Tests\TestFixtures;

use StdClass;

use Splot\DependencyInjection\Tests\TestFixtures\SimpleService;

class ParametrizedService
{

    public $simple;

    public $not_existent;

    public $name;

    public $version;

    public $debug;

    public function __construct(SimpleService $simple, $name, $version, $debug, StdClass $not_existent = null) {
        $this->simple = $simple;
        $this->name = $name;
        $this->version = $version;
        $this->debug = $debug;
        $this->not_existent = $not_existent;
    }

}