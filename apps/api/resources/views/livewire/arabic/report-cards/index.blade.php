<div>
    <h1 class="text-2xl font-semibold text-stone-900">{{ __('Bulletins arabes') }}</h1>

    <div class="mt-4 flex flex-wrap items-end gap-4 rounded-lg border border-stone-200 bg-white p-4">
        <div>
            <label for="arabic_classroom_id" class="block text-sm font-medium text-stone-700">{{ __('Classe arabe') }}</label>
            <select id="arabic_classroom_id" wire:model.live="arabic_classroom_id" class="mt-1 block w-48 rounded-lg border-stone-300 text-sm">
                <option value="">—</option>
                @foreach ($arabicClassrooms as $arabicClassroom)
                    <option value="{{ $arabicClassroom->id }}">{{ $arabicClassroom->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="arabic_term_id" class="block text-sm font-medium text-stone-700">{{ __('Période') }}</label>
            <select id="arabic_term_id" wire:model.live="arabic_term_id" class="mt-1 block w-48 rounded-lg border-stone-300 text-sm">
                <option value="">—</option>
                @foreach ($arabicTerms as $arabicTerm)
                    <option value="{{ $arabicTerm->id }}">{{ $arabicTerm->label }}</option>
                @endforeach
            </select>
        </div>

        @can('create', \App\Domain\Arabic\Models\ArabicReportCard::class)
            @if ($reportCards->isNotEmpty())
                <button
                    type="button"
                    wire:click="generate"
                    wire:loading.attr="disabled"
                    wire:target="generate"
                    wire:confirm="{{ __('Cette classe a déjà des bulletins générés pour cette période. Continuer va recalculer et remplacer le rang et la moyenne de chaque élève. Continuer ?') }}"
                    class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800"
                >
                    <span wire:loading.remove wire:target="generate">{{ __('Régénérer les bulletins') }}</span>
                    <span wire:loading wire:target="generate">{{ __('Génération…') }}</span>
                </button>
            @else
                <button
                    type="button"
                    wire:click="generate"
                    wire:loading.attr="disabled"
                    wire:target="generate"
                    class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800"
                >
                    <span wire:loading.remove wire:target="generate">{{ __('Générer les bulletins') }}</span>
                    <span wire:loading wire:target="generate">{{ __('Génération…') }}</span>
                </button>
            @endif
        @endcan
    </div>

    @error('arabic_classroom_id')
        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
    @enderror
    @error('arabic_term_id')
        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
    @enderror

    @if ($arabic_classroom_id && $arabic_term_id)
        <div class="mt-4 rounded-lg border border-stone-200 bg-white px-4 py-3 text-sm text-stone-600">
            @if ($reportCards->isNotEmpty())
                {{ __('Bulletins générés le :date — :count/:total élèves classés.', ['date' => $generatedAt?->format('d/m/Y à H:i'), 'count' => $reportCards->count(), 'total' => $totalStudents]) }}
                @if ($totalStudents !== null && $reportCards->count() < $totalStudents)
                    <span class="text-amber-700">{{ __(':count élève(s) sans note exclu(s) du classement.', ['count' => $totalStudents - $reportCards->count()]) }}</span>
                @endif
            @else
                {{ __(':count élève(s) inscrit(s) dans cette classe. Bulletins non encore générés pour cette période.', ['count' => $totalStudents]) }}
            @endif
        </div>
    @endif

    <div class="mt-6 overflow-hidden rounded-lg border border-stone-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead class="bg-stone-50">
                    <tr>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Rang') }}</th>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Élève') }}</th>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Moyenne / 20') }}</th>
                        <th class="whitespace-nowrap px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($reportCards as $reportCard)
                        <tr wire:key="arabic-report-card-{{ $reportCard->id }}">
                            <td class="whitespace-nowrap px-4 py-2 text-stone-900">{{ $reportCard->rank }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ $reportCard->enrollment?->student?->last_name }} {{ $reportCard->enrollment?->student?->first_name }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ $reportCard->average }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-end">
                                <a href="{{ route('arabic.report-cards.pdf', $reportCard) }}" target="_blank" class="text-stone-500 hover:text-stone-900">
                                    {{ __('Voir le PDF') }}
                                </a>
                                <a href="{{ route('arabic.report-cards.pdf', ['arabicReportCard' => $reportCard, 'download' => 1]) }}" class="ms-3 text-stone-500 hover:text-stone-900">
                                    {{ __('Télécharger') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-stone-500">
                                {{ __('Sélectionnez une classe et une période') }}{{ $arabic_classroom_id && $arabic_term_id ? __(', puis générez les bulletins') : '' }}.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
