<div>
    <a href="{{ route('students.index') }}" class="text-sm text-slate-500 hover:text-slate-900">&larr; Retour aux élèves</a>

    <div class="mt-2 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">{{ $student->last_name }} {{ $student->first_name }}</h1>
            <p class="text-sm text-slate-500">Matricule {{ $student->student_number }}</p>
        </div>

        @can('create', \App\Domain\Enrollment\Models\Enrollment::class)
            <button type="button" wire:click="addEnrollment" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                Nouvelle inscription
            </button>
        @endcan
    </div>

    @if ($showEnrollmentForm)
        <form wire:submit="saveEnrollment" class="mt-4 grid grid-cols-1 gap-4 rounded-md border border-slate-200 bg-white p-4 sm:grid-cols-3">
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
                <label class="block text-sm font-medium text-slate-700">Année scolaire</label>
                <select wire:model="school_year_id" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    <option value="">—</option>
                    @foreach ($schoolYears as $schoolYear)
                        <option value="{{ $schoolYear->id }}">{{ $schoolYear->label }}</option>
                    @endforeach
                </select>
                @error('school_year_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Date d'inscription</label>
                <input type="date" wire:model="enrolled_on" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                @error('enrolled_on') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-2 sm:col-span-3">
                <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                    Enregistrer
                </button>
                <button type="button" wire:click="cancelEnrollment" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
                    Annuler
                </button>
            </div>
        </form>
    @endif

    <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-slate-500">Inscriptions</h2>

    <div class="mt-2 overflow-hidden rounded-md border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Année scolaire</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Classe</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Date</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Statut</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($enrollments as $enrollment)
                    <tr wire:key="enrollment-{{ $enrollment->id }}">
                        <td class="px-4 py-2 text-slate-900">{{ $enrollment->schoolYear?->label }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $enrollment->classroom?->name }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $enrollment->enrolled_on->format('d/m/Y') }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $enrollment->status }}</td>
                        <td class="px-4 py-2 text-right">
                            @can('update', $enrollment)
                                @if ($enrollment->status === 'active')
                                    <button
                                        wire:click="withdrawEnrollment({{ $enrollment->id }})"
                                        wire:confirm="Marquer cette inscription comme retirée ?"
                                        class="text-red-500 hover:text-red-700"
                                    >
                                        Retirer
                                    </button>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-slate-500">Aucune inscription.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($student->father_name || $student->father_phone || $student->mother_name || $student->mother_phone || $student->tutor_name || $student->tutor_phone)
        <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-slate-500">Contacts familiaux (référence école)</h2>
        <div class="mt-2 grid grid-cols-1 gap-4 rounded-md border border-slate-200 bg-white p-4 sm:grid-cols-3">
            @if ($student->father_name || $student->father_phone)
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Père</p>
                    <p class="text-sm text-slate-900">{{ $student->father_name ?: '—' }}</p>
                    <p class="text-sm text-slate-600">{{ $student->father_phone ?: '—' }}</p>
                </div>
            @endif

            @if ($student->mother_name || $student->mother_phone)
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Mère</p>
                    <p class="text-sm text-slate-900">{{ $student->mother_name ?: '—' }}</p>
                    <p class="text-sm text-slate-600">{{ $student->mother_phone ?: '—' }}</p>
                </div>
            @endif

            @if ($student->tutor_name || $student->tutor_phone)
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Tuteur</p>
                    <p class="text-sm text-slate-900">{{ $student->tutor_name ?: '—' }}</p>
                    <p class="text-sm text-slate-600">{{ $student->tutor_phone ?: '—' }}</p>
                </div>
            @endif
        </div>
    @endif

    <div class="mt-8 flex items-center justify-between">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Tuteurs</h2>

        @can('update', $student)
            <button type="button" wire:click="addGuardian" class="text-sm text-slate-500 hover:text-slate-900">
                Lier un tuteur
            </button>
        @endcan
    </div>

    @if ($showGuardianForm)
        <form wire:submit="saveGuardian" class="mt-2 grid grid-cols-1 gap-4 rounded-md border border-slate-200 bg-white p-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">Rechercher un tuteur</label>
                <input type="text" wire:model.live.debounce.300ms="guardianSearch" placeholder="Nom du tuteur" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">Tuteur</label>
                <select wire:model="guardian_id" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    <option value="">—</option>
                    @foreach ($availableGuardians as $availableGuardian)
                        <option value="{{ $availableGuardian->id }}">{{ $availableGuardian->last_name }} {{ $availableGuardian->first_name }}</option>
                    @endforeach
                </select>
                @error('guardian_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Rôle</label>
                <select wire:model="relationship" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    <option value="">—</option>
                    @foreach (\App\Domain\Enrollment\Enums\GuardianRelationship::cases() as $relationshipOption)
                        <option value="{{ $relationshipOption->value }}">{{ $relationshipOption->label() }}</option>
                    @endforeach
                </select>
                @error('relationship') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2 self-end text-sm text-slate-600">
                <input type="checkbox" wire:model="is_primary_contact" class="rounded border-slate-300">
                Contact principal
            </label>

            <div class="flex gap-2 sm:col-span-3">
                <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                    Enregistrer
                </button>
                <button type="button" wire:click="cancelGuardian" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
                    Annuler
                </button>
            </div>
        </form>
    @endif

    <div class="mt-2 overflow-hidden rounded-md border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Nom</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Rôle</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Téléphone</th>
                    <th class="px-4 py-2 text-left font-medium text-slate-500">Contact principal</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($guardians as $guardian)
                    <tr wire:key="student-guardian-{{ $guardian->id }}">
                        <td class="px-4 py-2 text-slate-900">{{ $guardian->last_name }} {{ $guardian->first_name }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $guardian->pivot->relationship?->label() }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $guardian->phone }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $guardian->pivot->is_primary_contact ? 'Oui' : '' }}</td>
                        <td class="px-4 py-2 text-right">
                            @can('update', $student)
                                <button
                                    wire:click="removeGuardian({{ $guardian->id }})"
                                    wire:confirm="Délier ce tuteur ?"
                                    class="text-red-500 hover:text-red-700"
                                >
                                    Délier
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-slate-500">Aucun tuteur lié.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
