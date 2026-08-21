<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-stone-900">Bilan financier</h1>
    </div>

    @if ($scopedToOwn)
        <div class="mt-4 rounded-lg border border-orange-200 bg-orange-50 px-4 py-2.5 text-sm text-orange-900">
            Vue restreinte à vos propres encaissements et dépenses.
        </div>
    @endif

    <div class="mt-4 flex flex-wrap items-end gap-4">
        <div>
            <label class="block text-xs font-medium text-stone-500">Année scolaire</label>
            <select wire:model.live="school_year_id" @disabled($useCustomRange) class="mt-1 rounded-lg border-stone-300 text-sm disabled:bg-stone-100 disabled:text-stone-500">
                <option value="">—</option>
                @foreach ($schoolYears as $schoolYear)
                    <option value="{{ $schoolYear->id }}">{{ $schoolYear->label }}</option>
                @endforeach
            </select>
        </div>

        <label class="flex items-center gap-2 pb-2 text-sm text-stone-700">
            <input type="checkbox" wire:model.live="useCustomRange" class="rounded border-stone-300">
            Plage de dates personnalisée
        </label>

        @if ($useCustomRange)
            <div>
                <label class="block text-xs font-medium text-stone-500">Du</label>
                <input type="date" wire:model.live="start_date" class="mt-1 rounded-lg border-stone-300 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-stone-500">Au</label>
                <input type="date" wire:model.live="end_date" class="mt-1 rounded-lg border-stone-300 text-sm">
            </div>
        @endif

        @if (($isMultiSchoolFounder && count($establishmentGroups) > 0) || (! $isMultiSchoolFounder && count($groups) > 0))
            <a
                href="{{ route('reports.financial-summary-pdf', [
                    'school_year_id' => $useCustomRange ? null : $school_year_id,
                    'start_date' => $useCustomRange ? $start_date : null,
                    'end_date' => $useCustomRange ? $end_date : null,
                    'establishment_ids' => $isMultiSchoolFounder ? $establishmentFilter : null,
                ]) }}"
                target="_blank"
                class="mb-0.5 inline-block rounded-lg border border-orange-700 px-3 py-1.5 text-sm font-medium text-orange-700 hover:bg-orange-50"
            >
                Bilan financier (PDF)
            </a>
        @endif

        <span wire:loading class="text-xs text-stone-500">Chargement…</span>
    </div>

    @if ($isMultiSchoolFounder)
        <div class="mt-4">
            <label class="block text-xs font-medium text-stone-500">Écoles</label>
            <div class="mt-1 flex flex-wrap gap-3">
                @foreach ($groupEstablishments as $establishment)
                    <label class="flex items-center gap-1.5 text-sm text-stone-700">
                        <input type="checkbox" wire:model.live="establishmentFilter" value="{{ $establishment->id }}" class="rounded border-stone-300">
                        {{ $establishment->name }}
                    </label>
                @endforeach
            </div>
        </div>
    @endif

    @if ($invalidRange)
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-800">
            La date de fin doit être postérieure à la date de début.
        </div>
    @elseif ($isMultiSchoolFounder)
        @if ($noEstablishmentSelected)
            <div class="mt-6 rounded-lg border border-stone-200 bg-white px-4 py-6 text-center text-sm text-stone-500">
                Sélectionnez au moins une école.
            </div>
        @else
            <div class="mt-6 space-y-6">
                @foreach ($establishmentGroups as $establishmentGroup)
                    <div>
                        <h2 class="mb-2 text-base font-semibold text-stone-900">{{ $establishmentGroup['establishmentName'] }}</h2>
                        @if (count($establishmentGroup['groups']) === 0)
                            <div class="rounded-lg border border-stone-200 bg-white px-4 py-4 text-sm text-stone-500">
                                Aucun encaissement ni dépense enregistré pour cette école sur cette période.
                            </div>
                        @else
                            <div class="overflow-hidden rounded-lg border border-stone-200 bg-white">
                                <div class="overflow-x-auto">
                                    @include('livewire.billing.financial-summary.role-groups-table', [
                                        'groups' => $establishmentGroup['groups'],
                                        'totalCollected' => $establishmentGroup['collected'],
                                        'totalSpent' => $establishmentGroup['spent'],
                                        'totalNet' => $establishmentGroup['net'],
                                        'keyPrefix' => 'etab-'.$establishmentGroup['establishment_id'],
                                        'totalLabel' => 'Total école',
                                    ])
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach

                <div class="overflow-hidden rounded-lg border border-stone-300 bg-white">
                    <table class="min-w-full text-sm">
                        <tbody>
                            <tr>
                                <td class="whitespace-nowrap px-4 py-2 font-semibold text-stone-900">Total général ({{ count($establishmentGroups) }} {{ count($establishmentGroups) > 1 ? 'écoles' : 'école' }})</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right font-semibold text-stone-900">{{ money($grandTotalCollected) }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right font-semibold text-stone-900">{{ money($grandTotalSpent) }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right font-semibold text-stone-900">{{ money($grandTotalNet) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @elseif (count($groups) === 0)
        <div class="mt-6 rounded-lg border border-stone-200 bg-white px-4 py-6 text-center text-sm text-stone-500">
            Aucun encaissement ni dépense enregistré sur cette période.
        </div>
    @else
        <div class="mt-6 overflow-hidden rounded-lg border border-stone-200 bg-white">
            <div class="overflow-x-auto">
                @include('livewire.billing.financial-summary.role-groups-table', [
                    'groups' => $groups,
                    'totalCollected' => $totalCollected,
                    'totalSpent' => $totalSpent,
                    'totalNet' => $totalNet,
                ])
            </div>
        </div>
    @endif
</div>
