<?php
namespace Splot\DependencyInjection\Resolver;

use Splot\DependencyInjection\Container;
use Splot\DependencyInjection\Exceptions\InvalidParameterException;

class ParametersResolver
{

    /**
     * The container.
     * 
     * @var Container
     */
    protected $container;

    /**
     * Constructor.
     * 
     * @param Container $container The container.
     */
    public function __construct(Container $container) {
        $this->container = $container;
    }

    /**
     * Resolve a parameter if it references other parameters.
     *
     * Works recursively.
     * 
     * @param  mixed $parameter Parameter value to be resolved.
     * @return mixed
     */
    public function resolve($parameter) {
        // allow for deep resolving
        if (is_array($parameter)) {
            foreach($parameter as $key => $value) {
                $parameter[$key] = $this->resolve($value);
            }
            return $parameter;
        }

        if (!is_string($parameter)) {
            return $parameter;
        }
        
        // only bother with resolving when there are at least two %
        $parameterLength = mb_strlen($parameter);
        $firstDelimeter = strpos($parameter, '%');
        $secondDelimeter = strpos($parameter, '%', min((int)$firstDelimeter + 1, $parameterLength));
        if ($firstDelimeter === false || $secondDelimeter === false || $firstDelimeter === $secondDelimeter) {
            return $parameter;
        }

        // special case when fully referencing another parameter, to avoid regex
        // but also handle cases where referencing an array parameter
        // (otherwise preg_replace_callback below will trigger array to string conversion)
        if ($firstDelimeter === 0 && $secondDelimeter === $parameterLength - 1) {
            $referenced = mb_substr($parameter, 1, -1);
            return $this->container->hasParameter($referenced)
                ? $this->container->getParameter($referenced)
                : $parameter;
        }

        $container = $this->container;
        $original = $parameter;
        $parameter = preg_replace_callback('#(%%|%)([\w\d_\.]+)%#i', function($matches) use ($container, $original) {
            if ($matches[1] === '%%') {
                return '%'. $matches[2];
            }

            $name = $matches[2];
            if ($container->hasParameter($name)) {
                $param = $container->getParameter($name);

                // only scalar types can be referenced like that
                if (!is_scalar($param)) {
                    throw new InvalidParameterException('Invalid parameter construction - cannot reference non-scalar type parameter in "'. $original .'".');
                }

                return $container->getParameter($name);
            }

            return '%'. $name .'%';
        }, $parameter);

        return $parameter;
    }

}
