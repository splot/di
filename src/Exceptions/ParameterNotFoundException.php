<?php
namespace Splot\DependencyInjection\Exceptions;

use MD\Foundation\Exceptions\NotFoundException;

use Interop\Container\Exception\NotFoundException as InteropNotFoundException;

class ParameterNotFoundException extends NotFoundException implements InteropNotFoundException
{

    
}
