<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when a user requests a conversation that doesn't exist OR exists
 * but belongs to someone else. Both cases are treated identically and
 * raise the same exception, so callers can't probe for the existence of
 * another user's conversation (SRS FR-017, SECURITY.md authorization).
 */
class ConversationAccessDeniedException extends Exception
{
    public function __construct()
    {
        parent::__construct('Conversation not found or not accessible.');
    }
}
