<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Grilles tarifaires</h1>

        @can('create', \App\Domain\Billing\Models\FeeSchedule::class)
            <button type="button" wire:click="create" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                Nouvelle grille
            </button>
        @endcan
    </div>

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-md border border-slate-200 bg-white p-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">Libellé</label>
                <input type="text" wire:model="label" placeholder="Frais de scolarité T1" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('label') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Montant</label>
                <input type="number" step="0.01" wire:model="amount" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Échéance</label>
                <input type="date" wire:model="due_date" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('due_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Année scolaire</label>
                <select wire:model="school_year_id" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    <option value="">—</option>
                    @foreach ($schoolYears as $schoolYear)
                        <option value="{{ $schoolYear->id }}">{{ $schoolYear->label }}</option>
                    @endforeach
                </select>
                @error('school_year_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Classe (optionnel)</label>
                <select wire:model="classroom_id" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    <option value="">Toutes les classes</option>
                    @foreach ($classrooms as $classroom)
                        <option value="{{ $classroom->id }}">{{ $classroom->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-2 sm:col-span-3">
                <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                    Enregistrer
                </button>
                <button type="button" wire:click="cancel" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
                    Annuler
                </button>
            </div>
        </form>
    @endif

    <div class="mt-6 overflow-hidden rounded-md border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Libellé</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Montant</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Échéance</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Classe</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($feeSchedules as $feeSchedule)
                    <tr wire:key="fee-schedule-{{ $feeSchedule->id }}">
                        <td class="px-4 py-2 text-slate-900">{{ $feeSchedule->label }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ number_format((float) $feeSchedule->amount, 2) }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $feeSchedule->due_date->format('d/m/Y') }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $feeSchedule->classroom?->name ?? 'Toutes' }}</td>
                        <td class="px-4 py-2 text-right">
                            @can('create', \App\Domain\Billing\Models\Invoice::class)
                                <button
                                    wire:click="generateInvoices({{ $feeSchedule->id }})"
                                    wire:confirm="Générer les factures manquantes pour cette grille ?"
                                    class="text-slate-500 hover:text-slate-900"
                                >
                                    Générer les factures
                                </button>
                            @endcan
                            @can('update', $feeSchedule)
                                <button wire:click="edit({{ $feeSchedule->id }})" class="ml-3 text-slate-500 hover:text-slate-900">Modifier</button>
                            @endcan
                            @can('delete', $feeSchedule)
                                <button
                                    wire:click="delete({{ $feeSchedule->id }})"
                                    wire:confirm="Supprimer cette grille ?"
                                    class="ml-3 text-red-500 hover:text-red-700"
                                >
                                    Supprimer
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-slate-500">Aucune grille tarifaire.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
