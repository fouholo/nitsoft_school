<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-stone-900">Élèves</h1>

        @can('create', \App\Domain\Enrollment\Models\Student::class)
            <button type="button" wire:click="create" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                Nouvel élève
            </button>
        @endcan
    </div>

    @if (! empty($generatedAccounts))
        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-100 px-4 py-3 text-sm text-amber-900" role="alert" aria-live="assertive">
            <p class="font-semibold">{{ count($generatedAccounts) > 1 ? 'Comptes portail créés' : 'Compte portail créé' }}</p>
            <p class="mt-1">Mots de passe temporaires à communiquer aux parents — ils ne seront plus jamais affichés :</p>

            <ul class="mt-2 space-y-2">
                @foreach ($generatedAccounts as $account)
                    <li class="flex flex-wrap items-center gap-2">
                        <span class="font-medium">{{ $account['name'] }}</span>
                        <span class="text-amber-700">{{ $account['email'] }}</span>
                        <code class="rounded bg-amber-200 px-2 py-1 font-mono text-base">{{ $account['password'] }}</code>
                        <button
                            type="button"
                            x-data
                            x-on:click="navigator.clipboard.writeText(@js($account['password']))"
                            class="inline-flex min-h-11 items-center rounded-lg border border-amber-300 bg-white px-3 text-xs font-medium text-amber-800 hover:bg-amber-50"
                        >
                            Copier
                        </button>
                    </li>
                @endforeach
            </ul>

            <label class="mt-3 flex min-h-11 items-center gap-2">
                <input type="checkbox" wire:model="passwordsAcknowledged" class="rounded border-amber-300">
                J'ai noté {{ count($generatedAccounts) > 1 ? 'ces mots de passe' : 'ce mot de passe' }}
            </label>
            @if ($passwordsAcknowledged)
                <button type="button" wire:click="dismissGeneratedAccounts" class="mt-1 inline-flex min-h-11 items-center text-sm font-medium text-amber-800 underline">
                    Fermer
                </button>
            @endif
        </div>
    @endif

    <div class="mt-4">
        <input
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="Rechercher un élève..."
            class="block w-full max-w-sm rounded-lg border-stone-300 text-sm"
        >
    </div>

    @if ($showForm)
        <div class="mt-4 rounded-lg border border-stone-200 bg-white p-4" x-data x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'start' })">
            @php
                $formSubtitle = trim($last_name.' '.$first_name);
                $stepLabels = ['Identité', 'Acte de naissance et nationalité', 'Contacts familiaux'];
            @endphp

            <h2 class="text-lg font-semibold text-stone-900">
                {{ $editingId ? 'Modification' : 'Nouvel élève' }}{{ $formSubtitle !== '' ? ' — '.$formSubtitle : '' }}
            </h2>

            <div class="mt-3 flex items-center gap-2">
                @foreach ($stepLabels as $index => $label)
                    <div class="h-1.5 flex-1 rounded-full {{ $index + 1 <= $currentStep ? 'bg-orange-700' : 'bg-stone-200' }}"></div>
                @endforeach
            </div>
            <p class="mt-2 text-xs font-medium uppercase tracking-wide text-stone-500">
                Étape {{ $currentStep }} sur {{ count($stepLabels) }} — {{ $stepLabels[$currentStep - 1] }}
            </p>

            <form wire:submit="save" class="mt-4">
                @if ($currentStep === 1)
                    <p class="mb-3 text-xs text-stone-500">Les champs marqués d'un <span class="text-red-600">*</span> sont obligatoires.</p>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <div>
                            <label for="last_name" class="block text-sm font-medium text-stone-700">Nom <span class="text-red-600">*</span></label>
                            <input id="last_name" type="text" wire:model="last_name" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                            @error('last_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="first_name" class="block text-sm font-medium text-stone-700">Prénom <span class="text-red-600">*</span></label>
                            <input id="first_name" type="text" wire:model="first_name" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                            @error('first_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="student_number" class="block text-sm font-medium text-stone-700">Matricule</label>
                            <input id="student_number" type="text" wire:model="student_number" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                            @error('student_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="gender" class="block text-sm font-medium text-stone-700">Genre</label>
                            <select id="gender" wire:model="gender" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                                <option value="">—</option>
                                <option value="m">Masculin</option>
                                <option value="f">Féminin</option>
                            </select>
                        </div>

                        <div>
                            <label for="birth_date" class="block text-sm font-medium text-stone-700">Date de naissance</label>
                            <input id="birth_date" type="date" wire:model="birth_date" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                            @error('birth_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="birth_place" class="block text-sm font-medium text-stone-700">Lieu de naissance</label>
                            <input id="birth_place" type="text" wire:model="birth_place" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                            @error('birth_place') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label for="photo" class="block text-sm font-medium text-stone-700">Photo</label>
                            <input id="photo" type="file" wire:model="photo" accept=".jpg,.jpeg,.png,.webp,.gif" class="mt-1 block w-full text-sm">
                            <p class="mt-1 text-xs text-stone-500">JPG, PNG, WebP ou GIF, 100 Ko max.</p>
                            @error('photo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

                            @if ($photo)
                                <img src="{{ $photo->temporaryUrl() }}" alt="Aperçu" class="mt-2 h-16 w-16 rounded-lg object-cover">
                            @elseif ($existingPhotoPath)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($existingPhotoPath) }}" alt="Photo actuelle" class="mt-2 h-16 w-16 rounded-lg object-cover">
                            @endif
                        </div>
                    </div>
                @endif

                @if ($currentStep === 2)
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <div>
                            <label for="nationalite_code" class="block text-sm font-medium text-stone-700">Nationalité</label>
                            <select id="nationalite_code" wire:model="nationalite_code" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                                <option value="">—</option>
                                @foreach ($nationalites as $nationalite)
                                    <option value="{{ $nationalite->code }}">{{ $nationalite->libelle }}</option>
                                @endforeach
                            </select>
                            @error('nationalite_code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="birth_certificate_number" class="block text-sm font-medium text-stone-700">N° acte de naissance</label>
                            <input id="birth_certificate_number" type="text" wire:model="birth_certificate_number" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                            @error('birth_certificate_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="birth_certificate_date" class="block text-sm font-medium text-stone-700">Date de l'acte de naissance</label>
                            <input id="birth_certificate_date" type="date" wire:model="birth_certificate_date" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                            @error('birth_certificate_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="birth_certificate_place" class="block text-sm font-medium text-stone-700">Lieu de l'acte de naissance</label>
                            <input id="birth_certificate_place" type="text" wire:model="birth_certificate_place" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                            @error('birth_certificate_place') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="residence" class="block text-sm font-medium text-stone-700">Résidence</label>
                            <input id="residence" type="text" wire:model="residence" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                            @error('residence') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @endif

                @if ($currentStep === 3)
                    <p class="mb-3 text-xs text-stone-500">Cochez « Créer et lier un compte » pour donner à un parent l'accès au portail parents — un mot de passe sera généré et affiché une seule fois.</p>

                    <div class="space-y-4">
                        @foreach ([
                            ['key' => 'father', 'label' => 'Père', 'nameField' => 'father_name', 'phoneField' => 'father_phone', 'emailField' => 'fatherEmail', 'createField' => 'createAccountForFather'],
                            ['key' => 'mother', 'label' => 'Mère', 'nameField' => 'mother_name', 'phoneField' => 'mother_phone', 'emailField' => 'motherEmail', 'createField' => 'createAccountForMother'],
                            ['key' => 'tutor', 'label' => 'Tuteur', 'nameField' => 'tutor_name', 'phoneField' => 'tutor_phone', 'emailField' => 'tutorEmail', 'createField' => 'createAccountForTutor'],
                        ] as $row)
                            <div wire:key="family-row-{{ $row['key'] }}" class="rounded-lg border border-stone-200 p-4">
                                <h3 class="text-sm font-semibold text-stone-900">{{ $row['label'] }}</h3>

                                <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label for="{{ $row['nameField'] }}" class="block text-sm font-medium text-stone-700">Nom</label>
                                        <input id="{{ $row['nameField'] }}" type="text" wire:model="{{ $row['nameField'] }}" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                                        @error($row['nameField']) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>

                                    <div>
                                        <label for="{{ $row['phoneField'] }}" class="block text-sm font-medium text-stone-700">Téléphone</label>
                                        <input id="{{ $row['phoneField'] }}" type="text" wire:model="{{ $row['phoneField'] }}" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                                        @error($row['phoneField']) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                </div>

                                <div class="mt-3 flex flex-wrap items-center gap-4">
                                    <label class="flex items-center gap-2 text-sm text-stone-700">
                                        <input type="radio" wire:model="primaryContact" value="{{ $row['key'] }}" class="border-stone-300 text-orange-700 focus:ring-orange-600">
                                        Contact principal
                                    </label>

                                    <label class="flex items-center gap-2 text-sm text-stone-700">
                                        <input type="checkbox" wire:model.live="{{ $row['createField'] }}" class="rounded border-stone-300 text-orange-700 focus:ring-orange-600">
                                        Créer et lier un compte
                                    </label>
                                </div>

                                @if (${$row['createField']})
                                    <div class="mt-3">
                                        <label for="{{ $row['emailField'] }}" class="block text-sm font-medium text-stone-700">
                                            E-mail — {{ $row['label'] }} <span class="text-red-600">*</span>
                                        </label>
                                        <input id="{{ $row['emailField'] }}" type="email" wire:model="{{ $row['emailField'] }}" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                                        @error($row['emailField']) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-4 flex items-center justify-between gap-2 border-t border-stone-100 pt-4">
                    <div class="flex gap-2">
                        @if ($currentStep > 1)
                            <button type="button" wire:click="previousStep" class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm text-stone-700 hover:bg-stone-50">
                                Précédent
                            </button>
                        @endif
                        <button type="button" wire:click="cancel" class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm text-stone-700 hover:bg-stone-50">
                            Annuler
                        </button>
                    </div>

                    @if ($currentStep < count($stepLabels))
                        <button type="button" wire:click="nextStep" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                            Suivant
                        </button>
                    @else
                        <button type="submit" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                            Enregistrer
                        </button>
                    @endif
                </div>
            </form>
        </div>
    @endif

    <div class="mt-6 overflow-hidden rounded-lg border border-stone-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead class="bg-stone-50">
                    <tr>
                        <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-stone-500">Matricule</th>
                        <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-stone-500">Nom</th>
                        <th class="whitespace-nowrap px-4 py-2 text-left font-medium text-stone-500">Date de naissance</th>
                        <th class="whitespace-nowrap px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($students as $student)
                        <tr wire:key="student-{{ $student->id }}" class="{{ $editingId === $student->id ? 'bg-orange-50' : '' }}">
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ $student->student_number }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-900">
                                <a href="{{ route('students.show', $student) }}" class="hover:underline">
                                    {{ $student->last_name }} {{ $student->first_name }}
                                </a>
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ $student->birth_date?->format('d/m/Y') }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-right">
                                @can('update', $student)
                                    <button wire:click="edit({{ $student->id }})" class="text-stone-500 hover:text-stone-900">Modifier</button>
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
                            <td colspan="4" class="px-4 py-6 text-center text-stone-500">Aucun élève.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-stone-200 px-4 py-3">
            {{ $students->links() }}
        </div>
    </div>
</div>
