# AI Content Automation System — Planning Doc
*Last updated: September 5, 2026*

## Overview
An internal Laravel admin tool that lets content writers generate blog-ready content using AI. A writer submits a prompt (topic, keywords, tone, etc.) and requests one or more content variations. The AI generates each variation as a complete draft — a title, body broken into sections, and SEO metadata. The writer reviews the variations, locks the ones worth keeping, regenerates the rest (in whole or in part), edits as needed, then copies the final result out to wherever the blog is actually hosted.

## Roles
- **Writer** — creates content requests, reviews and edits AI output, decides what to keep or regenerate. Handles the whole content loop end to end.
- **Admin** — manages writer accounts and system-level settings. No separate editor/approval role.

## System flow
1. **Writer input** — submits a content request: topic, keywords, tone, audience, and how many variations to generate.
2. **Generate** — a queued background job sends one single-pass call to OpenAI *per variation* (not one call producing all of them at once, since quality and distinctiveness drop off past 2–3 in a single response). Each call returns a structured result: title, body sections, and SEO fields.
3. **Review & select** — writer compares the generated variations side by side. For each one, they can:
   - Lock it to keep and edit
   - Discard it and regenerate the whole thing
   - Regenerate just the title
   - Regenerate just one section of the body, leaving the rest untouched
4. **Output** — no publishing integration. The writer copies the finished title, body, and metadata manually into wherever the blog actually lives.

## Confirmed requirements

### Stack & provider
- Laravel as the main stack
- OpenAI as the sole AI provider — no multi-provider or failover setup
- Model set manually in code/config, not chosen dynamically — no budget or model-tier logic to build

### Generation
- Single-pass per variation; writer chooses how many variations to generate
- Variations must differ from each other in wording *and* idea, not just phrasing — each generation call should be told what angles the earlier variations in the same batch already took

### Review & regeneration
- Writer can lock/save a variation, discard and regenerate it, or regenerate just its title or one section
- No image generation for now (budget)

### Roles & access
- Two roles only: admin, writer
- No dynamic budget tracking or per-writer cost limits

### Publishing
- Out of scope for now. Output is copy-paste only — no CMS, webhook, or API integration

## Proposed data model
A starting point — the exact SEO fields still need to be nailed down (see Open questions), so `content_variations` includes a flexible JSON column for whatever gets added later.

| Table | Purpose | Key columns |
|---|---|---|
| `users` | Writers and admins | `id`, `name`, `email`, `role` (admin/writer) |
| `content_requests` | One writer submission | `id`, `user_id`, `topic`, `keywords`, `tone`, `audience`, `variation_count`, `status` |
| `content_variations` | One generated draft (N per request) | `id`, `content_request_id`, `variation_number`, `title`, `meta_description`, `slug`, `tags`, `additional_seo` (json), `status` (generated/locked/discarded), `model_used` |
| `content_sections` | Ordered body chunks of a variation | `id`, `content_variation_id`, `section_order`, `heading`, `body` |

Regenerating "just a section" means re-running generation for one `content_sections` row, passing in the request's original prompt plus the variation's other sections as context so it stays consistent. Regenerating "just the title" targets the `title` column on `content_variations` the same way.

## Technical notes
- Queue every generation call — even single-pass generation can take 10–60+ seconds, and title/section regeneration should queue the same way
- Store the raw AI response alongside the parsed fields for each generation — useful for debugging and for tuning prompts later
- A "copy" button per field (title, each section, meta description, etc.) is worth adding given the copy-paste output — saves the writer from hand-selecting text
- Admin panel: worth considering Filament for this, since it's mostly CRUD and review screens with only two roles to support

## Open questions
- **Input & prompting** — exactly which fields the writer fills in beyond topic/keywords/tone/audience, whether there are reusable templates per content type, and whether the AI should follow a brand voice/style guide
- **SEO fields** — the exact list needed (meta title, meta description, slug, focus keyword, OG tags, schema markup, etc.), and whether any fact-checking guardrail is needed for AI-generated claims or statistics

## Assumptions made (flag if any are wrong)
- Writers handle the whole loop themselves (generate → review → edit → output); admin is for account/system management only, not content review
- "No image for now" extends to skipping alt-text/image-prompt suggestions too, not just actual image generation
