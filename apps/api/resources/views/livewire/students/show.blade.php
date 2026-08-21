<div>
    <a href="{{ route('students.index') }}" class="text-sm text-stone-500 hover:text-stone-900">{!! app()->getLocale() === 'ar' ? '&rarr;' : '&larr;' !!} {{ __('Retour aux élèves') }}</a>

    <div class="mt-2 flex items-center justify-between">
        <div class="flex items-center gap-4">
            @if ($student->photo_path)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($student->photo_path) }}" alt="{{ __('Photo de :name', ['name' => $student->first_name]) }}" class="h-16 w-16 rounded-lg object-cover">
            @endif

            <div>
                <h1 class="text-2xl font-semibold text-stone-900">{{ $student->last_name }} {{ $student->first_name }}</h1>
                <p class="text-sm text-stone-500">{{ __('Matricule :number', ['number' => $student->student_number]) }}</p>
            </div>
        </div>

        @can('create', \App\Domain\Enrollment\Models\Enrollment::class)
            <button type="button" wire:click="addEnrollment" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                {{ __('Nouvelle inscription') }}
            </button>
        @endcan
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-4">
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-stone-500">{{ __('Classe actuelle') }}</p>
            <p class="text-sm text-stone-900">{{ $currentEnrollment?->classroom?->name ?? '—' }}</p>
            <p class="text-sm text-stone-500">{{ $currentEnrollment?->schoolYear?->label }}</p>
        </div>

        @can('viewAny', \App\Domain\Billing\Models\Payment::class)
            @if ($financialSummary)
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-stone-500">{{ __('Dû à ce jour') }}</p>
                    <p class="text-sm text-stone-900">{{ money($financialSummary['due_so_far']) }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-stone-500">{{ __('Payé') }}</p>
                    <p class="text-sm text-stone-900">{{ money($financialSummary['total_paid']) }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-stone-500">{{ __('Solde') }}</p>
                    @if ($financialSummary['balance'] > 0)
                        <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">
                            {{ __('Retard de :amount', ['amount' => money($financialSummary['balance'])]) }}
                        </span>
                    @elseif ($financialSummary['balance'] < 0)
                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">
                            {{ __('Avance de :amount', ['amount' => money(abs($financialSummary['balance']))]) }}
                        </span>
                    @else
                        <span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-medium text-stone-700">
                            {{ __('À jour') }}
                        </span>
                    @endif
                </div>
            @endif
        @endcan
    </div>

    @if ($showEnrollmentForm)
        <form wire:submit="saveEnrollment" class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-3">
            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Classe') }}</label>
                <select wire:model.live="classroom_id" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    <option value="">—</option>
                    @foreach ($classrooms as $classroom)
                        <option value="{{ $classroom->id }}">{{ $classroom->name }}</option>
                    @endforeach
                </select>
                @error('classroom_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Année scolaire') }}</label>
                <select wire:model="school_year_id" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    <option value="">—</option>
                    @foreach ($schoolYears as $schoolYear)
                        <option value="{{ $schoolYear->id }}">{{ $schoolYear->label }}</option>
                    @endforeach
                </select>
                @error('school_year_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __("Date d'inscription") }}</label>
                <input type="date" wire:model="enrolled_on" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('enrolled_on') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            @if ($isSecondaireClassroom)
                <div class="grid grid-cols-2 gap-3 sm:col-span-3 sm:grid-cols-4">
                    <label class="flex items-center gap-2 text-sm text-stone-700">
                        <input type="checkbox" wire:model="is_repeating"> {{ __('Redoublant') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm text-stone-700">
                        <input type="checkbox" wire:model="is_scholarship"> {{ __('Boursier') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm text-stone-700">
                        <input type="checkbox" wire:model="is_boarding"> {{ __('Internat') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm text-stone-700">
                        <input type="checkbox" wire:model="is_assigned"> {{ __('Affecté(e)') }}
                    </label>
                </div>
            @endif

            <div class="flex gap-2 sm:col-span-3">
                <button type="submit" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                    {{ __('Enregistrer') }}
                </button>
                <button type="button" wire:click="cancelEnrollment" class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm text-stone-700 hover:bg-stone-50">
                    {{ __('Annuler') }}
                </button>
            </div>
        </form>
    @endif

    <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-stone-500">{{ __('Inscriptions') }}</h2>

    <div class="mt-2 overflow-hidden rounded-lg border border-stone-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead class="bg-stone-50">
                    <tr>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Année scolaire') }}</th>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Classe') }}</th>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Date') }}</th>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Statut') }}</th>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Statuts') }}</th>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Finances') }}</th>
                        <th class="whitespace-nowrap px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($enrollments as $enrollment)
                        <tr wire:key="enrollment-{{ $enrollment->id }}">
                            <td class="whitespace-nowrap px-4 py-2 text-stone-900">{{ $enrollment->schoolYear?->label }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ $enrollment->classroom?->name }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ $enrollment->enrolled_on->format('d/m/Y') }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ $enrollment->status }}</td>
                            <td class="whitespace-nowrap px-4 py-2">
                                <div class="flex flex-wrap gap-1">
                                    @if ($enrollment->is_repeating)
                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">{{ __('Redoublant') }}</span>
                                    @endif
                                    @if ($enrollment->is_scholarship)
                                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800">{{ __('Boursier') }}</span>
                                    @endif
                                    @if ($enrollment->is_boarding)
                                        <span class="rounded-full bg-orange-100 px-2 py-0.5 text-xs text-orange-900">{{ __('Internat') }}</span>
                                    @endif
                                    @if ($enrollment->is_assigned)
                                        <span class="rounded-full bg-stone-200 px-2 py-0.5 text-xs text-stone-700">{{ __('Affecté(e)') }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-2">
                                @can('viewAny', \App\Domain\Billing\Models\Payment::class)
                                    <a href="{{ route('billing.enrollments.show', $enrollment) }}" wire:navigate class="text-stone-500 hover:text-stone-900">
                                        {{ __('Détails') }}
                                    </a>
                                @endcan
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-end">
                                @can('update', $enrollment)
                                    @if ($enrollment->status === 'active')
                                        <button
                                            wire:click="withdrawEnrollment({{ $enrollment->id }})"
                                            wire:confirm="{{ __("Retirer cette inscription ? L'élève ne sera plus compté comme actif dans cette classe pour cette année scolaire.") }}"
                                            class="text-red-500 hover:text-red-700"
                                        >
                                            {{ __('Retirer') }}
                                        </button>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-stone-500">{{ __('Aucune inscription.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-8 flex items-center justify-between">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-stone-500">{{ __('Tuteurs') }}</h2>

        @can('update', $student)
            <button type="button" wire:click="addGuardian" class="text-sm text-stone-500 hover:text-stone-900">
                {{ __('Lier un tuteur') }}
            </button>
        @endcan
    </div>

    @if ($showGuardianForm)
        <form wire:submit="saveGuardian" class="mt-2 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-stone-700">{{ __('Rechercher un tuteur') }}</label>
                <input type="text" wire:model.live.debounce.300ms="guardianSearch" placeholder="{{ __('Nom du tuteur') }}" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-stone-700">{{ __('Tuteur') }}</label>
                <select wire:model="guardian_id" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    <option value="">—</option>
                    @foreach ($availableGuardians as $availableGuardian)
                        <option value="{{ $availableGuardian->id }}">{{ $availableGuardian->last_name }} {{ $availableGuardian->first_name }}</option>
                    @endforeach
                </select>
                @error('guardian_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Rôle') }}</label>
                <select wire:model="relationship" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    <option value="">—</option>
                    @foreach (\App\Domain\Enrollment\Enums\GuardianRelationship::cases() as $relationshipOption)
                        <option value="{{ $relationshipOption->value }}">{{ $relationshipOption->label() }}</option>
                    @endforeach
                </select>
                @error('relationship') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2 self-end text-sm text-stone-600">
                <input type="checkbox" wire:model="is_primary_contact" class="rounded border-stone-300">
                {{ __('Contact principal') }}
            </label>

            <div class="flex gap-2 sm:col-span-3">
                <button type="submit" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                    {{ __('Enregistrer') }}
                </button>
                <button type="button" wire:click="cancelGuardian" class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm text-stone-700 hover:bg-stone-50">
                    {{ __('Annuler') }}
                </button>
            </div>
        </form>
    @endif

    <div class="mt-2 overflow-hidden rounded-lg border border-stone-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead class="bg-stone-50">
                    <tr>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Nom') }}</th>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Rôle') }}</th>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Téléphone') }}</th>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Contact principal') }}</th>
                        <th class="whitespace-nowrap px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($guardians as $guardian)
                        <tr wire:key="student-guardian-{{ $guardian->id }}">
                            <td class="whitespace-nowrap px-4 py-2 text-stone-900">{{ $guardian->last_name }} {{ $guardian->first_name }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ $guardian->pivot->relationship?->label() }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ $guardian->phone }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ $guardian->pivot->is_primary_contact ? __('Oui') : '' }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-end">
                                @can('update', $student)
                                    <button
                                        wire:click="removeGuardian({{ $guardian->id }})"
                                        wire:confirm="{{ __("Délier ce tuteur ? Il perdra l'accès à l'espace parent pour cet élève si ce lien lui donnait accès au portail.") }}"
                                        class="text-red-500 hover:text-red-700"
                                    >
                                        {{ __('Délier') }}
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-stone-500">{{ __('Aucun tuteur lié.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($student->birth_place || $student->nationalite || $student->birth_certificate_number || $student->birth_certificate_date || $student->birth_certificate_place || $student->residence)
        <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-stone-500">{{ __('Identité') }}</h2>
        <div class="mt-2 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-3">
            @if ($student->birth_place)
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-stone-500">{{ __('Lieu de naissance') }}</p>
                    <p class="text-sm text-stone-900">{{ $student->birth_place }}</p>
                </div>
            @endif

            @if ($student->nationalite)
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-stone-500">{{ __('Nationalité') }}</p>
                    <p class="text-sm text-stone-900">{{ $student->nationalite->libelle }}</p>
                </div>
            @endif

            @if ($student->residence)
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-stone-500">{{ __('Résidence') }}</p>
                    <p class="text-sm text-stone-900">{{ $student->residence }}</p>
                </div>
            @endif

            @if ($student->birth_certificate_number || $student->birth_certificate_date || $student->birth_certificate_place)
                <div class="sm:col-span-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-stone-500">{{ __('Acte de naissance') }}</p>
                    <p class="text-sm text-stone-900">
                        {{ $student->birth_certificate_number ?: '—' }}
                        @if ($student->birth_certificate_date)
                            &middot; {{ $student->birth_certificate_date->format('d/m/Y') }}
                        @endif
                        @if ($student->birth_certificate_place)
                            &middot; {{ $student->birth_certificate_place }}
                        @endif
                    </p>
                </div>
            @endif
        </div>
    @endif

    @if ($student->father_name || $student->father_phone || $student->mother_name || $student->mother_phone || $student->tutor_name || $student->tutor_phone)
        <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-stone-500">{{ __('Contacts familiaux (référence école)') }}</h2>
        <div class="mt-2 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-3">
            @if ($student->father_name || $student->father_phone)
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-stone-500">{{ __('Père') }}</p>
                    <p class="text-sm text-stone-900">{{ $student->father_name ?: '—' }}</p>
                    <p class="text-sm text-stone-600">{{ $student->father_phone ?: '—' }}</p>
                </div>
            @endif

            @if ($student->mother_name || $student->mother_phone)
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-stone-500">{{ __('Mère') }}</p>
                    <p class="text-sm text-stone-900">{{ $student->mother_name ?: '—' }}</p>
                    <p class="text-sm text-stone-600">{{ $student->mother_phone ?: '—' }}</p>
                </div>
            @endif

            @if ($student->tutor_name || $student->tutor_phone)
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-stone-500">{{ __('Tuteur') }}</p>
                    <p class="text-sm text-stone-900">{{ $student->tutor_name ?: '—' }}</p>
                    <p class="text-sm text-stone-600">{{ $student->tutor_phone ?: '—' }}</p>
                </div>
            @endif
        </div>
    @endif
</div>
