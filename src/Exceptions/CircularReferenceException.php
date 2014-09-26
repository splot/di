<?php
namespace Splot\DependencyInjection\Exceptions;

use LogicException;

use Interop\Container\Exception\ContainerException;

class CircularReferenceException extends LogicException implements ContainerException
{

    
}
