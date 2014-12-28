<?php
namespace Splot\DependencyInjection\Definition;

use Splot\DependencyInjection\Definition\Service;

class ClosureService extends Service
{

    protected $closure;

    public function __construct($name, $closure) {
        parent::__construct($name);
        $this->closure = $closure;
    }

    public function setClass($class) {
        // noop
    }

    public function getClosure() {
        return $this->closure;
    }

}
