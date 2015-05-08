<?php
namespace Splot\DependencyInjection\Resolver;

use Exception;
use RuntimeException;
use ReflectionClass;

use Splot\DependencyInjection\Definition\ClosureService;
use Splot\DependencyInjection\Definition\FactoryService;
use Splot\DependencyInjection\Definition\ObjectService;
use Splot\DependencyInjection\Definition\Service;
use Splot\DependencyInjection\Exceptions\AbstractServiceException;
use Splot\DependencyInjection\Exceptions\CircularReferenceException;
use Splot\DependencyInjection\Exceptions\InvalidServiceException;
use Splot\DependencyInjection\Exceptions\ServiceNotFoundException;
use Splot\DependencyInjection\Resolver\ArgumentsResolver;
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
     * Arguments resolver.
     * 
     * @var ArgumentsResolver
     */
    protected $argumentsResolver;

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
     * @param ParametersResolver $parametersResolver Parameters resolver.
     * @param ArgumentsResolver $argumentsResolver Arguments resolver.
     */
    public function __construct(Container $container, ParametersResolver $parametersResolver, ArgumentsResolver $argumentsResolver) {
        $this->container = $container;
        $this->parametersResolver = $parametersResolver;
        $this->argumentsResolver = $argumentsResolver;
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

        // cannot resolve an abstract service
        if ($service->isAbstract()) {
            throw new AbstractServiceException('Could not instantiate abstract service "'. $service->getName() .'".');
        }

        // if the service is extending any other service then resolve all parent definitions
        $service = $this->resolveHierarchy($service);

        $instance = $this->instantiateService($service);
        // setting the instance already here will help circular reference via setter injection working
        // but only for singleton services
        $service->setInstance($instance);

        // if there are any method calls, then call them
        try {
            $methodCalls = $service->getMethodCalls();
            foreach($methodCalls as $call) {
                $this->callMethod($service, $call, $instance);
            }
        } catch(CircularReferenceException $e) {
            throw $e;
        } catch(Exception $e) {
            throw new InvalidServiceException('Could not instantiate service "'. $service->getName() .'" because one of its method calls could not be resolved: '. $e->getMessage(), 0, $e);
        }

        return $instance;
    }

    /**
     * Clears internal cache.
     */
    public function clearInternalCache() {
        $this->instantiateClosuresCache = array();   
    }

    /**
     * Resolve a hierarchy of services that extend other services definitions
     * and return a final service definition (cloned original).
     * 
     * @param  Service $originalService Original service definition.
     * @return Service
     */
    protected function resolveHierarchy(Service $originalService) {
        if (!$originalService->isExtending()) {
            return $originalService;
        }

        $parentName = $this->parametersResolver->resolve($originalService->getExtends());

        try {
            $parent = $this->container->getDefinition($parentName);
        } catch(ServiceNotFoundException $e) {
            throw new InvalidServiceException('Service "'. $originalService->getName() .'" tried to extend an inexisting service "'. $parentName .'".', 0, $e);
        }

        if ($parent instanceof ObjectService || $parent instanceof FactoryService) {
            throw new InvalidServiceException('Service "'. $originalService->getName() .'" cannot extend an object service "'. $parent->getName() .'".');
        }

        $parent = $this->resolveHierarchy($parent);

        $service = clone $originalService;
        $service->applyParent($parent);

        return $service;
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
            return call_user_func($this->instantiateClosuresCache[$service->getName()]);
        }

        // deal with closure services
        if ($service instanceof ClosureService) {
            return call_user_func_array($service->getClosure(), array($this->container));
        }

        // deal with object services
        if ($service instanceof ObjectService) {
            return $service->getInstance();
        }

        // deal with factory services
        if ($service instanceof FactoryService) {
            try {
                $factoryServiceName = $this->parametersResolver->resolve($service->getFactoryService());
                $factoryService = $this->container->get($factoryServiceName);
                $factoryArguments = $this->argumentsResolver->resolve($service->getFactoryArguments());
                return call_user_func_array(array($factoryService, $service->getFactoryMethod()), $factoryArguments);
            } catch(Exception $e) {
                throw new InvalidServiceException('Could not instantiate service "'. $service->getName() .'" due to: '. $e->getMessage(), 0, $e);
            }
        }

        // class can be defined as a parameter
        $class = $this->parametersResolver->resolve($service->getClass());
        if (!class_exists($class)) {
            throw new InvalidServiceException('Could not instantiate service "'. $service->getName() .'" because class '. $class .' was not found.');
        }

        // we need some more information about the class, so we need to use reflection
        $classReflection = new ReflectionClass($class);

        if (!$classReflection->isInstantiable()) {
            throw new InvalidServiceException('The class '. $class .' for service "'. $service->getName() .'" is not instantiable!');
        }

        $resolver = $this;
        $arguments = $service->getArguments();
        $instantiateClosure = function() use ($class, $arguments, $classReflection, $resolver) {
            // if no constructor arguments then simply instantiate the class using "new" keyword
            if (empty($arguments)) {
                return new $class();
            }

            $arguments = $this->argumentsResolver->resolve($arguments);

            // we need to instantiate using reflection
            $instance = $classReflection->newInstanceArgs($arguments);
            return $instance;
        };

        // cache this closure
        $this->instantiateClosuresCache[$service->getName()] = $instantiateClosure;

        // and finally call it
        return call_user_func($instantiateClosure);
    }

    /**
     * Call a method on a service.
     * 
     * @param  Service $service Service to call a method on.
     * @param  array   $call    Method call parameters (`method` and `arguments`).
     * @param  object  $instance [optional] Service object instance. Should be passed for all non-singleton services.
     * @return mixed
     *
     * @throws RuntimeException When trying to call a method on a service that hasn't been instantiated yet.
     */
    public function callMethod(Service $service, array $call, $instance = null) {
        if ($instance === null && !$service->isInstantiated()) {
            throw new RuntimeException('Cannot call a method of a service that has not been instantiated yet, when trying to call "::'. $call['method'] .'" on "'. $service->getName() .'".');
        }

        $instance = $instance ? $instance : $service->getInstance();

        $arguments = $this->argumentsResolver->resolve($call['arguments']);

        return call_user_func_array(array($instance, $call['method']), $arguments);
    }

}
