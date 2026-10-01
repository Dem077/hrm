<?php

namespace App\Exceptions;

use RuntimeException;

class PayrollBuildCancelledException extends RuntimeException
{
    public function __construct(string $message = 'Payroll build was cancelled.')
    {
        parent::__construct($message);
    }
}
