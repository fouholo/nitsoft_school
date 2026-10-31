<?php

declare(strict_types=1);

use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Enums\PdfHeaderStyle;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\Foundation;
use App\Domain\Establishments\Models\GeneralInformation;
use App\Domain\Establishments\Models\Inspection;
use App\Livewire\Staff\ManageOrganization;
use App\Models\User;
use Livewire\Livewire;

/*
 * Choix de l'en-tête des documents — voir
 * docs/superpowers/specs/2026-10-31-choix-entete-documents-design.md.
 */

function renderHeader(Establishment $establishment): string
{
    return view('pdf.partials.reports-header', [
        'establishment' => $establishment->fresh(),
        'generalInformation' => GeneralInformation::create(['nom_ministere' => 'Ministère Test', 'annee_scolaire_courante' => '2026-2027']),
    ])->render();
}

function primarySchool(array $attributes = []): Establishment
{
    return Establishment::factory()->create([
        'name' => 'Ecole Test',
        'type' => EstablishmentType::PrescolairePrimaire,
        'inspection_id' => Inspection::create(['codeiep' => 'IEPHDR', 'inspection_name' => 'Abobo'])->id,
        ...$attributes,
    ]);
}

test('une école a le modèle officiel par défaut', function () {
    expect(Establishment::factory()->create()->fresh()->pdf_header_style)->toBe(PdfHeaderStyle::Official);
});

test('le modèle officiel garde le ministère, l’inspection et le nom de l’école', function () {
    $html = renderHeader(primarySchool(['logo_path' => 'logos/ecole.png']));

    expect($html)->toContain('MINISTÈRE TEST')
        ->toContain("INSPECTION DE L'ENSEIGNEMENT PRESCOLAIRE ET PRIMAIRE ABOBO")
        ->toContain('Ecole Test')
        ->toContain('height: 50px')
        ->toContain("REPUBLIQUE DE CÔTE D'IVOIRE");
});

test('le modèle logo affiche le logo en grand, sans le ministère ni le nom, et garde la colonne de droite', function () {
    $html = renderHeader(primarySchool(['logo_path' => 'logos/ecole.png', 'pdf_header_style' => PdfHeaderStyle::Logo]));

    expect($html)->toContain('logos/ecole.png')
        ->toContain('height: 100pt')
        ->not->toContain('MINISTÈRE TEST')
        ->not->toContain('INSPECTION')
        ->not->toContain('Ecole Test')
        ->toContain("REPUBLIQUE DE CÔTE D'IVOIRE")
        ->toContain('Union-Discipline-Travail')
        ->toContain('2026-2027');
});

test('le modèle logo affiche le nom de l’école quand elle n’a pas de logo', function () {
    $html = renderHeader(primarySchool(['logo_path' => null, 'pdf_header_style' => PdfHeaderStyle::Logo]));

    expect($html)->toContain('Ecole Test')
        ->not->toContain('<img src')
        ->not->toContain('MINISTÈRE TEST');
});

test('l’administrateur général d’une fondation choisit le modèle école par école', function () {
    $foundation = Foundation::factory()->create();
    $schoolA = Establishment::factory()->create(['foundation_id' => $foundation->id]);
    $schoolB = Establishment::factory()->create(['foundation_id' => $foundation->id]);
    $this->actingAs(createGeneralAdmin($foundation));

    Livewire::test(ManageOrganization::class)
        ->assertSee(__('En-tête des documents'))
        ->call('setPdfHeaderStyle', $schoolA->id, 'logo')
        ->assertHasNoErrors();

    expect($schoolA->fresh()->pdf_header_style)->toBe(PdfHeaderStyle::Logo)
        ->and($schoolB->fresh()->pdf_header_style)->toBe(PdfHeaderStyle::Official);
});

test('le fondateur d’une école indépendante voit son choix conservé à l’écran', function () {
    $school = Establishment::factory()->create(['foundation_id' => null]);
    $this->actingAs(createGeneralAdmin($school));

    Livewire::test(ManageOrganization::class)
        ->call('setPdfHeaderStyle', $school->id, 'logo')
        ->assertSeeHtml('value="logo" selected');

    expect($school->fresh()->pdf_header_style)->toBe(PdfHeaderStyle::Logo);
});

test('une école hors de l’organisation et une valeur inconnue sont refusées', function () {
    $foundation = Foundation::factory()->create();
    $school = Establishment::factory()->create(['foundation_id' => $foundation->id]);
    $outside = Establishment::factory()->create();
    $this->actingAs(createGeneralAdmin($foundation));

    Livewire::test(ManageOrganization::class)
        ->call('setPdfHeaderStyle', $outside->id, 'logo')
        ->assertNotFound();

    Livewire::test(ManageOrganization::class)
        ->call('setPdfHeaderStyle', $school->id, 'inconnu')
        ->assertStatus(422);

    expect($outside->fresh()->pdf_header_style)->toBe(PdfHeaderStyle::Official)
        ->and($school->fresh()->pdf_header_style)->toBe(PdfHeaderStyle::Official);
});

test('un fondateur qui n’est pas administrateur général ne peut pas choisir', function () {
    $foundation = Foundation::factory()->create();
    $school = Establishment::factory()->create(['foundation_id' => $foundation->id]);

    createGeneralAdmin($foundation);
    $otherFounder = User::factory()->create();
    $foundation->users()->attach($otherFounder->id, ['role' => 'fondateur', 'is_active' => true]);
    $this->actingAs($otherFounder);

    Livewire::test(ManageOrganization::class)
        ->assertDontSee(__('En-tête des documents'))
        ->call('setPdfHeaderStyle', $school->id, 'logo')
        ->assertForbidden();

    expect($school->fresh()->pdf_header_style)->toBe(PdfHeaderStyle::Official);
});

test('un directeur n’accède pas au réglage', function () {
    $school = Establishment::factory()->create();
    $this->actingAs(createUserWithRole($school, 'directeur'));

    Livewire::test(ManageOrganization::class)->assertForbidden();
});
