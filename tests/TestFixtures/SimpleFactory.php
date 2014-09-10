<?php
namespace Splot\DependencyInjection\Tests\TestFixtures;

use Splot\DependencyInjection\Tests\TestFixtures\SimpleService;

class SimpleFactory
{

    public function get() {
        return new SimpleService();
    }
    
}