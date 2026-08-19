<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Élèves</h1>

        @can('create', \App\Domain\Enrollment\Models\Student::class)
            <button type="button" wire:click="create" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                Nouvel élève
            </button>
        @endcan
    </div>

    <div class="mt-4">
        <input
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="Rechercher un élève..."
            class="block w-full max-w-sm rounded-md border-slate-300 text-sm"
        >
    </div>

    @if ($showForm)
        <div class="mt-4 rounded-md border border-slate-200 bg-white p-4" x-data x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'start' })">
            @php
                $formSubtitle = trim($last_name.' '.$first_name);
                $stepLabels = ['Identité', 'Acte de naissance et nationalité', 'Contacts familiaux'];
            @endphp

            <h2 class="text-lg font-semibold text-slate-900">
                {{ $editingId ? 'Modification' : 'Nouvel élève' }}{{ $formSubtitle !== '' ? ' — '.$formSubtitle : '' }}
            </h2>

            <div class="mt-3 flex items-center gap-2">
                @foreach ($stepLabels as $index => $label)
                    <div class="h-1.5 flex-1 rounded-full {{ $index + 1 <= $currentStep ? 'bg-indigo-600' : 'bg-slate-200' }}"></div>
                @endforeach
            </div>
            <p class="mt-2 text-xs font-medium uppercase tracking-wide text-slate-500">
                Étape {{ $currentStep }} sur {{ count($stepLabels) }} — {{ $stepLabels[$currentStep - 1] }}
            </p>

            <form wire:submit="save" class="mt-4">
                @if ($currentStep === 1)
                    <p class="mb-3 text-xs text-slate-500">Les champs marqués d'un <span class="text-red-600">*</span> sont obligatoires.</p>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <div>
                            <label for="last_name" class="block text-sm font-medium text-slate-700">Nom <span class="text-red-600">*</span></label>
                            <input id="last_name" type="text" wire:model="last_name" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                            @error('last_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="first_name" class="block text-sm font-medium text-slate-700">Prénom <span class="text-red-600">*</span></label>
                            <input id="first_name" type="text" wire:model="first_name" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                            @error('first_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="student_number" class="block text-sm font-medium text-slate-700">Matricule <span class="text-red-600">*</span></label>
                            <input id="student_number" type="text" wire:model="student_number" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                            @error('student_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="gender" class="block text-sm font-medium text-slate-700">Genre</label>
                            <select id="gender" wire:model="gender" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                                <option value="">—</option>
                                <option value="m">Masculin</option>
                                <option value="f">Féminin</option>
                            </select>
                        </div>

                        <div>
                            <label for="birth_date" class="block text-sm font-medium text-slate-700">Date de naissance</label>
                            <input id="birth_date" type="date" wire:model="birth_date" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                            @error('birth_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="birth_place" class="block text-sm font-medium text-slate-700">Lieu de naissance</label>
                            <input id="birth_place" type="text" wire:model="birth_place" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                            @error('birth_place') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label for="photo" class="block text-sm font-medium text-slate-700">Photo</label>
                            <input id="photo" type="file" wire:model="photo" accept=".jpg,.jpeg,.png,.webp,.gif" class="mt-1 block w-full text-sm">
                            <p class="mt-1 text-xs text-slate-500">JPG, PNG, WebP ou GIF, 100 Ko max.</p>
                            @error('photo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

                            @if ($photo)
                                <img src="{{ $photo->temporaryUrl() }}" alt="Aperçu" class="mt-2 h-16 w-16 rounded-md object-cover">
                            @elseif ($existingPhotoPath)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($existingPhotoPath) }}" alt="Photo actuelle" class="mt-2 h-16 w-16 rounded-md object-cover">
                            @endif
                        </div>
                    </div>
                @endif

                @if ($currentStep === 2)
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <div>
                            <label for="nationalite_code" class="block text-sm font-medium text-slate-700">Nationalité</label>
                            <select id="nationalite_code" wire:model="nationalite_code" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                                <option value="">—</option>
                                @foreach ($nationalites as $nationalite)
                                    <option value="{{ $nationalite->code }}">{{ $nationalite->libelle }}</option>
                                @endforeach
                            </select>
                            @error('nationalite_code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="birth_certificate_number" class="block text-sm font-medium text-slate-700">N° acte de naissance</label>
                            <input id="birth_certificate_number" type="text" wire:model="birth_certificate_number" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                            @error('birth_certificate_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="birth_certificate_date" class="block text-sm font-medium text-slate-700">Date de l'acte de naissance</label>
                            <input id="birth_certificate_date" type="date" wire:model="birth_certificate_date" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                            @error('birth_certificate_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="birth_certificate_place" class="block text-sm font-medium text-slate-700">Lieu de l'acte de naissance</label>
                            <input id="birth_certificate_place" type="text" wire:model="birth_certificate_place" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                            @error('birth_certificate_place') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="residence" class="block text-sm font-medium text-slate-700">Résidence</label>
                            <input id="residence" type="text" wire:model="residence" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                            @error('residence') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @endif

                @if ($currentStep === 3)
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <div>
                            <label for="father_name" class="block text-sm font-medium text-slate-700">Nom du père</label>
                            <input id="father_name" type="text" wire:model="father_name" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                            @error('father_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="father_phone" class="block text-sm font-medium text-slate-700">Téléphone du père</label>
                            <input id="father_phone" type="text" wire:model="father_phone" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                            @error('father_phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="mother_name" class="block text-sm font-medium text-slate-700">Nom de la mère</label>
                            <input id="mother_name" type="text" wire:model="mother_name" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                            @error('mother_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="mother_phone" class="block text-sm font-medium text-slate-700">Téléphone de la mère</label>
                            <input id="mother_phone" type="text" wire:model="mother_phone" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                            @error('mother_phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="tutor_name" class="block text-sm font-medium text-slate-700">Nom du tuteur</label>
                            <input id="tutor_name" type="text" wire:model="tutor_name" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                            @error('tutor_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="tutor_phone" class="block text-sm font-medium text-slate-700">Téléphone du tuteur</label>
                            <input id="tutor_phone" type="text" wire:model="tutor_phone" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                            @error('tutor_phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @endif

                <div class="mt-4 flex items-center justify-between gap-2 border-t border-slate-100 pt-4">
                    <div class="flex gap-2">
                        @if ($currentStep > 1)
                            <button type="button" wire:click="previousStep" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
                                Précédent
                            </button>
                        @endif
                        <button type="button" wire:click="cancel" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
                            Annuler
                        </button>
                    </div>

                    @if ($currentStep < count($stepLabels))
                        <button type="button" wire:click="nextStep" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                            Suivant
                        </button>
                    @else
                        <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                            Enregistrer
                        </button>
                    @endif
                </div>
            </form>
        </div>
    @endif

    <div class="mt-6 overflow-hidden rounded-md border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-slate-500">Matricule</th>
                        <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-slate-500">Nom</th>
                        <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-slate-500">Date de naissance</th>
                        <th class="whitespace-nowrap px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($students as $student)
                        <tr wire:key="student-{{ $student->id }}" class="{{ $editingId === $student->id ? 'bg-indigo-50' : '' }}">
                            <td class="whitespace-nowrap px-4 py-2 text-slate-600">{{ $student->student_number }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-slate-900">
                                <a href="{{ route('students.show', $student) }}" class="hover:underline">
                                    {{ $student->last_name }} {{ $student->first_name }}
                                </a>
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-slate-600">{{ $student->birth_date?->format('d/m/Y') }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-right">
                                @can('update', $student)
                                    <button wire:click="edit({{ $student->id }})" class="text-slate-500 hover:text-slate-900">Modifier</button>
                                @endcan
                                @can('delete', $student)
                                    <button
                                        wire:click="delete({{ $student->id }})"
                                        wire:confirm="Supprimer cet élève ?"
                                        class="ml-3 text-red-500 hover:text-red-700"
                                    >
                                        Supprimer
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-slate-500">Aucun élève.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 px-4 py-3">
            {{ $students->links() }}
        </div>
    </div>
</div>
