<?php
namespace Splot\DependencyInjection\Tests\TestFixtures;

use Splot\DependencyInjection\Tests\TestFixtures\CollectionService;
use Splot\DependencyInjection\Tests\TestFixtures\SimpleService;

class CollectionServiceDependant extends SimpleService
{

    protected $collection;

    public function __construct(CollectionService $collection) {
        $this->collection = $collection;
    }
    
}