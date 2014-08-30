<?php
namespace Splot\DependencyInjection;

use MD\Foundation\Exceptions\InvalidFileException;
use MD\Foundation\Exceptions\NotImplementedException;
use MD\Foundation\Exceptions\NotFoundException;

use Symfony\Component\Yaml\Yaml;

use Splot\DependencyInjection\Exceptions\ParameterNotFoundException;
use Splot\DependencyInjection\Resolver\ParametersResolver;

class Container
{

    /**
     * Set of all parameters.
     * 
     * @var array
     */
    protected $parameters = array();

    /**
     * List of all loaded files to prevent double loading.
     * 
     * @var array
     */
    protected $loadedFiles = array();

    /**
     * Parameters resolver.
     * 
     * @var ParametersResolver
     */
    private $parametersResolver;

    /**
     * Constructor.
     */
    public function __construct() {
        // register itself
        /*
        $this->set('container', $this, array(
            'read_only' => true,
            'aliases' => array('service_container', 'services_container', 'di_container')
        ));
        */
    }

    // not type hinting $object to function for backward compatibility
    // last 2 arguments are deprecated
    public function set($name, $object, $options, $singleton = true) {
        // for backward compatibility
        $options = is_array($options) ? $options : array('read_only' => $options, 'singleton' => $singleton);

        throw new NotImplementedException();
    }

    public function register($name, $options) {
        // if $options is a string then treat it as a class name
        $options = is_array($options) ? $options : array('class' => $options);
        throw new NotImplementedException();
    }

    public function get($name) {
        throw new NotImplementedException();
    }

    public function has($name) {
        throw new NotImplementedException();
    }

    public function dump() {
        throw new NotImplementedException();
    }

    /**
     * Sets a paremeter to the given value.
     * 
     * @param string $name Name of the parameter.
     * @param mixed $value Value of the parameter.
     */
    public function setParameter($name, $value) {
        $this->parameters[mb_strtolower($name)] = $value;
    }

    /**
     * Returns the given parameter.
     * 
     * @param string $name Name of the parameter.
     * @return mixed
     * 
     * @throws ParameterNotFoundException When there is no such parameter.
     */
    public function getParameter($name) {
        $name = mb_strtolower((string)$name);

        if (!$this->hasParameter($name)) {
            throw new ParameterNotFoundException('Requested undefined parameter "'. $name .'".');
        }

        return $this->getParametersResolver()->resolve($this->parameters[$name]);
    }

    /**
     * Checks if the given parameter was defined.
     * 
     * @param string $name Name of the parameter.
     * @return bool
     */
    public function hasParameter($name) {
        return isset($this->parameters[mb_strtolower((string)$name)]);
    }

    /**
     * Return all registered parameters.
     * 
     * @return array
     */
    public function dumpParameters() {
        return $this->getParametersResolver()->resolve($this->parameters);
    }

    /**
     * Load parameters and services from the given file.
     * 
     * @param  string $file Path to the file.
     * @return bool
     *
     * @throws NotFoundException If the file could not be found.
     * @throws InvalidFileException If could not read the given file format (currently only YAML is supported)
     */
    public function loadFromFile($file) {
        // if file already loaded then ignore it
        if (in_array($file, $this->loadedFiles)) {
            return true;
        }

        // check if file exists
        if (!is_file($file)) {
            throw new NotFoundException('Could not find file "'. $file .'" to load into the container.');
        }

        $extension = mb_strtolower(mb_substr($file, strrpos($file, '.') + 1));

        switch($extension) {
            case 'yml':
            case 'yaml':
                $definitions = Yaml::parse(file_get_contents($file));
                break;

            default:
                throw new InvalidFileException('Unrecognized file type "'. $extension .'" could not be loaded into the container. Only supported file format is YAML (.yml, .yaml)');
        }

        return $this->loadFromArray($definitions);
    }

    /**
     * Load parameters and services from an array with definitions.
     *
     * There should be at least one top level key: `parameters` or `services`. 
     * 
     * @param  array  $definitions Array of definitions.
     * @return bool
     */
    public function loadFromArray(array $definitions) {
        // load parameters
        if (isset($definitions['parameters'])) {
            foreach($definitions['parameters'] as $name => $value) {
                $this->setParameter($name, $value);
            }
        }

        // @todo load services definitions

        return true;
    }

    /**
     * Gets a parameters resolver.
     *
     * Any calls to parameters resolver should always go through this getter to ensure lazy instantiation of it.
     * 
     * @return ParametersResolver
     */
    protected function getParametersResolver() {
        if (!isset($this->parametersResolver)) {
            $this->parametersResolver = new ParametersResolver($this);
        }
        return $this->parametersResolver;
    }

}