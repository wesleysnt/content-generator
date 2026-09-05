<?php

declare(strict_types=1);

namespace App\AI\Exceptions;

use Exception;

class ValidationFailedException extends Exception
{
    public function __construct(private readonly array $errors)
    {
        parent::__construct('AI response failed validation: '.json_encode($errors));
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
