<?php

declare(strict_types=1);

use App\AI\Prompts\PromptBuilder;

it('replaces placeholders in templates', function () {
    $rendered = PromptBuilder::render('Write about {{topic}} for {{audience}}', [
        'topic' => 'cloud accounting',
        'audience' => 'SMB owners',
    ]);

    expect($rendered)->toBe('Write about cloud accounting for SMB owners');
});

it('replaces missing placeholders with an empty string', function () {
    $rendered = PromptBuilder::render('Topic: {{topic}}', []);

    expect($rendered)->toBe('Topic: ');
});

it('builds a single system message without repair context', function () {
    $messages = PromptBuilder::buildMessages('SYSTEM PROMPT');

    expect($messages)->toHaveCount(1);
    expect($messages[0]['role'])->toBe('system');
    expect($messages[0]['content'])->toBe('SYSTEM PROMPT');
});

it('appends a repair message after the system message', function () {
    $messages = PromptBuilder::buildMessages('SYSTEM PROMPT', 'Errors: {"title": ["required"]}');

    expect($messages)->toHaveCount(2);
    expect($messages[1]['role'])->toBe('user');
    expect($messages[1]['content'])->toContain('Your previous response failed validation');
    expect($messages[1]['content'])->toContain('Errors:');
});
