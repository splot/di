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
     * Instantiate the service and perform constructor injection if necessary.
     * 
     * @param  Service $service Service definition.
     * @return object
     */
    protected function instantiateService(Service $service) {
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

        // get constructor arguments
        try {
            $arguments = $this->resolveArguments($service->getArguments());
        } catch(CircularReferenceException $e) {
            throw $e;
        } catch(Exception $e) {
            throw new InvalidServiceException('Could not instantiate service "'. $service->getName() .'" because one or more of its arguments could not be resolved: '. $e->getMessage(), 0, $e);
        }

        // if no constructor arguments then simply instantiate the class using "new" keyword
        if (empty($arguments)) {
            return new $class();
        }

        // otherwise we need to instantiate using reflection
        $instance = $classReflection->newInstanceArgs($arguments);

        return $instance;
    }

    /**
     * Resolves an argument to an actual argument that can be used with services instances.
     * 
     * @param  string|array $argument Argument(s) to be resolved.
     * @return mixed
     */
    protected function resolveArguments($argument) {
        // make it work recursively for arrays
        if (is_array($argument)) {
            foreach($argument as $i => $arg) {
                $argument[$i] = $this->resolveArguments($arg);
            }
            return $argument;
        }

        // if string then resolve possible parameters
        if (is_string($argument)) {
            $argument = $this->parametersResolver->resolve($argument);
        }

        // and maybe it's a reference to another service?
        $argument = $this->resolveServiceLink($argument);

        return $argument;
    }

    /**
     * Resolves a link to a service into an actual service instance.
     * 
     * @param  string $link Service link to be resolved.
     * @return string|object|null
     */
    protected function resolveServiceLink($link) {
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

        // if no such service but its optional link then just return null
        if (!$this->container->has($name) && $optional) {
            return null;
        }

        return $this->container->get($name);
    }

}