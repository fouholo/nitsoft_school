<?php

declare(strict_types=1);

namespace App\Livewire\Billing\Invoices;

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Enrollment\Models\Student;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Factures')]
class Index extends Component
{
    use WithPagination;

    public bool $showForm = false;

    public string $search = '';

    public string $statusFilter = '';

    public ?int $student_id = null;

    public string $label = '';

    public ?float $amount_due = null;

    public string $due_date = '';

    public ?int $school_year_id = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Invoice::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('create', Invoice::class);

        $this->reset(['student_id', 'label', 'amount_due', 'due_date']);
        $this->school_year_id = SchoolYear::where('is_current', true)->value('id');
        $this->due_date = now()->toDateString();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('create', Invoice::class);

        $data = $this->validate([
            'student_id' => ['required', 'exists:students,id'],
            'label' => ['required', 'string', 'max:255'],
            'amount_due' => ['required', 'numeric', 'min:0'],
            'due_date' => ['required', 'date'],
            'school_year_id' => ['required', 'exists:school_years,id'],
        ]);

        Invoice::create([...$data, 'status' => 'pending']);

        $this->showForm = false;
    }

    public function cancel(): void
    {
        $this->showForm = false;
    }

    public function render()
    {
        $invoices = Invoice::query()
            ->with('student')
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->search !== '', function ($query): void {
                $query->whereHas('student', function ($query): void {
                    $query->where('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%")
                        ->orWhere('student_number', 'like', "%{$this->search}%");
                });
            })
            ->orderByDesc('due_date')
            ->paginate(15);

        return view('livewire.billing.invoices.index', [
            'invoices' => $invoices,
            'students' => Student::orderBy('last_name')->get(),
            'schoolYears' => SchoolYear::orderByDesc('starts_on')->get(),
        ]);
    }
}
