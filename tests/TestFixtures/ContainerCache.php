<?php
namespace Splot\DependencyInjection\Tests\TestFixtures;

use Splot\DependencyInjection\Exceptions\CacheDataNotFoundException;
use Splot\DependencyInjection\ContainerCacheInterface;

class ContainerCache implements ContainerCacheInterface
{

    protected $data = null;

    public function load() {
        if ($this->data === null) {
            throw new CacheDataNotFoundException();
        }

        return unserialize($this->data);
    }

    public function save($data) {
        $this->data = serialize($data);
    }

    public function flush() {
        $this->data = null;
    }

}