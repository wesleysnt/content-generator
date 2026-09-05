<?php

// app/AI/DeepSeek/DeepSeekProvider.php

declare(strict_types=1);

namespace App\AI\DeepSeek;

use App\AI\Contracts\AIProvider;
use App\AI\DTO\GenerationRequest;
use App\AI\DTO\SectionRegenerationRequest;
use App\AI\DTO\SectionResult;
use App\AI\DTO\TitleRegenerationRequest;
use App\AI\DTO\TitleResult;
use App\AI\DTO\VariationResult;
use App\AI\Exceptions\AICallException;
use App\AI\Exceptions\AIResponseException;
use App\AI\Prompts\PromptBuilder;
use App\AI\Validation\GenerationValidator;
use App\AI\Validation\SectionValidator;
use App\AI\Validation\TitleValidator;
use OpenAI\Client;
use OpenAI\Exceptions\ErrorException;
use OpenAI\Exceptions\TransporterException;

class DeepSeekProvider implements AIProvider
{
    public function __construct(
        private readonly GenerationValidator $generationValidator,
        private readonly SectionValidator $sectionValidator,
        private readonly TitleValidator $titleValidator,
        private readonly Client $client,
    ) {}

    public function generateVariation(GenerationRequest $request): VariationResult
    {
        $messages = PromptBuilder::buildMessages(
            PromptBuilder::render($request->promptContent, $this->generationVars($request)),
            $request->repairContext
        );

        $api = $this->callApi($request->model, $messages, $request->maxTokens, config('ai.temperature_generation'));

        return $this->parseResponse($api, $request->model);
    }

    public function regenerateVariation(GenerationRequest $request): VariationResult
    {
        return $this->generateVariation($request);
    }

    public function regenerateSection(SectionRegenerationRequest $request): SectionResult
    {
        $messages = PromptBuilder::buildMessages(
            PromptBuilder::render($request->promptContent, [
                'topic' => $request->topic,
                'primary_keyword' => $request->primaryKeyword,
                'tone' => $request->tone,
                'target_persona' => $request->targetPersona ?? 'Not specified',
                'context_sections' => $request->contextSections,
                'heading' => $request->heading,
                'current_body' => $request->currentBody,
            ])
        );

        $api = $this->callApi($request->model, $messages, $request->maxTokens, config('ai.temperature_regeneration'));

        return $this->parseSectionResponse($api, $request->model);
    }

    public function regenerateTitle(TitleRegenerationRequest $request): TitleResult
    {
        $messages = PromptBuilder::buildMessages(
            PromptBuilder::render($request->promptContent, [
                'topic' => $request->topic,
                'primary_keyword' => $request->primaryKeyword,
                'tone' => $request->tone,
                'target_persona' => $request->targetPersona ?? 'Not specified',
                'current_title' => $request->currentTitle,
                'negative_context' => implode("\n", $request->negativeContext) ?: 'None.',
            ])
        );

        $api = $this->callApi($request->model, $messages, $request->maxTokens, config('ai.temperature_regeneration'));

        return $this->parseTitleResponse($api, $request->model);
    }

    public function parseResponse(array $apiResponse, string $model): VariationResult
    {
        [$content, $in, $out] = $this->extract($apiResponse);

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new AIResponseException('Response is not valid JSON');
        }

        $this->generationValidator->validate($decoded);

        return VariationResult::fromArray($decoded, $in, $out, $model);
    }

    public function parseSectionResponse(array $apiResponse, string $model): SectionResult
    {
        [$content, $in, $out] = $this->extract($apiResponse);

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new AIResponseException('Response is not valid JSON');
        }

        $this->sectionValidator->validate($decoded);

        return new SectionResult($decoded['heading'], $decoded['body'], $in, $out, $model, $decoded);
    }

    public function parseTitleResponse(array $apiResponse, string $model): TitleResult
    {
        [$content, $in, $out] = $this->extract($apiResponse);

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new AIResponseException('Response is not valid JSON');
        }

        $this->titleValidator->validate($decoded);

        return new TitleResult(
            $decoded['title'],
            $decoded['slug'],
            $decoded['meta_title'],
            $decoded['meta_description'],
            $in,
            $out,
            $model,
            $decoded
        );
    }

    private function generationVars(GenerationRequest $request): array
    {
        return [
            'topic' => $request->topic,
            'primary_keyword' => $request->primaryKeyword,
            'secondary_keywords' => implode(', ', $request->secondaryKeywords),
            'tone' => $request->tone,
            'target_persona' => $request->targetPersona ?? 'Not specified',
            'target_word_count' => (string) $request->targetWordCount,
            'angle' => $request->angle->label(),
            'additional_instructions' => $request->additionalInstructions ?? 'None.',
            'negative_context' => implode("\n", $request->negativeContext) ?: 'None.',
        ];
    }

    private function extract(array $apiResponse): array
    {
        $choice = $apiResponse['choices'][0] ?? null;

        if ($choice === null) {
            throw new AICallException('No choices in API response');
        }

        $finishReason = $choice['finish_reason'] ?? null;

        if ($finishReason !== 'stop') {
            throw new AICallException('Generation truncated or stopped abnormally: '.($finishReason ?? 'unknown'));
        }

        $content = $choice['message']['content'] ?? '';

        if (trim($content) === '') {
            throw new AICallException('Empty content returned by provider');
        }

        $usage = $apiResponse['usage'] ?? [];

        return [$content, (int) ($usage['prompt_tokens'] ?? 0), (int) ($usage['completion_tokens'] ?? 0)];
    }

    private function callApi(string $model, array $messages, int $maxTokens, float $temperature): array
    {
        try {
            return $this->client->chat()->create([
                'model' => $model,
                'messages' => $messages,
                'response_format' => ['type' => 'json_object'],
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
            ])->toArray();
        } catch (ErrorException $e) {
            throw new AICallException('Provider API error: '.$e->getMessage(), 0, $e);
        } catch (TransporterException $e) {
            throw new AICallException('Provider transport error: '.$e->getMessage(), 0, $e);
        }
    }
}
