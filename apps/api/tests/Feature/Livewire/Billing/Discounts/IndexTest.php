<?php

declare(strict_types=1);

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\Discount;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Livewire\Billing\Discounts\Index;
use Livewire\Livewire;

test('un directeur accorde une réduction à un élève', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->call('create')
        ->set('student_id', $student->id)
        ->set('type', 'percentage')
        ->set('value', 20)
        ->set('reason', 'Fratrie')
        ->call('save')
        ->assertHasNoErrors();

    $discount = Discount::sole();

    expect($discount->student_id)->toBe($student->id)
        ->and($discount->type)->toBe('percentage')
        ->and((float) $discount->value)->toBe(20.0)
        ->and($discount->reason)->toBe('Fratrie')
        ->and($discount->created_by)->toBe($directeur->id);
});

test('accorder une nouvelle réduction au même élève/année remplace l’ancienne', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);

    Discount::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'school_year_id' => $schoolYear->id,
        'type' => 'percentage',
        'value' => 10,
    ]);

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->call('create')
        ->set('student_id', $student->id)
        ->set('type', 'fixed_amount')
        ->set('value', 5000)
        ->call('save')
        ->assertHasNoErrors();

    expect(Discount::count())->toBe(1);

    $discount = Discount::sole();
    expect($discount->type)->toBe('fixed_amount')
        ->and((float) $discount->value)->toBe(5000.0);
});

test('un directeur supprime une réduction', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    test()->actingAs($directeur);

    $schoolYear = SchoolYear::factory()->create(['establishment_id' => $establishment->id, 'is_current' => true]);
    $student = Student::factory()->create(['establishment_id' => $establishment->id]);
    $discount = Discount::factory()->create([
        'establishment_id' => $establishment->id,
        'student_id' => $student->id,
        'school_year_id' => $schoolYear->id,
    ]);

    Livewire::test(Index::class)
        ->set('school_year_id', $schoolYear->id)
        ->call('delete', $discount->id);

    expect(Discount::find($discount->id))->toBeNull();
});

test('un enseignant n’a aucun accès à l’écran des réductions', function () {
    $establishment = Establishment::factory()->create();
    $teacher = createUserWithRole($establishment, 'enseignant');
    actingInEstablishment($establishment);
    test()->actingAs($teacher);

    Livewire::test(Index::class)->assertForbidden();
});
