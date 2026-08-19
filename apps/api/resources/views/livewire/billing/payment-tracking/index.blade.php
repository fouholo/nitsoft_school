<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Suivi des paiements</h1>
    </div>

    @if ($lateCount > 0)
        <div class="mt-4 flex items-center gap-2 rounded-md border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-800">
            <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">{{ $lateCount }}</span>
            <span>{{ $lateCount > 1 ? 'élèves en retard' : 'élève en retard' }} — {{ money($lateTotal) }} restant à percevoir</span>
        </div>
    @endif

    <div class="mt-4 flex flex-wrap items-end gap-4">
        <div>
            <label class="sr-only">Année scolaire</label>
            <select wire:model.live="school_year_id" class="rounded-md border-slate-300 text-sm">
                <option value="">—</option>
                @foreach ($schoolYears as $schoolYear)
                    <option value="{{ $schoolYear->id }}">{{ $schoolYear->label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="sr-only">Niveau</label>
            <select wire:model.live="levelFilter" class="rounded-md border-slate-300 text-sm">
                <option value="">Tous les niveaux</option>
                @foreach ($levels as $level)
                    <option value="{{ $level->id }}">{{ $level->level_wording }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="sr-only">Statut</label>
            <select wire:model.live="statusFilter" class="rounded-md border-slate-300 text-sm">
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
                class="rounded-md border-slate-300 text-sm"
            >
        </div>

        <span wire:loading class="text-xs text-slate-500">Chargement…</span>
    </div>

    <div class="mt-6 overflow-hidden rounded-md border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-slate-500">Élève</th>
                        <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-slate-500">Classe</th>
                        <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-slate-500">Dû à ce jour</th>
                        <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-slate-500">Payé</th>
                        <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-slate-500">Solde</th>
                        <th class="whitespace-nowrap px-4 py-2"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $row)
                        <tr wire:key="student-{{ $row['student_id'] }}" class="hover:bg-slate-50">
                            <td class="whitespace-nowrap px-4 py-2 text-slate-900">{{ $row['student']->last_name }} {{ $row['student']->first_name }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-slate-600">{{ $row['classroom']?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-slate-600">{{ money($row['due_so_far']) }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-slate-600">{{ money($row['total_paid']) }}</td>
                            <td class="whitespace-nowrap px-4 py-2">
                                @if ($row['balance'] > 0)
                                    <span class="whitespace-nowrap rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">
                                        Retard de {{ money($row['balance']) }}
                                    </span>
                                @elseif ($row['balance'] < 0)
                                    <span class="whitespace-nowrap rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">
                                        Avance de {{ money(abs($row['balance'])) }}
                                    </span>
                                @else
                                    <span class="whitespace-nowrap rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                                        À jour
                                    </span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-right">
                                @if ($row['enrollment'])
                                    @can('create', \App\Domain\Billing\Models\Payment::class)
                                        <a
                                            href="{{ route('billing.enrollments.show', $row['enrollment']) }}"
                                            wire:navigate
                                            class="inline-block rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                        >
                                            Encaisser
                                        </a>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-slate-500">
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
                    <tfoot class="border-t border-slate-200">
                        <tr>
                            <td class="whitespace-nowrap px-4 py-2 font-medium text-slate-700" colspan="2">Total</td>
                            <td class="whitespace-nowrap px-4 py-2 font-medium text-slate-700">{{ money($displayedDue) }}</td>
                            <td class="whitespace-nowrap px-4 py-2 font-medium text-slate-700">{{ money($displayedPaid) }}</td>
                            <td class="whitespace-nowrap px-4 py-2 font-medium text-slate-700" colspan="2">
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
    </div>
</div>
