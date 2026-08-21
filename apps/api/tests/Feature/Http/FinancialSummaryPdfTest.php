<?php

declare(strict_types=1);

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\Expense;
use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\Services\FinancialSummaryService;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\GeneralInformation;

test('un directeur peut consulter le bilan financier en ligne', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);
    SchoolYear::factory()->create(['is_current' => true]);

    $response = $this->actingAs($directeur)->get(route('reports.financial-summary-pdf'));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

test('un enseignant sans accès à la facturation ne peut pas générer le bilan', function () {
    $establishment = Establishment::factory()->create();
    $teacher = createUserWithRole($establishment, 'enseignant');
    actingInEstablishment($establishment);
    SchoolYear::factory()->create(['is_current' => true]);

    $response = $this->actingAs($teacher)->get(route('reports.financial-summary-pdf'));

    $response->assertForbidden();
});

test('une plage personnalisée invalide renvoie 404 plutôt qu’un PDF vide', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);

    $response = $this->actingAs($directeur)->get(route('reports.financial-summary-pdf', [
        'start_date' => '2026-02-10',
        'end_date' => '2026-01-01',
    ]));

    $response->assertNotFound();
});

test('sans année scolaire courante ni plage fournie, le bilan renvoie 404', function () {
    $establishment = Establishment::factory()->create();
    $directeur = createUserWithRole($establishment, 'directeur');
    actingInEstablishment($establishment);

    $response = $this->actingAs($directeur)->get(route('reports.financial-summary-pdf'));

    $response->assertNotFound();
});

/**
 * @param  list<array{role: string|null, roleLabel: string, rows: list<array<string, mixed>>, collected: float, spent: float, net: float}>  $groups
 */
function renderFinancialSummaryHtml(array $groups, Establishment $establishment, string $periodLabel = 'Année 2026-2027'): string
{
    return view('pdf.financial-summary', [
        'establishment' => $establishment,
        'generalInformation' => GeneralInformation::current(),
        'groups' => $groups,
        'totalCollected' => (float) array_sum(array_column($groups, 'collected')),
        'totalSpent' => (float) array_sum(array_column($groups, 'spent')),
        'totalNet' => (float) array_sum(array_column($groups, 'net')),
        'periodLabel' => $periodLabel,
    ])->render();
}

test('le PDF affiche les groupes de rôle, le détail par utilisateur et le total général', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $directeur = createUserWithRole($establishment, 'directeur');
    Payment::factory()->create(['establishment_id' => $establishment->id, 'received_by' => $directeur->id, 'amount' => 4000, 'paid_at' => now()]);

    $service = new FinancialSummaryService;
    $summary = $service->summaryByUser(now()->subDay(), now()->addDay());
    $groups = $service->groupByRole($summary);

    $html = renderFinancialSummaryHtml($groups, $establishment);

    expect($html)->toContain('BILAN FINANCIER')
        ->and($html)->toContain('Directeur')
        ->and($html)->toContain(e($directeur->name))
        ->and($html)->toContain('Total général');
});

test('un message d’état vide s’affiche quand aucun groupe n’existe sur la période', function () {
    $establishment = Establishment::factory()->create();
    actingInEstablishment($establishment);

    $html = renderFinancialSummaryHtml([], $establishment);

    expect($html)->toContain('Aucun encaissement ni dépense enregistré sur cette période.');
});

test('isolation tenant : les mouvements d’un autre établissement n’entrent pas dans l’agrégation', function () {
    $establishmentA = Establishment::factory()->create();
    $establishmentB = Establishment::factory()->create();

    actingInEstablishment($establishmentA);
    $userA = createUserWithRole($establishmentA, 'directeur');
    Payment::factory()->create(['establishment_id' => $establishmentA->id, 'received_by' => $userA->id, 'amount' => 1000, 'paid_at' => now()]);

    actingInEstablishment($establishmentB);
    $userB = createUserWithRole($establishmentB, 'directeur');
    Payment::factory()->create(['establishment_id' => $establishmentB->id, 'received_by' => $userB->id, 'amount' => 9000, 'paid_at' => now()]);
    Expense::factory()->create(['establishment_id' => $establishmentB->id, 'recorded_by' => $userB->id, 'amount' => 500, 'spent_at' => now()->toDateString()]);

    $summary = (new FinancialSummaryService)->summaryByUser(now()->subDay(), now()->addDay());

    expect($summary)->toHaveCount(1)
        ->and($summary[0]['user_id'])->toBe($userB->id);
});

test('un éducateur consultant le bilan par HTTP ne récupère que ses propres mouvements', function () {
    $establishment = Establishment::factory()->create();
    $educator = createUserWithRole($establishment, 'educateur');
    $otherEducator = createUserWithRole($establishment, 'educateur');
    actingInEstablishment($establishment);
    SchoolYear::factory()->create(['is_current' => true]);

    Payment::factory()->create(['establishment_id' => $establishment->id, 'received_by' => $educator->id, 'amount' => 1000, 'paid_at' => '2026-10-01']);
    Payment::factory()->create(['establishment_id' => $establishment->id, 'received_by' => $otherEducator->id, 'amount' => 9000, 'paid_at' => '2026-10-01']);

    $response = $this->actingAs($educator)->get(route('reports.financial-summary-pdf'));

    $response->assertOk();
});
