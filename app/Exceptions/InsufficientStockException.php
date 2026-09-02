<?php

namespace App\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    public function __construct(
        public readonly string $productName,
        public readonly int $availableStock,
        public readonly int $requestedQuantity,
        string $message = '',
        int $code = 422,
        ?Exception $previous = null,
    ) {
        $message = $message ?: "Insufficient stock for product '{$this->productName}'. Available: {$this->availableStock}, requested: {$this->requestedQuantity}.";

        parent::__construct($message, $code, $previous);
    }
}
