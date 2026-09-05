<?php

declare(strict_types=1);

namespace App\AI\Validation;

use App\AI\Exceptions\ValidationFailedException;
use Illuminate\Support\Facades\Validator;

class TitleValidator
{
    public function validate(array $data): void
    {
        $validator = Validator::make($data, [
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'string', 'max:200'],
            'meta_title' => ['required', 'string', 'max:200'],
            'meta_description' => ['required', 'string', 'max:300'],
        ]);

        if ($validator->fails()) {
            throw new ValidationFailedException($validator->errors()->toArray());
        }
    }
}
