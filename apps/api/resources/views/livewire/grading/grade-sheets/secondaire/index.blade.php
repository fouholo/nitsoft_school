<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Évaluations</h1>

        @can('create', \App\Domain\Grading\Models\GradeSheet::class)
            <button type="button" wire:click="create" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                Nouvelle évaluation
            </button>
        @endcan
    </div>

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-md border border-slate-200 bg-white p-4 sm:grid-cols-4">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">Titre</label>
                <input type="text" wire:model="title" placeholder="Devoir 1" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Classe</label>
                <select wire:model.live="classroom_id" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    <option value="">—</option>
                    @foreach ($classrooms as $classroom)
                        <option value="{{ $classroom->id }}">{{ $classroom->name }}</option>
                    @endforeach
                </select>
                @error('classroom_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Type</label>
                <select wire:model.live="type" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    <option value="devoir">Devoir</option>
                    <option value="interrogation">Interrogation</option>
                </select>
                @error('type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Matière</label>
                <select wire:model="subject_id" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    <option value="">—</option>
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                    @endforeach
                </select>
                @error('subject_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Période</label>
                <select wire:model="term_id" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    <option value="">—</option>
                    @foreach ($terms as $term)
                        <option value="{{ $term->id }}">{{ $term->label }}</option>
                    @endforeach
                </select>
                @error('term_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Barème</label>
                <input type="number" step="0.5" wire:model="max_score" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('max_score') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Coefficient</label>
                <input type="number" step="0.5" wire:model="weight" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('weight') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Date</label>
                <input type="date" wire:model="graded_on" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('graded_on') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-2 sm:col-span-4">
                <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                    Enregistrer
                </button>
                <button type="button" wire:click="cancel" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
                    Annuler
                </button>
            </div>
        </form>
    @endif

    <div class="mt-6 overflow-hidden rounded-md border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Titre</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Type</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Classe</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Matière</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Période</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Date</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($gradeSheets as $gradeSheet)
                    <tr wire:key="grade-sheet-{{ $gradeSheet->id }}">
                        <td class="px-4 py-2 text-slate-900">{{ $gradeSheet->title }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ ucfirst($gradeSheet->type) }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $gradeSheet->classroom?->name }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $gradeSheet->subject?->name }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $gradeSheet->term?->label }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $gradeSheet->graded_on->format('d/m/Y') }}</td>
                        <td class="px-4 py-2 text-right">
                            @can('update', $gradeSheet)
                                <a href="{{ route('grading.grade-sheets.enter', $gradeSheet) }}" class="text-slate-500 hover:text-slate-900">
                                    Saisir les notes
                                </a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-slate-500">Aucune évaluation.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
