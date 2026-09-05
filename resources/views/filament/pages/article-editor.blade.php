{{-- resources/views/filament/pages/article-editor.blade.php --}}
{{-- NOTE: brief specified x-filament::tabs / x-filament::tabs.item wrappers. In Filament v3.3.55
     tabs.item renders a tab *header button* whose slot is the label — it cannot host page content
     (forms inside <button> is invalid HTML). Also x-filament::textarea does not exist in this
     version and x-filament::input.wrapper has no label prop. Adapted minimally: content stacked in
     plain x-filament::section cards in the same order as the brief's tabs, with native labels and
     a <textarea> styled with the standard fi-input classes. --}}
<x-filament-panels::page>
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-lg font-semibold">{{ $record->title }}</h2>
            <p class="text-sm text-gray-500">
                Request #{{ $record->request->id }} · {{ $record->angle_type->label() }}
            </p>
        </div>
        <div class="flex gap-2">
            @if (!$isFinal)
                <x-filament::button wire:click="markFinal" color="success" size="sm">Mark Final</x-filament::button>
            @else
                <x-filament::badge color="success">FINAL</x-filament::badge>
            @endif
            @if (!$isLocked && !$isFinal)
                <x-filament::button wire:click="regenerateTitle" color="warning" size="sm">Regenerate Title</x-filament::button>
            @endif
        </div>
    </div>

    <div class="space-y-6">
        <x-filament::section heading="Content">
            <form wire:submit="save" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="editor-title" class="block text-sm font-medium text-gray-950 dark:text-white">Title</label>
                        <x-filament::input.wrapper class="mt-2">
                            <x-filament::input id="editor-title" type="text" wire:model="formState.title" />
                        </x-filament::input.wrapper>
                    </div>
                    <div>
                        <label for="editor-slug" class="block text-sm font-medium text-gray-950 dark:text-white">Slug</label>
                        <x-filament::input.wrapper class="mt-2">
                            <x-filament::input id="editor-slug" type="text" wire:model="formState.slug" />
                        </x-filament::input.wrapper>
                    </div>
                </div>
                <div>
                    <label for="editor-excerpt" class="block text-sm font-medium text-gray-950 dark:text-white">Excerpt</label>
                    <x-filament::input.wrapper class="mt-2">
                        <textarea
                            id="editor-excerpt"
                            wire:model="formState.excerpt"
                            rows="2"
                            class="fi-input block w-full border-none bg-white/0 py-1.5 ps-3 pe-3 text-base text-gray-950 transition duration-75 placeholder:text-gray-400 focus:ring-0 disabled:text-gray-500 disabled:placeholder:text-gray-400 dark:text-white dark:placeholder:text-gray-500 sm:text-sm sm:leading-6"
                        ></textarea>
                    </x-filament::input.wrapper>
                </div>

                <div class="space-y-4">
                    @foreach ($record->sections as $section)
                        <x-filament::section>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="editor-section-{{ $section->id }}-heading" class="block text-sm font-medium text-gray-950 dark:text-white">Heading</label>
                                    <x-filament::input.wrapper class="mt-2">
                                        <x-filament::input
                                            id="editor-section-{{ $section->id }}-heading"
                                            type="text"
                                            wire:model="sectionState.{{ $section->id }}.heading"
                                        />
                                    </x-filament::input.wrapper>
                                </div>
                                <div class="flex items-end justify-end">
                                    @if (!$isLocked && !$isFinal)
                                        <x-filament::button
                                            wire:click="regenerateSection({{ $section->id }})"
                                            size="sm"
                                            color="warning"
                                        >
                                            Regenerate Section
                                        </x-filament::button>
                                    @endif
                                </div>
                            </div>
                            <div class="mt-4">
                                <label for="editor-section-{{ $section->id }}-body" class="block text-sm font-medium text-gray-950 dark:text-white">Body (HTML)</label>
                                <x-filament::input.wrapper class="mt-2">
                                    <textarea
                                        id="editor-section-{{ $section->id }}-body"
                                        wire:model="sectionState.{{ $section->id }}.body"
                                        rows="8"
                                        class="fi-input block w-full border-none bg-white/0 py-1.5 ps-3 pe-3 text-base text-gray-950 transition duration-75 placeholder:text-gray-400 focus:ring-0 disabled:text-gray-500 disabled:placeholder:text-gray-400 dark:text-white dark:placeholder:text-gray-500 sm:text-sm sm:leading-6"
                                    ></textarea>
                                </x-filament::input.wrapper>
                            </div>
                        </x-filament::section>
                    @endforeach
                </div>

                <x-filament::button type="submit">Save</x-filament::button>
            </form>
        </x-filament::section>

        <x-filament::section heading="SEO" class="max-w-2xl">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="editor-meta-title" class="block text-sm font-medium text-gray-950 dark:text-white">Meta Title</label>
                    <x-filament::input.wrapper class="mt-2">
                        <x-filament::input id="editor-meta-title" type="text" wire:model="formState.meta_title" />
                    </x-filament::input.wrapper>
                </div>
                <div>
                    <label for="editor-meta-description" class="block text-sm font-medium text-gray-950 dark:text-white">Meta Description</label>
                    <x-filament::input.wrapper class="mt-2">
                        <textarea
                            id="editor-meta-description"
                            wire:model="formState.meta_description"
                            rows="3"
                            class="fi-input block w-full border-none bg-white/0 py-1.5 ps-3 pe-3 text-base text-gray-950 transition duration-75 placeholder:text-gray-400 focus:ring-0 disabled:text-gray-500 disabled:placeholder:text-gray-400 dark:text-white dark:placeholder:text-gray-500 sm:text-sm sm:leading-6"
                        ></textarea>
                    </x-filament::input.wrapper>
                </div>
                <div>
                    <label for="editor-focus-keyword" class="block text-sm font-medium text-gray-950 dark:text-white">Focus Keyword</label>
                    <x-filament::input.wrapper class="mt-2">
                        <x-filament::input id="editor-focus-keyword" type="text" wire:model="formState.focus_keyword" />
                    </x-filament::input.wrapper>
                </div>
                <div>
                    <label for="editor-category" class="block text-sm font-medium text-gray-950 dark:text-white">Category</label>
                    <x-filament::input.wrapper class="mt-2">
                        <x-filament::input id="editor-category" type="text" wire:model="formState.category" />
                    </x-filament::input.wrapper>
                </div>
                <div>
                    <label for="editor-og-title" class="block text-sm font-medium text-gray-950 dark:text-white">OG Title</label>
                    <x-filament::input.wrapper class="mt-2">
                        <x-filament::input id="editor-og-title" type="text" wire:model="formState.og_title" />
                    </x-filament::input.wrapper>
                </div>
                <div>
                    <label for="editor-og-description" class="block text-sm font-medium text-gray-950 dark:text-white">OG Description</label>
                    <x-filament::input.wrapper class="mt-2">
                        <x-filament::input id="editor-og-description" type="text" wire:model="formState.og_description" />
                    </x-filament::input.wrapper>
                </div>
                <div>
                    <label for="editor-schema-type" class="block text-sm font-medium text-gray-950 dark:text-white">Schema Type</label>
                    <x-filament::input.wrapper class="mt-2">
                        <x-filament::input id="editor-schema-type" type="text" wire:model="formState.schema_type" />
                    </x-filament::input.wrapper>
                </div>
            </div>
            <x-filament::button wire:click="save" class="mt-4">Save</x-filament::button>
        </x-filament::section>

        <x-filament::section heading="FAQ" class="max-w-2xl">
            @forelse ($record->faq ?? [] as $item)
                <div class="mb-3">
                    <p class="font-semibold">{{ $item['question'] }}</p>
                    <p class="text-sm text-gray-600">{{ $item['answer'] }}</p>
                </div>
            @empty
                <p class="text-sm text-gray-500">No FAQ entries.</p>
            @endforelse
        </x-filament::section>

        <x-filament::section heading="Revisions" class="max-w-2xl">
            @forelse ($record->revisions as $revision)
                <div class="flex justify-between items-center border-b py-2">
                    <div>
                        <p class="font-semibold">{{ $revision->revision_type->value }}</p>
                        <p class="text-xs text-gray-500">
                            {{ $revision->created_at->diffForHumans() }}
                            @if ($revision->creator) · {{ $revision->creator->name }} @endif
                        </p>
                    </div>
                    <x-filament::button
                        wire:click="restoreRevision({{ $revision->id }})"
                        size="sm"
                        color="gray"
                    >
                        Restore
                    </x-filament::button>
                </div>
            @empty
                <p class="text-sm text-gray-500">No revisions.</p>
            @endforelse
        </x-filament::section>

        @php
            // Payloads precomputed here: a blade {{ }} expression containing PHP
            // double-quoted string literals inside a component attribute breaks the
            // component attribute parser (compiled view gets an unbalanced @endif).
            $copyTitle = Illuminate\Support\Js::from($record->title);
            $copyArticle = Illuminate\Support\Js::from($record->sections->map(fn ($s) => '<h2>'.$s->heading.'</h2>'.$s->body)->implode("\n\n"));
            $copySeo = Illuminate\Support\Js::from("Meta Title: ".$record->meta_title."\nMeta Description: ".$record->meta_description."\nFocus Keyword: ".$record->focus_keyword."\nCategory: ".$record->category."\nTags: ".implode(', ', $record->tags ?? []));
            $copyAll = Illuminate\Support\Js::from(
                $record->title."\n\n".$record->slug."\n\nMeta Title: ".$record->meta_title."\nMeta Description: ".$record->meta_description."\nCategory: ".$record->category."\nTags: ".implode(', ', $record->tags ?? [])."\n\n".$record->sections->map(fn ($s) => '<h2>'.$s->heading.'</h2>'.$s->body)->implode("\n\n")
            );
        @endphp
        <x-filament::section heading="Copy" class="max-w-2xl">
            <p class="text-sm text-gray-600 mb-4">Copy content for manual publishing.</p>
            <div class="flex gap-2 flex-wrap">
                <x-filament::button
                    size="sm"
                    x-data
                    x-on:click="navigator.clipboard.writeText({{ $copyTitle }})"
                >
                    Copy Title
                </x-filament::button>
                <x-filament::button
                    size="sm"
                    x-data
                    x-on:click="navigator.clipboard.writeText({{ $copyArticle }})"
                >
                    Copy Article
                </x-filament::button>
                <x-filament::button
                    size="sm"
                    x-data
                    x-on:click="navigator.clipboard.writeText({{ $copySeo }})"
                >
                    Copy SEO
                </x-filament::button>
                <x-filament::button
                    size="sm"
                    x-data
                    x-on:click="navigator.clipboard.writeText({{ $copyAll }})"
                >
                    Copy All
                </x-filament::button>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
