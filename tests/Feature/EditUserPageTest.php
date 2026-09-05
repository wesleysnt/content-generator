<?php

declare(strict_types=1);

use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('does not offer deleting the account you are signed in as', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $admin->id])
        ->assertActionHidden('delete');

    expect(User::find($admin->id))->not->toBeNull();
});

it('refuses to demote the account you are signed in as', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $admin->id])
        ->fillForm(['role' => 'writer'])
        ->call('save')
        ->assertHasNoFormErrors();

    // A lone admin who demotes themselves would lock the panel shut; the
    // self role change is silently reverted instead.
    expect($admin->fresh()->role)->toBe('admin');
});

it('still lets an admin change another admins role', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $other = User::factory()->create(['role' => 'admin']);

    Livewire::actingAs($admin)
        ->test(EditUser::class, ['record' => $other->id])
        ->fillForm(['role' => 'writer'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($other->fresh()->role)->toBe('writer');
    expect($admin->fresh()->role)->toBe('admin');
});
