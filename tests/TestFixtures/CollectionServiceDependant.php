<?php
namespace Splot\DependencyInjection\Tests\TestFixtures;

use Splot\DependencyInjection\Tests\TestFixtures\CollectionService;

class CollectionServiceDependant
{

    protected $collection;

    public function __construct(CollectionService $collection) {
        $this->collection = $collection;
    }
    
}