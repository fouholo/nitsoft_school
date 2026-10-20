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
            <p class="text-sm text-stone-900">{{ $currentEnrollment?->mainClassroomName() ?? '—' }}</p>
            @if ($isArabe && $currentEnrollment)
                <p class="text-sm text-stone-500">{{ __('Secondaire : :name', ['name' => $currentEnrollment->secondaryClassroom()?->name ?? '—']) }}</p>
            @endif
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
            @if ($isArabe)
                <fieldset class="sm:col-span-3">
                    <legend class="text-sm font-medium text-stone-700">{{ __('Filière principale') }}</legend>
                    <p class="text-xs text-stone-500">{{ __('La classe principale détermine les frais de scolarité et la classe affichée pour l’élève.') }}</p>
                    <div class="mt-2 flex flex-wrap gap-4">
                        @foreach (\App\Domain\Enrollment\Enums\Track::cases() as $trackOption)
                            <label class="flex items-center gap-2 text-sm text-stone-700">
                                <input type="radio" wire:model.live="main_track" value="{{ $trackOption->value }}" class="border-stone-300 text-orange-700 focus:ring-orange-600">
                                {{ $trackOption->label() }}
                            </label>
                        @endforeach
                    </div>
                    @error('main_track') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </fieldset>
            @endif

            <div>
                <label for="school_year_id" class="block text-sm font-medium text-stone-700">{{ __('Année scolaire') }}</label>
                <select id="school_year_id" wire:model.live="school_year_id" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    <option value="">—</option>
                    @foreach ($schoolYears as $schoolYear)
                        <option value="{{ $schoolYear->id }}">{{ $schoolYear->label }}</option>
                    @endforeach
                </select>
                @error('school_year_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            @php
                $frenchSelect = [
                    'id' => 'classroom_id',
                    'label' => ! $isArabe ? __('Classe') : ($track === \App\Domain\Enrollment\Enums\Track::Francais ? __('Classe principale (française)') : __('Classe secondaire (française)')),
                    'options' => $classrooms,
                ];
                $arabicSelect = [
                    'id' => 'arabic_classroom_id',
                    'label' => $track === \App\Domain\Enrollment\Enums\Track::Arabe ? __('Classe principale (arabe)') : __('Classe secondaire (arabe)'),
                    'options' => $arabicClassrooms,
                ];
                $classroomSelects = ! $isArabe
                    ? [$frenchSelect]
                    : ($track === \App\Domain\Enrollment\Enums\Track::Arabe ? [$arabicSelect, $frenchSelect] : [$frenchSelect, $arabicSelect]);
            @endphp

            @foreach ($classroomSelects as $select)
                <div wire:key="enrollment-select-{{ $select['id'] }}">
                    <label for="{{ $select['id'] }}" class="block text-sm font-medium text-stone-700">{{ $select['label'] }}</label>
                    <select id="{{ $select['id'] }}" wire:model.live="{{ $select['id'] }}" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                        <option value="">{{ $loop->first ? '—' : __('— Plus tard —') }}</option>
                        @foreach ($select['options'] as $classroomOption)
                            <option value="{{ $classroomOption->id }}">{{ $classroomOption->name }}</option>
                        @endforeach
                    </select>
                    @error($select['id']) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endforeach

            <div>
                <label for="enrolled_on" class="block text-sm font-medium text-stone-700">{{ __("Date d'inscription") }}</label>
                <input id="enrolled_on" type="date" wire:model="enrolled_on" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('enrolled_on') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            @php
                $showFrenchMainStatuses = $isSecondaireClassroom && $track === \App\Domain\Enrollment\Enums\Track::Francais;
                $showArabicRepeating = $isArabe && $arabic_classroom_id;
            @endphp

            @if ($isSecondaireClassroom || $showArabicRepeating)
                <div class="grid grid-cols-2 gap-3 sm:col-span-3 sm:grid-cols-4">
                    @if ($isSecondaireClassroom)
                        <label class="flex items-center gap-2 text-sm text-stone-700">
                            <input type="checkbox" wire:model="is_repeating"> {{ $isArabe ? __('Redoublant (français)') : __('Redoublant') }}
                        </label>
                    @endif
                    @if ($showArabicRepeating)
                        <label class="flex items-center gap-2 text-sm text-stone-700">
                            <input type="checkbox" wire:model="is_arabic_repeating"> {{ __('Redoublant (arabe)') }}
                        </label>
                    @endif
                    @if ($showFrenchMainStatuses)
                        <label class="flex items-center gap-2 text-sm text-stone-700">
                            <input type="checkbox" wire:model="is_scholarship"> {{ __('Boursier') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm text-stone-700">
                            <input type="checkbox" wire:model="is_boarding"> {{ __('Internat') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm text-stone-700">
                            <input type="checkbox" wire:model="is_assigned"> {{ __('Affecté(e)') }}
                        </label>
                    @endif
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

    @if ($secondaryEnrollment)
        <form wire:submit="saveSecondaryClassroom" class="mt-2 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-3">
            <div>
                <label for="secondary_classroom_id" class="block text-sm font-medium text-stone-700">
                    {{ $secondaryTrack === \App\Domain\Enrollment\Enums\Track::Arabe ? __('Classe secondaire (arabe)') : __('Classe secondaire (française)') }}
                </label>
                <select id="secondary_classroom_id" wire:model="secondary_classroom_id" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    <option value="">{{ __('— Aucune —') }}</option>
                    @foreach ($secondaryOptions as $classroomOption)
                        <option value="{{ $classroomOption->id }}">{{ $classroomOption->name }}</option>
                    @endforeach
                </select>
                @error('secondary_classroom_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                <p class="mt-1 text-xs text-stone-500">{{ __('Les frais de scolarité ne changent pas : ils dépendent de la classe principale.') }}</p>
            </div>

            <label class="flex items-center gap-2 self-center text-sm text-stone-700">
                <input type="checkbox" wire:model="secondary_is_repeating"> {{ __('Redoublant dans cette classe') }}
            </label>

            <div class="flex items-center gap-2">
                <button type="submit" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                    {{ __('Enregistrer') }}
                </button>
                <button type="button" wire:click="cancelSecondaryClassroom" class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm text-stone-700 hover:bg-stone-50">
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
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Année scolaire') }}</th>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ $isArabe ? __('Classe principale') : __('Classe') }}</th>
                        @if ($isArabe)
                            <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Classe secondaire') }}</th>
                        @endif
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Date') }}</th>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Statut') }}</th>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Statuts') }}</th>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Finances') }}</th>
                        <th class="whitespace-nowrap px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($enrollments as $enrollment)
                        @php $secondaryClassroom = $enrollment->secondaryClassroom(); @endphp
                        <tr wire:key="enrollment-{{ $enrollment->id }}">
                            <td class="whitespace-nowrap px-4 py-2 text-stone-900">{{ $enrollment->schoolYear?->label }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">
                                {{ $enrollment->mainClassroom()?->name }}
                                @if ($isArabe)
                                    <span class="ms-1 rounded-full bg-stone-100 px-2 py-0.5 text-xs text-stone-600">{{ $enrollment->mainTrack()->label() }}</span>
                                @endif
                            </td>
                            @if ($isArabe)
                                <td class="whitespace-nowrap px-4 py-2 text-stone-600">
                                    @if ($secondaryClassroom)
                                        {{ $secondaryClassroom->name }}
                                    @elseif ($enrollment->status === 'active')
                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">{{ __('Classe secondaire à renseigner') }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                            @endif
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ $enrollment->enrolled_on->format('d/m/Y') }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ $enrollment->status }}</td>
                            <td class="whitespace-nowrap px-4 py-2">
                                <div class="flex flex-wrap gap-1">
                                    @if ($enrollment->is_repeating)
                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">{{ $isArabe ? __('Redoublant (français)') : __('Redoublant') }}</span>
                                    @endif
                                    @if ($enrollment->is_arabic_repeating)
                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">{{ __('Redoublant (arabe)') }}</span>
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
                                        @if ($isArabe)
                                            <button type="button" wire:click="editSecondaryClassroom({{ $enrollment->id }})" class="text-stone-500 hover:text-stone-900">
                                                {{ __('Classe secondaire') }}
                                            </button>
                                        @endif
                                        <button
                                            type="button"
                                            wire:click="withdrawEnrollment({{ $enrollment->id }})"
                                            wire:confirm="{{ __("Retirer cette inscription ? L'élève ne sera plus compté comme actif dans cette classe pour cette année scolaire.") }}"
                                            class="ms-3 text-red-600 hover:text-red-800"
                                        >
                                            {{ __('Retirer') }}
                                        </button>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isArabe ? 8 : 7 }}" class="px-4 py-6 text-center text-stone-500">{{ __('Aucune inscription.') }}</td>
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
                <label for="guardianSearch" class="block text-sm font-medium text-stone-700">{{ __('Rechercher un tuteur') }}</label>
                <input id="guardianSearch" type="text" wire:model.live.debounce.300ms="guardianSearch" placeholder="{{ __('Nom du tuteur') }}" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
            </div>

            <div class="sm:col-span-2">
                <label for="guardian_id" class="block text-sm font-medium text-stone-700">{{ __('Tuteur') }}</label>
                <select id="guardian_id" wire:model="guardian_id" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    <option value="">—</option>
                    @foreach ($availableGuardians as $availableGuardian)
                        <option value="{{ $availableGuardian->id }}">{{ $availableGuardian->last_name }} {{ $availableGuardian->first_name }}</option>
                    @endforeach
                </select>
                @error('guardian_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="relationship" class="block text-sm font-medium text-stone-700">{{ __('Rôle') }}</label>
                <select id="relationship" wire:model="relationship" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
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
                                        class="text-red-600 hover:text-red-800"
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
