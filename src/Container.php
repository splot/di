<?php
namespace Splot\DependencyInjection;

use MD\Foundation\Debug\Debugger;
use MD\Foundation\Exceptions\InvalidFileException;
use MD\Foundation\Exceptions\NotImplementedException;
use MD\Foundation\Exceptions\NotFoundException;

use Symfony\Component\Yaml\Yaml;

use Splot\DependencyInjection\Definition\ClosureService;
use Splot\DependencyInjection\Definition\FactoryService;
use Splot\DependencyInjection\Definition\ObjectService;
use Splot\DependencyInjection\Definition\Service;
use Splot\DependencyInjection\Exceptions\CircularReferenceException;
use Splot\DependencyInjection\Exceptions\InvalidServiceException;
use Splot\DependencyInjection\Exceptions\ParameterNotFoundException;
use Splot\DependencyInjection\Exceptions\PrivateServiceException;
use Splot\DependencyInjection\Exceptions\ReadOnlyException;
use Splot\DependencyInjection\Exceptions\ServiceNotFoundException;
use Splot\DependencyInjection\Resolver\ArgumentsResolver;
use Splot\DependencyInjection\Resolver\NotificationsResolver;
use Splot\DependencyInjection\Resolver\ParametersResolver;
use Splot\DependencyInjection\Resolver\ServicesResolver;
use Splot\DependencyInjection\ContainerInterface;

class Container implements ContainerInterface
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
     * Aliases to services.
     * 
     * @var array
     */
    protected $aliases = array();

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
        'factory_service' => null,
        'factory_method' => null,
        'factory_arguments' => array(),
        'notify' => array(),
        'abstract' => false,
        'singleton' => true,
        'alias' => false,
        'aliases' => array(),
        'private' => false,
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
    protected $parametersResolver;

    /**
     * Services resolver.
     * 
     * @var ServicesResolver
     */
    protected $servicesResolver;

    /**
     * Notifications resolver.
     * 
     * @var NotificationsResolver
     */
    protected $notificationsResolver;

    /**
     * Arguments resolver.
     * 
     * @var ArgumentsResolver
     */
    protected $argumentsResolver;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->parametersResolver = new ParametersResolver($this);
        $this->argumentsResolver = new ArgumentsResolver($this, $this->parametersResolver);
        $this->servicesResolver = new ServicesResolver($this, $this->parametersResolver, $this->argumentsResolver);
        $this->notificationsResolver = new NotificationsResolver($this, $this->argumentsResolver);

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

        // if overwriting an alias $name then make sure the real service is overwritten, not just the alias
        try {
            $name = $this->resolveServiceName($name);
        } catch(ServiceNotFoundException $e) {}

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

        // if overwriting an alias $name then make sure the real service is overwritten, not just the alias
        try {
            $name = $this->resolveServiceName($name);
        } catch(ServiceNotFoundException $e) {}

        $this->addService(new Service($name), $options);
    }

    /**
     * Retrieves a service with the given name.
     *
     * @param  string $name Name of the service to retrieve.
     * @return mixed
     *
     * @throws ServiceNotFoundException When could not find a service with the given name.
     * @throws CircularReferenceException When a circular dependency was found while retrieving the service.
     */
    public function get($name) {
        $requestedName = $name;
        $name = $this->resolveServiceName($name);
        $debugName = '"'. $requestedName .'"'. ($name !== $requestedName ? ' (alias for: "'. $name .'")' : '');

        // if this service is already on the loading list then it means there's a circular reference somewhere
        if (isset($this->loading[$name])) {
            $loadingServices = implode(', ', array_keys($this->loading));
            $this->loading = array();
            throw new CircularReferenceException('Circular reference detected during loading of chained services '. $loadingServices .'. Referenced service: '. $debugName .'.');
        }

        // get service definition
        $service = $this->services[$name];

        if ($service->isPrivate() && empty($this->loading) && !$this->notificationsResolver->isResolvingQueue()) {
            $this->loading = array();
            throw new PrivateServiceException('Requested private service '. $debugName .'.');
        }

        // mark this service as being currently loaded
        $this->loading[$name] = true;

        // load this service
        $instance = $this->servicesResolver->resolve($service);

        // enqueue the service for delivering any notifications directed at it,
        // after the whole dependency tree has been resolved
        $this->notificationsResolver->queueForResolving($service, $instance);

        // if loaded successfully then remove it from loading array
        unset($this->loading[$name]);

        if (empty($this->loading)) {
            $this->notificationsResolver->resolveQueue();
        }

        return $instance;
    }

    /**
     * Checks if the given service is registered.
     *
     * @param  string  $name Name of the service.
     * @return boolean
     */
    public function has($name) {
        try {
            $this->resolveServiceName($name);
        } catch(ServiceNotFoundException $e) {
            return false;
        }
        return true;
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
        $name = $this->resolveServiceName($name);
        return $this->services[$name];
    }

    /**
     * Resolves a service name through the chain of aliases.
     * 
     * @param  string $name         Service name.
     * @param  string $originalName [optional] Original requested name. For internal use. Default: `null`.
     * @return string
     *
     * @throws ServiceNotFoundException When the given name could not be resolved (meaning that the service probably does not exist).
     */
    public function resolveServiceName($name, $originalName = null) {
        if (isset($this->services[$name])) {
            return $name;
        }

        if (isset($this->aliases[$name])) {
            return $this->resolveServiceName($this->aliases[$name], $originalName ? $originalName : $name);
        }

        throw new ServiceNotFoundException('Could not find service called "'. $name .'"' . ($originalName ? ' (requested as "'. $originalName .'")' : '') .'.');
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

        $success = $this->loadFromArray($definitions);

        // add to loaded files
        if ($success) {
            $this->loadedFiles[] = $file;
        }

        return $success;
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
     * @throws InvalidServiceException When something is wrong with the service definition.
     */
    protected function addService(Service $service, array $options = array()) {
        // trying to overwrite previously defined read only service?
        if ($this->has($service->getName()) && $this->services[$service->getName()]->isReadOnly()) {
            throw new ReadOnlyException('Could not overwrite a read only service "'. $service->getName() .'".');
        }

        $options = $this->expandAndVerifyOptions($options, $service);

        // if just an alias then add to list of aliases
        if ($options['alias']) {
            return $this->addAlias($service->getName(), $options['alias']);
        }

        // if factory then replace the service instance
        if ($options['factory_service']) {
            $service = new FactoryService($service->getName(), $options['factory_service'], $options['factory_method'], $options['factory_arguments']);
        }

        $service->setExtends($options['extends']); // needs to be called before setting the class
        $service->setClass($options['class']);
        $service->setArguments($options['arguments']);
        $service->setSingleton($options['singleton']);
        $service->setAbstract($options['abstract']);
        $service->setReadOnly($options['read_only']);
        $service->setPrivate($options['private']);

        // do register the service
        $this->services[$service->getName()] = $service;

        // also register aliases
        foreach($options['aliases'] as $alias) {
            $this->addAlias($alias, $service->getName());
        }

        // register method calls on the service (setter injection)
        if (is_array($options['call']) && !empty($options['call'])) {
            foreach($options['call'] as $call) {
                if (!isset($call[0]) || !is_string($call[0]) || empty($call[0])) {
                    throw new InvalidServiceException('Invalid method calls definition in definition of service "'. $service->getName() .'".');
                }

                $arguments = isset($call[1])
                    ? (is_array($call[1])
                        ? $call[1] : array($call[1])
                    ) : array();
                $service->addMethodCall($call[0], $arguments);
            }
        }

        // register notifications
        if (is_array($options['notify']) && !empty($options['notify'])) {
            foreach($options['notify'] as $notify) {
                if (!isset($notify[0]) || !is_string($notify[0]) || empty($notify[0])) {
                    throw new InvalidServiceException('Invalid service name given to notify by "'. $service->getName() .'".');
                }

                $targetServiceName = ltrim($notify[0], '@');

                if (!isset($notify[1]) || !is_string($notify[1]) || empty($notify[1])) {
                    throw new InvalidServiceException('Invalid method name given to notify "'. $targetServiceName .'" by "'. $service->getName() .'".');
                }

                $methodName = $notify[1];

                $arguments = isset($notify[2])
                    ? (is_array($notify[2])
                        ? $notify[2] : array($notify[2])
                    ) : array();

                $this->notificationsResolver->registerNotification($service->getName(), $targetServiceName, $methodName, $arguments);
            }
        }

        $this->clearInternalCaches();
    }

    /**
     * Expands short options and definitions to full options array.
     * 
     * @param  array   $options Array of service definition options.
     * @param  Service $service The service to configure.
     * @return array
     */
    protected function expandAndVerifyOptions(array $options, Service $service) {
        // if options is an array with at least 2 numeric keys then treat it as a very compact factory definition
        if (isset($options[0]) && isset($options[1]) && (count($options) === 2 || count($options) === 3)) {
            $options = array('factory' => $options);
        }

        // if defined factory key then expand it
        if (isset($options['factory'])) {
            if (!isset($options['factory'][0]) || !isset($options['factory'][1])) {
                throw new InvalidServiceException('You have to specify factory service name and method when registering a service built from a factory for "'. $service->getName() .'".');
            }
            $options['factory_service'] = $options['factory'][0];
            $options['factory_method'] = $options['factory'][1];
            $options['factory_arguments'] = isset($options['factory'][2])
                ? (is_array($options['factory'][2]) ? $options['factory'][2] : array($options['factory'][2]))
                : array();
        }

        $options = array_merge($this->defaultOptions, $options);

        if ($options['factory_service'] && empty($options['factory_method'])) {
            throw new InvalidServiceException('Cannot define service built from factory without specifying factory method for "'. $service->getName() .'".');
        }

        if (is_string($options['aliases'])) {
            $options['aliases'] = array($options['aliases']);
        }

        return $options;
    }

    /**
     * Adds an alias for a service.
     * 
     * @param string $alias Alias name.
     * @param string $for   Name of the target/aliased service.
     */
    protected function addAlias($alias, $for) {
        if ($this->has($alias)) {
            throw new InvalidServiceException('Trying to overwrite a previously defined service with an alias "'. $alias .'" for "'. $for .'".');
        }

        $this->aliases[$alias] = $for;

        $this->notificationsResolver->rerouteNotifications($alias, $for);
    }

    /**
     * Clear all internal caches.
     */
    protected function clearInternalCaches() {
        $this->servicesResolver->clearInternalCache();
    }

}
