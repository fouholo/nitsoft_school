<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Services;

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\Term;
use App\Domain\Billing\Services\PaymentTrackingService;
use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Scopes\EstablishmentScope;
use App\Domain\Grading\Models\ReportCard;
use Illuminate\Support\Collection;

/**
 * Points d'attention du tableau de bord (fondateur/directeur/gestionnaire) —
 * voir docs/superpowers/specs/2026-08-21-tableau-de-bord-fondateur-design.md.
 * Chaque méthode retourne une liste plate, indépendamment du nombre
 * d'établissements passés — jamais fusionnée par établissement, pour que
 * l'appelant puisse simplement concaténer les 4 types d'alerte.
 */
final class DashboardAlertsService
{
    /**
     * @param  list<int>  $establishmentIds
     * @return list<array{type: string, label: string, establishment_id: int, establishmentName: string, link: ?string}>
     */
    public function overdueInvoices(array $establishmentIds): array
    {
        if ($establishmentIds === []) {
            return [];
        }

        $schoolYearId = SchoolYear::where('is_current', true)->value('id');

        if ($schoolYearId === null) {
            return [];
        }

        $establishmentNames = $this->establishmentNames($establishmentIds);
        $trackingService = app(PaymentTrackingService::class);

        $items = [];

        foreach ($establishmentIds as $establishmentId) {
            $overdue = $trackingService->balances((int) $schoolYearId, null, null, $establishmentId)
                ->where('balance', '>', 0);

            if ($overdue->isEmpty()) {
                continue;
            }

            $items[] = [
                'type' => 'overdue_invoices',
                'label' => __(':count facture(s) en retard — :amount restant à percevoir', [
                    'count' => $overdue->count(),
                    'amount' => money((float) $overdue->sum('balance')),
                ]),
                'establishment_id' => $establishmentId,
                'establishmentName' => $establishmentNames->get($establishmentId, '—'),
                'link' => $this->linkForCurrentEstablishmentOnly($establishmentId, 'billing.payment-tracking.index'),
            ];
        }

        return $items;
    }

    /**
     * @param  list<int>  $establishmentIds
     * @return list<array{type: string, label: string, establishment_id: int, establishmentName: string, link: ?string}>
     */
    public function classroomsWithoutTeacher(array $establishmentIds): array
    {
        if ($establishmentIds === []) {
            return [];
        }

        $establishmentNames = $this->establishmentNames($establishmentIds);

        $classrooms = Classroom::withoutTenant()
            ->whereIn('establishment_id', $establishmentIds)
            ->whereHas('schoolYear', fn ($query) => $query->where('is_current', true))
            // whereDoesntHave() construit sa sous-requête sur le modèle lié
            // (TeacherAssignment), qui porte lui aussi TenantScoped : sans
            // withoutGlobalScope() ici, la sous-requête se limiterait
            // implicitement à app('currentEstablishmentId') et ignorerait
            // les affectations des autres écoles du groupe. withoutTenant()
            // ferait la même chose mais Larastan ne résout pas ce scope
            // nommé sur un builder générique de closure.
            ->whereDoesntHave('teacherAssignments', fn ($query) => $query->withoutGlobalScope(EstablishmentScope::class))
            ->get();

        return $classrooms->map(fn (Classroom $classroom): array => [
            'type' => 'classroom_without_teacher',
            'label' => __(':name : aucun enseignant affecté', ['name' => $classroom->name]),
            'establishment_id' => $classroom->establishment_id,
            'establishmentName' => $establishmentNames->get($classroom->establishment_id, '—'),
            'link' => $this->linkForCurrentEstablishmentOnly($classroom->establishment_id, 'academics.teacher-assignments.index'),
        ])->all();
    }

    /**
     * @param  list<int>  $establishmentIds
     * @return list<array{type: string, label: string, establishment_id: int, establishmentName: string, link: ?string}>
     */
    public function classroomsOverCapacity(array $establishmentIds): array
    {
        if ($establishmentIds === []) {
            return [];
        }

        $establishmentNames = $this->establishmentNames($establishmentIds);

        $classrooms = Classroom::withoutTenant()
            ->whereIn('establishment_id', $establishmentIds)
            ->whereHas('schoolYear', fn ($query) => $query->where('is_current', true))
            ->whereNotNull('capacity')
            // Même piège que classroomsWithoutTeacher() : Enrollment porte
            // TenantScoped, la sous-requête de comptage doit explicitement
            // s'en affranchir pour compter les inscriptions des autres
            // écoles du groupe.
            ->withCount(['enrollments' => fn ($query) => $query->withoutGlobalScope(EstablishmentScope::class)->where('status', 'active')])
            ->get()
            ->filter(fn (Classroom $classroom): bool => $classroom->enrollments_count > $classroom->capacity);

        return $classrooms->map(fn (Classroom $classroom): array => [
            'type' => 'classroom_over_capacity',
            'label' => __(':name : :count/:capacity élèves', [
                'name' => $classroom->name,
                'count' => $classroom->enrollments_count,
                'capacity' => $classroom->capacity,
            ]),
            'establishment_id' => $classroom->establishment_id,
            'establishmentName' => $establishmentNames->get($classroom->establishment_id, '—'),
            'link' => $this->linkForCurrentEstablishmentOnly($classroom->establishment_id, 'academics.classrooms.index'),
        ])->values()->all();
    }

    /**
     * Ne concerne que les établissements Secondaire : seul ce cycle est
     * découpé en Term (ends_on) et produit des ReportCard liés à un term_id
     * — le préscolaire/primaire utilise composition_number à la place (voir
     * ReportCard::$term docblock), sans notion de période "terminée"
     * comparable.
     *
     * @param  list<int>  $establishmentIds
     * @return list<array{type: string, label: string, establishment_id: int, establishmentName: string, link: ?string}>
     */
    public function unfinalizedReportCards(array $establishmentIds): array
    {
        if ($establishmentIds === []) {
            return [];
        }

        $schoolYearId = SchoolYear::where('is_current', true)->value('id');

        if ($schoolYearId === null) {
            return [];
        }

        $secondaireEstablishmentIds = Establishment::whereIn('id', $establishmentIds)
            ->where('type', EstablishmentType::Secondaire)
            ->pluck('id')
            ->all();

        if ($secondaireEstablishmentIds === []) {
            return [];
        }

        $establishmentNames = $this->establishmentNames($establishmentIds);

        $endedTerms = Term::withoutTenant()
            ->whereIn('establishment_id', $secondaireEstablishmentIds)
            ->where('school_year_id', $schoolYearId)
            ->where('ends_on', '<', now())
            ->get();

        if ($endedTerms->isEmpty()) {
            return [];
        }

        $items = [];

        foreach ($endedTerms as $term) {
            $classrooms = Classroom::withoutTenant()
                ->where('establishment_id', $term->establishment_id)
                ->where('school_year_id', $term->school_year_id)
                ->gradable()
                ->get();

            foreach ($classrooms as $classroom) {
                $studentIds = $classroom->enrollments()->withoutGlobalScope(EstablishmentScope::class)->where('status', 'active')->pluck('student_id');

                if ($studentIds->isEmpty()) {
                    continue;
                }

                $finalizedStudentIds = ReportCard::withoutTenant()
                    ->where('term_id', $term->id)
                    ->whereIn('student_id', $studentIds)
                    ->whereNotNull('generated_at')
                    ->pluck('student_id');

                $missingCount = $studentIds->diff($finalizedStudentIds)->count();

                if ($missingCount === 0) {
                    continue;
                }

                $items[] = [
                    'type' => 'unfinalized_report_cards',
                    'label' => __(':name — :term : :count bulletin(s) non finalisé(s)', [
                        'name' => $classroom->name,
                        'term' => $term->label,
                        'count' => $missingCount,
                    ]),
                    'establishment_id' => $term->establishment_id,
                    'establishmentName' => $establishmentNames->get($term->establishment_id, '—'),
                    'link' => $this->linkForCurrentEstablishmentOnly($term->establishment_id, 'grading.report-cards.index'),
                ];
            }
        }

        return $items;
    }

    /**
     * @param  list<int>  $establishmentIds
     * @return Collection<int, string>
     */
    private function establishmentNames(array $establishmentIds): Collection
    {
        return Establishment::whereIn('id', $establishmentIds)->pluck('name', 'id');
    }

    /**
     * Un lien vers un écran de gestion n'est fiable que pour l'établissement
     * couramment sélectionné dans le switcher : ces écrans lisent
     * app('currentEstablishmentId') depuis la session, pas depuis l'URL. Une
     * alerte sur une autre école du groupe (fondateur multi-écoles) reste
     * donc affichée mais sans lien cliquable, plutôt que de pointer vers les
     * données de la mauvaise école.
     */
    private function linkForCurrentEstablishmentOnly(int $establishmentId, string $routeName): ?string
    {
        if (! app()->bound('currentEstablishmentId') || (int) app('currentEstablishmentId') !== $establishmentId) {
            return null;
        }

        return route($routeName);
    }
}
