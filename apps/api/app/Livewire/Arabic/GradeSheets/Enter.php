<?php

declare(strict_types=1);

namespace App\Livewire\Arabic\GradeSheets;

use App\Domain\Arabic\Models\ArabicGrade;
use App\Domain\Arabic\Models\ArabicGradeSheet;
use App\Domain\Enrollment\Models\Enrollment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Enter extends Component
{
    public ArabicGradeSheet $gradeSheet;

    /** @var array<int, string> */
    public array $scores = [];

    /** @var array<int, string> */
    public array $comments = [];

    public bool $justSaved = false;

    public function mount(ArabicGradeSheet $gradeSheet): void
    {
        $this->authorize('update', $gradeSheet);

        $this->gradeSheet = $gradeSheet;

        $existingGrades = $gradeSheet->grades()->get()->keyBy('enrollment_id');

        foreach ($this->enrollments() as $enrollment) {
            $grade = $existingGrades->get($enrollment->id);
            $score = $grade instanceof ArabicGrade ? $grade->score : null;
            $comment = $grade instanceof ArabicGrade ? $grade->comment : '';

            $this->scores[$enrollment->id] = $score !== null ? (string) $score : '';
            $this->comments[$enrollment->id] = $comment ?? '';
        }
    }

    public function save(): void
    {
        $this->authorize('update', $this->gradeSheet);

        $rules = [];
        foreach (array_keys($this->scores) as $enrollmentId) {
            $rules["scores.{$enrollmentId}"] = ['nullable', 'numeric', 'min:0', 'max:'.$this->gradeSheet->max_score];
        }

        Validator::make(['scores' => $this->scores], $rules)->validate();

        foreach ($this->scores as $enrollmentId => $score) {
            ArabicGrade::updateOrCreate(
                ['arabic_grade_sheet_id' => $this->gradeSheet->id, 'enrollment_id' => $enrollmentId],
                [
                    'score' => $score !== '' ? $score : null,
                    'comment' => $this->comments[$enrollmentId] !== '' ? $this->comments[$enrollmentId] : null,
                ]
            );
        }

        $this->justSaved = true;
    }

    /**
     * @return Collection<int, Enrollment>
     */
    protected function enrollments()
    {
        return Enrollment::query()
            ->with('student')
            ->where('establishment_id', $this->gradeSheet->establishment_id)
            ->where('arabic_level_id', $this->gradeSheet->arabic_level_id)
            ->where('arabic_serie_id', $this->gradeSheet->arabic_serie_id)
            ->where('status', 'active')
            ->get()
            ->sortBy(fn (Enrollment $enrollment) => $enrollment->student?->last_name)
            ->values();
    }

    public function render()
    {
        $enrollments = $this->enrollments();
        $scoredCount = collect($this->scores)->filter(fn ($score) => $score !== '')->count();

        return view('livewire.arabic.grade-sheets.enter', [
            'enrollments' => $enrollments,
            'scoredCount' => $scoredCount,
            'totalCount' => $enrollments->count(),
        ])->title(__('Saisie des notes arabes'));
    }
}
