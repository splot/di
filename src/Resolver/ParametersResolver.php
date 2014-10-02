<?php
namespace Splot\DependencyInjection\Resolver;

use Splot\DependencyInjection\Container;

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
        $firstDelimeter = strpos($parameter, '%');
        $secondDelimeter = strpos($parameter, '%', min((int)$firstDelimeter + 1, mb_strlen($parameter)));
        if ($firstDelimeter === false || $secondDelimeter === false || $firstDelimeter === $secondDelimeter) {
            return $parameter;
        }

        $container = $this->container;
        $parameter = preg_replace_callback('#%([\w\d_\.]+)%#i', function($matches) use ($container) {
            $name = $matches[1];
            if ($container->hasParameter($name)) {
                return $container->getParameter($name);
            }

            return '%'. $name .'%';
        }, $parameter);

        return $parameter;
    }

}
