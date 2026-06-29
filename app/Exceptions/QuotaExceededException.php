<?php

namespace App\Exceptions;

use RuntimeException;

class QuotaExceededException extends RuntimeException
{
    public function __construct(
        public readonly string $period,
        public readonly int $limit,
        public readonly int $used,
        public readonly int $requested,
    ) {
        parent::__construct(
            sprintf(
                'Token quota exceeded for %s period. limit=%d used=%d requested=%d',
                $period,
                $limit,
                $used,
                $requested,
            ),
        );
    }
}
