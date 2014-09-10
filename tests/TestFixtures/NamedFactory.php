<?php
namespace Splot\DependencyInjection\Tests\TestFixtures;

use Splot\DependencyInjection\Tests\TestFixtures\SimpleService;
use Splot\DependencyInjection\Tests\TestFixtures\NamedProduct;

class NamedFactory
{

    protected $products = array();

    public function provide($name) {
        if (isset($this->products[$name])) {
            return $this->products[$name];
        }

        $this->products[$name] = new NamedProduct($name);
        return $this->products[$name];
    }
    
}