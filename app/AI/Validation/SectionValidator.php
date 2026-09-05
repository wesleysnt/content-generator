<?php

declare(strict_types=1);

namespace App\AI\Validation;

use App\AI\Exceptions\ValidationFailedException;
use Illuminate\Support\Facades\Validator;

class SectionValidator
{
    public function validate(array $data): void
    {
        $validator = Validator::make($data, [
            'heading' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            throw new ValidationFailedException($validator->errors()->toArray());
        }
    }
}
