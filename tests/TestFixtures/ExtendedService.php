<?php
namespace Splot\DependencyInjection\Tests\TestFixtures;

use Splot\DependencyInjection\Tests\TestFixtures\CalledService;

class ExtendedService extends CalledService
{

    public $subname;

    protected $isExtended = false;

    public function __construct($name, $version, $subname) {
        parent::__construct($name, $version);
        $this->subname = $subname;
    }

    public function setExtended($extended) {
        $this->isExtended = $extended;
    }

    public function isExtended() {
        return $this->isExtended;
    }

}