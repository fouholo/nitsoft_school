<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-stone-900">{{ __('Établissements') }}</h1>

        @can('create', \App\Domain\Establishments\Models\Establishment::class)
            <button
                type="button"
                wire:click="create"
                class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800"
            >
                {{ __('Nouvel établissement') }}
            </button>
        @endcan
    </div>

    @if ($pendingRegistrations->isNotEmpty())
        <section class="mt-4 overflow-hidden rounded-lg border border-amber-200 bg-white">
            <h2 class="border-b border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-900">
                {{ __('Écoles en attente de validation (:count)', ['count' => $pendingRegistrations->count()]) }}
            </h2>

            <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead class="bg-stone-50">
                    <tr>
                        <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Date') }}</th>
                        <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('École') }}</th>
                        <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Inspection / Direction') }}</th>
                        <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Groupe scolaire') }}</th>
                        <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Fondateur') }}</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach ($pendingRegistrations as $registration)
                        <tr wire:key="registration-{{ $registration->id }}">
                            <td class="px-4 py-2 whitespace-nowrap text-stone-600">{{ $registration->created_at->format('d/m/Y') }}</td>
                            <td class="px-4 py-2">
                                <div class="text-stone-900">{{ $registration->establishment_name }}</div>
                                <div class="text-xs text-stone-500">{{ __($registration->establishment_type->label()) }}</div>
                            </td>
                            <td class="px-4 py-2 text-stone-600">{{ $registration->inspection?->inspection_name ?? $registration->direction?->direction_name ?? '—' }}</td>
                            <td class="px-4 py-2 text-stone-600">{{ $registration->foundation_name ?? '—' }}</td>
                            <td class="px-4 py-2">
                                <div class="text-stone-900">{{ $registration->first_name }} {{ $registration->name }}</div>
                                <div class="text-xs text-stone-500">{{ $registration->email }}</div>
                                @if ($registrationErrors[$registration->id] ?? null)
                                    <p class="mt-1 text-xs text-red-600">{{ $registrationErrors[$registration->id] }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-end whitespace-nowrap">
                                @can('approve', $registration)
                                    <button wire:click="approveRegistration({{ $registration->id }})" class="font-medium text-emerald-700 hover:text-emerald-900">{{ __('Valider') }}</button>
                                @endcan
                                @can('reject', $registration)
                                    <button
                                        wire:click="rejectRegistration({{ $registration->id }})"
                                        wire:confirm="{{ __('Refuser et supprimer définitivement cette demande ?') }}"
                                        class="ms-3 text-red-600 hover:text-red-800"
                                    >
                                        {{ __('Refuser') }}
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </section>
    @endif

    @if ($pendingStaff->isNotEmpty() || $pendingFounders->isNotEmpty())
        <section class="mt-4 overflow-hidden rounded-lg border border-amber-200 bg-white">
            <h2 class="border-b border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-900">
                {{ __('Inscriptions du personnel en attente (:count)', ['count' => $pendingStaff->count() + $pendingFounders->count()]) }}
            </h2>
            <p class="border-b border-stone-200 px-4 py-2 text-xs text-stone-500">
                {{ __("Ces personnes se sont inscrites avec l'identifiant d'une organisation qui n'a pas encore d'administrateur pour les valider. Vérifiez leur identité avant d'ouvrir l'accès.") }}
            </p>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead class="bg-stone-50">
                        <tr>
                            <th scope="col" class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Date') }}</th>
                            <th scope="col" class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Personne') }}</th>
                            <th scope="col" class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Rôle demandé') }}</th>
                            <th scope="col" class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Organisation') }}</th>
                            <th scope="col" class="px-4 py-2"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($pendingFounders as $pendingFounder)
                            <tr wire:key="pending-founder-{{ $pendingFounder->id }}">
                                <td class="px-4 py-2 whitespace-nowrap text-stone-600">{{ $pendingFounder->created_at?->format('d/m/Y') }}</td>
                                <td class="px-4 py-2">
                                    <div class="text-stone-900">{{ $pendingFounder->user->fullName() }}</div>
                                    <div class="text-xs text-stone-500">{{ $pendingFounder->user->email }}</div>
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap text-stone-600">{{ __('Fondateur') }}</td>
                                <td class="px-4 py-2 text-stone-600">{{ $pendingFounder->foundation->name }} <span class="text-xs text-stone-500">({{ __('Groupe scolaire') }})</span></td>
                                <td class="px-4 py-2 text-end whitespace-nowrap">
                                    <button wire:click="approveFounder({{ $pendingFounder->id }})" class="font-medium text-emerald-700 hover:text-emerald-900">{{ __('Valider') }}</button>
                                    <button
                                        wire:click="rejectFounder({{ $pendingFounder->id }})"
                                        wire:confirm="{{ __('Refuser et supprimer définitivement cette inscription ?') }}"
                                        class="ms-3 text-red-600 hover:text-red-800"
                                    >
                                        {{ __('Refuser') }}
                                    </button>
                                </td>
                            </tr>
                        @endforeach

                        @foreach ($pendingStaff as $pendingMember)
                            <tr wire:key="pending-staff-{{ $pendingMember->id }}">
                                <td class="px-4 py-2 whitespace-nowrap text-stone-600">{{ $pendingMember->created_at?->format('d/m/Y') }}</td>
                                <td class="px-4 py-2">
                                    <div class="text-stone-900">{{ $pendingMember->user->fullName() }}</div>
                                    <div class="text-xs text-stone-500">{{ $pendingMember->user->email }}</div>
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap text-stone-600">{{ __(ucfirst($pendingMember->role)) }}</td>
                                <td class="px-4 py-2 text-stone-600">{{ $pendingMember->establishment->name }}</td>
                                <td class="px-4 py-2 text-end whitespace-nowrap">
                                    <button wire:click="approveStaffMember({{ $pendingMember->id }})" class="font-medium text-emerald-700 hover:text-emerald-900">{{ __('Valider') }}</button>
                                    <button
                                        wire:click="rejectStaffMember({{ $pendingMember->id }})"
                                        wire:confirm="{{ __('Refuser et supprimer définitivement cette inscription ?') }}"
                                        class="ms-3 text-red-600 hover:text-red-800"
                                    >
                                        {{ __('Refuser') }}
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-4">
            <div class="sm:col-span-2">
                <label for="name" class="block text-sm font-medium text-stone-700">{{ __('Nom') }}</label>
                <input id="name" type="text" wire:model="name" placeholder="{{ __('Groupe Scolaire Excellence') }}" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="foundation_id" class="block text-sm font-medium text-stone-700">{{ __('Fondation') }}</label>
                <select id="foundation_id" wire:model="foundation_id" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    <option value="">{{ __('Aucune (indépendant)') }}</option>
                    @foreach ($foundations as $foundation)
                        <option value="{{ $foundation->id }}">{{ $foundation->name }}</option>
                    @endforeach
                </select>
                @error('foundation_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="type" class="block text-sm font-medium text-stone-700">{{ __('Type') }}</label>
                <select id="type" wire:model.live="type" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    <option value="">—</option>
                    @foreach ($types as $typeOption)
                        <option value="{{ $typeOption->value }}">{{ $typeOption->label() }}</option>
                    @endforeach
                </select>
                @error('type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label for="address" class="block text-sm font-medium text-stone-700">{{ __('Adresse') }}</label>
                <input id="address" type="text" wire:model="address" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="phone" class="block text-sm font-medium text-stone-700">{{ __('Téléphone') }}</label>
                <input id="phone" type="text" wire:model="phone" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-stone-700">{{ __('E-mail') }}</label>
                <input id="email" type="email" wire:model="email" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            @if ($type === \App\Domain\Establishments\Enums\EstablishmentType::PrescolairePrimaire->value)
                <div>
                    <label for="inspection_id" class="block text-sm font-medium text-stone-700">{{ __('Inspection') }}</label>
                    <select id="inspection_id" wire:model="inspection_id" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                        <option value="">—</option>
                        @foreach ($inspections as $inspection)
                            <option value="{{ $inspection->id }}">{{ $inspection->codeiep }} — {{ $inspection->inspection_name }}</option>
                        @endforeach
                    </select>
                    @error('inspection_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @elseif ($type === \App\Domain\Establishments\Enums\EstablishmentType::Secondaire->value)
                <div>
                    <label for="direction_id" class="block text-sm font-medium text-stone-700">{{ __('Direction') }}</label>
                    <select id="direction_id" wire:model="direction_id" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                        <option value="">—</option>
                        @foreach ($directions as $direction)
                            <option value="{{ $direction->id }}">{{ $direction->code }} — {{ $direction->direction_name }}</option>
                        @endforeach
                    </select>
                    @error('direction_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif

            <div>
                <label for="opening_code" class="block text-sm font-medium text-stone-700">{{ __("Code d'ouverture") }}</label>
                <input id="opening_code" type="text" wire:model="opening_code" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('opening_code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="dsps_code" class="block text-sm font-medium text-stone-700">{{ __('Code DSPS') }}</label>
                <input id="dsps_code" type="text" wire:model="dsps_code" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('dsps_code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="latitude" class="block text-sm font-medium text-stone-700">{{ __('Latitude') }}</label>
                <input id="latitude" type="text" wire:model="latitude" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('latitude') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="longitude" class="block text-sm font-medium text-stone-700">{{ __('Longitude') }}</label>
                <input id="longitude" type="text" wire:model="longitude" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('longitude') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label for="logo" class="block text-sm font-medium text-stone-700">{{ __('Logo') }}</label>
                @if ($existingLogoPath)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($existingLogoPath) }}" alt="{{ __('Logo actuel') }}" class="mt-1 mb-2 h-12 w-12 rounded-lg object-cover">
                @endif
                <input id="logo" type="file" wire:model="logo" class="mt-1 block w-full text-sm">
                @error('logo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-stone-600">
                <input type="checkbox" wire:model="is_active" class="rounded border-stone-300">
                {{ __('Actif') }}
            </label>

            <label class="flex items-center gap-2 text-sm text-stone-600">
                <input type="checkbox" wire:model="is_arabe" class="rounded border-stone-300">
                {{ __('École arabe') }}
            </label>

            <div class="flex gap-2 sm:col-span-4">
                <button type="submit" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                    {{ __('Enregistrer') }}
                </button>
                <button type="button" wire:click="cancel" class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm text-stone-700 hover:bg-stone-50">
                    {{ __('Annuler') }}
                </button>
            </div>
        </form>
    @endif

    <div class="mt-6 overflow-hidden rounded-lg border border-stone-200 bg-white">
        <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50">
                <tr>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Nom') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Fondation') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Type') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Statut') }}</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($establishments as $establishment)
                    <tr wire:key="establishment-{{ $establishment->id }}">
                        <td class="px-4 py-2 text-stone-900">{{ $establishment->name }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ $establishment->foundation?->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ $establishment->type?->label() ?? '—' }}</td>
                        <td class="px-4 py-2">
                            @if ($establishment->is_active)
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">{{ __('Actif') }}</span>
                            @else
                                <span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-medium text-stone-600">{{ __('Inactif') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-end">
                            @can('update', $establishment)
                                <button wire:click="edit({{ $establishment->id }})" class="text-stone-500 hover:text-stone-900">{{ __('Modifier') }}</button>
                            @endcan
                            @can('delete', $establishment)
                                <button
                                    wire:click="delete({{ $establishment->id }})"
                                    wire:confirm="{{ __('Supprimer cet établissement ?') }}"
                                    class="ms-3 text-red-600 hover:text-red-800"
                                >
                                    {{ __('Supprimer') }}
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-stone-500">{{ __('Aucun établissement.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
