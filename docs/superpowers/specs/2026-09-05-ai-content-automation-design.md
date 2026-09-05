# AI Content Automation System — V1 Design (Merged)

*Date: 2026-09-05*
*Status: Approved design, pre-implementation*

This spec merges three planning documents:

1. `AI Content Automation System — V1 Technical Design.md` (79-section product + technical design)
2. `ai_content_automation_system_planning.md` (Laravel 11 + Filament 3 + webhook publishing spec)
3. `ai-content-automation-plan.md` (per-variation single-pass planning doc with `content_sections` model)

**Hard requirement:** DeepSeek replaces OpenAI as the sole AI provider.

---

## 1. Source-Document Analysis

### Doc 1 — "AI Content Automation System — V1 Technical Design"

**Pluses**

- Complete, coherent product definition with explicit V1 exclusions
- First-class research component with persisted sources and quality rules
- Full revision history: snapshot per change, restore creates a new revision
- Semantic similarity engine with pair scores, thresholds, and flag-not-delete
- Prompt management with immutable versioning and per-generation `prompt_version_id`
- Strong architectural rules: AI is not authoritative, Laravel controls state, validation, costs
- Security depth: HTML allowlist sanitization, prompt-injection protection
- Cost protection: max variations, max word count, per-user limits
- Clear ERD, models, service architecture, testing strategy, sprint plan

**Minuses**

- OpenAI-specific: Responses API web search + strict JSON-schema structured outputs. Neither exists on DeepSeek in the same form.
- One-call-produces-all-variations strategy — docs 2 and 3 both argue quality and diversity degrade past 2–3 variations in a single response.
- Similarity engine assumes an embeddings API; DeepSeek offers no embedding endpoint, so it would require a third-party paid provider.
- No angle/negative-context diversity mechanism — post-hoc similarity scoring instead of diversity enforcement at generation time.
- Separate `seo_metadata` table adds joins without much benefit when revisions snapshot everything anyway.

### Doc 2 — "ai_content_automation_system_planning.md"

**Pluses**

- Concrete migrations and Eloquent models ready to use
- Filament 3 choice — right tool for an internal admin CRUD app
- Distinct angle per job (`angle_type`) with temperature control
- Locked variations as explicit negative prompt context — diversity enforced at generation time, not measured after
- Per-variation token accounting (`prompt_tokens`, `completion_tokens`)
- Parallel batch jobs via Horizon

**Minuses**

- Webhook publishing to external CMS — scope creep for a system described as internal-only with manual publishing
- No revision history — every regeneration permanently destroys the previous version
- No web research, no prompt versioning, no usage dashboard, no cost protection
- `image_prompts` — contradicts doc 3's assumption that "no image for now" includes skipping image-prompt suggestions
- Hardcoded OpenAI `json_schema` strict mode — unavailable on DeepSeek's stable API

### Doc 3 — "ai-content-automation-plan.md"

**Pluses**

- `content_sections` table — ordered body chunks make section-level regeneration clean instead of splitting HTML
- Explicit rationale for one-call-per-variation: quality and distinctiveness drop past 2–3 in a single response
- Variation-to-variation diversity instruction (tell each call what angles earlier variations took)
- Store raw AI response for debugging and prompt tuning
- Per-field copy buttons for manual publishing
- Honest open-questions list (SEO field list, brand voice, fact-checking)

**Minuses**

- Skeleton-level detail: no validation strategy, no retry/idempotency policy, no security treatment
- No revision history
- No usage/cost tracking (explicitly ruled out — but token-level logging is cheap and needed for tuning)
- No prompt versioning
- Model name set in code, no per-operation model config

---

## 2. Product Definition

Internal admin application for content writers. A writer submits a brief, the system generates N substantially different article variations via parallel queued jobs, the writer locks promising variations, regenerates the rest (whole article, title, or single section), edits manually, and copies the final result into the company's external publishing system by hand.

**V1 does not include:** external publishing API or webhooks, automatic publication, AI image generation (including image prompts), file uploads, RAG knowledge base, multi-provider AI failover, editorial approval workflow, web research, social media generation, dynamic budget tracking.

**Roles:** Admin (users, prompts, AI settings, usage, system config) and Writer (briefs, generation, editing, locking, revisions, copy). Laravel Policies, not scattered role checks.

---

## 3. Technology Stack

| Layer | Choice |
|---|---|
| Backend | Laravel 11, PHP 8.2+ |
| Database | MySQL (PostgreSQL acceptable) |
| Queue | Laravel Horizon, Redis driver |
| Admin UI | Filament 3 panels (users, prompts, usage, settings) |
| Workspace/editor UI | Custom Livewire 3 + Alpine + Tailwind pages |
| AI provider | DeepSeek API (`https://api.deepseek.com`, OpenAI-compatible), via `openai-php/client` with base URL override |
| Rich text editor | Filament's TipTap integration |

---

## 4. DeepSeek Provider Constraints (verified 2026-09)

These constraints shaped the design and must stay in mind during implementation:

1. **No strict schema mode.** DeepSeek supports `response_format: {"type": "json_object"}` only — guaranteed *valid JSON syntax*, not schema adherence. Prompt must include the word "json" and an explicit shape example; forbid Markdown fences. Laravel-side schema + business validation is mandatory on every response.
2. **Known quirks.** Occasionally empty `content`; truncation on low `max_tokens`. Always check `finish_reason == "stop"`; handle `"length"` as failure/retry. Strict tool-calling exists in beta (`/beta` endpoint) — not used in V1.
3. **No embedding endpoint.** Semantic similarity would require a third-party paid provider. V1 therefore enforces diversity at generation time (angles + negative context) instead of measuring it after.
4. **Models.** `deepseek-v4-pro` (default), `deepseek-v4-flash`, `deepseek-chat` (V3.2). Configurable per operation via `config/ai.php` + env. Pricing ≈ $0.28/M input, $0.42/M output — per-variation calls are affordable.
5. **Web search exists but is a black box** (Responses API only, `deepseek-v4-flash` only, sources not returned to client). V1 skips research; the `ResearchService` seam remains for a later phase (third-party search API if provenance is ever needed).

---

## 5. Data Model

```text
users ──< content_requests ──< content_variations ──< content_sections
                                 └────< content_revisions

prompt_templates ──< prompt_versions
ai_usage_logs
```

### `content_requests`

One writer submission. Columns: `id`, `user_id` (FK), `topic`, `primary_keyword`, `secondary_keywords` (JSON), `tone`, `target_persona`, `target_word_count`, `variation_count`, `additional_instructions`, `status` (`draft`, `queued`, `processing`, `completed`, `failed`), `error_message`, timestamps.

### `content_variations`

One generated draft (N per request). Columns: `id`, `content_request_id` (FK), `angle_type`, `variation_number`, `title`, `slug`, `excerpt`, `meta_title`, `meta_description`, `focus_keyword`, `secondary_keywords` (JSON), `category`, `tags` (JSON), `og_title`, `og_description`, `faq` (JSON), `schema_type`, `status` (`pending`, `generated`, `discarded`, `final`), `is_locked` (bool, independent of status), `prompt_tokens`, `completion_tokens`, `model_used`, `prompt_version_id` (FK), `raw_response` (JSON, for debugging/tuning), `error_message`, timestamps.

SEO is inline on the variation (doc 2's approach) — simpler than doc 1's separate `seo_metadata` table, and revisions snapshot everything anyway. Index `(content_request_id, status, is_locked)`.

### `content_sections`

Ordered body chunks (doc 3's model — clean section-level regeneration, no HTML splitting). Columns: `id`, `content_variation_id` (FK), `section_order`, `heading`, `body`.

### `content_revisions`

Full snapshot per meaningful change. Columns: `id`, `content_variation_id` (FK), `snapshot` (JSON: title, slug, excerpt, content sections, SEO, taxonomy, FAQ), `revision_type` (`ai_generation`, `ai_regeneration`, `section_regeneration`, `title_regeneration`, `writer_edit`, `restore`), `created_by`, `created_at`. A snapshot is created *before* every AI overwrite and on writer save. Restore creates a new revision — old revisions are never physically overwritten.

### `prompt_templates` / `prompt_versions`

`prompt_templates`: `id`, `key` (`generation`, `regeneration`, `section_regeneration`, `title_regeneration`), `name`, `description`. `prompt_versions`: `id`, `prompt_template_id` (FK), `version`, `content`, `is_active`, timestamps. Versions are immutable; edits create a new version. Every generation stores its `prompt_version_id` for reproducibility.

### `ai_usage_logs`

`id`, `user_id` (FK), `content_request_id` (FK), `content_variation_id` (FK nullable), `provider`, `model`, `operation`, `input_tokens`, `output_tokens`, `total_tokens`, `estimated_cost`, `duration_ms`, `status`, `metadata` (JSON), `created_at`. Raw tokens always stored; cost computed from configurable pricing so historical records survive pricing changes.

---

## 6. AI Contract

### JSON shape (one shape for generation; subsets for regeneration ops)

```json
{
  "title": "string",
  "slug": "string",
  "excerpt": "string",
  "meta_title": "string",
  "meta_description": "string",
  "focus_keyword": "string",
  "secondary_keywords": ["string"],
  "category": "string",
  "tags": ["string"],
  "og_title": "string",
  "og_description": "string",
  "faq": [{"question": "string", "answer": "string"}],
  "schema_type": "Article",
  "sections": [{"heading": "string", "body": "string (HTML)"}]
}
```

Section regeneration returns a single replacement section object; title regeneration returns title/slug/meta_title.

### Provider abstraction

```php
interface AIProvider
{
    public function generateVariation(GenerationRequest $request): VariationResult;
    public function regenerateVariation(RegenerationRequest $request): VariationResult;
    public function regenerateSection(SectionRegenerationRequest $request): SectionResult;
    public function regenerateTitle(TitleRegenerationRequest $request): TitleResult;
}
```

Implementations: `DeepSeekProvider` (production), `FakeAIProvider` (tests, no API cost). Bound via Laravel container.

### Request construction

```text
SYSTEM INSTRUCTIONS
+ COMPANY SETTINGS
+ CONTENT BRIEF
+ ANGLE RULES
+ NEGATIVE CONTEXT (locked variations)
+ OUTPUT SHAPE + JSON INSTRUCTION
```

- Must contain the word "json", an example of the exact shape, "Return only valid JSON. No Markdown, no code fences."
- `max_tokens` sized to target word count; `temperature` 0.7–0.85 for generation.
- System prompt states: locked variations are constraints, not templates; each variation is an independently authored candidate — no mechanical paraphrasing (doc 1 §73).

### Response handling

1. Check `finish_reason == "stop"` (else fail/retry)
2. Handle empty `content` (known DeepSeek quirk) — retry once
3. Parse JSON, validate schema, validate business rules
4. One repair retry: send validation errors back as context, ask for corrected JSON
5. Still invalid → variation `failed` with human-readable error + retry button. Never store malformed output.

---

## 7. Generation Workflow

### Batch generation

1. Writer submits brief. Validation: variation count ≤ admin-configurable max (default 10), word count ≤ max.
2. Create `content_requests` row + N `content_variations` rows upfront (status pending), each assigned a distinct angle from the pool: Educational, Problem-Solution, Practical Guide, Data-Driven, Story/Case Study, Expert Analysis, Common Mistakes, Listicle, Comparison, FAQ-Driven. No angle repeats within a batch (pool size 10 = max variation count).
3. Dispatch N `GenerateContentJob` to Redis; Horizon runs them in parallel.
4. Each job: load active prompt version → build prompt (brief + angle rules + negative context from locked variations of this request) → DeepSeek call → validate → persist variation + sections + usage log + initial revision (`ai_generation`) in one DB transaction → mark variation generated.
5. Batch completion check: when all N finish, request → `completed`; if any fail after retries, request → `failed`, each failed variation carries its own error + retry.

### Negative context (diversity at generation time)

When regenerating unlocked variations, inject locked variations' titles + excerpts: "Do NOT duplicate these hooks, titles, or narrative structures: [...]". This replaces the similarity engine (doc 1) — diversity is enforced, not measured. DeepSeek has no embedding endpoint, and this costs nothing.

### Regeneration ops

- **Whole variation** (`RegenerateContentJob`): snapshot revision first, then new job with same angle + negative context. Never destroys the previous result.
- **Section** (`RegenerateSectionJob`): prompt = brief + variation's other sections as context + target section heading. Returns replacement section only. Snapshot first.
- **Title** (`RegenerateTitleJob`): targets title + slug + meta_title. Snapshot first.

All regeneration ops queue (10–60s+ calls never run synchronously).

### Lifecycle

```text
request:   draft → queued → processing → completed / failed
variation: generated → locked / discarded / final
```

Locked variations are untouched by "Regenerate Unlocked". Discarded are excluded from regeneration.

---

## 8. Validation Rules (Laravel side)

Schema: all required fields present, correct types, `sections` non-empty, FAQ items well-formed.

Business (thresholds configurable, not hardcoded):
- `meta_title` ≤ 60 chars (warning), `meta_description` ≤ 160 (warning)
- Word count within tolerance of target
- Focus keyword present in title and body
- Heading structure sane (no empty bodies)

HTML sanitization: allowlist (h1–h3, p, strong, em, ul, ol, li, blockquote, a) before render — AI output is untrusted.

---

## 9. Queue, Retry, Idempotency

- Transient failures (timeout, rate limit, 5xx): 3 queue retries, backoff 10s / 30s / 120s.
- Validation failures: no auto-retry (permanent).
- Idempotency: job keyed to variation id; re-dispatch checks current status, never double-generates. Persist phase inside `DB::transaction`.
- Never expose raw provider errors or secrets to writers — map to friendly messages.

---

## 10. Security

- Laravel session auth, CSRF, rate limiting; Filament auth scaffolding.
- Policies: `ContentRequestPolicy`, `ContentVariationPolicy` (writer owns content; admin manages users/prompts/settings/usage).
- No untrusted external input in V1 (no web research). System prompt still instructs: any externally sourced content is data, not instructions — future-proofing for the research phase.
- Prompt content may contain company information: store prompt/responses deliberately — raw responses stored on the variation for tuning, but `ai_usage_logs` stores metadata only.

---

## 11. Cost Protection

Admin-configurable in settings:
- Maximum variations per request (default 10, UI offers 1/3/5/10)
- Maximum target word count
- Maximum regenerations per minute per user
- Maximum monthly generations per user

With DeepSeek pricing (~$0.28/M in, $0.42/M out), a 5-variation × 2000-word batch costs cents — but per-user caps still protect against runaway use.

---

## 12. Prompt Management

- Four templates: `generation`, `regeneration`, `section_regeneration`, `title_regeneration`
- Admin Filament UI: list versions, edit creates a new immutable version, set active
- Generation records store `prompt_version_id` → reproducible generations, tunable without deploys

Generation prompt responsibilities: write blog article honoring context/theme/tone; respect audience and word count; return strict structured output; treat every variation as independently authored — maximize structural, stylistic, linguistic difference; no paraphrasing.

---

## 13. UX (Custom Livewire Pages)

### Brief form
Topic, primary keyword, secondary keywords, tone, target persona, variation count (1/3/5/10), target word count, additional instructions.

### Generation workspace
- Progress steps: Brief validated → Queued → Researching (future) → Generating N variations (live count) → Validating → Ready. Polled via Livewire; no generic 60s spinner.
- Variation cards side-by-side: angle badge, title, excerpt, status, [Open] [Lock] [Unlock] [Discard] [Regenerate].
- `[Regenerate Unlocked]` batch button (visible when ≥1 unlocked variation exists).

### Article editor
Tabs: Content (sections, each with [Regenerate section]), SEO (all fields editable), FAQ, Revisions (view diff / restore), Copy tools: [Copy Title] [Copy Article] [Copy SEO] [Copy All] (Copy All = title, slug, meta title, meta description, category, tags, article, FAQ).

### Filament resources
Users, Prompt templates/versions, AI Usage, Settings.

---

## 14. Usage Dashboard (Admin)

This month: generations, variations, tokens (input/output), estimated cost, top writers by usage. Computed from `ai_usage_logs`. Cost computed from configurable model pricing — raw tokens stored, so historical costs stay correct if pricing changes.

---

## 15. Testing Strategy

- **Unit:** PromptBuilder, validation rules, cost calculation, revision logic, angle assignment.
- **Feature:** brief CRUD, generate flow (with FakeAIProvider), lock/unlock, regenerate unlocked, section/title regeneration, restore revision, copy endpoints.
- **AI contract tests:** stored fake responses — valid, missing field, malformed JSON, truncated (`finish_reason: length`), empty content, too many sections, invalid SEO. All against `FakeAIProvider`.
- **Integration:** small env-gated suite (`DEEPSEEK_RUN_LIVE_TESTS=true`) hitting the real API — validates the JSON contract before any UI work (approach A's core idea).

---

## 16. Build Order (Approach A — AI contract first)

1. **AI contract:** freeze DB model + JSON schema + prompt templates → migrations; build `DeepSeekProvider` + `FakeAIProvider`; run live integration tests against DeepSeek until the JSON contract is reliable. *This is the risky part — DeepSeek's `json_object` guarantees syntax, not shape.*
2. **Pipeline:** jobs, queue, validation, retries, revisions, usage logging.
3. **UI:** Filament panel, brief form, workspace, editor, copy tools.
4. **Admin:** prompt management, usage dashboard, settings/limits.

---

## 17. MVP Acceptance Criteria

A writer can:

1. Create a brief (topic, keywords, tone, persona, variation count, word count)
2. Click Generate → N parallel queued variations with distinct angles, each with title, sections, SEO, FAQ
3. See progress steps while generating
4. Lock/discard variations; regenerate unlocked only, with locked content as negative context
5. Regenerate a whole variation, a single section, or the title — previous version always recoverable
6. Edit every field manually
7. View and restore any revision
8. Copy title/article/SEO/all for manual publishing
9. (Admin) edit prompt versions, view usage + cost, configure limits — all without code deploys

---

## 18. Most Important Architectural Rule

DeepSeek generates and suggests. Laravel validates, stores, and controls — state, permissions, status, locking, revisions, limits, costs. The AI is powerful but never authoritative. The writer makes the final decision.
