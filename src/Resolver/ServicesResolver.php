<?php
namespace Splot\DependencyInjection\Resolver;

use Exception;
use ReflectionClass;

use Splot\DependencyInjection\Definition\ClosureService;
use Splot\DependencyInjection\Definition\ObjectService;
use Splot\DependencyInjection\Definition\Service;
use Splot\DependencyInjection\Exceptions\CircularReferenceException;
use Splot\DependencyInjection\Exceptions\InvalidServiceException;
use Splot\DependencyInjection\Exceptions\ParameterNotFoundException;
use Splot\DependencyInjection\Resolver\ParametersResolver;
use Splot\DependencyInjection\Resolver\ServiceLink;
use Splot\DependencyInjection\Container;

class ServicesResolver
{

    /**
     * The container.
     * 
     * @var Container
     */
    protected $container;

    /**
     * Parameters resolver.
     * 
     * @var ParametersResolver
     */
    protected $parametersResolver;

    /**
     * Cache of closures that instantiate services.
     * 
     * @var array
     */
    protected $instantiateClosuresCache = array();

    /**
     * Constructor.
     * 
     * @param Container $container The container.
     */
    public function __construct(Container $container, ParametersResolver $parametersResolver) {
        $this->container = $container;
        $this->parametersResolver = $parametersResolver;
    }

    /**
     * Resolves a service definition into the actual service object.
     * 
     * @param  Service $service Service definition.
     * @return object
     */
    public function resolve(Service $service) {
        // if already instantiated then just return that instance
        if ($service->isSingleton() && ($instance = $service->getInstance())) {
            return $instance;
        }

        $instance = $this->instantiateService($service);
        $service->setInstance($instance);

        return $instance;
    }

    /**
     * Clears internal cache.
     */
    public function clearInternalCache() {
        $this->instantiateClosuresCache = array();   
    }

    /**
     * Instantiate the service and perform constructor injection if necessary.
     * 
     * @param  Service $service Service definition.
     * @return object
     */
    protected function instantiateService(Service $service) {
        // if already resolved the definition, then just call the resolved closure factory
        if (isset($this->instantiateClosuresCache[$service->getName()])) {
            return call_user_func_array($this->instantiateClosuresCache[$service->getName()], array());
        }

        // deal with closure services
        if ($service instanceof ClosureService) {
            return call_user_func_array($service->getClosure(), array($this->container));
        }

        // class can be defined as a parameter
        try {
            $class = $this->parametersResolver->resolve($service->getClass());
        } catch(ParameterNotFoundException $e) {
            throw new InvalidServiceException('Could not instantiate service "'. $service->getName() .'" because class parameter '. $class .' could not be found.', 0, $e);
        }

        if (!class_exists($class)) {
            throw new InvalidServiceException('Could not instantiate service "'. $service->getName() .'" because class '. $class .' was not found.');
        }

        // we need some more information about the class, so we need to use reflection
        $classReflection = new ReflectionClass($class);

        if (!$classReflection->isInstantiable()) {
            throw new InvalidServiceException('The class '. $class .' for service "'. $service->getName() .'" is not instantiable!');
        }


        // parse constructor arguments
        try {
            $arguments = $this->parseArguments($service->getArguments());
        } catch(Exception $e) {
            throw new InvalidServiceException('Could not instantiate service "'. $service->getName() .'" because one or more of its arguments could not be resolved: '. $e->getMessage(), 0, $e);
        }

        $resolver = $this;

        $instantiateClosure = function() use ($class, $classReflection, $arguments, $resolver) {
            // if no constructor arguments then simply instantiate the class using "new" keyword
            if (empty($arguments)) {
                return new $class();
            }

            // do resolve all service links in the arguments
            $arguments = $resolver->resolveArguments($arguments);

            // otherwise we need to instantiate using reflection
            $instance = $classReflection->newInstanceArgs($arguments);
            return $instance;
        };

        // cache this closure
        $this->instantiateClosuresCache[$service->getName()] = $instantiateClosure;

        // and finally call it
        return call_user_func_array($instantiateClosure, array());
    }

    /**
     * Parses arguments definition by resolving parameters and parsing service links.
     * 
     * @param  string|array $argument Argument(s) to be parsed.
     * @return mixed
     */
    protected function parseArguments($argument) {
        // make it work recursively for arrays
        if (is_array($argument)) {
            foreach($argument as $i => $arg) {
                $argument[$i] = $this->parseArguments($arg);
            }
            return $argument;
        }

        // if string then resolve possible parameters
        if (is_string($argument)) {
            $argument = $this->parametersResolver->resolve($argument);
        }

        // and maybe it's a reference to another service?
        $argument = $this->parseServiceLink($argument);

        return $argument;
    }

    /**
     * Parses a link to a service.
     * 
     * @param  string $link Service link to be parsed.
     * @return string|object
     */
    protected function parseServiceLink($link) {
        // only strings are linkable
        if (!is_string($link)) {
            return $link;
        }

        // if doesn't start with a @ then definetely not a link
        if (strpos($link, '@') !== 0) {
            return $link;
        }

        $name = mb_substr($link, 1);
        $optional = strrpos($name, '?') === mb_strlen($name) - 1;
        $name = $optional ? mb_substr($name, 0, mb_strlen($name) - 1) : $name;

        return new ServiceLink($name, $optional);

        // if no such service but its optional link then just return null
        if (!$this->container->has($name) && $optional) {
            return null;
        }

        return $this->container->get($name);
    }

    protected function resolveArguments($argument) {
        // make it work recursively for arrays
        if (is_array($argument)) {
            foreach($argument as $i => $arg) {
                $argument[$i] = $this->resolveArguments($arg);
            }
            return $argument;
        }

        // for reals only service links need to be resolved
        if ($argument instanceof ServiceLink) {
            $serviceName = $argument->getName();

            // if no such service, but its optional then just return null
            if (!$this->container->has($serviceName) && $argument->isOptional()) {
                return null;
            }

            return $this->container->get($serviceName);
        }

        return $argument;
    }

}