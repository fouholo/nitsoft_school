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

        @if (count($groups) > 0)
            <a
                href="{{ route('reports.financial-summary-pdf', [
                    'school_year_id' => $useCustomRange ? null : $school_year_id,
                    'start_date' => $useCustomRange ? $start_date : null,
                    'end_date' => $useCustomRange ? $end_date : null,
                ]) }}"
                target="_blank"
                class="mb-0.5 inline-block rounded-lg border border-orange-700 px-3 py-1.5 text-sm font-medium text-orange-700 hover:bg-orange-50"
            >
                Bilan financier (PDF)
            </a>
        @endif

        <span wire:loading class="text-xs text-stone-500">Chargement…</span>
    </div>

    @if ($invalidRange)
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-800">
            La date de fin doit être postérieure à la date de début.
        </div>
    @elseif (count($groups) === 0)
        <div class="mt-6 rounded-lg border border-stone-200 bg-white px-4 py-6 text-center text-sm text-stone-500">
            Aucun encaissement ni dépense enregistré sur cette période.
        </div>
    @else
        <div class="mt-6 overflow-hidden rounded-lg border border-stone-200 bg-white">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead class="bg-stone-50">
                        <tr>
                            <th scope="col" class="whitespace-nowrap px-4 py-2 text-left font-medium text-stone-500">Rôle / Utilisateur</th>
                            <th scope="col" class="whitespace-nowrap px-4 py-2 text-right font-medium text-stone-500">Encaissé</th>
                            <th scope="col" class="whitespace-nowrap px-4 py-2 text-right font-medium text-stone-500">Dépensé</th>
                            <th scope="col" class="whitespace-nowrap px-4 py-2 text-right font-medium text-stone-500">Net</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($groups as $group)
                            <tr class="bg-stone-50" wire:key="role-{{ $group['role'] ?? 'none' }}">
                                <td class="whitespace-nowrap px-4 py-2 font-medium text-stone-800">{{ $group['roleLabel'] }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right font-medium text-stone-800">{{ money($group['collected']) }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right font-medium text-stone-800">{{ money($group['spent']) }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right font-medium text-stone-800">{{ money($group['net']) }}</td>
                            </tr>
                            @foreach ($group['rows'] as $row)
                                <tr wire:key="user-{{ $row['user_id'] }}">
                                    <td class="whitespace-nowrap px-4 py-2 pl-8 text-stone-600">{{ $row['user_name'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right text-stone-600">{{ money($row['collected']) }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right text-stone-600">{{ money($row['spent']) }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right text-stone-600">{{ money($row['net']) }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-stone-200">
                        <tr>
                            <td class="whitespace-nowrap px-4 py-2 font-semibold text-stone-900">Total général</td>
                            <td class="whitespace-nowrap px-4 py-2 text-right font-semibold text-stone-900">{{ money($totalCollected) }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-right font-semibold text-stone-900">{{ money($totalSpent) }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-right font-semibold text-stone-900">{{ money($totalNet) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endif
</div>
