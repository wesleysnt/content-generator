<?php

declare(strict_types=1);

use App\Enums\RequestStatus;
use App\Filament\Pages\ListContentRequests;
use App\Models\ContentRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('colors each status badge with its own color', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    foreach ([RequestStatus::Processing, RequestStatus::Completed, RequestStatus::Failed] as $status) {
        ContentRequest::create([
            'user_id' => $admin->id,
            'topic' => "Topic {$status->value}",
            'primary_keyword' => 'kw',
            'status' => $status->value,
        ]);
    }

    $html = Livewire::actingAs($admin)->test(ListContentRequests::class)->html();

    // Failed/completed used to fall through the enum-to-string comparison
    // and render every badge as the warning color.
    expect(substr_count($html, 'fi-color-danger'))->toBe(1);
    expect(substr_count($html, 'fi-color-success'))->toBe(1);
    expect(substr_count($html, 'fi-color-warning'))->toBe(1);
});
