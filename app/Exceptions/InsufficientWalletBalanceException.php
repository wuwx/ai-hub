<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientWalletBalanceException extends RuntimeException
{
    public function __construct(
        public readonly int $requestedCents,
        public readonly int $availableCents,
    ) {
        parent::__construct(sprintf(
            'Insufficient wallet balance: requested %d cents, only %d available.',
            $requestedCents,
            $availableCents,
        ));
    }
}
