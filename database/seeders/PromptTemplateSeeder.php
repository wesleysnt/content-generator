<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PromptTemplate;
use Illuminate\Database\Seeder;

class PromptTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            'generation' => [
                'name' => 'Blog Generation',
                'description' => 'Generates one complete article variation from a brief.',
                'content' => <<<'PROMPT'
You are a professional blog writer. Write one complete blog article.

TOPIC: {{topic}}
PRIMARY KEYWORD: {{primary_keyword}}
SECONDARY KEYWORDS: {{secondary_keywords}}
TONE: {{tone}}
TARGET AUDIENCE: {{target_persona}}
TARGET WORD COUNT: {{target_word_count}} words
ANGLE: {{angle}}
ADDITIONAL INSTRUCTIONS: {{additional_instructions}}

NEGATIVE CONTEXT — do NOT duplicate these hooks, titles, or narrative structures from locked variations:
{{negative_context}}

RULES:
- Treat this as an independently authored candidate. Do not mechanically paraphrase another article.
- Maximize structural, stylistic, and linguistic differences within your angle.
- Use the primary and secondary keywords naturally; do not stuff.
- Break the body into 4-8 sections with clear headings.
- Write the section bodies as HTML using only these tags: h1, h2, h3, p, strong, em, ul, ol, li, blockquote, a.
- Do not invent statistics or quote specific figures without qualification.
- meta_title must be 60 characters or fewer. meta_description between 120 and 160 characters.

Return ONLY valid JSON matching this exact shape. No Markdown, no code fences, no explanatory text:
{
  "title": "",
  "slug": "",
  "excerpt": "",
  "meta_title": "",
  "meta_description": "",
  "focus_keyword": "",
  "secondary_keywords": [],
  "category": "",
  "tags": [],
  "og_title": "",
  "og_description": "",
  "faq": [{"question": "", "answer": ""}],
  "schema_type": "Article",
  "sections": [{"heading": "", "body": ""}]
}
PROMPT,
            ],
            'regeneration' => [
                'name' => 'Article Regeneration',
                'description' => 'Regenerates a whole variation, same angle, new wording.',
                'content' => <<<'PROMPT'
You are a professional blog writer. Rewrite an article variation from scratch — substantially different wording and structure, same topic and angle.

TOPIC: {{topic}}
PRIMARY KEYWORD: {{primary_keyword}}
SECONDARY KEYWORDS: {{secondary_keywords}}
TONE: {{tone}}
TARGET AUDIENCE: {{target_persona}}
TARGET WORD COUNT: {{target_word_count}} words
ANGLE: {{angle}}
ADDITIONAL INSTRUCTIONS: {{additional_instructions}}

NEGATIVE CONTEXT — do NOT duplicate these hooks, titles, or narrative structures:
{{negative_context}}

RULES:
- Treat this as an independently authored candidate, not a paraphrase of any previous version.
- Break the body into 4-8 sections with clear headings.
- Write the section bodies as HTML using only these tags: h1, h2, h3, p, strong, em, ul, ol, li, blockquote, a.
- meta_title must be 60 characters or fewer. meta_description between 120 and 160 characters.

Return ONLY valid JSON matching this exact shape. No Markdown, no code fences, no explanatory text:
{
  "title": "",
  "slug": "",
  "excerpt": "",
  "meta_title": "",
  "meta_description": "",
  "focus_keyword": "",
  "secondary_keywords": [],
  "category": "",
  "tags": [],
  "og_title": "",
  "og_description": "",
  "faq": [{"question": "", "answer": ""}],
  "schema_type": "Article",
  "sections": [{"heading": "", "body": ""}]
}
PROMPT,
            ],
            'section_regeneration' => [
                'name' => 'Section Regeneration',
                'description' => 'Rewrites one section of an existing article.',
                'content' => <<<'PROMPT'
You are a professional blog editor. Rewrite ONLY the body of the section specified below, inside an existing article. Do not change the heading.

ARTICLE CONTEXT (other sections — for style and consistency, do not modify them):
{{context_sections}}

SECTION TO REWRITE:
Heading: {{heading}}
Current body: {{current_body}}

BRIEF:
TOPIC: {{topic}}
PRIMARY KEYWORD: {{primary_keyword}}
TONE: {{tone}}
TARGET AUDIENCE: {{target_persona}}

RULES:
- Return only the replacement body HTML for this section.
- Stay consistent with the surrounding sections' style, terminology, and length.
- Use the primary keyword naturally at least once.
- Write the body as HTML using only these tags: h1, h2, h3, p, strong, em, ul, ol, li, blockquote, a.

Return ONLY valid JSON matching this exact shape. No Markdown, no code fences, no explanatory text:
{"heading": "{{heading}}", "body": ""}
PROMPT,
            ],
            'title_regeneration' => [
                'name' => 'Title Regeneration',
                'description' => 'Rewrites the title, slug, and meta fields only.',
                'content' => <<<'PROMPT'
You are a professional blog editor. Rewrite ONLY the title, slug, meta title, and meta description for an existing article. The body stays unchanged.

CURRENT TITLE: {{current_title}}

BRIEF:
TOPIC: {{topic}}
PRIMARY KEYWORD: {{primary_keyword}}
TONE: {{tone}}
TARGET AUDIENCE: {{target_persona}}

NEGATIVE CONTEXT — do NOT duplicate these titles from locked variations:
{{negative_context}}

RULES:
- The new title must differ substantially from the current title.
- Include the primary keyword.
- meta_title must be 60 characters or fewer. meta_description between 120 and 160 characters.

Return ONLY valid JSON matching this exact shape. No Markdown, no code fences, no explanatory text:
{"title": "", "slug": "", "meta_title": "", "meta_description": ""}
PROMPT,
            ],
        ];

        foreach ($templates as $key => $template) {
            $prompt = PromptTemplate::updateOrCreate(['key' => $key], [
                'name' => $template['name'],
                'description' => $template['description'],
            ]);

            // Versions are immutable (I5), so a reseed must not rewrite an
            // existing prompt; it only restores version 1 when the template
            // has no versions left at all.
            if ($prompt->versions()->doesntExist()) {
                $prompt->versions()->create([
                    'version' => 1,
                    'content' => $template['content'],
                    'is_active' => true,
                ]);
            }
        }
    }
}
