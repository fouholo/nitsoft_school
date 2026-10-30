<?php

declare(strict_types=1);

namespace App\Livewire\Arabic\Compositions;

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Arabic\Models\ArabicClassroom;
use App\Domain\Arabic\Models\ArabicComposition;
use App\Domain\Arabic\Models\ArabicCompositionGrade;
use App\Domain\Arabic\Models\ArabicCompositionResult;
use App\Domain\Arabic\Models\ArabicLevelSubject;
use App\Domain\Arabic\Services\ArabicCompositionCalculator;
use App\Domain\Enrollment\Models\Enrollment;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Saisie d'une composition pour un élève : note sur 10 et absence par
 * matière, aperçu en direct, appréciation du maître — miroir de
 * Grading\GradeSheets\Primaire\EnterStudent. Voir
 * docs/superpowers/specs/2026-10-30-compositions-primaire-arabe-design.md.
 */
#[Layout('layouts.app')]
class EnterStudent extends Component
{
    public ArabicComposition $arabicComposition;

    public Enrollment $enrollment;

    public ArabicClassroom $arabicClassroom;

    /** @var array<int, string> */
    public array $scores = [];

    /** @var array<int, bool> */
    public array $absences = [];

    public bool $absentGenerale = false;

    public string $appreciation = '';

    public bool $justSaved = false;

    public function mount(ArabicComposition $arabicComposition, Enrollment $enrollment): void
    {
        $classroom = $enrollment->arabicClassroom;

        abort_unless(
            $classroom instanceof ArabicClassroom
                && $classroom->arabicLevel->cycle === Cycle::Primaire
                && $classroom->school_year_id === $arabicComposition->school_year_id
                && $enrollment->status === 'active',
            404,
        );

        $this->authorize('accessClassroom', [ArabicComposition::class, $classroom]);

        $this->arabicComposition = $arabicComposition;
        $this->enrollment = $enrollment;
        $this->arabicClassroom = $classroom;

        $grades = ArabicCompositionGrade::query()
            ->where('arabic_composition_id', $arabicComposition->id)
            ->where('enrollment_id', $enrollment->id)
            ->get()
            ->keyBy('arabic_subject_id');

        foreach ($this->subjects() as $levelSubject) {
            $grade = $grades->get($levelSubject->arabic_subject_id);

            $this->scores[$levelSubject->arabic_subject_id] = $grade instanceof ArabicCompositionGrade && $grade->score !== null
                ? rtrim(rtrim($grade->score, '0'), '.')
                : '';
            $this->absences[$levelSubject->arabic_subject_id] = $grade instanceof ArabicCompositionGrade && $grade->is_absent;
        }

        $this->absentGenerale = $this->absences !== [] && ! in_array(false, $this->absences, true);
        $this->appreciation = (string) ArabicCompositionResult::query()
            ->where('arabic_composition_id', $arabicComposition->id)
            ->where('enrollment_id', $enrollment->id)
            ->value('appreciation');
    }

    /**
     * Coche l'absence de toutes les matières en une fois ; le décochement
     * reste manuel, comme en français, pour ne pas perdre une saisie.
     */
    public function updatedAbsentGenerale(bool $value): void
    {
        if (! $value) {
            return;
        }

        foreach (array_keys($this->absences) as $subjectId) {
            $this->absences[$subjectId] = true;
        }
    }

    public function updatedScores(mixed $value, string $key): void
    {
        $rules = $this->scoreRules();

        if (isset($rules["scores.{$key}"])) {
            $this->validateOnly("scores.{$key}", $rules, $this->scoreMessages());
        }
    }

    public function save(): void
    {
        $this->authorize('accessClassroom', [ArabicComposition::class, $this->arabicClassroom]);

        $this->validate([
            ...$this->scoreRules(),
            'appreciation' => ['nullable', 'string', 'max:200'],
        ], $this->scoreMessages());

        foreach ($this->subjects() as $levelSubject) {
            $subjectId = $levelSubject->arabic_subject_id;
            $isAbsent = $this->absences[$subjectId] ?? false;
            $score = $this->scores[$subjectId] ?? '';

            ArabicCompositionGrade::updateOrCreate(
                [
                    'arabic_composition_id' => $this->arabicComposition->id,
                    'enrollment_id' => $this->enrollment->id,
                    'arabic_subject_id' => $subjectId,
                ],
                [
                    'establishment_id' => $this->arabicComposition->establishment_id,
                    'score' => ! $isAbsent && $score !== '' ? $score : null,
                    'is_absent' => $isAbsent,
                ],
            );
        }

        ArabicCompositionResult::updateOrCreate(
            ['arabic_composition_id' => $this->arabicComposition->id, 'enrollment_id' => $this->enrollment->id],
            [
                'establishment_id' => $this->arabicComposition->establishment_id,
                'arabic_classroom_id' => $this->arabicClassroom->id,
                'appreciation' => trim($this->appreciation) !== '' ? trim($this->appreciation) : null,
            ],
        );

        $this->justSaved = true;
    }

    /**
     * @return Collection<int, ArabicLevelSubject>
     */
    private function subjects(): Collection
    {
        return app(ArabicCompositionCalculator::class)->subjectsFor($this->arabicClassroom);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function scoreRules(): array
    {
        $rules = [];

        foreach ($this->subjects() as $levelSubject) {
            if (! ($this->absences[$levelSubject->arabic_subject_id] ?? false)) {
                $rules["scores.{$levelSubject->arabic_subject_id}"] = ['nullable', 'numeric', 'min:0', 'max:'.ArabicCompositionCalculator::MAX_SCORE];
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    private function scoreMessages(): array
    {
        $messages = [];

        foreach ($this->subjects() as $levelSubject) {
            $messages["scores.{$levelSubject->arabic_subject_id}.max"] = __('La note ne peut pas dépasser 10.');
            $messages["scores.{$levelSubject->arabic_subject_id}.min"] = __('La note ne peut pas être négative.');
            $messages["scores.{$levelSubject->arabic_subject_id}.numeric"] = __('La note doit être un nombre.');
        }

        return $messages;
    }

    public function render()
    {
        $subjects = $this->subjects();

        $preview = app(ArabicCompositionCalculator::class)->summarize(
            $subjects->map(fn (ArabicLevelSubject $levelSubject) => [
                'score' => $this->scores[$levelSubject->arabic_subject_id] ?? '',
                'is_absent' => $this->absences[$levelSubject->arabic_subject_id] ?? false,
            ])
        );

        return view('livewire.arabic.compositions.enter-student', [
            'subjects' => $subjects,
            'preview' => $preview,
        ])->title(__('Saisie des notes'));
    }
}
