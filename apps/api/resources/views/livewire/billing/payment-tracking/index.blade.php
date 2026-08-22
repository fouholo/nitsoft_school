<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-stone-900">{{ __('Suivi des paiements') }}</h1>
    </div>

    @if ($scopedToOwn)
        <div class="mt-4 rounded-lg border border-orange-200 bg-orange-50 px-4 py-2.5 text-sm text-orange-900">
            {{ __("Vue restreinte aux élèves dont vous avez personnellement encaissé un paiement — un élève n'ayant jamais payé peut ne pas apparaître ici.") }}
        </div>
    @endif

    @if ($lateCount > 0)
        <div class="mt-4 flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-800">
            <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">{{ $lateCount }}</span>
            <span>{{ __(':count élève(s) en retard — :amount restant à percevoir', ['count' => $lateCount, 'amount' => money($lateTotal)]) }}</span>
        </div>
    @endif

    <div class="mt-4 flex flex-wrap items-end gap-4">
        <div>
            <label class="sr-only">{{ __('Année scolaire') }}</label>
            <select wire:model.live="school_year_id" class="rounded-lg border-stone-300 text-sm">
                <option value="">—</option>
                @foreach ($schoolYears as $schoolYear)
                    <option value="{{ $schoolYear->id }}">{{ $schoolYear->label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="sr-only">{{ __('Niveau') }}</label>
            <select wire:model.live="levelFilter" class="rounded-lg border-stone-300 text-sm">
                <option value="">{{ __('Tous les niveaux') }}</option>
                @foreach ($levels as $level)
                    <option value="{{ $level->id }}">{{ $level->level_wording }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="sr-only">{{ __('Statut') }}</label>
            <select wire:model.live="statusFilter" class="rounded-lg border-stone-300 text-sm">
                <option value="">{{ __('Tous les statuts') }}</option>
                <option value="late">{{ __('En retard') }}</option>
                <option value="ontime">{{ __('À jour') }}</option>
                <option value="advance">{{ __('En avance') }}</option>
            </select>
        </div>

        <div>
            <label class="sr-only">{{ __('Rechercher un élève') }}</label>
            <input
                type="search"
                wire:model.live.debounce.400ms="search"
                placeholder="{{ __('Rechercher un élève…') }}"
                class="rounded-lg border-stone-300 text-sm"
            >
        </div>

        <span wire:loading class="text-xs text-stone-500">{{ __('Chargement…') }}</span>
    </div>

    <div class="mt-6 overflow-hidden rounded-lg border border-stone-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead class="bg-stone-50">
                    <tr>
                        <th scope="col" class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Élève') }}</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Classe') }}</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-2 text-end font-medium text-stone-500">{{ __('Dû à ce jour') }}</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-2 text-end font-medium text-stone-500">{{ __('Payé') }}</th>
                        <th scope="col" class="whitespace-nowrap px-4 py-2 text-end font-medium text-stone-500">{{ __('Solde') }} <span class="font-normal text-stone-400" title="{{ __('Triés du solde le plus élevé au plus faible') }}">▾</span></th>
                        @if ($canRecordPayments)
                            <th scope="col" class="whitespace-nowrap px-4 py-2"><span class="sr-only">{{ __('Actions') }}</span></th>
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
                            <td class="whitespace-nowrap px-4 py-2 text-end text-stone-600">{{ money($row['due_so_far']) }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-end text-stone-600">{{ money($row['total_paid']) }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-end">
                                @if ($row['balance'] > 0)
                                    <span class="whitespace-nowrap rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">
                                        {{ __('Retard de :amount', ['amount' => money($row['balance'])]) }}
                                    </span>
                                @elseif ($row['balance'] < 0)
                                    <span class="whitespace-nowrap rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">
                                        {{ __('Avance de :amount', ['amount' => money(abs($row['balance']))]) }}
                                    </span>
                                @else
                                    <span class="whitespace-nowrap rounded-full bg-stone-100 px-2 py-0.5 text-xs font-medium text-stone-700">
                                        {{ __('À jour') }}
                                    </span>
                                @endif
                            </td>
                            @if ($canRecordPayments)
                                <td class="whitespace-nowrap px-4 py-2 text-end">
                                    <div class="inline-flex items-center gap-2">
                                        @if ($row['enrollment'])
                                            <a
                                                href="{{ route('billing.enrollments.show', $row['enrollment']) }}"
                                                wire:navigate
                                                class="inline-flex min-h-11 items-center rounded-lg border border-stone-300 px-3 text-sm font-medium text-stone-700 hover:bg-stone-50"
                                            >
                                                {{ __('Encaisser') }}
                                            </a>
                                        @endif
                                        @if ($row['balance'] > 0)
                                            <a
                                                href="{{ route('reports.payment-reminder-pdf', $row['student']) }}"
                                                target="_blank"
                                                class="inline-flex min-h-11 items-center rounded-lg border border-stone-300 px-3 text-sm font-medium text-stone-700 hover:bg-stone-50"
                                            >
                                                {{ __('Relance') }}
                                            </a>
                                        @endif
                                        @if ($row['hasUpcomingInstallment'])
                                            <a
                                                href="{{ route('reports.payment-reminder-pdf', ['student' => $row['student'], 'type' => 'upcoming']) }}"
                                                target="_blank"
                                                class="inline-flex min-h-11 items-center rounded-lg border border-stone-300 px-3 text-sm font-medium text-stone-700 hover:bg-stone-50"
                                            >
                                                {{ __('Échéance') }}
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canRecordPayments ? 6 : 5 }}" class="px-4 py-6 text-center text-stone-500">
                                @if (! $school_year_id)
                                    {{ __('Sélectionnez une année scolaire pour afficher le suivi des paiements.') }}
                                @else
                                    {{ __('Aucun élève ne correspond à cette sélection.') }}
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot class="border-t border-stone-200">
                        <tr>
                            <td class="whitespace-nowrap px-4 py-2 font-medium text-stone-700" colspan="2">{{ __('Total (:count élève(s) affiché(s))', ['count' => $displayedCount]) }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-end font-medium text-stone-700">{{ money($displayedDue) }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-end font-medium text-stone-700">{{ money($displayedPaid) }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-end font-medium text-stone-700" colspan="{{ $canRecordPayments ? 2 : 1 }}">
                                @if ($displayedBalance > 0)
                                    {{ __('Retard de :amount', ['amount' => money($displayedBalance)]) }}
                                @elseif ($displayedBalance < 0)
                                    {{ __('Avance de :amount', ['amount' => money(abs($displayedBalance))]) }}
                                @else
                                    {{ __('À jour') }}
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
