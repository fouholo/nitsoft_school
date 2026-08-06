<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Affectations enseignants</h1>

        @can('create', \App\Domain\Academics\Models\TeacherAssignment::class)
            <button type="button" wire:click="create" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                Nouvelle affectation
            </button>
        @endcan
    </div>

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-md border border-slate-200 bg-white p-4 sm:grid-cols-4">
            <div>
                <label class="block text-sm font-medium text-slate-700">Enseignant</label>
                <select wire:model="user_id" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    <option value="">—</option>
                    @foreach ($teachers as $teacher)
                        <option value="{{ $teacher->id }}">{{ $teacher->name }}</option>
                    @endforeach
                </select>
                @error('user_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Classe</label>
                <select wire:model="classroom_id" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    <option value="">—</option>
                    @foreach ($classrooms as $classroom)
                        <option value="{{ $classroom->id }}">{{ $classroom->name }}</option>
                    @endforeach
                </select>
                @error('classroom_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
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
                <label class="block text-sm font-medium text-slate-700">Année scolaire</label>
                <select wire:model="school_year_id" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    <option value="">—</option>
                    @foreach ($schoolYears as $schoolYear)
                        <option value="{{ $schoolYear->id }}">{{ $schoolYear->label }}</option>
                    @endforeach
                </select>
                @error('school_year_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
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
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Enseignant</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Classe</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Matière</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Année scolaire</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($assignments as $assignment)
                    <tr wire:key="assignment-{{ $assignment->id }}">
                        <td class="px-4 py-2 text-slate-900">{{ $assignment->teacher?->name }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $assignment->classroom?->name }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $assignment->subject?->name }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $assignment->schoolYear?->label }}</td>
                        <td class="px-4 py-2 text-right">
                            @can('delete', $assignment)
                                <button
                                    wire:click="delete({{ $assignment->id }})"
                                    wire:confirm="Retirer cette affectation ?"
                                    class="text-red-500 hover:text-red-700"
                                >
                                    Retirer
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-slate-500">Aucune affectation.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
