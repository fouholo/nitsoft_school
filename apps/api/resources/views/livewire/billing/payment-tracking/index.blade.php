<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Suivi des paiements</h1>
    </div>

    <div class="mt-4 flex flex-wrap gap-4">
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
    </div>

    <div class="mt-6 overflow-hidden rounded-md border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Élève</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Classe</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Dû à ce jour</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Payé</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Solde</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $row)
                    <tr wire:key="student-{{ $row['student_id'] }}">
                        <td class="px-4 py-2 text-slate-900">{{ $row['student']->last_name }} {{ $row['student']->first_name }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $row['classroom']?->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ money($row['due_so_far']) }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ money($row['total_paid']) }}</td>
                        <td class="px-4 py-2">
                            @if ($row['balance'] > 0)
                                <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">
                                    Retard de {{ money($row['balance']) }}
                                </span>
                            @elseif ($row['balance'] < 0)
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">
                                    Avance de {{ money(abs($row['balance'])) }}
                                </span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                                    À jour
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right">
                            @if ($row['enrollment'])
                                @can('create', \App\Domain\Billing\Models\Payment::class)
                                    <a href="{{ route('billing.enrollments.show', $row['enrollment']) }}" wire:navigate class="text-slate-500 hover:text-slate-900">
                                        Encaisser
                                    </a>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-slate-500">Aucune inscription pour cette sélection.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
