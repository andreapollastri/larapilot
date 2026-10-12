<?php

namespace App\Exceptions;

use RuntimeException;

class PaymentWebhookException extends RuntimeException
{
    public static function stripe(int $status, string $message, int $memberId): self
    {
        return new self("Stripe answered {$status} for customer of member {$memberId}: {$message}");
    }
}
