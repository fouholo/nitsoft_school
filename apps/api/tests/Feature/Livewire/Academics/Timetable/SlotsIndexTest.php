<?php

declare(strict_types=1);

use App\Domain\Establishments\Models\Establishment;
use App\Domain\Timetable\Models\TimetableSlot;
use App\Livewire\Academics\Timetable\SlotsIndex;
use Livewire\Livewire;

test('un admin habilité peut créer, modifier et supprimer un créneau', function () {
    $establishment = Establishment::factory()->create();
    $admin = createLocalAdmin($establishment, 'gestionnaire');
    actingInEstablishment($establishment);
    $this->actingAs($admin);

    Livewire::test(SlotsIndex::class)
        ->call('create')
        ->set('label', '1')
        ->set('start_time', '08:00')
        ->set('end_time', '09:00')
        ->set('sequence', 1)
        ->call('save')
        ->assertHasNoErrors();

    $slot = TimetableSlot::sole();
    expect($slot->label)->toBe('1')
        ->and($slot->sequence)->toBe(1)
        ->and($slot->is_break)->toBeFalse();

    Livewire::test(SlotsIndex::class)
        ->call('edit', $slot->id)
        ->set('label', '1 bis')
        ->call('save')
        ->assertHasNoErrors();

    expect($slot->fresh()->label)->toBe('1 bis');

    Livewire::test(SlotsIndex::class)
        ->call('delete', $slot->id);

    expect(TimetableSlot::count())->toBe(0);
});

test('un utilisateur sans le pouvoir timetable.manage ne peut pas créer de créneau', function () {
    $establishment = Establishment::factory()->create();
    $cashier = createLocalAdmin($establishment, 'caissier');
    actingInEstablishment($establishment);
    $this->actingAs($cashier);

    Livewire::test(SlotsIndex::class)
        ->call('create')
        ->assertForbidden();

    expect(TimetableSlot::count())->toBe(0);
});

test('la grille de créneaux est cloisonnée par établissement', function () {
    $establishmentA = Establishment::factory()->create();
    $establishmentB = Establishment::factory()->create();

    actingInEstablishment($establishmentA);
    TimetableSlot::create([
        'establishment_id' => $establishmentA->id,
        'label' => '1',
        'start_time' => '08:00',
        'end_time' => '09:00',
        'sequence' => 1,
    ]);

    $adminB = createLocalAdmin($establishmentB, 'directeur');
    actingInEstablishment($establishmentB);
    $this->actingAs($adminB);

    Livewire::test(SlotsIndex::class)
        ->assertViewHas('timetableSlots', fn ($timetableSlots) => $timetableSlots->isEmpty());
});
