<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception that should be thrown when something that should be found in de database
 * is not found.
 */
class NotFoundInDatabaseException extends Exception
{
}
