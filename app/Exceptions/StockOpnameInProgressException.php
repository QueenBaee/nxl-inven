<?php

namespace App\Exceptions;

use Exception;

class StockOpnameInProgressException extends Exception
{
    public function __construct(
        public readonly int $productId,
        public readonly string $sessionName,
        string $message = '',
        int $code = 422,
        ?Exception $previous = null,
    ) {
        $message = $message ?: "Stock mutations are frozen for product ID {$this->productId} because Stock Opname session '{$this->sessionName}' is currently in progress.";

        parent::__construct($message, $code, $previous);
    }
}
