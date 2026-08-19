<div>
    <h1 class="text-2xl font-semibold text-slate-900">Bulletins</h1>

    <div class="mt-4 flex flex-wrap items-end gap-4 rounded-md border border-slate-200 bg-white p-4">
        <div>
            <label class="block text-sm font-medium text-slate-700">Classe</label>
            <select wire:model.live="classroom_id" class="mt-1 block w-48 rounded-md border-slate-300 text-sm">
                <option value="">—</option>
                @foreach ($classrooms as $classroom)
                    <option value="{{ $classroom->id }}">{{ $classroom->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Période</label>
            <select wire:model.live="term_id" class="mt-1 block w-48 rounded-md border-slate-300 text-sm">
                <option value="">—</option>
                @foreach ($terms as $term)
                    <option value="{{ $term->id }}">{{ $term->label }}</option>
                @endforeach
            </select>
        </div>

        @can('create', \App\Domain\Grading\Models\ReportCard::class)
            @if ($reportCards->isNotEmpty())
                <button
                    type="button"
                    wire:click="generate"
                    wire:loading.attr="disabled"
                    wire:target="generate"
                    wire:confirm="Cette classe a déjà des bulletins générés pour cette période. Continuer va recalculer et remplacer le rang et la moyenne de chaque élève. Continuer ?"
                    class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
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
                    class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
                >
                    <span wire:loading.remove wire:target="generate">Générer les bulletins</span>
                    <span wire:loading wire:target="generate">Génération…</span>
                </button>
            @endif
        @endcan
    </div>

    @error('classroom_id')
        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
    @enderror
    @error('term_id')
        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
    @enderror

    @if ($classroom_id && $term_id)
        <div class="mt-4 rounded-md border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
            @if ($reportCards->isNotEmpty())
                Bulletins générés le {{ $generatedAt?->format('d/m/Y à H:i') }} — {{ $reportCards->count() }}/{{ $totalStudents }} élèves classés.
                @if ($totalStudents !== null && $reportCards->count() < $totalStudents)
                    <span class="text-amber-700">{{ $totalStudents - $reportCards->count() }} élève(s) sans note exclu(s) du classement.</span>
                @endif
            @else
                {{ $totalStudents }} élève(s) inscrit(s) dans cette classe. Bulletins non encore générés pour cette période.
            @endif
        </div>
    @endif

    <div class="mt-6 overflow-hidden rounded-md border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-slate-500">Rang</th>
                        <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-slate-500">Élève</th>
                        <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-slate-500">Moyenne / 20</th>
                        <th class="whitespace-nowrap px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($reportCards as $reportCard)
                        <tr wire:key="report-card-{{ $reportCard->id }}">
                            <td class="whitespace-nowrap px-4 py-2 text-slate-900">{{ $reportCard->rank }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-slate-600">{{ $reportCard->student?->last_name }} {{ $reportCard->student?->first_name }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-slate-600">{{ $reportCard->average }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-right">
                                <a href="{{ route('grading.report-cards.pdf', $reportCard) }}" target="_blank" class="text-slate-500 hover:text-slate-900">
                                    Voir le PDF
                                </a>
                                <a href="{{ route('grading.report-cards.pdf', ['reportCard' => $reportCard, 'download' => 1]) }}" class="ml-3 text-slate-500 hover:text-slate-900">
                                    Télécharger
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-slate-500">
                                Sélectionnez une classe et une période{{ $classroom_id && $term_id ? ', puis générez les bulletins' : '' }}.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
