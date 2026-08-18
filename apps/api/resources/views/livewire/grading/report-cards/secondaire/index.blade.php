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
            <button
                type="button"
                wire:click="generate"
                wire:loading.attr="disabled"
                class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
            >
                Générer les bulletins
            </button>
        @endcan
    </div>

    @error('classroom_id')
        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
    @enderror

    <div class="mt-6 overflow-hidden rounded-md border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Rang</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Élève</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Moyenne / 20</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($reportCards as $reportCard)
                    <tr wire:key="report-card-{{ $reportCard->id }}">
                        <td class="px-4 py-2 text-slate-900">{{ $reportCard->rank }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $reportCard->student?->last_name }} {{ $reportCard->student?->first_name }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $reportCard->average }}</td>
                        <td class="px-4 py-2 text-right">
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
