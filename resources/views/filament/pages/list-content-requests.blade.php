{{-- resources/views/filament/pages/list-content-requests.blade.php --}}
<x-filament-panels::page>
    <div class="mb-4">
        <x-filament::input.select wire:model.live="statusFilter" class="max-w-xs">
            <option value="">All statuses</option>
            @foreach ($statuses as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </x-filament::input.select>
    </div>

    <x-filament::section>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b text-left">
                    <th class="p-2">#</th>
                    <th class="p-2">Topic</th>
                    <th class="p-2">Writer</th>
                    <th class="p-2">Status</th>
                    <th class="p-2">Variations</th>
                    <th class="p-2">Created</th>
                    <th class="p-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($requests as $request)
                    <tr class="border-b hover:bg-gray-50 dark:hover:bg-gray-800">
                        <td class="p-2">{{ $request->id }}</td>
                        <td class="p-2">{{ $request->topic }}</td>
                        <td class="p-2">{{ $request->user->name }}</td>
                        <td class="p-2">
                            <x-filament::badge :color="$request->status === \App\Enums\RequestStatus::Failed ? 'danger' : ($request->status === \App\Enums\RequestStatus::Completed ? 'success' : 'warning')">
                                {{ $request->status->value }}
                            </x-filament::badge>
                        </td>
                        <td class="p-2">{{ $request->variations->count() }}</td>
                        <td class="p-2">{{ $request->created_at->diffForHumans() }}</td>
                        <td class="p-2">
                            <x-filament::button
                                tag="a"
                                href="{{ App\Filament\Pages\GenerationWorkspace::getUrl(['record' => $request->id]) }}"
                                size="sm"
                            >
                                Open
                            </x-filament::button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-4 text-center text-gray-500">No content requests yet.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="mt-4">{{ $requests->links() }}</div>
    </x-filament::section>
</x-filament-panels::page>
