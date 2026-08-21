<div>
    <h1 class="text-2xl font-semibold text-stone-900">Bulletins arabes</h1>

    <div class="mt-4 flex flex-wrap items-end gap-4 rounded-lg border border-stone-200 bg-white p-4">
        <div>
            <label class="block text-sm font-medium text-stone-700">Niveau arabe</label>
            <select wire:model.live="arabic_level_id" class="mt-1 block w-48 rounded-lg border-stone-300 text-sm" dir="rtl">
                <option value="">—</option>
                @foreach ($arabicLevels as $arabicLevel)
                    <option value="{{ $arabicLevel->id }}">{{ $arabicLevel->wording }}</option>
                @endforeach
            </select>
        </div>

        @if ($this->selectedLevelRequiresSeries())
            <div>
                <label class="block text-sm font-medium text-stone-700">Série arabe</label>
                <select wire:model.live="arabic_serie_id" class="mt-1 block w-48 rounded-lg border-stone-300 text-sm" dir="rtl">
                    <option value="">—</option>
                    @foreach ($arabicSeries as $arabicSerie)
                        <option value="{{ $arabicSerie->id }}">{{ $arabicSerie->serie_wording }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div>
            <label class="block text-sm font-medium text-stone-700">Période</label>
            <select wire:model.live="arabic_term_id" class="mt-1 block w-48 rounded-lg border-stone-300 text-sm">
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
                    wire:confirm="Ce groupe a déjà des bulletins générés pour cette période. Continuer va recalculer et remplacer le rang et la moyenne de chaque élève. Continuer ?"
                    class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800"
                >
                    <span wire:loading.remove wire:target="generate">Régénérer les bulletins</span>
                    <span wire:loading wire:target="generate">Génération…</span>
                </button>
            @else
                <button
                    type="button"
                    wire:click="generate"
                    wire:loading.attr="disabled"
                    wire:target="generate"
                    class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800"
                >
                    <span wire:loading.remove wire:target="generate">Générer les bulletins</span>
                    <span wire:loading wire:target="generate">Génération…</span>
                </button>
            @endif
        @endcan
    </div>

    @error('arabic_level_id')
        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
    @enderror
    @error('arabic_serie_id')
        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
    @enderror
    @error('arabic_term_id')
        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
    @enderror

    @if ($arabic_level_id && $arabic_term_id)
        <div class="mt-4 rounded-lg border border-stone-200 bg-white px-4 py-3 text-sm text-stone-600">
            @if ($reportCards->isNotEmpty())
                Bulletins générés le {{ $generatedAt?->format('d/m/Y à H:i') }} — {{ $reportCards->count() }}/{{ $totalStudents }} élèves classés.
                @if ($totalStudents !== null && $reportCards->count() < $totalStudents)
                    <span class="text-amber-700">{{ $totalStudents - $reportCards->count() }} élève(s) sans note exclu(s) du classement.</span>
                @endif
            @else
                {{ $totalStudents }} élève(s) inscrit(s) dans ce groupe. Bulletins non encore générés pour cette période.
            @endif
        </div>
    @endif

    <div class="mt-6 overflow-hidden rounded-lg border border-stone-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead class="bg-stone-50">
                    <tr>
                        <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-stone-500">Rang</th>
                        <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-stone-500">Élève</th>
                        <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-stone-500">Moyenne / 20</th>
                        <th class="whitespace-nowrap px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($reportCards as $reportCard)
                        <tr wire:key="arabic-report-card-{{ $reportCard->id }}">
                            <td class="whitespace-nowrap px-4 py-2 text-stone-900">{{ $reportCard->rank }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ $reportCard->enrollment?->student?->last_name }} {{ $reportCard->enrollment?->student?->first_name }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ $reportCard->average }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-right">
                                <a href="{{ route('arabic.report-cards.pdf', $reportCard) }}" target="_blank" class="text-stone-500 hover:text-stone-900">
                                    Voir le PDF
                                </a>
                                <a href="{{ route('arabic.report-cards.pdf', ['arabicReportCard' => $reportCard, 'download' => 1]) }}" class="ml-3 text-stone-500 hover:text-stone-900">
                                    Télécharger
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-stone-500">
                                Sélectionnez un niveau et une période{{ $arabic_level_id && $arabic_term_id ? ', puis générez les bulletins' : '' }}.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
