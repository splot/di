<?php
namespace Splot\DependencyInjection;

use Interop\Container\ContainerInterface as InteropContainerInterface;

interface ContainerInterface extends InteropContainerInterface
{

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
    function set($name, $object, $options = array(), $singleton = true);

    /**
     * Register a service with the given name and options.
     *
     * @param  string $name    Name of the service.
     * @param  array|string $options Array of options for the service definition or a string with name
     *                               of a class to instantiate.
     *
     * @throws ReadOnlyException When trying to overwrite a service that is marked as read only.
     */
    function register($name, $options);

    /**
     * Retrieves a service with the given name.
     *
     * @param  string $name Name of the service to retrieve.
     * @return object
     *
     * @throws ServiceNotFoundException When could not find a service with the given name.
     * @throws CircularReferenceException When a circular dependency was found while retrieving the service.
     */
    function get($name);

    /**
     * Checks if the given service is registered.
     *
     * @param  string  $name Name of the service.
     * @return boolean
     */
    function has($name);

    /**
     * Returns the definition of a service.
     *
     * @param  string $name Name of the service which definition we want to retrieve.
     * @return Service
     *
     * @throws ServiceNotFoundException When could not find a service with the given name.
     */
    function getDefinition($name);

    /**
     * Sets a paremeter to the given value.
     *
     * @param string $name Name of the parameter.
     * @param mixed $value Value of the parameter.
     */
    function setParameter($name, $value);

    /**
     * Returns the given parameter.
     *
     * @param string $name Name of the parameter.
     * @return mixed
     *
     * @throws ParameterNotFoundException When there is no such parameter.
     */
    function getParameter($name);

    /**
     * Checks if the given parameter was defined.
     *
     * @param string $name Name of the parameter.
     * @return bool
     */
    function hasParameter($name);

    /**
     * Load parameters and services from the given file.
     *
     * @param  string $file Path to the file.
     * @return bool
     *
     * @throws NotFoundException If the file could not be found.
     * @throws InvalidFileException If could not read the given file format (currently only YAML is supported)
     */
    function loadFromFile($file);

    /**
     * Load parameters and services from an array with definitions.
     *
     * There should be at least one top level key: `parameters` or `services`.
     *
     * @param  array  $definitions Array of definitions.
     * @return bool
     */
    function loadFromArray(array $definitions);

}
