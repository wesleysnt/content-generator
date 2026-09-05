<?php

declare(strict_types=1);

namespace App\AI\Validation;

use App\AI\Exceptions\ValidationFailedException;
use Illuminate\Support\Facades\Validator;

class GenerationValidator
{
    public function validate(array $data): void
    {
        $validator = Validator::make($data, [
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'string', 'max:200'],
            'excerpt' => ['required', 'string'],
            'meta_title' => ['required', 'string', 'max:200'],
            'meta_description' => ['required', 'string', 'max:300'],
            'focus_keyword' => ['required', 'string'],
            'secondary_keywords' => ['required', 'array'],
            'category' => ['required', 'string', 'max:100'],
            'tags' => ['required', 'array'],
            'og_title' => ['required', 'string', 'max:200'],
            'og_description' => ['required', 'string', 'max:300'],
            'faq' => ['required', 'array', 'max:10'],
            'faq.*.question' => ['required', 'string'],
            'faq.*.answer' => ['required', 'string'],
            'schema_type' => ['required', 'string'],
            'sections' => ['required', 'array', 'min:1', 'max:12'],
            'sections.*.heading' => ['required', 'string', 'max:200'],
            'sections.*.body' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            throw new ValidationFailedException($validator->errors()->toArray());
        }
    }

    public function warnings(array $data, int $targetWordCount): array
    {
        $warnings = [];

        if (mb_strlen($data['meta_title']) > 60) {
            $warnings[] = 'meta_title exceeds 60 characters ('.mb_strlen($data['meta_title']).')';
        }

        if (mb_strlen($data['meta_description']) < 120 || mb_strlen($data['meta_description']) > 160) {
            $warnings[] = 'meta_description outside 120-160 characters ('.mb_strlen($data['meta_description']).')';
        }

        $allText = mb_strtolower($data['title'].' '.collect($data['sections'])->pluck('body')->implode(' '));
        $keyword = mb_strtolower($data['focus_keyword']);

        if (! str_contains($allText, $keyword)) {
            $warnings[] = 'focus keyword not found in title or body';
        }

        $wordCount = str_word_count(strip_tags($allText));

        if ($wordCount < $targetWordCount * 0.7 || $wordCount > $targetWordCount * 1.3) {
            $warnings[] = "word count {$wordCount} is below target {$targetWordCount} (tolerance 30%)";
        }

        return $warnings;
    }
}
