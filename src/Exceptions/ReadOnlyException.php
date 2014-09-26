<?php
namespace Splot\DependencyInjection\Exceptions;

use Interop\Container\Exception\ContainerException;

use MD\Foundation\Exceptions\ReadOnlyException as BaseReadOnlyException;

class ReadOnlyException extends BaseReadOnlyException implements ContainerException
{

    
}
