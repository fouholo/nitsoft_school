<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Tarifs</h1>

        <div>
            <label class="sr-only">Année scolaire</label>
            <select wire:model.live="school_year_id" class="rounded-md border-slate-300 text-sm">
                <option value="">—</option>
                @foreach ($schoolYears as $schoolYear)
                    <option value="{{ $schoolYear->id }}">{{ $schoolYear->label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if (! $school_year_id)
        <p class="mt-6 text-sm text-slate-500">Sélectionnez une année scolaire pour gérer ses tarifs.</p>
    @else
        {{-- Tranches --}}
        <div class="mt-8">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-slate-900">Tranches</h2>

                @can('create', \App\Domain\Billing\Models\Installment::class)
                    <button type="button" wire:click="createInstallment" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                        Nouvelle tranche
                    </button>
                @endcan
            </div>

            @if ($showInstallmentForm)
                <form wire:submit="saveInstallment" class="mt-4 grid grid-cols-1 gap-4 rounded-md border border-slate-200 bg-white p-4 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700">Libellé</label>
                        <input type="text" wire:model="label" placeholder="Octobre" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                        @error('label') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700">Ordre</label>
                        <input type="number" wire:model="position" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                        @error('position') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700">Échéance</label>
                        <input type="date" wire:model="due_date" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                        @error('due_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex gap-2 sm:col-span-3">
                        <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                            Enregistrer
                        </button>
                        <button type="button" wire:click="cancelInstallment" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
                            Annuler
                        </button>
                    </div>
                </form>
            @endif

            <div class="mt-4 overflow-hidden rounded-md border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-slate-500">Ordre</th>
                            <th class="px-4 py-2 text-left font-medium text-slate-500">Libellé</th>
                            <th class="px-4 py-2 text-left font-medium text-slate-500">Échéance</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($installments as $installment)
                            <tr wire:key="installment-{{ $installment->id }}">
                                <td class="px-4 py-2 text-slate-600">{{ $installment->position }}</td>
                                <td class="px-4 py-2 text-slate-900">{{ $installment->label }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ $installment->due_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-2 text-right">
                                    @can('update', $installment)
                                        <button wire:click="editInstallment({{ $installment->id }})" class="text-slate-500 hover:text-slate-900">Modifier</button>
                                    @endcan
                                    @can('delete', $installment)
                                        <button
                                            wire:click="deleteInstallment({{ $installment->id }})"
                                            wire:confirm="Supprimer cette tranche ? Les montants de scolarité configurés dessus seront aussi supprimés."
                                            class="ml-3 text-red-500 hover:text-red-700"
                                        >
                                            Supprimer
                                        </button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-slate-500">Aucune tranche pour cette année scolaire.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Tarifs par niveau --}}
        <div class="mt-8">
            <h2 class="text-lg font-semibold text-slate-900">Tarifs par niveau</h2>

            @if ($installments->isEmpty())
                <p class="mt-2 text-sm text-slate-500">Aucune tranche n'est encore définie pour cette année scolaire — vous pouvez tout de même configurer les frais d'inscription par niveau.</p>
            @endif

            <div class="mt-4 overflow-hidden rounded-md border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-slate-500">Niveau</th>
                            <th class="px-4 py-2 text-left font-medium text-slate-500">Frais d'inscription</th>
                            <th class="px-4 py-2 text-left font-medium text-slate-500">Total scolarité configuré</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($levels as $level)
                            @php
                                $levelFee = $levelFees->get($level->id);
                                $total = $levelFee ? $levelFee->installmentAmounts->whereNotNull('amount')->sum('amount') : 0;
                            @endphp
                            <tr wire:key="level-{{ $level->id }}">
                                <td class="px-4 py-2 text-slate-900">{{ $level->level_wording }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ number_format((float) ($levelFee->registration_amount ?? 0), 2) }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ number_format((float) $total, 2) }}</td>
                                <td class="px-4 py-2 text-right">
                                    @can('create', \App\Domain\Billing\Models\LevelFee::class)
                                        <button wire:click="configureLevel({{ $level->id }})" class="text-slate-500 hover:text-slate-900">Configurer</button>
                                    @endcan
                                    @if ($levelFee)
                                        @can('create', \App\Domain\Billing\Models\Invoice::class)
                                            <button
                                                wire:click="generateInvoices({{ $level->id }})"
                                                wire:confirm="Générer les factures manquantes pour ce niveau ?"
                                                class="ml-3 text-slate-500 hover:text-slate-900"
                                            >
                                                Générer les factures
                                            </button>
                                        @endcan
                                    @endif
                                </td>
                            </tr>

                            @if ($showLevelFeeForm && $configuringLevelId === $level->id)
                                <tr>
                                    <td colspan="4" class="bg-slate-50 px-4 py-4">
                                        <form wire:submit="saveLevelFees" class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                            <div>
                                                <label class="block text-sm font-medium text-slate-700">Frais d'inscription</label>
                                                <input type="number" step="0.01" wire:model="registration_amount" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                                                @error('registration_amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                            </div>

                                            @foreach ($installments as $installment)
                                                <div>
                                                    <label class="block text-sm font-medium text-slate-700">{{ $installment->label }}</label>
                                                    <input type="number" step="0.01" wire:model="installment_amounts.{{ $installment->id }}" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                                                    @error('installment_amounts.' . $installment->id) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                                </div>
                                            @endforeach

                                            <div class="col-span-2 flex items-end gap-2 sm:col-span-4">
                                                <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                                                    Enregistrer
                                                </button>
                                                <button type="button" wire:click="cancelLevelFee" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
                                                    Annuler
                                                </button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
