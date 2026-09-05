<?php

declare(strict_types=1);

namespace App\Services;

use App\AI\Angles\AnglePool;
use App\AI\Contracts\AIProvider;
use App\AI\DTO\GenerationRequest;
use App\AI\DTO\SectionRegenerationRequest;
use App\AI\DTO\TitleRegenerationRequest;
use App\AI\Exceptions\AICallException;
use App\AI\Exceptions\AIResponseException;
use App\AI\Exceptions\ValidationFailedException;
use App\AI\Validation\GenerationValidator;
use App\Enums\RequestStatus;
use App\Enums\RevisionType;
use App\Enums\VariationStatus;
use App\Jobs\GenerateContentJob;
use App\Jobs\RegenerateContentJob;
use App\Models\ContentRequest;
use App\Models\ContentVariation;
use App\Models\PromptTemplate;
use App\Models\PromptVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;

class GenerationService
{
    public function __construct(
        private readonly AIProvider $provider,
        private readonly AnglePool $anglePool,
        private readonly RevisionService $revisionService,
        private readonly UsageService $usageService,
        private readonly SettingsService $settingsService,
        private readonly GenerationValidator $generationValidator,
    ) {}

    public function createRequest(User $user, array $data): ContentRequest
    {
        $validated = validator($data, [
            'topic' => ['required', 'string', 'max:500'],
            'primary_keyword' => ['required', 'string', 'max:200'],
            'secondary_keywords' => ['nullable', 'array', 'max:20'],
            'tone' => ['nullable', 'string', 'max:50'],
            'target_persona' => ['nullable', 'string', 'max:200'],
            'variation_count' => ['required', 'integer', 'min:1', 'max:'.$this->settingsService->limitVariations()],
            'target_word_count' => ['required', 'integer', 'min:100', 'max:'.$this->settingsService->limitWordCount()],
            'additional_instructions' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        return ContentRequest::create([
            'user_id' => $user->id,
            'topic' => $validated['topic'],
            'primary_keyword' => $validated['primary_keyword'],
            'secondary_keywords' => $validated['secondary_keywords'] ?? [],
            'tone' => $validated['tone'] ?? 'professional',
            'target_persona' => $validated['target_persona'] ?? null,
            'variation_count' => $validated['variation_count'],
            'target_word_count' => $validated['target_word_count'],
            'additional_instructions' => $validated['additional_instructions'] ?? null,
            'status' => RequestStatus::Draft,
        ]);
    }

    public function dispatchBatch(ContentRequest $request): void
    {
        $angles = $this->anglePool->assign($request->variation_count, $request->id);

        foreach ($angles as $index => $angle) {
            $request->variations()->create([
                'variation_number' => $index + 1,
                'angle_type' => $angle,
                'status' => VariationStatus::Pending,
            ]);
        }

        $request->update(['status' => RequestStatus::Queued]);

        foreach ($request->variations()->get() as $variation) {
            GenerateContentJob::dispatch($variation->id);
        }
    }

    public function generateVariation(int $variationId): void
    {
        $variation = ContentVariation::findOrFail($variationId);
        $request = $variation->request;

        // Batch generation is only valid for a variation that has not been
        // generated yet. A duplicate/stale job instance (e.g. re-dispatched
        // after a worker restart or horizon drain) must never overwrite a
        // variation that is locked, discarded, final, or already generated —
        // the smoke run observed three sequential full generations rewrite a
        // locked variation because GenerateContentJob lacked this guard.
        if ($variation->is_locked || $variation->status !== VariationStatus::Pending) {
            // Converge a stuck header: the job instance that originally
            // generated this variation may have died between its success
            // transaction and updateRequestStatus, leaving the request on
            // "queued"/"processing" — the no-op retry must finish that
            // convergence. Never downgrade a request that is already
            // completed (its variations may all be Final/Discarded by now,
            // which updateRequestStatus would misread as "failed").
            if ($request->status !== RequestStatus::Completed) {
                $this->updateRequestStatus($request);
            }

            return;
        }

        $template = PromptTemplate::where('key', 'generation')->firstOrFail();
        $promptVersion = $template->activeVersion()->firstOrFail();

        $request->update(['status' => RequestStatus::Processing]);

        $generationRequest = new GenerationRequest(
            topic: $request->topic,
            primaryKeyword: $request->primary_keyword,
            secondaryKeywords: $request->secondary_keywords ?? [],
            tone: $request->tone,
            targetPersona: $request->target_persona,
            targetWordCount: $request->target_word_count,
            additionalInstructions: $request->additional_instructions,
            angle: $variation->angle_type,
            negativeContext: $this->negativeContext($request, $variation),
            promptContent: $promptVersion->content,
            model: config('ai.models.generation'),
            maxTokens: $this->maxTokens($request->target_word_count),
        );

        $start = hrtime(true);

        try {
            try {
                $result = $this->provider->generateVariation($generationRequest);
            } catch (ValidationFailedException $e) {
                $repair = new GenerationRequest(
                    topic: $generationRequest->topic,
                    primaryKeyword: $generationRequest->primaryKeyword,
                    secondaryKeywords: $generationRequest->secondaryKeywords,
                    tone: $generationRequest->tone,
                    targetPersona: $generationRequest->targetPersona,
                    targetWordCount: $generationRequest->targetWordCount,
                    additionalInstructions: $generationRequest->additionalInstructions,
                    angle: $generationRequest->angle,
                    negativeContext: $generationRequest->negativeContext,
                    promptContent: $generationRequest->promptContent,
                    model: $generationRequest->model,
                    maxTokens: $generationRequest->maxTokens,
                    repairContext: json_encode($e->errors()),
                );
                try {
                    $result = $this->provider->generateVariation($repair);
                } catch (ValidationFailedException) {
                    $this->recordFailure($request, $variation, config('ai.models.generation'), 'generation', $start, 'Response failed validation after repair');
                    $this->updateRequestStatus($request);

                    return;
                }
            }
        } catch (AICallException|AIResponseException $e) {
            $this->recordFailure($request, $variation, config('ai.models.generation'), 'generation', $start, $e->getMessage());
            $this->updateRequestStatus($request);

            return;
        }

        $duration = $this->elapsedMs($start);
        $warnings = $this->generationValidator->warnings($result->raw, $request->target_word_count);

        DB::transaction(function () use ($variation, $result, $promptVersion, $request, $duration, $warnings) {
            $variation->update([
                'title' => $result->title,
                'slug' => $result->slug,
                'excerpt' => $result->excerpt,
                'meta_title' => $result->metaTitle,
                'meta_description' => $result->metaDescription,
                'focus_keyword' => $result->focusKeyword,
                'secondary_keywords' => $result->secondaryKeywords,
                'category' => $result->category,
                'tags' => $result->tags,
                'og_title' => $result->ogTitle,
                'og_description' => $result->ogDescription,
                'faq' => $result->faq,
                'schema_type' => $result->schemaType,
                'status' => VariationStatus::Generated,
                'prompt_tokens' => $result->inputTokens,
                'completion_tokens' => $result->outputTokens,
                'model_used' => $result->model,
                'prompt_version_id' => $promptVersion->id,
                'raw_response' => $result->raw,
                'error_message' => null,
            ]);

            $variation->sections()->delete();

            foreach ($result->sections as $index => $section) {
                $variation->sections()->create([
                    'section_order' => $index + 1,
                    'heading' => $section['heading'],
                    'body' => Purifier::clean($section['body']),
                ]);
            }

            $this->revisionService->snapshot($variation, RevisionType::AiGeneration, $request->user_id);

            $usageLog = $this->usageService->record(
                $request->user, $request, $variation, $result->model,
                'generation', $result->inputTokens, $result->outputTokens, $duration, 'success'
            );

            if ($warnings !== []) {
                $usageLog->update(['metadata' => ['warnings' => $warnings]]);
            }
        });

        $this->updateRequestStatus($request);
    }

    public function regenerateVariation(int $variationId): void
    {
        $variation = ContentVariation::findOrFail($variationId);

        abort_if($variation->is_locked, 403, 'Locked variations cannot be regenerated.');
        abort_if($variation->status === VariationStatus::Final, 403, 'Final variations cannot be regenerated.');

        $this->revisionService->snapshot($variation, RevisionType::AiRegeneration, auth()->id());

        $request = $variation->request;
        $promptVersion = PromptTemplate::where('key', 'regeneration')->firstOrFail()->activeVersion()->firstOrFail();

        $generationRequest = new GenerationRequest(
            topic: $request->topic,
            primaryKeyword: $request->primary_keyword,
            secondaryKeywords: $request->secondary_keywords ?? [],
            tone: $request->tone,
            targetPersona: $request->target_persona,
            targetWordCount: $request->target_word_count,
            additionalInstructions: $request->additional_instructions,
            angle: $variation->angle_type,
            negativeContext: $this->negativeContext($request, $variation),
            promptContent: $promptVersion->content,
            model: config('ai.models.regeneration'),
            maxTokens: $this->maxTokens($request->target_word_count),
        );

        $this->runVariationCall($request, $variation, $promptVersion, $generationRequest, 'regeneration');
    }

    public function regenerateSection(int $variationId, int $sectionId): void
    {
        $variation = ContentVariation::with('sections')->findOrFail($variationId);
        $section = $variation->sections()->findOrFail($sectionId);
        $request = $variation->request;

        abort_if($variation->is_locked, 403, 'Locked variations cannot be regenerated.');
        abort_if($variation->status === VariationStatus::Final, 403, 'Final variations cannot be regenerated.');

        $this->revisionService->snapshot($variation, RevisionType::SectionRegeneration, auth()->id());

        $promptVersion = PromptTemplate::where('key', 'section_regeneration')->firstOrFail()->activeVersion()->firstOrFail();

        $contextSections = $variation->sections()
            ->where('id', '!=', $sectionId)
            ->get()
            ->map(fn ($s) => "## {$s->heading}\n".Str::limit(strip_tags($s->body), 1500))
            ->implode("\n\n");

        $sectionRequest = new SectionRegenerationRequest(
            topic: $request->topic,
            primaryKeyword: $request->primary_keyword,
            tone: $request->tone,
            targetPersona: $request->target_persona,
            contextSections: $contextSections,
            heading: $section->heading,
            currentBody: strip_tags($section->body),
            promptContent: $promptVersion->content,
            model: config('ai.models.section_regeneration'),
            maxTokens: 4096,
        );

        $start = hrtime(true);

        try {
            $result = $this->provider->regenerateSection($sectionRequest);
        } catch (AICallException|AIResponseException $e) {
            $variation->update(['error_message' => 'Section regeneration failed: '.$e->getMessage()]);

            return;
        }

        DB::transaction(function () use ($variation, $section, $result, $promptVersion, $request, $start) {
            $section->update(['body' => Purifier::clean($result->body)]);
            $variation->update(['model_used' => $result->model, 'prompt_version_id' => $promptVersion->id, 'error_message' => null]);
            $this->usageService->record(
                $request->user, $request, $variation, $result->model,
                'section_regeneration', $result->inputTokens, $result->outputTokens, $this->elapsedMs($start)
            );
        });
    }

    public function regenerateTitle(int $variationId): void
    {
        $variation = ContentVariation::findOrFail($variationId);
        $request = $variation->request;

        abort_if($variation->is_locked, 403, 'Locked variations cannot be regenerated.');
        abort_if($variation->status === VariationStatus::Final, 403, 'Final variations cannot be regenerated.');

        $this->revisionService->snapshot($variation, RevisionType::TitleRegeneration, auth()->id());

        $promptVersion = PromptTemplate::where('key', 'title_regeneration')->firstOrFail()->activeVersion()->firstOrFail();

        $titleRequest = new TitleRegenerationRequest(
            topic: $request->topic,
            primaryKeyword: $request->primary_keyword,
            tone: $request->tone,
            targetPersona: $request->target_persona,
            currentTitle: $variation->title ?? '',
            negativeContext: $this->negativeContext($request, $variation),
            promptContent: $promptVersion->content,
            model: config('ai.models.title_regeneration'),
            maxTokens: 2048,
        );

        $start = hrtime(true);

        try {
            $result = $this->provider->regenerateTitle($titleRequest);
        } catch (AICallException|AIResponseException $e) {
            $variation->update(['error_message' => 'Title regeneration failed: '.$e->getMessage()]);

            return;
        }

        DB::transaction(function () use ($variation, $result, $promptVersion, $request, $start) {
            $variation->update([
                'title' => $result->title,
                'slug' => $result->slug,
                'meta_title' => $result->metaTitle,
                'meta_description' => $result->metaDescription,
                'model_used' => $result->model,
                'prompt_version_id' => $promptVersion->id,
                'error_message' => null,
            ]);
            $this->usageService->record(
                $request->user, $request, $variation, $result->model,
                'title_regeneration', $result->inputTokens, $result->outputTokens, $this->elapsedMs($start)
            );
        });
    }

    public function lock(int $variationId): void
    {
        ContentVariation::findOrFail($variationId)->update(['is_locked' => true]);
    }

    public function unlock(int $variationId): void
    {
        ContentVariation::findOrFail($variationId)->update(['is_locked' => false]);
    }

    public function discard(int $variationId): void
    {
        $variation = ContentVariation::findOrFail($variationId);
        abort_if($variation->is_locked, 403, 'Unlock before discarding.');
        $variation->update(['status' => VariationStatus::Discarded]);
    }

    public function markFinal(int $variationId): void
    {
        ContentVariation::findOrFail($variationId)->update(['status' => VariationStatus::Final]);
    }

    public function regenerateUnlocked(ContentRequest $request): void
    {
        foreach ($request->unlockedVariations()->get() as $variation) {
            if ($variation->isRegenerable()) {
                RegenerateContentJob::dispatch($variation->id);
            }
        }
    }

    public function updateRequestStatus(ContentRequest $request): void
    {
        $request->refresh();

        $pending = $request->variations()
            ->where('status', VariationStatus::Pending)
            ->whereNull('error_message')
            ->count();

        if ($pending === 0) {
            // A Final variation implies a prior successful generation, so it
            // counts as a success anchor: convergence must never downgrade a
            // request whose variations are all Final/Discarded to "failed".
            $succeeded = $request->variations()
                ->whereIn('status', [VariationStatus::Generated, VariationStatus::Final])
                ->count();

            $request->update([
                'status' => $succeeded > 0 ? RequestStatus::Completed : RequestStatus::Failed,
                'error_message' => $succeeded > 0
                    ? null
                    : 'All variations failed. Check each variation for details.',
            ]);
        }
    }

    private function recordFailure(
        ContentRequest $request,
        ContentVariation $variation,
        string $model,
        string $operation,
        int $start,
        string $errorMessage,
    ): void {
        $this->usageService->record(
            $request->user, $request, $variation, $model,
            $operation, 0, 0, $this->elapsedMs($start), 'failed'
        );
        $variation->update(['error_message' => $errorMessage]);
    }

    private function runVariationCall(
        ContentRequest $request,
        ContentVariation $variation,
        PromptVersion $promptVersion,
        GenerationRequest $generationRequest,
        string $operation,
    ): void {
        $start = hrtime(true);

        try {
            try {
                $result = $this->provider->regenerateVariation($generationRequest);
            } catch (ValidationFailedException $e) {
                $repair = new GenerationRequest(
                    topic: $generationRequest->topic,
                    primaryKeyword: $generationRequest->primaryKeyword,
                    secondaryKeywords: $generationRequest->secondaryKeywords,
                    tone: $generationRequest->tone,
                    targetPersona: $generationRequest->targetPersona,
                    targetWordCount: $generationRequest->targetWordCount,
                    additionalInstructions: $generationRequest->additionalInstructions,
                    angle: $generationRequest->angle,
                    negativeContext: $generationRequest->negativeContext,
                    promptContent: $generationRequest->promptContent,
                    model: $generationRequest->model,
                    maxTokens: $generationRequest->maxTokens,
                    repairContext: json_encode($e->errors()),
                );
                try {
                    $result = $this->provider->regenerateVariation($repair);
                } catch (ValidationFailedException) {
                    $this->recordFailure($request, $variation, $generationRequest->model, $operation, $start, 'Response failed validation after repair');
                    $this->updateRequestStatus($request);

                    return;
                }
            }
        } catch (AICallException|AIResponseException $e) {
            $this->recordFailure($request, $variation, $generationRequest->model, $operation, $start, $e->getMessage());
            $this->updateRequestStatus($request);

            return;
        }

        $duration = $this->elapsedMs($start);

        DB::transaction(function () use ($variation, $result, $promptVersion, $request, $operation, $duration) {
            $variation->update([
                'title' => $result->title,
                'slug' => $result->slug,
                'excerpt' => $result->excerpt,
                'meta_title' => $result->metaTitle,
                'meta_description' => $result->metaDescription,
                'focus_keyword' => $result->focusKeyword,
                'secondary_keywords' => $result->secondaryKeywords,
                'category' => $result->category,
                'tags' => $result->tags,
                'og_title' => $result->ogTitle,
                'og_description' => $result->ogDescription,
                'faq' => $result->faq,
                'schema_type' => $result->schemaType,
                'status' => VariationStatus::Generated,
                'prompt_tokens' => $result->inputTokens,
                'completion_tokens' => $result->outputTokens,
                'model_used' => $result->model,
                'prompt_version_id' => $promptVersion->id,
                'raw_response' => $result->raw,
                'error_message' => null,
            ]);

            $variation->sections()->delete();

            foreach ($result->sections as $index => $section) {
                $variation->sections()->create([
                    'section_order' => $index + 1,
                    'heading' => $section['heading'],
                    'body' => Purifier::clean($section['body']),
                ]);
            }

            $this->usageService->record(
                $request->user, $request, $variation, $result->model,
                $operation, $result->inputTokens, $result->outputTokens, $duration
            );
        });

        // A regeneration can be the first successful write for a request
        // (e.g. an interrupted batch, or retry after all attempts failed),
        // so converge the request header instead of leaving stale state.
        $this->updateRequestStatus($request);
    }

    private function negativeContext(ContentRequest $request, ContentVariation $current): array
    {
        return $request->lockedVariations()
            ->where('id', '!=', $current->id)
            ->whereNotNull('title')
            ->get()
            ->map(fn ($v) => "Variation {$v->variation_number} ({$v->angle_type->label()}): {$v->title} — ".Str::limit($v->excerpt ?? '', 200))
            ->all();
    }

    private function maxTokens(int $wordCount): int
    {
        // Reasoning-capable providers count chain-of-thought tokens against
        // max_tokens. The smoke run observed a 1,500-word generation stop at
        // finish_reason=length with a 6,000-token budget, so the article
        // budget now includes explicit reasoning headroom. Capped at the
        // largest value the provider is known to accept.
        return min(32768, max(8192, (int) ceil($wordCount * 8) + 6000));
    }

    private function elapsedMs(int $start): int
    {
        return (int) ((hrtime(true) - $start) / 1_000_000);
    }
}
