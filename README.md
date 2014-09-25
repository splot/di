Splot Dependency Injection Container
============

Dependency Injection Container by Splot Framework.

[![Build Status](https://travis-ci.org/splot/di.svg?branch=master)](https://travis-ci.org/splot/di)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/0e3472b4-4630-4e1c-b11f-72314fe55e99/mini.png)](https://insight.sensiolabs.com/projects/0e3472b4-4630-4e1c-b11f-72314fe55e99)
[![HHVM Status](http://hhvm.h4cc.de/badge/splot/di.png)](http://hhvm.h4cc.de/package/splot/di)

Splot Dependency Injection Container is a standalone PHP library that helps with following [Inversion of Control and Dependency Injection pattern](http://www.martinfowler.com/articles/injection.html).

It's a viable alternative to popular [Symfony Dependency Injection Component](http://symfony.com/doc/current/components/dependency_injection/introduction.html) or [PHP-DI](http://php-di.org/) or any other implementation.

## Features

- Supports both constructor (`arguments` option) and setter injection (`call` option).
- Includes a "parameter container" and parameters can be used inside of various options or as dependencies or include one another.
- Can notify other services about a service existence (e.g. for adding handlers to a collection).
- Supports factory definitions, where service is created by calling a method on another service.
- Service definitions can extend other service definitions to avoid repeating common formulae (`extends` option).
- Allows for optional dependencies.
- Supports private, read only and aliased services as well as singleton services (default) and not-singleton services (option).
- Reads definitions from YML files.
- Fully tested.

## TL;DR docs

Get from Composer:

    $ composer require splot/di dev-master

Use in PHP:

    $container = new \Splot\DependencyInjection\Container();

    // register services in PHP
    $container->setParameter('debug', true);
    $container->register('logger', '\Psr\Log\NullLogger');
    $container->register('my_service', array(
        'class' => 'MyClass',
        'arguments' => array('MyService', '1.0', '%debug%'),
        'call' => array(
            array('setLogger', array('@logger'))
        )
    ));

    // load parameters and service definitions from a YML file
    $container->loadFromFile(__DIR__ .'/services.yml');

    // retrieve a service
    $myService = $container->get('my_service');

    // retrieve a parameter
    $debug = $container->getParameter('debug');

You can check out full list of options and a bunch of examples in the coverall test file: [tests/fixtures/coverall.yml](https://github.com/splot/di/blob/master/tests/fixtures/coverall.yml).

## Installation

You can install Splot DI using [Composer](https://getcomposer.org/).

    $ composer require splot/di dev-master

All you need to do to start using it is instantiation:

    $container = new \Splot\DependencyInjection\Container();

## Using the container

#### Registering

There are several ways to register services:

- `$container->register('my_service_name', 'MyClass')` - easiest if your class doesn't have any other dependencies,
- `$container->register('my_service_name', array('class' => 'MyClass', ...))` - more advanced if you want to add some options (documented below),
- `$container->set('my_service_name', new \MyClass())` - when you want to register an existing object as a service,
- `$container->set('my_service_name', function() { return new \MyClass(); })` - when you want to build a service through a closure.

The `::register()` method is preferred.

#### Loading from YML

You can also load service definitions and parameters from a YML file. This is highly encouraged as it is much cleaner and much more readable.

    $container->loadFromFile(__DIR__ .'/services.yml');

You can check an example YML file here: [tests/fixtures/coverall.yml](https://github.com/splot/di/blob/master/tests/fixtures/coverall.yml).

#### Retrieving services

To retrieve a service from the container just call:

    $myService = $container->get('my_service_name');

This will instantiate the service and resolve and inject any dependencies.

#### Using parameters

You can use container parameters for parametrized common arguments or even class names or other options.

To set a parameter call:

    $container->setParameter('debug', true);

And to get a parameter call:

    $debug = $container->getParameter('debug');

Parameters can also link to one another:
    
    $container->setParameter('dev_mode', true);
    $container->setParameter('debug', '%dev_mode%');

They can also concatenate as strings:

    $container->setParameter('version', 1);
    $container->setParameter('app_name', 'MyApp ver. %version%');

Whenever referencing a parameter you should surround its name in `%` signs - one in front and one behind.

### Referencing other services and parameters

You can inject other services and parameters as dependencies to a service.

When referencing a service either in `arguments` option or in `call` option, you have to prepend a `@` sign to its name, e.g. `@my_service`.

When referencing a parameter either in `arguments` option or in `call` option, or any other option, you have to surround it in `%` signs - one in front and one in behind, e.g. `%my_parameter.name%`.

## Options

The following options are available when registering a service, both in PHP and YML file.

Some of them are obsolete when registering already existing object as a service or when using closure as a service recipe.

All examples are using YML notation, but can be translated 1:1 to PHP options array.

### class

Class name of the service. Should be a full namespaced class, e.g.

    my_service:
        class: MyLib\MyService

Can also be a parameter, if previously defined:

    my_service:
        class: %my_service.class%

### arguments

List of constructor arguments for the service. Can reference other services (by `@` notation) or parameters (by `%` notation):

    my_service:
        class: %my_service.class%
        arguments:
            - MyServiceTitle
            - @logger
            - %debug%

When referencing other services they can be optional - if you add `?` sign at the end of the reference then if such service was not found, `null` will be injected:

    my_service:
        class: %my_service.class%
        arguments:
            - @logger?

In the example above, if `logger` service does not exist then `null` will be put in its place.

### call

List of service methods that should be called on the service right after it has been instantiated. Each call entry is an array where index 0 is method name and index 1 is an array of the method arguments.

You can also reference parameters or other services here in method arguments, e.g.:

    my_service:
        class: %my_service.class%
        call:
            - [setName, ["My Service Title"]]
            - [setVersion, ["1.0", "stable"]]
            - [setLogger, "@logger"]
            - [setDebug, "%debug%"]


If a single argument is given to the method, then index 1 or the call array can be that argument and not an array (like in two last lines in the above example).

### factory_service, factory_method, factory_arguments

TBD.

### factory

TBD.

### notify

TBD.

### abstract

Set to `true` if you want to mark this service as not instantiable.

### extends

TBD.

### singleton

All services in Splot DI are singletons by default. Set `singleton` option to `false` if you want the container to always return a new instance.

    my_service:
        class: %my_service.class%
        singleton: false

### aliases

When registering a service you can also register a bunch of aliases for it. You will be able to use them when retrieving the service.

    my_service:
        class: %my_service.class%
        aliases:
            - my_service.alias
            - my_fake_service
            - your_service

### alias

You can register a service as an alias to another service. It will simply make the container refer to that service also by its new name.

    my_service:
        class: %my_service.class%

    my_service.alias:
        alias: my_service

### private

If you don't want a service to be directly retrievable from the container, mark it as `private`. You will not be able to get it using `$container->get()` method, but you will be able to use it as a dependency to another service.

    my_service:
        class: %my_service.class%
        private: true

### read_only

If you want to make sure that your service will not be overwritten by another service then mark it as read only.

    my_service:
        class: %my_service.class%
        read_only: true

## Tips

TBD.

### Compact definition

TBD.

### Compact factory definition

TBD.

## Common recipes

TBD.

## Contribute

Issues and pull requests are very welcome! When creating a pull request please include full test coverage to your changes.
