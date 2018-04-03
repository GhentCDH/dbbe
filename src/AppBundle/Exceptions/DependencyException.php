<?php

namespace AppBundle\Exceptions;

use Exception;

/**
 * Exception that should be thrown when something that should be found in de database
 * is not found.
 */
class DependencyException extends Exception
{
}
