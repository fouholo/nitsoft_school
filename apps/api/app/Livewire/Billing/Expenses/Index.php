<?php

declare(strict_types=1);

namespace App\Livewire\Billing\Expenses;

use App\Domain\Billing\Models\Expense;
use App\Domain\Establishments\Support\RolePermissions;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Dépenses')]
class Index extends Component
{
    use WithPagination;

    public bool $showForm = false;

    public string $month = '';

    public string $label = '';

    public ?float $amount = null;

    public string $spent_at = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Expense::class);
    }

    public function updatingMonth(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('create', Expense::class);

        $this->resetForm();
        $this->spent_at = now()->toDateString();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('create', Expense::class);

        $data = $this->validate([
            'label' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'spent_at' => ['required', 'date'],
        ]);

        Expense::create([...$data, 'recorded_by' => Auth::id()]);

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $expenseId): void
    {
        $expense = Expense::findOrFail($expenseId);

        $this->authorize('delete', $expense);

        $expense->delete();
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    protected function resetForm(): void
    {
        $this->reset(['label', 'amount', 'spent_at']);
    }

    public function render()
    {
        /** @var User $user */
        $user = Auth::user();

        $query = Expense::with('recordedBy')
            ->when(
                RolePermissions::can($user->currentRole(), 'finance.scope_own_only'),
                fn ($query) => $query->ownedBy($user),
            )
            ->when($this->month !== '', function ($query): void {
                [$year, $month] = explode('-', $this->month);
                $query->whereYear('spent_at', (int) $year)->whereMonth('spent_at', (int) $month);
            })
            ->orderByDesc('spent_at');

        $total = (clone $query)->sum('amount');

        return view('livewire.billing.expenses.index', [
            'expenses' => $query->paginate(15),
            'total' => $total,
        ]);
    }
}
