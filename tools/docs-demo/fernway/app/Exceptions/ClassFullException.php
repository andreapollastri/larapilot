<?php

namespace App\Exceptions;

use App\Models\ClassSession;
use RuntimeException;

class ClassFullException extends RuntimeException
{
    public static function for(ClassSession $session, int $booked): self
    {
        return new self("Class session {$session->id} is full ({$booked}/{$session->capacity}).");
    }
}
