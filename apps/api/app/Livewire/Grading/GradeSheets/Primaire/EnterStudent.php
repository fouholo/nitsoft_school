<?php

declare(strict_types=1);

namespace App\Livewire\Grading\GradeSheets\Primaire;

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\PrimarySubject;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Grading\Models\GradeSheet;
use App\Domain\Grading\Models\PrimaryGrade;
use App\Domain\Grading\Models\ReportCard;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Saisie des notes')]
class EnterStudent extends Component
{
    public GradeSheet $gradeSheet;

    public Student $student;

    /** @var array<int, string> */
    public array $scores = [];

    public string $appreciation = '';

    public bool $justSaved = false;

    public function mount(GradeSheet $gradeSheet, Student $student): void
    {
        $this->authorize('update', $gradeSheet);
        abort_unless($gradeSheet->classroom->level->cycle === Cycle::Primaire, 404);

        $this->gradeSheet = $gradeSheet;
        $this->student = $student;

        $existingGrades = PrimaryGrade::query()
            ->where('grade_sheet_id', $gradeSheet->id)
            ->where('student_id', $student->id)
            ->get()
            ->keyBy('primary_subject_id');

        foreach ($this->subjects() as $subject) {
            $grade = $existingGrades->get($subject->id);
            $this->scores[$subject->id] = $grade?->score !== null ? (string) $grade->score : '';
        }

        $reportCard = ReportCard::query()
            ->where('student_id', $student->id)
            ->where('school_year_id', $gradeSheet->classroom->school_year_id)
            ->where('composition_number', $gradeSheet->composition_number)
            ->first();

        $this->appreciation = $reportCard->appreciation ?? '';
    }

    /**
     * @return Collection<int, PrimarySubject>
     */
    private function subjects(): Collection
    {
        $column = PrimarySubject::coefficientColumn($this->gradeSheet->classroom->level);

        return PrimarySubject::whereNotNull($column)->orderBy('name')->get();
    }

    public function save(): void
    {
        $this->authorize('update', $this->gradeSheet);

        $rules = ['appreciation' => ['nullable', 'string', 'max:1000']];
        foreach (array_keys($this->scores) as $subjectId) {
            $rules["scores.{$subjectId}"] = ['nullable', 'numeric', 'min:0'];
        }

        $this->validate($rules);

        foreach ($this->scores as $subjectId => $score) {
            PrimaryGrade::updateOrCreate(
                [
                    'grade_sheet_id' => $this->gradeSheet->id,
                    'student_id' => $this->student->id,
                    'primary_subject_id' => $subjectId,
                ],
                ['score' => $score !== '' ? $score : null]
            );
        }

        ReportCard::updateOrCreate(
            [
                'student_id' => $this->student->id,
                'school_year_id' => $this->gradeSheet->classroom->school_year_id,
                'composition_number' => $this->gradeSheet->composition_number,
            ],
            [
                'establishment_id' => $this->gradeSheet->establishment_id,
                'classroom_id' => $this->gradeSheet->classroom_id,
                'appreciation' => $this->appreciation !== '' ? $this->appreciation : null,
            ]
        );

        $this->justSaved = true;
    }

    /**
     * Aperçu en direct — mêmes formules que ReportCardService::generalAverage(),
     * calculé à partir des notes en cours de saisie (non persisté).
     *
     * @return array{totalPoints: float, totalCoefficient: float, average: ?float}
     */
    private function preview(): array
    {
        $level = $this->gradeSheet->classroom->level;
        $totalPoints = 0.0;
        $totalCoefficient = 0.0;

        foreach ($this->subjects() as $subject) {
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

        return [
            'totalPoints' => round($totalPoints, 2),
            'totalCoefficient' => $totalCoefficient,
            'average' => $totalCoefficient > 0 ? round($totalPoints / $totalCoefficient, 2) : null,
        ];
    }

    public function render()
    {
        return view('livewire.grading.grade-sheets.primaire.enter-student', [
            'subjects' => $this->subjects(),
            'preview' => $this->preview(),
        ]);
    }
}
