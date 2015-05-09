<?php
namespace Splot\DependencyInjection\Tests\TestFixtures;

class VeryArgumentedService extends ArgumentedService
{

    public $debug;

    public $copyrightString;

    public $buildNumber;

    public function __construct($name, $version, $stability, $debug, $copyrightString, $buildNumber) {
        parent::__construct($name, $version, $stability);
        $this->debug;
        $this->copyrightString;
        $this->buildNumber;
    }

    public function setEverything($name = null, $version = null, $stability = null, $debug = null, $copyrightString = null, $buildNumber = null) {
        $this->name = $name !== null ? $name : $this->name;
        $this->version = $version !== null ? $version : $this->version;
        $this->stability = $stability !== null ? $stability : $this->stability;
        $this->debug = $debug !== null ? $debug : $this->debug;
        $this->copyrightString = $copyrightString !== null ? $copyrightString : $this->copyrightString;
        $this->buildNumber = $buildNumber !== null ? $buildNumber : $this->buildNumber;
    }

}