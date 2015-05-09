<?php
namespace Splot\DependencyInjection\Resolver;

use Splot\DependencyInjection\Exceptions\ServiceNotFoundException;
use Splot\DependencyInjection\Resolver\ParametersResolver;
use Splot\DependencyInjection\Container;

class ArgumentsResolver
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
     * @param Container          $container          The container.
     * @param ParametersResolver $parametersResolver Parameters resolver.
     */
    public function __construct(Container $container, ParametersResolver $parametersResolver) {
        $this->container = $container;
        $this->parametersResolver = $parametersResolver;
    }

    /**
     * Resolves function arguments to applicable arguments.
     *
     * This method will resolve all parameters in arguments as well as references to
     * other services (in the form of `@service_name`).
     * 
     * @param  array|mixed $argument   Either an array of arguments or a single argument.
     * @param  string $selfServiceName If the arguments can reference "self service name", the name of
     *                                 such references service should be passed here. It can be referenced
     *                                 in arguments as "@=". Default: `null`.
     * @return array|mixed
     */
    public function resolve($argument, $selfServiceName = null) {
        // deeply resolve arguments
        if (is_array($argument)) {
            foreach($argument as $i => $arg) {
                $argument[$i] = $this->resolve($arg, $selfServiceName);
            }
            return $argument;
        }

        // possible parameter argument
        if (is_string($argument)) {
            $argument = $this->parametersResolver->resolve($argument);
        }

        // if possible to reference self in arguments then do it
        // (reference by name instead of getting the service now)
        if ($selfServiceName !== null && $argument === '@') {
            $argument = '@'. $selfServiceName;
        }

        // if possible to reference self name in arguments then do it
        if ($selfServiceName !== null && $argument === '@=') {
            $argument = $selfServiceName;
        }
        
        // and maybe referencing a different service?
        if (is_string($argument) && mb_substr($argument, 0, 1) === '@') {
            $serviceName = mb_substr($argument, 1);
            $optional = mb_substr($argument, -1) === '?';
            $serviceName = $optional ? mb_substr($serviceName, 0, -1) : $serviceName;
            try {
                $argument = $this->container->get($serviceName);
            } catch(ServiceNotFoundException $e) {
                if ($optional) {
                    $argument = null;
                } else {
                    throw new ServiceNotFoundException('Could not find service "'. $serviceName .'" when resolving a non-optional argument.', 0, $e);
                }
            }
        }

        return $argument;
    }

}
