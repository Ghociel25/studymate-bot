<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when a user requests a project that doesn't exist OR belongs to
 * someone else. Same reasoning as ConversationAccessDeniedException: one
 * error for both cases, so existence can't be probed (SRS FR-017).
 */
class ProjectAccessDeniedException extends Exception
{
    public function __construct()
    {
        parent::__construct('Project not found or not accessible.');
    }
}
