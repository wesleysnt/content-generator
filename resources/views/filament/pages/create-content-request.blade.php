{{-- resources/views/filament/pages/create-content-request.blade.php --}}
<x-filament-panels::page>
    <form wire:submit="submit" class="max-w-2xl">
        {{ $this->form }}

        <x-filament::button type="submit" class="mt-4">
            Generate
        </x-filament::button>
    </form>
</x-filament-panels::page>
