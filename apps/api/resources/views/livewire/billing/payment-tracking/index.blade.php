<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-stone-900">Suivi des paiements</h1>
    </div>

    @if ($scopedToOwn)
        <div class="mt-4 rounded-lg border border-orange-200 bg-orange-50 px-4 py-2.5 text-sm text-orange-900">
            Vue restreinte aux élèves dont vous avez personnellement encaissé un paiement — un élève n'ayant jamais payé peut ne pas apparaître ici.
        </div>
    @endif

    @if ($lateCount > 0)
        <div class="mt-4 flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-800">
            <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">{{ $lateCount }}</span>
            <span>{{ $lateCount > 1 ? 'élèves en retard' : 'élève en retard' }} — {{ money($lateTotal) }} restant à percevoir</span>
        </div>
    @endif

    <div class="mt-4 flex flex-wrap items-end gap-4">
        <div>
            <label class="sr-only">Année scolaire</label>
            <select wire:model.live="school_year_id" class="rounded-lg border-stone-300 text-sm">
                <option value="">—</option>
                @foreach ($schoolYears as $schoolYear)
                    <option value="{{ $schoolYear->id }}">{{ $schoolYear->label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="sr-only">Niveau</label>
            <select wire:model.live="levelFilter" class="rounded-lg border-stone-300 text-sm">
                <option value="">Tous les niveaux</option>
                @foreach ($levels as $level)
                    <option value="{{ $level->id }}">{{ $level->level_wording }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="sr-only">Statut</label>
            <select wire:model.live="statusFilter" class="rounded-lg border-stone-300 text-sm">
                <option value="">Tous les statuts</option>
                <option value="late">En retard</option>
                <option value="ontime">À jour</option>
                <option value="advance">En avance</option>
            </select>
        </div>

        <div>
            <label class="sr-only">Rechercher un élève</label>
            <input
                type="search"
                wire:model.live.debounce.400ms="search"
                placeholder="Rechercher un élève…"
                class="rounded-lg border-stone-300 text-sm"
            >
        </div>

        <span wire:loading class="text-xs text-stone-500">Chargement…</span>
    </div>

    <div class="mt-6 overflow-hidden rounded-lg border border-stone-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead class="bg-stone-50">
                    <tr>
                        <th scope="col" class="whitespace-nowrap px-4 py-2 text-left font-medium text-stone-500">Élève</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-2 text-left font-medium text-stone-500">Classe</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-2 text-right font-medium text-stone-500">Dû à ce jour</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-2 text-right font-medium text-stone-500">Payé</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-2 text-right font-medium text-stone-500">Solde <span class="font-normal text-stone-400" title="Triés du solde le plus élevé au plus faible">▾</span></th>
                        @if ($canRecordPayments)
                            <th scope="col" class="whitespace-nowrap px-4 py-2"><span class="sr-only">Actions</span></th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($rows as $row)
                        <tr wire:key="student-{{ $row['student_id'] }}" class="hover:bg-stone-50">
                            <td class="px-4 py-2 text-stone-900">
                                <div class="max-w-[14rem] truncate" title="{{ $row['student']->last_name }} {{ $row['student']->first_name }}">{{ $row['student']->last_name }} {{ $row['student']->first_name }}</div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ $row['classroom']?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-right text-stone-600">{{ money($row['due_so_far']) }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-right text-stone-600">{{ money($row['total_paid']) }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-right">
                                @if ($row['balance'] > 0)
                                    <span class="whitespace-nowrap rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">
                                        Retard de {{ money($row['balance']) }}
                                    </span>
                                @elseif ($row['balance'] < 0)
                                    <span class="whitespace-nowrap rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">
                                        Avance de {{ money(abs($row['balance'])) }}
                                    </span>
                                @else
                                    <span class="whitespace-nowrap rounded-full bg-stone-100 px-2 py-0.5 text-xs font-medium text-stone-700">
                                        À jour
                                    </span>
                                @endif
                            </td>
                            @if ($canRecordPayments)
                                <td class="whitespace-nowrap px-4 py-2 text-right">
                                    @if ($row['enrollment'])
                                        <a
                                            href="{{ route('billing.enrollments.show', $row['enrollment']) }}"
                                            wire:navigate
                                            class="inline-flex min-h-11 items-center rounded-lg border border-stone-300 px-3 text-sm font-medium text-stone-700 hover:bg-stone-50"
                                        >
                                            Encaisser
                                        </a>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canRecordPayments ? 6 : 5 }}" class="px-4 py-6 text-center text-stone-500">
                                @if (! $school_year_id)
                                    Sélectionnez une année scolaire pour afficher le suivi des paiements.
                                @else
                                    Aucun élève ne correspond à cette sélection.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot class="border-t border-stone-200">
                        <tr>
                            <td class="whitespace-nowrap px-4 py-2 font-medium text-stone-700" colspan="2">Total ({{ $displayedCount }} {{ $displayedCount > 1 ? 'élèves affichés' : 'élève affiché' }})</td>
                            <td class="whitespace-nowrap px-4 py-2 text-right font-medium text-stone-700">{{ money($displayedDue) }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-right font-medium text-stone-700">{{ money($displayedPaid) }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-right font-medium text-stone-700" colspan="{{ $canRecordPayments ? 2 : 1 }}">
                                @if ($displayedBalance > 0)
                                    Retard de {{ money($displayedBalance) }}
                                @elseif ($displayedBalance < 0)
                                    Avance de {{ money(abs($displayedBalance)) }}
                                @else
                                    À jour
                                @endif
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        @if ($rows->hasPages())
            <div class="border-t border-stone-200 px-4 py-3">
                {{ $rows->links() }}
            </div>
        @endif
    </div>
</div>
