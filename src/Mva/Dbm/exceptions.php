<?php

/**
 * This file is part of the Mva\Dbm library.
 * @license    MIT
 * @link       https://github.com/Vyki/mva-dbm
 */

namespace Mva\Dbm;

class NotSupportedException extends \LogicException
{
	
}

class MemberAccessException extends \LogicException
{
	
}

class InvalidStateException extends \RuntimeException
{
	
}

class InvalidArgumentException extends \InvalidArgumentException
{
	
}

class DriverException extends \Exception
{
	
}

class QueryException extends DriverException
{

}
