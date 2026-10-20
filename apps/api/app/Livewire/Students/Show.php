<?php

declare(strict_types=1);

namespace App\Livewire\Students;

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Billing\Services\LevelFeeDefaultsService;
use App\Domain\Billing\Services\PaymentTrackingService;
use App\Domain\Enrollment\Enums\GuardianLinkStatus;
use App\Domain\Enrollment\Enums\GuardianRelationship;
use App\Domain\Enrollment\Enums\Track;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Enrollment\Models\Guardian;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Support\RolePermissions;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Student $student;

    public bool $showEnrollmentForm = false;

    /**
     * Filière principale de l'inscription (Track), proposée seulement dans
     * une école franco-arabe.
     */
    public string $main_track = 'francais';

    public ?int $classroom_id = null;

    public ?int $arabic_classroom_id = null;

    public ?int $school_year_id = null;

    public string $enrolled_on = '';

    public bool $is_repeating = false;

    public bool $is_arabic_repeating = false;

    public bool $is_scholarship = false;

    public bool $is_boarding = false;

    public bool $is_assigned = false;

    /**
     * Inscription dont on modifie la classe secondaire (formulaire en ligne).
     */
    public ?int $secondaryEnrollmentId = null;

    public ?int $secondary_classroom_id = null;

    public bool $secondary_is_repeating = false;

    public bool $showGuardianForm = false;

    public ?int $guardian_id = null;

    public ?string $relationship = null;

    public bool $is_primary_contact = false;

    public string $guardianSearch = '';

    public function mount(Student $student): void
    {
        $this->authorize('view', $student);

        $this->student = $student->loadMissing('nationalite');
    }

    public function addEnrollment(): void
    {
        $this->authorize('create', Enrollment::class);

        $this->resetErrorBag();
        $this->main_track = Track::Francais->value;
        $this->classroom_id = null;
        $this->arabic_classroom_id = null;
        $this->school_year_id = SchoolYear::where('is_current', true)->value('id');
        $this->enrolled_on = now()->toDateString();
        $this->is_repeating = false;
        $this->is_arabic_repeating = false;
        $this->is_scholarship = false;
        $this->is_boarding = false;
        $this->is_assigned = false;
        $this->showEnrollmentForm = true;
    }

    /**
     * Inscription dans une classe principale (française, ou arabe dans une
     * école franco-arabe) et, facultativement, dans une classe secondaire de
     * l'autre filière — voir
     * docs/superpowers/specs/2026-10-20-classes-arabes-design.md. Les frais
     * sont préremplis depuis la grille de la filière principale.
     */
    public function saveEnrollment(LevelFeeDefaultsService $levelFeeDefaults): void
    {
        $this->authorize('create', Enrollment::class);

        $isArabe = $this->currentEstablishmentIsArabe();

        // Défense en profondeur : hors école franco-arabe, la filière est
        // toujours française et aucune classe arabe n'est acceptée, quoi
        // qu'envoie le navigateur.
        if (! $isArabe) {
            $this->main_track = Track::Francais->value;
            $this->arabic_classroom_id = null;
            $this->is_arabic_repeating = false;
        }

        $data = $this->validate([
            'main_track' => ['required', Rule::enum(Track::class)],
            // Une seule inscription par élève et par année (contrainte unique
            // en base, inscriptions retirées ou supprimées en douceur
            // comprises) : message clair plutôt qu'une erreur SQL.
            'school_year_id' => [
                'required',
                'exists:school_years,id',
                Rule::unique('enrollments', 'school_year_id')->where('student_id', $this->student->id),
            ],
            'classroom_id' => [
                Rule::requiredIf(fn () => $this->main_track === Track::Francais->value),
                'nullable',
                $this->classroomExistsRule('classrooms'),
            ],
            'arabic_classroom_id' => [
                Rule::requiredIf(fn () => $this->main_track === Track::Arabe->value),
                'nullable',
                $this->classroomExistsRule('arabic_classrooms'),
            ],
            'enrolled_on' => ['required', 'date'],
            'is_repeating' => ['boolean'],
            'is_arabic_repeating' => ['boolean'],
            'is_scholarship' => ['boolean'],
            'is_boarding' => ['boolean'],
            'is_assigned' => ['boolean'],
        ], [
            'school_year_id.unique' => __('Cet élève a déjà une inscription pour cette année scolaire.'),
        ], [
            'main_track' => __('filière principale'),
            'classroom_id' => $isArabe ? __('classe française') : __('classe'),
            'arabic_classroom_id' => __('classe arabe'),
            'school_year_id' => __('année scolaire'),
            'enrolled_on' => __("date d'inscription"),
        ]);

        $track = Track::from($data['main_track']);
        $classroom = $data['classroom_id'] ? Classroom::findOrFail($data['classroom_id']) : null;
        $arabicClassroom = $data['arabic_classroom_id'] ? ArabicClassroom::findOrFail($data['arabic_classroom_id']) : null;

        // Défense en profondeur : ces statuts ne sont proposés côté vue que
        // pour une classe française du secondaire (et, pour boursier /
        // interne / affecté, seulement si elle est la classe principale) ;
        // on ignore toute valeur transmise hors de ce cas plutôt que de faire
        // confiance au client.
        $frenchSecondaire = $classroom?->level?->cycle === Cycle::Secondaire;
        $mainFrenchSecondaire = $frenchSecondaire && $track === Track::Francais;

        $data['is_repeating'] = $frenchSecondaire && $data['is_repeating'];
        $data['is_arabic_repeating'] = $arabicClassroom !== null && $data['is_arabic_repeating'];
        $data['is_scholarship'] = $mainFrenchSecondaire && $data['is_scholarship'];
        $data['is_boarding'] = $mainFrenchSecondaire && $data['is_boarding'];
        $data['is_assigned'] = $mainFrenchSecondaire && $data['is_assigned'];

        $financialDefaults = $track === Track::Arabe && $arabicClassroom !== null
            ? $levelFeeDefaults->arabicDefaultsFor($data['school_year_id'], $arabicClassroom->arabic_level_id)
            : $levelFeeDefaults->defaultsFor($data['school_year_id'], (int) $classroom?->level_id, $data['is_assigned']);

        $this->student->enrollments()->create([
            ...$data,
            ...$financialDefaults,
            'status' => 'active',
        ]);

        $this->showEnrollmentForm = false;
    }

    public function cancelEnrollment(): void
    {
        $this->resetErrorBag();
        $this->showEnrollmentForm = false;
    }

    /**
     * Ajout, changement ou retrait de la classe secondaire après
     * l'inscription (école franco-arabe) ; les frais ne changent pas, ils
     * dépendent de la classe principale.
     */
    public function editSecondaryClassroom(int $enrollmentId): void
    {
        $enrollment = Enrollment::findOrFail($enrollmentId);

        $this->authorize('update', $enrollment);
        abort_unless($this->currentEstablishmentIsArabe(), 403);

        $this->resetErrorBag();
        $this->secondaryEnrollmentId = $enrollment->id;
        $this->secondary_classroom_id = $enrollment->secondaryClassroom()?->id;
        $this->secondary_is_repeating = $enrollment->mainTrack() === Track::Francais
            ? (bool) $enrollment->is_arabic_repeating
            : (bool) $enrollment->is_repeating;
    }

    public function saveSecondaryClassroom(): void
    {
        $enrollment = Enrollment::findOrFail((int) $this->secondaryEnrollmentId);

        $this->authorize('update', $enrollment);
        abort_unless($this->currentEstablishmentIsArabe(), 403);

        $secondaryTrack = $enrollment->mainTrack()->other();
        $table = $secondaryTrack === Track::Arabe ? 'arabic_classrooms' : 'classrooms';

        $data = $this->validate([
            'secondary_classroom_id' => [
                'nullable',
                Rule::exists($table, 'id')
                    ->where('establishment_id', $enrollment->establishment_id)
                    ->where('school_year_id', $enrollment->school_year_id),
            ],
            'secondary_is_repeating' => ['boolean'],
        ], [], [
            'secondary_classroom_id' => __('classe secondaire'),
        ]);

        $classroomId = $data['secondary_classroom_id'] ?: null;

        if ($secondaryTrack === Track::Arabe) {
            $enrollment->update([
                'arabic_classroom_id' => $classroomId,
                'is_arabic_repeating' => $classroomId !== null && $data['secondary_is_repeating'],
            ]);
        } else {
            $isSecondaire = $classroomId !== null
                && Classroom::find($classroomId)?->level?->cycle === Cycle::Secondaire;

            $enrollment->update([
                'classroom_id' => $classroomId,
                'is_repeating' => $isSecondaire && $data['secondary_is_repeating'],
            ]);
        }

        $this->cancelSecondaryClassroom();
    }

    public function cancelSecondaryClassroom(): void
    {
        $this->resetErrorBag();
        $this->reset(['secondaryEnrollmentId', 'secondary_classroom_id', 'secondary_is_repeating']);
    }

    /**
     * Classe de l'établissement courant et de l'année choisie.
     */
    private function classroomExistsRule(string $table): Exists
    {
        $rule = Rule::exists($table, 'id')
            ->where('establishment_id', (int) app('currentEstablishmentId'))
            ->where('school_year_id', $this->school_year_id);

        return $table === 'classrooms' ? $rule->whereNull('deleted_at') : $rule;
    }

    private function currentEstablishmentIsArabe(): bool
    {
        return (bool) Establishment::whereKey((int) app('currentEstablishmentId'))->value('is_arabe');
    }

    public function withdrawEnrollment(int $enrollmentId): void
    {
        $enrollment = Enrollment::findOrFail($enrollmentId);

        $this->authorize('update', $enrollment);

        $enrollment->update(['status' => 'withdrawn']);
    }

    public function addGuardian(): void
    {
        $this->authorize('update', $this->student);

        $this->reset(['guardian_id', 'relationship', 'is_primary_contact', 'guardianSearch']);
        $this->showGuardianForm = true;
    }

    public function saveGuardian(): void
    {
        $this->authorize('update', $this->student);

        $data = $this->validate([
            'guardian_id' => ['required', 'exists:guardians,id'],
            'relationship' => ['required', Rule::enum(GuardianRelationship::class)],
            'is_primary_contact' => ['boolean'],
        ], [], [
            'guardian_id' => __('tuteur'),
            'relationship' => __('rôle'),
        ]);

        DB::transaction(function () use ($data): void {
            if ($data['is_primary_contact']) {
                DB::table('guardian_student')
                    ->where('student_id', $this->student->id)
                    ->update(['is_primary_contact' => false]);
            }

            $this->student->guardians()->syncWithoutDetaching([
                $data['guardian_id'] => [
                    'establishment_id' => $this->student->establishment_id,
                    'status' => GuardianLinkStatus::Approved,
                    'relationship' => $data['relationship'],
                    'is_primary_contact' => $data['is_primary_contact'],
                ],
            ]);
        });

        $this->showGuardianForm = false;
    }

    public function cancelGuardian(): void
    {
        $this->showGuardianForm = false;
    }

    public function removeGuardian(int $guardianId): void
    {
        $this->authorize('update', $this->student);

        $this->student->guardians()->detach($guardianId);
    }

    public function render()
    {
        /** @var User $user */
        $user = Auth::user();

        $ownerId = RolePermissions::can($user->currentRole(), 'finance.scope_own_only') ? $user->id : null;

        $schoolYearId = $this->student->enrollments()->where('status', 'active')->latest('enrolled_on')->value('school_year_id')
            ?? SchoolYear::where('is_current', true)->value('id');

        $financialSummary = $schoolYearId
            ? app(PaymentTrackingService::class)->balanceForStudent($this->student->id, $schoolYearId, $ownerId)
            : null;

        $isArabe = $this->currentEstablishmentIsArabe();
        $track = $isArabe ? (Track::tryFrom($this->main_track) ?? Track::Francais) : Track::Francais;

        $isSecondaireClassroom = $this->classroom_id
            ? Classroom::find($this->classroom_id)?->level?->cycle === Cycle::Secondaire
            : false;

        $enrollments = $this->student->enrollments()
            ->with(['classroom', 'arabicClassroom', 'schoolYear'])
            ->latest('enrolled_on')
            ->get();

        $secondaryEnrollment = $this->secondaryEnrollmentId ? $enrollments->firstWhere('id', $this->secondaryEnrollmentId) : null;
        $secondaryTrack = $secondaryEnrollment?->mainTrack()->other();

        return view('livewire.students.show', [
            'enrollments' => $enrollments,
            'currentEnrollment' => $enrollments->firstWhere('status', 'active') ?? $enrollments->first(),
            'isArabe' => $isArabe,
            'track' => $track,
            'classrooms' => Classroom::query()
                ->when($this->school_year_id, fn ($query) => $query->where('school_year_id', $this->school_year_id))
                ->orderBy('name')
                ->get(),
            'arabicClassrooms' => $isArabe
                ? ArabicClassroom::query()
                    ->when($this->school_year_id, fn ($query) => $query->where('school_year_id', $this->school_year_id))
                    ->orderBy('name')
                    ->get()
                : collect(),
            'secondaryEnrollment' => $secondaryEnrollment,
            'secondaryTrack' => $secondaryTrack,
            'secondaryOptions' => match ($secondaryTrack) {
                Track::Arabe => ArabicClassroom::where('school_year_id', $secondaryEnrollment->school_year_id)->orderBy('name')->get(),
                Track::Francais => Classroom::where('school_year_id', $secondaryEnrollment->school_year_id)->orderBy('name')->get(),
                default => collect(),
            },
            'schoolYears' => SchoolYear::orderByDesc('starts_on')->get(),
            'isSecondaireClassroom' => $isSecondaireClassroom,
            'financialSummary' => $financialSummary,
            'guardians' => $this->student->guardians()->wherePivot('status', GuardianLinkStatus::Approved)->get(),
            'availableGuardians' => Guardian::query()
                ->when($this->guardianSearch !== '', fn ($query) => $query
                    ->where('last_name', 'like', "%{$this->guardianSearch}%")
                    ->orWhere('first_name', 'like', "%{$this->guardianSearch}%"))
                ->orderBy('last_name')
                ->limit(20)
                ->get(),
        ])->title(__('Fiche élève'));
    }
}
