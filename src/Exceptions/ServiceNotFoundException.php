<?php
namespace Splot\DependencyInjection\Exceptions;

use MD\Foundation\Exceptions\NotFoundException;

use Interop\Container\Exception\NotFoundException as InteropNotFoundException;

class ServiceNotFoundException extends NotFoundException implements InteropNotFoundException
{

    
}
