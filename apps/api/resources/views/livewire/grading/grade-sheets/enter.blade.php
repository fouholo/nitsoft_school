<div>
    <a href="{{ route('grading.grade-sheets.index') }}" class="text-sm text-slate-500 hover:text-slate-900">&larr; Retour aux évaluations</a>

    <div class="mt-2">
        <h1 class="text-2xl font-semibold text-slate-900">{{ $gradeSheet->title }}</h1>
        <p class="text-sm text-slate-500">
            {{ $gradeSheet->classroom?->name }} — {{ $gradeSheet->subject?->name }} — Barème {{ $gradeSheet->max_score }} — {{ $gradeSheet->term?->label }}
        </p>
    </div>

    @if ($justSaved)
        <div class="mt-4 rounded-md bg-emerald-50 px-4 py-2 text-sm text-emerald-700">
            Notes enregistrées.
        </div>
    @endif

    <form wire:submit="save" class="mt-6">
        <div class="overflow-hidden rounded-md border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium text-slate-500">Élève</th>
                        <th class="px-4 py-2 text-left font-medium text-slate-500">Note / {{ $gradeSheet->max_score }}</th>
                        <th class="px-4 py-2 text-left font-medium text-slate-500">Commentaire</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($students as $student)
                        <tr wire:key="grade-row-{{ $student->id }}">
                            <td class="px-4 py-2 text-slate-900">{{ $student->last_name }} {{ $student->first_name }}</td>
                            <td class="px-4 py-2">
                                <input
                                    type="number"
                                    step="0.25"
                                    min="0"
                                    max="{{ $gradeSheet->max_score }}"
                                    wire:model="scores.{{ $student->id }}"
                                    class="block w-24 rounded-md border-slate-300 text-sm"
                                >
                                @error('scores.'.$student->id) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </td>
                            <td class="px-4 py-2">
                                <input
                                    type="text"
                                    wire:model="comments.{{ $student->id }}"
                                    class="block w-full rounded-md border-slate-300 text-sm"
                                >
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-6 text-center text-slate-500">Aucun élève inscrit dans cette classe.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <button type="submit" class="mt-4 rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800">
            Enregistrer les notes
        </button>
    </form>
</div>
