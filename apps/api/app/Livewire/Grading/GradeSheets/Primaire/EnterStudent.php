<?php

declare(strict_types=1);

namespace App\Livewire\Grading\GradeSheets\Primaire;

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\PrimarySubject;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Establishments\Support\RolePermissions;
use App\Domain\Grading\Models\AppreciationScale;
use App\Domain\Grading\Models\GradeSheet;
use App\Domain\Grading\Models\PrimaryGrade;
use App\Domain\Grading\Models\ReportCard;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Saisie des notes')]
class EnterStudent extends Component
{
    public GradeSheet $gradeSheet;

    public Student $student;

    public Classroom $classroom;

    /** @var array<int, string> */
    public array $scores = [];

    /** @var array<int, bool> */
    public array $absences = [];

    public bool $absentGenerale = false;

    public bool $justSaved = false;

    public function mount(GradeSheet $gradeSheet, Student $student): void
    {
        $this->authorize('viewAny', GradeSheet::class);

        $classroom = $student->enrollments()->where('status', 'active')->first()?->classroom;
        abort_unless($classroom && $classroom->level->cycle === Cycle::Primaire, 404);

        /** @var User $user */
        $user = Auth::user();
        abort_unless($this->hasBroadGradeAccess($user) || $user->isAssignedToClassroom($classroom->id), 403);

        $this->gradeSheet = $gradeSheet;
        $this->student = $student;
        $this->classroom = $classroom;

        $existingGrades = PrimaryGrade::query()
            ->where('grade_sheet_id', $gradeSheet->id)
            ->where('student_id', $student->id)
            ->get()
            ->keyBy('primary_subject_id');

        foreach ($this->subjects() as $subject) {
            $grade = $existingGrades->get($subject->id);
            $this->scores[$subject->id] = $grade?->score !== null ? (string) $grade->score : '';
            $this->absences[$subject->id] = $grade !== null && $grade->is_absent;
        }

        $this->absentGenerale = $this->absences !== [] && ! in_array(false, $this->absences, true);
    }

    /**
     * Accès large (toutes classes primaire) : admin classique ou educateur —
     * voir RolePermissions::MATRIX['grades.enter'].
     */
    private function hasBroadGradeAccess(User $user): bool
    {
        return $user->hasAdminRightsOnCurrentEstablishment()
            || RolePermissions::can($user->currentRole(), 'grades.enter');
    }

    /**
     * Case « absent à la composition » : coche l'absence de toutes les
     * matières en une fois. Le décochement reste manuel, matière par
     * matière, pour éviter de perdre une saisie déjà faite par erreur.
     */
    public function updatedAbsentGenerale(bool $value): void
    {
        if (! $value) {
            return;
        }

        foreach ($this->subjects() as $subject) {
            $this->absences[$subject->id] = true;
        }
    }

    /**
     * @return Collection<int, PrimarySubject>
     */
    private function subjects(): Collection
    {
        $column = PrimarySubject::coefficientColumn($this->classroom->level);

        return PrimarySubject::whereNotNull($column)->orderBy('name')->get();
    }

    /**
     * Contrôle la note d'une matière dès la saisie (avant même
     * l'enregistrement) pour alerter immédiatement en cas de dépassement du
     * barème de la matière.
     */
    public function updatedScores(mixed $value, string $key): void
    {
        $rules = $this->scoreRules();

        if (isset($rules["scores.{$key}"])) {
            $this->validateOnly("scores.{$key}", $rules, $this->scoreMessages());
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function scoreRules(): array
    {
        $level = $this->classroom->level;
        $rules = [];

        foreach ($this->subjects() as $subject) {
            if ($this->absences[$subject->id] ?? false) {
                continue;
            }

            $bareme = $subject->bareme($level) ?? 20.0;
            $rules["scores.{$subject->id}"] = ['nullable', 'numeric', 'min:0', "max:{$bareme}"];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    private function scoreMessages(): array
    {
        $level = $this->classroom->level;
        $messages = [];

        foreach ($this->subjects() as $subject) {
            $bareme = $subject->bareme($level) ?? 20.0;
            $messages["scores.{$subject->id}.max"] = "La note dépasse le barème de la matière (maximum {$bareme}).";
        }

        return $messages;
    }

    public function save(): void
    {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($this->hasBroadGradeAccess($user) || $user->isAssignedToClassroom($this->classroom->id), 403);

        $rules = $this->scoreRules();

        if ($rules !== []) {
            $this->validate($rules, $this->scoreMessages());
        }

        foreach ($this->scores as $subjectId => $score) {
            $isAbsent = $this->absences[$subjectId] ?? false;

            PrimaryGrade::updateOrCreate(
                [
                    'grade_sheet_id' => $this->gradeSheet->id,
                    'student_id' => $this->student->id,
                    'primary_subject_id' => $subjectId,
                ],
                [
                    'score' => ! $isAbsent && $score !== '' ? $score : null,
                    'is_absent' => $isAbsent,
                ]
            );
        }

        ReportCard::updateOrCreate(
            [
                'student_id' => $this->student->id,
                'school_year_id' => $this->classroom->school_year_id,
                'composition_number' => $this->gradeSheet->composition_number,
            ],
            [
                'establishment_id' => $this->gradeSheet->establishment_id,
                'classroom_id' => $this->classroom->id,
                'appreciation' => $this->preview()['appreciation'],
            ]
        );

        $this->justSaved = true;
    }

    /**
     * Aperçu en direct — mêmes formules que ReportCardService::generalAverage(),
     * calculé à partir des notes en cours de saisie (non persisté).
     *
     * @return array{totalPoints: float, totalCoefficient: float, average: ?float, result: string, appreciation: ?string}
     */
    private function preview(): array
    {
        $level = $this->classroom->level;
        $totalPoints = 0.0;
        $totalCoefficient = 0.0;

        foreach ($this->subjects() as $subject) {
            if ($this->absences[$subject->id] ?? false) {
                continue;
            }

            $score = $this->scores[$subject->id] ?? '';

            if ($score === '' || ! is_numeric($score)) {
                continue;
            }

            $coefficient = $subject->coefficientFor($level) ?? 0.0;
            $bareme = $subject->bareme($level) ?? 20.0;
            $normalized = ((float) $score / $bareme) * 20;

            $totalPoints += $normalized * $coefficient;
            $totalCoefficient += $coefficient;
        }

        // Moyenne calculée sur 20 (chaque matière y est normalisée), ramenée
        // à l'échelle du niveau : 10 pour CP1/CP2/CE1, 20 sinon.
        $scale = $level->compositionAverageScale();
        $average = $totalCoefficient > 0 ? round(($totalPoints / $totalCoefficient) * ($scale / 20), 2) : null;
        $passingAverage = $scale / 2;

        return [
            'totalPoints' => round($totalPoints, 2),
            'totalCoefficient' => $totalCoefficient,
            'average' => $average,
            'result' => match (true) {
                $average === null => 'Absence',
                $average >= $passingAverage => 'Admis(e)',
                default => 'Refusé(e)',
            },
            'appreciation' => $average !== null ? AppreciationScale::forAverage($average, $scale)?->appreciation : null,
        ];
    }

    public function render()
    {
        return view('livewire.grading.grade-sheets.primaire.enter-student', [
            'subjects' => $this->subjects(),
            'preview' => $this->preview(),
            'scale' => $this->classroom->level->compositionAverageScale(),
        ]);
    }
}
