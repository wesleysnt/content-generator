{{-- resources/views/filament/pages/generation-workspace.blade.php --}}
<x-filament-panels::page>
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-lg font-semibold">Content Request #{{ $record->id }}</h2>
            <p class="text-sm text-gray-500">{{ $record->topic }} — {{ $record->variations->count() }} variations</p>
        </div>
        @if ($hasUnlocked)
            <x-filament::button wire:click="regenerateUnlocked" color="warning">
                Regenerate Unlocked
            </x-filament::button>
        @endif
    </div>

    @if ($isProcessing)
        <x-filament::section class="mb-4" wire:poll.5s="refreshRecord">
            <div class="text-sm font-medium">
                Generating {{ $record->variations->where('status', \App\Enums\VariationStatus::Pending)->count() }}
                variation(s) remaining…
            </div>
        </x-filament::section>
    @endif

    @if ($record->error_message)
        <x-filament::section class="mb-4" icon="heroicon-o-exclamation-triangle" icon-color="danger">
            {{ $record->error_message }}
        </x-filament::section>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach ($record->variations as $variation)
            <x-filament::section>
                <div class="flex items-center justify-between mb-2">
                    <x-filament::badge color="gray">
                        #{{ $variation->variation_number }} · {{ $variation->angle_type->label() }}
                    </x-filament::badge>

                    <div class="flex gap-1">
                        @if ($variation->is_locked)
                            <x-filament::badge color="success">LOCKED</x-filament::badge>
                        @elseif ($variation->status === \App\Enums\VariationStatus::Discarded)
                            <x-filament::badge color="danger">DISCARDED</x-filament::badge>
                        @elseif ($variation->status === \App\Enums\VariationStatus::Final)
                            <x-filament::badge color="primary">FINAL</x-filament::badge>
                        @elseif ($variation->status === \App\Enums\VariationStatus::Pending && $variation->error_message !== null)
                            <x-filament::badge color="danger">FAILED</x-filament::badge>
                        @elseif ($variation->status === \App\Enums\VariationStatus::Pending)
                            <x-filament::badge color="warning">GENERATING</x-filament::badge>
                        @endif
                    </div>
                </div>

                @if ($variation->error_message)
                    <p class="text-sm text-red-600">{{ $variation->error_message }}</p>
                    @if ($variation->isRegenerable())
                        <div class="mt-2">
                            <x-filament::button wire:click="retryVariation({{ $variation->id }})" size="sm" color="danger">
                                Retry
                            </x-filament::button>
                        </div>
                    @endif
                @endif

                @if ($variation->status === \App\Enums\VariationStatus::Pending && $variation->error_message === null)
                    <p class="text-sm text-gray-500">Waiting for generation job…</p>
                @elseif (in_array($variation->status, [
                    \App\Enums\VariationStatus::Generated,
                    \App\Enums\VariationStatus::Final,
                    \App\Enums\VariationStatus::Discarded,
                ], true))
                    <h3 class="font-semibold mb-1">{{ $variation->title }}</h3>
                    <p class="text-sm text-gray-600 mb-3 line-clamp-3">{{ $variation->excerpt }}</p>
                    <p class="text-xs text-gray-400 mb-3">{{ $variation->sections->count() }} sections · {{ number_format($variation->prompt_tokens + $variation->completion_tokens) }} tokens</p>
                @endif

                <div class="flex gap-2 flex-wrap">
                    @if (class_exists(\App\Filament\Pages\ArticleEditor::class) && in_array($variation->status, [\App\Enums\VariationStatus::Generated, \App\Enums\VariationStatus::Final], true))
                        <x-filament::button
                            tag="a"
                            href="{{ App\Filament\Pages\ArticleEditor::getUrl(['record' => $variation->id]) }}"
                            size="sm"
                        >
                            Open
                        </x-filament::button>
                    @endif

                    @if ($variation->is_locked)
                        <x-filament::button wire:click="unlock({{ $variation->id }})" size="sm" color="gray">
                            Unlock
                        </x-filament::button>
                    @elseif ($variation->status === \App\Enums\VariationStatus::Generated)
                        <x-filament::button wire:click="lock({{ $variation->id }})" size="sm" color="success">
                            Lock
                        </x-filament::button>
                        <x-filament::button wire:click="regenerateVariation({{ $variation->id }})" size="sm" color="warning">
                            Regenerate
                        </x-filament::button>
                        <x-filament::button wire:click="discard({{ $variation->id }})" size="sm" color="danger">
                            Discard
                        </x-filament::button>
                    @endif
                </div>
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
