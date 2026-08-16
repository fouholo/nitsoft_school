<div>
    <a href="{{ route('grading.grade-sheets.primaire-students', $gradeSheet) }}" class="text-sm text-slate-500 hover:text-slate-900">&larr; Retour aux élèves</a>

    <div class="mt-2">
        <h1 class="text-2xl font-semibold text-slate-900">{{ $student->last_name }} {{ $student->first_name }}</h1>
        <p class="text-sm text-slate-500">
            {{ $classroom->name }} — {{ $gradeSheet->title }} (Composition {{ $gradeSheet->composition_number }})
        </p>
    </div>

    @if ($justSaved)
        <div class="mt-4 rounded-md bg-emerald-50 px-4 py-2 text-sm text-emerald-700">
            Notes enregistrées.
        </div>
    @endif

    <form wire:submit="save" class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 overflow-hidden rounded-md border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium text-slate-500">Matière</th>
                        <th class="px-4 py-2 text-left font-medium text-slate-500">Note</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($subjects as $subject)
                        <tr wire:key="subject-{{ $subject->id }}">
                            <td class="px-4 py-2 text-slate-900">{{ $subject->name }}</td>
                            <td class="px-4 py-2">
                                <input
                                    type="number"
                                    step="0.5"
                                    wire:model.live="scores.{{ $subject->id }}"
                                    class="block w-24 rounded-md border-slate-300 text-sm"
                                >
                                @error("scores.{$subject->id}") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-4 py-6 text-center text-slate-500">Aucune matière configurée pour ce niveau.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="border-t border-slate-200 p-4">
                <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                    Enregistrer
                </button>
            </div>
        </div>

        <div class="rounded-md border border-slate-200 bg-white p-4">
            <h2 class="text-sm font-semibold text-slate-900">Résultats</h2>

            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-slate-500">Total des points</dt>
                    <dd class="text-slate-900">{{ $preview['totalPoints'] }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Total des coefficients</dt>
                    <dd class="text-slate-900">{{ $preview['totalCoefficient'] }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Moyenne / 20</dt>
                    <dd class="font-semibold text-slate-900">{{ $preview['average'] ?? '—' }}</dd>
                </div>
            </dl>

            <p class="mt-3 text-xs text-slate-400">Aperçu calculé à partir des notes ci-contre — le bulletin officiel reste généré séparément depuis l'écran Bulletins.</p>

            <div class="mt-4">
                <label class="block text-sm font-medium text-slate-700">Appréciation</label>
                <textarea wire:model="appreciation" rows="4" class="mt-1 block w-full rounded-md border-slate-300 text-sm"></textarea>
                @error('appreciation') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </form>
</div>
