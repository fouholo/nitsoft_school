<?php

declare(strict_types=1);

namespace App\Livewire\Grading\GradeSheets\Primaire;

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Enrollment\Models\Student;
use App\Domain\Grading\Models\GradeSheet;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Élèves — saisie des notes')]
class Students extends Component
{
    public GradeSheet $gradeSheet;

    public function mount(GradeSheet $gradeSheet): void
    {
        $this->authorize('update', $gradeSheet);
        abort_unless($gradeSheet->classroom->level->cycle === Cycle::Primaire, 404);

        $this->gradeSheet = $gradeSheet;
    }

    /**
     * @return Collection<int, Student>
     */
    private function students(): Collection
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
        return view('livewire.grading.grade-sheets.primaire.students', [
            'students' => $this->students(),
        ]);
    }
}
