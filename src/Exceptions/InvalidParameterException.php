<?php
namespace Splot\DependencyInjection\Exceptions;

use UnexpectedValueException;

use Interop\Container\Exception\ContainerException;

class InvalidParameterException extends UnexpectedValueException implements ContainerException
{
    
}
