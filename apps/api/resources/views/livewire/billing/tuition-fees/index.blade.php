<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-stone-900">{{ __('Tarifs') }}</h1>

        <div>
            <label class="sr-only">{{ __('Année scolaire') }}</label>
            <select wire:model.live="school_year_id" class="rounded-lg border-stone-300 text-sm">
                <option value="">—</option>
                @foreach ($schoolYears as $schoolYear)
                    <option value="{{ $schoolYear->id }}">{{ $schoolYear->label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if (! $school_year_id)
        <p class="mt-6 text-sm text-stone-500">{{ __('Sélectionnez une année scolaire pour gérer ses tarifs.') }}</p>
    @else
        {{-- Tranches --}}
        <div class="mt-8">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-stone-900">{{ __('Tranches') }}</h2>

                <div class="flex items-center gap-3">
                    @if ($installmentJustSaved)
                        <span class="rounded-lg bg-emerald-50 px-3 py-1.5 text-sm text-emerald-700">{{ __('Tranche enregistrée.') }}</span>
                    @endif

                    @can('create', \App\Domain\Billing\Models\Installment::class)
                        <button type="button" wire:click="createInstallment" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                            {{ __('Nouvelle tranche') }}
                        </button>
                    @endcan
                </div>
            </div>

            @if ($showInstallmentForm)
                <form wire:submit="saveInstallment" class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-stone-700">{{ __('Libellé') }}</label>
                        <input type="text" wire:model="label" placeholder="{{ __('Octobre') }}" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                        @error('label') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-stone-700">{{ __('Ordre') }}</label>
                        <input type="number" wire:model="position" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                        @error('position') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-stone-700">{{ __('Échéance') }}</label>
                        <input type="date" wire:model="due_date" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                        @error('due_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex gap-2 sm:col-span-3">
                        <button type="submit" wire:loading.attr="disabled" wire:target="saveInstallment" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800 disabled:opacity-60">
                            <span wire:loading.remove wire:target="saveInstallment">{{ __('Enregistrer') }}</span>
                            <span wire:loading wire:target="saveInstallment">{{ __('Enregistrement…') }}</span>
                        </button>
                        <button type="button" wire:click="cancelInstallment" class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm text-stone-700 hover:bg-stone-50">
                            {{ __('Annuler') }}
                        </button>
                    </div>
                </form>
            @endif

            <div class="mt-4 overflow-hidden rounded-lg border border-stone-200 bg-white">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-stone-200 text-sm">
                        <thead class="bg-stone-50">
                            <tr>
                                <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Ordre') }}</th>
                                <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Libellé') }}</th>
                                <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Échéance') }}</th>
                                <th class="whitespace-nowrap px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @forelse ($installments as $installment)
                                <tr wire:key="installment-{{ $installment->id }}">
                                    <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ $installment->position }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-stone-900">{{ $installment->label }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ $installment->due_date->format('d/m/Y') }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-end">
                                        @can('update', $installment)
                                            <button wire:click="editInstallment({{ $installment->id }})" class="text-stone-500 hover:text-stone-900">{{ __('Modifier') }}</button>
                                        @endcan
                                        @can('delete', $installment)
                                            <button
                                                wire:click="deleteInstallment({{ $installment->id }})"
                                                wire:confirm="{{ __('Supprimer cette tranche ? Les montants de scolarité configurés dessus seront aussi supprimés.') }}"
                                                class="ms-3 text-red-500 hover:text-red-700"
                                            >
                                                {{ __('Supprimer') }}
                                            </button>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-stone-500">{{ __('Aucune tranche pour cette année scolaire.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Tarifs par niveau --}}
        <div class="mt-8">
            <h2 class="text-lg font-semibold text-stone-900">{{ __('Tarifs par niveau') }}</h2>

            @if ($installments->isEmpty())
                <p class="mt-2 text-sm text-stone-500">{{ __("Aucune tranche n'est encore définie pour cette année scolaire — vous pouvez tout de même configurer les frais d'inscription par niveau.") }}</p>
            @endif

            <div class="mt-4 overflow-hidden rounded-lg border border-stone-200 bg-white">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-stone-200 text-sm">
                        <thead class="bg-stone-50">
                            <tr>
                                <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Niveau') }}</th>
                                <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __("Frais d'inscription") }}</th>
                                <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Total scolarité configuré') }}</th>
                                <th class="whitespace-nowrap px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @php
                                $otherConfiguredLevelIds = $levelFees->keys()->reject(fn ($id) => $id === $configuringLevelId);
                            @endphp
                            @foreach ($levels as $level)
                                @php
                                    $levelFee = $levelFees->get($level->id);
                                    $total = $levelFee ? $levelFee->installmentAmounts->whereNotNull('amount')->sum('amount') : 0;
                                @endphp
                                <tr wire:key="level-{{ $level->id }}">
                                    <td class="whitespace-nowrap px-4 py-2 text-stone-900">{{ $level->level_wording }}</td>
                                    <td class="px-4 py-2 text-stone-600">
                                        @if ($level->cycle === \App\Domain\Academics\Enums\Cycle::Secondaire)
                                            <div class="whitespace-nowrap">{{ __('Non affecté :') }} {{ money((float) ($levelFee->registration_amount ?? 0)) }}</div>
                                            <div class="whitespace-nowrap">{{ __('Affecté :') }} {{ money((float) ($levelFee->registration_amount_assigned ?? 0)) }}</div>
                                        @else
                                            <span class="whitespace-nowrap">{{ money((float) ($levelFee->registration_amount ?? 0)) }}</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ money((float) $total) }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-end">
                                        @if ($levelFeeJustSaved && $lastSavedLevelId === $level->id)
                                            <span class="me-3 rounded-lg bg-emerald-50 px-2 py-1 text-xs text-emerald-700">{{ __('Tarifs enregistrés.') }}</span>
                                        @endif
                                        @can('create', \App\Domain\Billing\Models\LevelFee::class)
                                            <button wire:click="configureLevel({{ $level->id }})" class="text-stone-500 hover:text-stone-900">{{ __('Configurer') }}</button>
                                        @endcan
                                    </td>
                                </tr>

                                @if ($showLevelFeeForm && $configuringLevelId === $level->id)
                                    <tr>
                                        <td colspan="4" class="bg-stone-50 px-4 py-4">
                                            <form
                                                wire:submit="saveLevelFees"
                                                class="grid grid-cols-2 gap-4 sm:grid-cols-4"
                                            >
                                                @if ($otherConfiguredLevelIds->isNotEmpty())
                                                    <div class="col-span-2 sm:col-span-4">
                                                        <label class="block text-sm font-medium text-stone-700">{{ __('Dupliquer les montants depuis un autre niveau') }}</label>
                                                        <select wire:model.live="duplicateSourceLevelId" class="mt-1 block w-full max-w-xs rounded-lg border-stone-300 text-sm">
                                                            <option value="">{{ __('— Choisir un niveau —') }}</option>
                                                            @foreach ($levels->whereIn('id', $otherConfiguredLevelIds) as $otherLevel)
                                                                <option value="{{ $otherLevel->id }}">{{ $otherLevel->level_wording }}</option>
                                                            @endforeach
                                                        </select>
                                                        <p class="mt-1 text-xs text-stone-500">{{ __('Les montants seront pré-remplis ; vous pourrez les ajuster avant d\'enregistrer.') }}</p>
                                                    </div>
                                                @endif

                                                <div>
                                                    <label class="block text-sm font-medium text-stone-700">{{ __("Frais d'inscription") }} {{ $configuringLevelIsSecondaire ? __('(non affecté)') : '' }} — F CFA</label>
                                                    <input type="number" step="0.01" wire:model="registration_amount" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                                                    @error('registration_amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                                </div>

                                                @if ($configuringLevelIsSecondaire)
                                                    <div>
                                                        <label class="block text-sm font-medium text-stone-700">{{ __("Frais d'inscription (affecté)") }} — F CFA</label>
                                                        <input type="number" step="0.01" wire:model="registration_amount_assigned" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                                                        @error('registration_amount_assigned') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                                    </div>

                                                    <p class="col-span-2 -mt-2 text-xs text-stone-500 sm:col-span-4">{{ __('Le montant « affecté » s\'applique une fois l\'élève affecté à une série ; « non affecté » s\'applique avant cette affectation.') }}</p>
                                                @endif

                                                @foreach ($installments as $installment)
                                                    <div>
                                                        <label class="block text-sm font-medium text-stone-700">{{ $installment->label }} — F CFA</label>
                                                        <input type="number" step="0.01" wire:model="installment_amounts.{{ $installment->id }}" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                                                        @error('installment_amounts.' . $installment->id) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                                    </div>
                                                @endforeach

                                                <div class="col-span-2 flex items-center justify-between rounded-lg bg-white px-3 py-2 text-sm sm:col-span-4" x-data>
                                                    <span class="font-medium text-stone-700">{{ __('Total scolarité (aperçu)') }}</span>
                                                    <span class="font-semibold text-stone-900" x-text="new Intl.NumberFormat('fr-FR').format(Object.values($wire.installment_amounts).reduce((sum, v) => sum + (parseFloat(v) || 0), 0)) + ' F CFA'"></span>
                                                </div>

                                                <div class="col-span-2 flex items-end gap-2 sm:col-span-4">
                                                    <button type="submit" wire:loading.attr="disabled" wire:target="saveLevelFees" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800 disabled:opacity-60">
                                                        <span wire:loading.remove wire:target="saveLevelFees">{{ __('Enregistrer') }}</span>
                                                        <span wire:loading wire:target="saveLevelFees">{{ __('Enregistrement…') }}</span>
                                                    </button>
                                                    <button type="button" wire:click="cancelLevelFee" class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm text-stone-700 hover:bg-stone-50">
                                                        {{ __('Annuler') }}
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
        </div>
    @endif
</div>
