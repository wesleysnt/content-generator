<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;

class SettingsService
{
    public function get(string $key, mixed $default = null): mixed
    {
        $setting = Setting::where('key', $key)->first();

        if ($setting === null) {
            return $default;
        }

        // Values are stored JSON-encoded so scalar types survive the text
        // column; fall back to the raw string for rows written directly
        // (e.g. seeds) that are not valid JSON.
        $decoded = json_decode($setting->value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $setting->value;
    }

    public function set(string $key, mixed $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => json_encode($value)]);
    }

    public function limitVariations(): int
    {
        return (int) $this->get('max_variations', config('ai.limits.max_variations'));
    }

    public function limitWordCount(): int
    {
        return (int) $this->get('max_word_count', config('ai.limits.max_word_count'));
    }

    public function limitMonthlyGenerations(): int
    {
        return (int) $this->get('max_monthly_generations', config('ai.limits.max_monthly_generations'));
    }
}
