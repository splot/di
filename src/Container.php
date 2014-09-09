<?php
namespace Splot\DependencyInjection;

use MD\Foundation\Debug\Debugger;
use MD\Foundation\Exceptions\InvalidFileException;
use MD\Foundation\Exceptions\NotImplementedException;
use MD\Foundation\Exceptions\NotFoundException;

use Symfony\Component\Yaml\Yaml;

use Splot\DependencyInjection\Definition\ClosureService;
use Splot\DependencyInjection\Definition\ObjectService;
use Splot\DependencyInjection\Definition\Service;
use Splot\DependencyInjection\Exceptions\CircularReferenceException;
use Splot\DependencyInjection\Exceptions\InvalidServiceException;
use Splot\DependencyInjection\Exceptions\ParameterNotFoundException;
use Splot\DependencyInjection\Exceptions\ReadOnlyException;
use Splot\DependencyInjection\Exceptions\ServiceNotFoundException;
use Splot\DependencyInjection\Resolver\ParametersResolver;
use Splot\DependencyInjection\Resolver\ServicesResolver;

class Container
{

    /**
     * Set of all parameters.
     * 
     * @var array
     */
    protected $parameters = array();

    /**
     * Set of all services.
     * 
     * @var array
     */
    protected $services = array();

    /**
     * Default service options.
     * 
     * @var array
     */
    protected $defaultOptions = array(
        'class' => null,
        'extends' => null,
        'arguments' => array(),
        'call' => array(),
        'abstract' => false,
        'singleton' => true,
        'read_only' => false
    );

    /**
     * List of all loaded files to prevent double loading.
     * 
     * @var array
     */
    protected $loadedFiles = array();

    /**
     * Array used to store which services are currently resolving in order to detect circular references.
     * 
     * @var array
     */
    protected $loading = array();

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
        $this->parametersResolver = new ParametersResolver($this);
        $this->servicesResolver = new ServicesResolver($this, $this->parametersResolver);

        // register itself
        $this->set('container', $this, array(
            'read_only' => true,
            'aliases' => array('service_container', 'services_container', 'di_container')
        ));
    }

    /**
     * Set a service by passing an object instance.
     *
     * Also accepts closures which will be treated as factories.
     * 
     * @param string  $name      Name of the service.
     * @param object|closure $object Object to be set as a service or a closure that returns the service.
     * @param array   $options   [optional] Array of options for the service definition.
     * @param boolean $singleton Deprecated.
     *
     * @throws ReadOnlyException When trying to overwrite a service that is marked as read only.
     */
    public function set($name, $object, $options = array(), $singleton = true) {
        // for backward compatibility
        $options = is_array($options) ? $options : array('read_only' => $options, 'singleton' => $singleton);

        $service = Debugger::getType($object) === 'closure'
            ? new ClosureService($name, $object)
            : new ObjectService($name, $object);

        $this->addService($service, $options);
    }

    /**
     * Register a service with the given name and options.
     * 
     * @param  string $name    Name of the service.
     * @param  array|string $options Array of options for the service definition or a string with name
     *                               of a class to instantiate.
     * 
     * @throws ReadOnlyException When trying to overwrite a service that is marked as read only.
     */
    public function register($name, $options) {
        // if $options is a string then treat it as a class name
        $options = is_array($options) ? $options : array('class' => $options);
        $this->addService(new Service($name), $options);
    }

    /**
     * Retrieves a service with the given name.
     * 
     * @param  string $name Name of the service to retrieve.
     * @return object
     *
     * @throws ServiceNotFoundException When could not find a service with the given name.
     * @throws CircularReferenceException When 
     */
    public function get($name) {
        if (!isset($this->services[$name])) {
            throw new ServiceNotFoundException('Requested undefined service "'. $name .'".');
        }

        // if this service is already on the loading list then it means there's a circular reference somewhere
        if (isset($this->loading[$name])) {
            $loadingServices = implode(', ', array_keys($this->loading));
            $this->loading = array();
            throw new CircularReferenceException('Circular reference detected during loading of chained services '. $loadingServices .'. Referenced service: "'. $name .'".');
        }

        // mark this service as being currently loaded
        $this->loading[$name] = true;

        $service = $this->services[$name];
        $instance = $this->servicesResolver->resolve($service);

        // if loaded successfully then remove it from loading array
        unset($this->loading[$name]);

        return $instance;
    }

    /**
     * Checks if the given service is registered.
     * 
     * @param  string  $name Name of the service.
     * @return boolean
     */
    public function has($name) {
        return isset($this->services[$name]);
    }

    /**
     * Returns the definition of a service.
     * 
     * @param  string $name Name of the service which definition we want to retrieve.
     * @return Service
     *
     * @throws ServiceNotFoundException When could not find a service with the given name.
     */
    public function getDefinition($name) {
        if (!isset($this->services[$name])) {
            throw new ServiceNotFoundException('Requested definition of an undefined service "'. $name .'".');
        }

        return $this->services[$name];
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
        $this->parameters[$name] = $value;
        $this->clearInternalCaches();
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
        if (!$this->hasParameter($name)) {
            throw new ParameterNotFoundException('Requested undefined parameter "'. $name .'".');
        }

        return $this->parametersResolver->resolve($this->parameters[$name]);
    }

    /**
     * Checks if the given parameter was defined.
     * 
     * @param string $name Name of the parameter.
     * @return bool
     */
    public function hasParameter($name) {
        return isset($this->parameters[$name]);
    }

    /**
     * Return all registered parameters.
     * 
     * @return array
     */
    public function dumpParameters() {
        return $this->parametersResolver->resolve($this->parameters);
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

        // load services
        if (isset($definitions['services'])) {
            foreach($definitions['services'] as $name => $options) {
                $this->register($name, $options);
            }
        }

        return true;
    }

    /**
     * Add a service definition and decorate it with options.
     * 
     * @param Service $service Service definition.
     * @param array   $options [optional] Array of options.
     *
     * @throws ReadOnlyException When trying to overwrite a service that is marked as read only.
     */
    protected function addService(Service $service, array $options = array()) {
        // trying to overwrite previously defined read only service?
        if ($this->has($service->getName()) && $this->services[$service->getName()]->isReadOnly()) {
            throw new ReadOnlyException('Could not overwrite a read only service "'. $service->getName() .'".');
        }

        $options = array_merge($this->defaultOptions, $options);

        if (empty($options['class']) && empty($options['extends']) && !$service instanceof ClosureService && !$service instanceof ObjectService) {
            throw new InvalidServiceException('Cannot define service "'. $service->getName() .'" without specifying its class.');
        }

        if (!empty($options['class'])) {
            $service->setClass($options['class']);
        }

        $service->setArguments($options['arguments']);
        $service->setExtends($options['extends']);
        $service->setSingleton($options['singleton']);
        $service->setAbstract($options['abstract']);
        $service->setReadOnly($options['read_only']);

        if (is_array($options['call']) && !empty($options['call'])) {
            foreach($options['call'] as $call) {
                if (!isset($call[0]) || !is_string($call[0])) {
                    throw new InvalidServiceException('Invalid method calls definition in definition of service "'. $service->getName() .'".');
                }

                $arguments = isset($call[1])
                    ? (is_array($call[1])
                        ? $call[1] : array($call[1])
                    ) : array();
                $service->addMethodCall($call[0], $arguments);
            }
        }

        $this->services[$service->getName()] = $service;

        $this->clearInternalCaches();
    }

    /**
     * Clear all internal caches.
     */
    protected function clearInternalCaches() {
        $this->servicesResolver->clearInternalCache();
    }

}