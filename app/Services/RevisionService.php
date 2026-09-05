<?php

declare(strict_types=1);

// app/Services/RevisionService.php

namespace App\Services;

use App\Enums\RevisionType;
use App\Models\ContentRevision;
use App\Models\ContentVariation;
use Illuminate\Support\Facades\DB;

class RevisionService
{
    public function snapshot(ContentVariation $variation, RevisionType $type, ?int $userId): ContentRevision
    {
        return $variation->revisions()->create([
            'snapshot' => $this->buildSnapshot($variation),
            'revision_type' => $type,
            'created_by' => $userId,
        ]);
    }

    public function restore(ContentVariation $variation, ContentRevision $revision, ?int $userId): void
    {
        // A revision is only restorable onto the variation whose snapshot it
        // holds; otherwise one article could be overwritten with another's
        // historical content.
        abort_if($revision->content_variation_id !== $variation->id, 404, 'Revision does not belong to this variation.');

        $this->snapshot($variation, RevisionType::Restore, $userId);

        $snapshot = $revision->snapshot;

        DB::transaction(function () use ($variation, $snapshot) {
            $variation->update([
                'title' => $snapshot['title'],
                'slug' => $snapshot['slug'],
                'excerpt' => $snapshot['excerpt'],
                'meta_title' => $snapshot['meta_title'],
                'meta_description' => $snapshot['meta_description'],
                'focus_keyword' => $snapshot['focus_keyword'],
                'secondary_keywords' => $snapshot['secondary_keywords'],
                'category' => $snapshot['category'],
                'tags' => $snapshot['tags'],
                'og_title' => $snapshot['og_title'],
                'og_description' => $snapshot['og_description'],
                'faq' => $snapshot['faq'],
                'schema_type' => $snapshot['schema_type'],
            ]);

            $variation->sections()->delete();

            foreach ($snapshot['sections'] as $index => $section) {
                $variation->sections()->create([
                    'section_order' => $index + 1,
                    'heading' => $section['heading'],
                    'body' => $section['body'],
                ]);
            }
        });
    }

    private function buildSnapshot(ContentVariation $variation): array
    {
        return [
            'title' => $variation->title,
            'slug' => $variation->slug,
            'excerpt' => $variation->excerpt,
            'meta_title' => $variation->meta_title,
            'meta_description' => $variation->meta_description,
            'focus_keyword' => $variation->focus_keyword,
            'secondary_keywords' => $variation->secondary_keywords,
            'category' => $variation->category,
            'tags' => $variation->tags,
            'og_title' => $variation->og_title,
            'og_description' => $variation->og_description,
            'faq' => $variation->faq,
            'schema_type' => $variation->schema_type,
            'sections' => $variation->sections()->orderBy('section_order')->get()
                ->map(fn ($s) => ['heading' => $s->heading, 'body' => $s->body])
                ->values()
                ->all(),
        ];
    }
}
