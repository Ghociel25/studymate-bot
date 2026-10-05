<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when a user requests a file that doesn't exist OR belongs to
 * someone else. Same reasoning as Conversation/ProjectAccessDeniedException.
 */
class FileAccessDeniedException extends Exception
{
    public function __construct()
    {
        parent::__construct('File not found or not accessible.');
    }
}
