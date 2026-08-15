<?php

declare(strict_types=1);

namespace App\Livewire\Grading\GradeSheets;

use App\Domain\Enrollment\Models\Student;
use App\Domain\Grading\Models\Grade;
use App\Domain\Grading\Models\GradeSheet;
use App\Domain\Grading\Models\PrimaryGrade;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Saisie des notes')]
class Enter extends Component
{
    public GradeSheet $gradeSheet;

    /** @var array<int, string> */
    public array $scores = [];

    /** @var array<int, string> */
    public array $comments = [];

    public bool $justSaved = false;

    public function mount(GradeSheet $gradeSheet): void
    {
        $this->authorize('update', $gradeSheet);

        $this->gradeSheet = $gradeSheet;

        $existingGrades = $this->isPrimaire()
            ? $gradeSheet->primaryGrades()->get()->keyBy('student_id')
            : $gradeSheet->grades()->get()->keyBy('student_id');

        foreach ($this->students() as $student) {
            $grade = $existingGrades->get($student->id);
            $score = $grade instanceof Grade || $grade instanceof PrimaryGrade ? $grade->score : null;
            $comment = $grade instanceof Grade || $grade instanceof PrimaryGrade ? $grade->comment : '';

            $this->scores[$student->id] = $score !== null ? (string) $score : '';
            $this->comments[$student->id] = $comment ?? '';
        }
    }

    public function save(): void
    {
        $this->authorize('update', $this->gradeSheet);

        $rules = [];
        foreach (array_keys($this->scores) as $studentId) {
            $rules["scores.{$studentId}"] = ['nullable', 'numeric', 'min:0', 'max:'.$this->gradeSheet->max_score];
        }

        Validator::make(['scores' => $this->scores], $rules)->validate();

        foreach ($this->scores as $studentId => $score) {
            $attributes = [
                'score' => $score !== '' ? $score : null,
                'comment' => $this->comments[$studentId] !== '' ? $this->comments[$studentId] : null,
            ];

            if ($this->isPrimaire()) {
                PrimaryGrade::updateOrCreate(
                    ['grade_sheet_id' => $this->gradeSheet->id, 'student_id' => $studentId],
                    [...$attributes, 'primary_subject_id' => $this->gradeSheet->primary_subject_id]
                );
            } else {
                Grade::updateOrCreate(
                    ['grade_sheet_id' => $this->gradeSheet->id, 'student_id' => $studentId],
                    $attributes
                );
            }
        }

        $this->justSaved = true;
    }

    private function isPrimaire(): bool
    {
        return $this->gradeSheet->primary_subject_id !== null;
    }

    /**
     * @return Collection<int, Student>
     */
    protected function students()
    {
        return Student::query()
            ->whereHas('enrollments', function ($query): void {
                $query->where('classroom_id', $this->gradeSheet->classroom_id)
                    ->where('status', 'active');
            })
            ->orderBy('last_name')
            ->get();
    }

    public function render()
    {
        return view('livewire.grading.grade-sheets.enter', [
            'students' => $this->students(),
        ]);
    }
}
