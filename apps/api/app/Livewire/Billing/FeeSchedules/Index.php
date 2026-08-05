<?php

declare(strict_types=1);

namespace App\Livewire\Billing\FeeSchedules;

use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\FeeSchedule;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Enrollment\Models\Enrollment;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Grilles tarifaires')]
class Index extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $label = '';

    public ?float $amount = null;

    public string $due_date = '';

    public ?int $school_year_id = null;

    public ?int $classroom_id = null;

    public function mount(): void
    {
        $this->authorize('viewAny', FeeSchedule::class);
    }

    public function create(): void
    {
        $this->authorize('create', FeeSchedule::class);

        $this->resetForm();
        $this->school_year_id = SchoolYear::where('is_current', true)->value('id');
        $this->showForm = true;
    }

    public function edit(int $feeScheduleId): void
    {
        $feeSchedule = FeeSchedule::findOrFail($feeScheduleId);

        $this->authorize('update', $feeSchedule);

        $this->editingId = $feeSchedule->id;
        $this->label = $feeSchedule->label;
        $this->amount = (float) $feeSchedule->amount;
        $this->due_date = $feeSchedule->due_date->toDateString();
        $this->school_year_id = $feeSchedule->school_year_id;
        $this->classroom_id = $feeSchedule->classroom_id;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'label' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['required', 'date'],
            'school_year_id' => ['required', 'exists:school_years,id'],
            'classroom_id' => ['nullable', 'exists:classrooms,id'],
        ]);

        if ($this->editingId) {
            $feeSchedule = FeeSchedule::findOrFail($this->editingId);
            $this->authorize('update', $feeSchedule);
        } else {
            $this->authorize('create', FeeSchedule::class);
            $feeSchedule = new FeeSchedule;
        }

        $feeSchedule->fill($data);
        $feeSchedule->save();

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $feeScheduleId): void
    {
        $feeSchedule = FeeSchedule::findOrFail($feeScheduleId);

        $this->authorize('delete', $feeSchedule);

        $feeSchedule->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    /**
     * Génère une facture par élève activement inscrit couvert par la grille
     * (une classe précise, ou toutes les classes de l'année scolaire si
     * aucune classe n'est ciblée). Idempotent : n'émet pas de doublon si une
     * facture existe déjà pour cet élève sur cette grille.
     */
    public function generateInvoices(int $feeScheduleId): void
    {
        $feeSchedule = FeeSchedule::findOrFail($feeScheduleId);

        $this->authorize('create', Invoice::class);

        $enrollments = Enrollment::query()
            ->where('school_year_id', $feeSchedule->school_year_id)
            ->where('status', 'active')
            ->when($feeSchedule->classroom_id, fn ($query) => $query->where('classroom_id', $feeSchedule->classroom_id))
            ->get();

        $existingStudentIds = Invoice::query()
            ->where('fee_schedule_id', $feeSchedule->id)
            ->pluck('student_id');

        foreach ($enrollments as $enrollment) {
            if ($existingStudentIds->contains($enrollment->student_id)) {
                continue;
            }

            Invoice::create([
                'student_id' => $enrollment->student_id,
                'school_year_id' => $feeSchedule->school_year_id,
                'fee_schedule_id' => $feeSchedule->id,
                'label' => $feeSchedule->label,
                'amount_due' => $feeSchedule->amount,
                'due_date' => $feeSchedule->due_date,
                'status' => 'pending',
            ]);
        }
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'label', 'amount', 'due_date', 'school_year_id', 'classroom_id']);
    }

    public function render()
    {
        return view('livewire.billing.fee-schedules.index', [
            'feeSchedules' => FeeSchedule::with(['schoolYear', 'classroom'])->orderByDesc('due_date')->get(),
            'schoolYears' => SchoolYear::orderByDesc('starts_on')->get(),
            'classrooms' => Classroom::orderBy('name')->get(),
        ]);
    }
}
