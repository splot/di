<?php
namespace Splot\DependencyInjection\Resolver;

use Splot\DependencyInjection\Definition\ClosureService;
use Splot\DependencyInjection\Definition\ObjectService;
use Splot\DependencyInjection\Definition\Service;
use Splot\DependencyInjection\Exceptions\InvalidServiceException;
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
        $class = $this->parametersResolver->resolve($service->getClass());

        if (!class_exists($class)) {
            throw new InvalidServiceException('Could not instantiate service "'. $service->getName() .'" because class '. $class .' was not found.');
        }

        // get constructor arguments
        $arguments = $this->resolveArguments($service);

        // if no constructor arguments then simply instantiate the class using "new" keyword
        if (empty($arguments)) {
            return new $class();
        }
    }

    protected function resolveArguments(Service $service) {
        $arguments = $service->getArguments();
        // @todo
        return $arguments;
    }

}