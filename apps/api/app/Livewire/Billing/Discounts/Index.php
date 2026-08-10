<?php

declare(strict_types=1);

namespace App\Livewire\Billing\Discounts;

use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Billing\Models\Discount;
use App\Domain\Enrollment\Models\Student;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Réductions')]
class Index extends Component
{
    public ?int $school_year_id = null;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public ?int $student_id = null;

    public string $type = 'percentage';

    public ?float $value = null;

    public string $reason = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Discount::class);

        $this->school_year_id = SchoolYear::where('is_current', true)->value('id');
    }

    public function create(): void
    {
        $this->authorize('create', Discount::class);

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $discountId): void
    {
        $discount = Discount::findOrFail($discountId);

        $this->authorize('update', $discount);

        $this->editingId = $discount->id;
        $this->student_id = $discount->student_id;
        $this->type = $discount->type;
        $this->value = (float) $discount->value;
        $this->reason = (string) $discount->reason;
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'student_id' => ['required', 'exists:students,id'],
            'type' => ['required', 'in:percentage,fixed_amount'],
            'value' => [
                'required',
                'numeric',
                'min:0',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->type === 'percentage' && (float) $value > 100) {
                        $fail('Le pourcentage ne peut pas dépasser 100.');
                    }
                },
            ],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $existing = Discount::where('school_year_id', $this->school_year_id)
            ->where('student_id', $data['student_id'])
            ->first();

        $this->authorize($existing ? 'update' : 'create', $existing ?? Discount::class);

        Discount::updateOrCreate(
            ['school_year_id' => $this->school_year_id, 'student_id' => $data['student_id']],
            [
                'type' => $data['type'],
                'value' => $data['value'],
                'reason' => $data['reason'] !== '' ? $data['reason'] : null,
                'created_by' => $existing->created_by ?? Auth::id(),
            ],
        );

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $discountId): void
    {
        $discount = Discount::findOrFail($discountId);

        $this->authorize('delete', $discount);

        $discount->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    protected function resetForm(): void
    {
        $this->reset(['editingId', 'student_id', 'value', 'reason']);
        $this->type = 'percentage';
    }

    public function render()
    {
        $discounts = Discount::where('school_year_id', $this->school_year_id)
            ->with(['student', 'createdBy'])
            ->when($this->search !== '', function ($query): void {
                $query->whereHas('student', function ($query): void {
                    $query->where('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%");
                });
            })
            ->get();

        return view('livewire.billing.discounts.index', [
            'discounts' => $discounts,
            'students' => Student::orderBy('last_name')->get(),
            'schoolYears' => SchoolYear::orderByDesc('starts_on')->get(),
        ]);
    }
}
