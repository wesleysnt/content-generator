<?php

declare(strict_types=1);

namespace App\AI\Prompts;

class PromptBuilder
{
    public static function render(string $template, array $vars): string
    {
        $output = $template;

        foreach ($vars as $key => $value) {
            $output = str_replace('{{'.$key.'}}', (string) $value, $output);
        }

        // Replace any placeholders not supplied in $vars with an empty string.
        return preg_replace('/\{\{[^{}]+\}\}/', '', $output) ?? $output;
    }

    public static function buildMessages(string $renderedPrompt, ?string $repairContext = null): array
    {
        $messages = [
            ['role' => 'system', 'content' => $renderedPrompt],
        ];

        if ($repairContext !== null) {
            $messages[] = [
                'role' => 'user',
                'content' => "Your previous response failed validation. Return a corrected JSON response.\n\nErrors:\n{$repairContext}",
            ];
        }

        return $messages;
    }
}
