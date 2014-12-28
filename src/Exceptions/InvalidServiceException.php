<?php
namespace Splot\DependencyInjection\Exceptions;

use RuntimeException;

use Interop\Container\Exception\ContainerException;

class InvalidServiceException extends RuntimeException implements ContainerException
{

    
}
