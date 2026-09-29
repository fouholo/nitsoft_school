<div>
    <h1 class="mb-6 text-lg font-semibold text-stone-900">{{ __('Créer mon école') }}</h1>

    @if ($submitted)
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
            {{ __("Votre demande a été enregistrée. Vous pourrez vous connecter dès qu'elle aura été validée par l'équipe Nitsoft.") }}
        </div>

        <p class="mt-4 text-center text-sm text-stone-500">
            <a href="{{ route('login') }}" wire:navigate class="text-orange-700 hover:underline">{{ __('Retour à la connexion') }}</a>
        </p>
    @else
        <form wire:submit="register" class="space-y-4">
            <section class="space-y-4">
                <h2 class="text-sm font-semibold text-stone-900">{{ __('Votre compte') }}</h2>

                <div>
                    <label for="first_name" class="block text-sm font-medium text-stone-700">{{ __('Prénom') }}</label>
                    <input type="text" id="first_name" wire:model="first_name" autofocus class="mt-1 block w-full rounded-lg border-stone-300 shadow-sm focus:border-stone-500 focus:ring-stone-500 sm:text-sm">
                    @error('first_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="name" class="block text-sm font-medium text-stone-700">{{ __('Nom') }}</label>
                    <input type="text" id="name" wire:model="name" class="mt-1 block w-full rounded-lg border-stone-300 shadow-sm focus:border-stone-500 focus:ring-stone-500 sm:text-sm">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-stone-700">{{ __('Adresse e-mail') }}</label>
                    <input type="email" id="email" wire:model="email" autocomplete="email" class="mt-1 block w-full rounded-lg border-stone-300 shadow-sm focus:border-stone-500 focus:ring-stone-500 sm:text-sm">
                    @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="pseudo" class="block text-sm font-medium text-stone-700">{{ __('Pseudo') }}</label>
                    <input type="text" id="pseudo" wire:model="pseudo" autocomplete="username" class="mt-1 block w-full rounded-lg border-stone-300 shadow-sm focus:border-stone-500 focus:ring-stone-500 sm:text-sm">
                    @error('pseudo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-stone-700">{{ __('Mot de passe') }}</label>
                    <input type="password" id="password" wire:model="password" autocomplete="new-password" class="mt-1 block w-full rounded-lg border-stone-300 shadow-sm focus:border-stone-500 focus:ring-stone-500 sm:text-sm">
                    @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-stone-700">{{ __('Confirmer le mot de passe') }}</label>
                    <input type="password" id="password_confirmation" wire:model="password_confirmation" autocomplete="new-password" class="mt-1 block w-full rounded-lg border-stone-300 shadow-sm focus:border-stone-500 focus:ring-stone-500 sm:text-sm">
                </div>
            </section>

            <section class="space-y-4 border-t border-stone-200 pt-4">
                <h2 class="text-sm font-semibold text-stone-900">{{ __('Votre école') }}</h2>

                <div>
                    <label for="establishment_name" class="block text-sm font-medium text-stone-700">{{ __("Nom de l'école") }}</label>
                    <input type="text" id="establishment_name" wire:model="establishment_name" class="mt-1 block w-full rounded-lg border-stone-300 shadow-sm focus:border-stone-500 focus:ring-stone-500 sm:text-sm">
                    @error('establishment_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="establishment_type" class="block text-sm font-medium text-stone-700">{{ __('Type') }}</label>
                    <select id="establishment_type" wire:model.live="establishment_type" class="mt-1 block w-full rounded-lg border-stone-300 shadow-sm focus:border-stone-500 focus:ring-stone-500 sm:text-sm">
                        <option value="">{{ __('Sélectionner…') }}</option>
                        @foreach ($types as $typeOption)
                            <option value="{{ $typeOption->value }}">{{ __($typeOption->label()) }}</option>
                        @endforeach
                    </select>
                    @error('establishment_type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                @if ($establishment_type === \App\Domain\Establishments\Enums\EstablishmentType::PrescolairePrimaire->value)
                    <div>
                        <label for="inspection_id" class="block text-sm font-medium text-stone-700">{{ __('Inspection') }}</label>
                        <select id="inspection_id" wire:model="inspection_id" class="mt-1 block w-full rounded-lg border-stone-300 shadow-sm focus:border-stone-500 focus:ring-stone-500 sm:text-sm">
                            <option value="">{{ __('Sélectionner…') }}</option>
                            @foreach ($inspections as $inspection)
                                <option value="{{ $inspection->id }}">{{ $inspection->inspection_name }}</option>
                            @endforeach
                        </select>
                        @error('inspection_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @elseif ($establishment_type === \App\Domain\Establishments\Enums\EstablishmentType::Secondaire->value)
                    <div>
                        <label for="direction_id" class="block text-sm font-medium text-stone-700">{{ __('Direction') }}</label>
                        <select id="direction_id" wire:model="direction_id" class="mt-1 block w-full rounded-lg border-stone-300 shadow-sm focus:border-stone-500 focus:ring-stone-500 sm:text-sm">
                            <option value="">{{ __('Sélectionner…') }}</option>
                            @foreach ($directions as $direction)
                                <option value="{{ $direction->id }}">{{ $direction->direction_name }}</option>
                            @endforeach
                        </select>
                        @error('direction_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div>
                    <label for="phone" class="block text-sm font-medium text-stone-700">{{ __('Téléphone') }}</label>
                    <input type="text" id="phone" wire:model="phone" autocomplete="tel" class="mt-1 block w-full rounded-lg border-stone-300 shadow-sm focus:border-stone-500 focus:ring-stone-500 sm:text-sm">
                    @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="address" class="block text-sm font-medium text-stone-700">{{ __('Adresse') }}</label>
                    <input type="text" id="address" wire:model="address" class="mt-1 block w-full rounded-lg border-stone-300 shadow-sm focus:border-stone-500 focus:ring-stone-500 sm:text-sm">
                    @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <label class="flex items-start gap-2 text-sm text-stone-700">
                    <input type="checkbox" wire:model.live="has_foundation" class="mt-0.5 rounded border-stone-300">
                    {{ __('Je gère un groupe scolaire (plusieurs écoles)') }}
                </label>

                @if ($has_foundation)
                    <div>
                        <label for="foundation_name" class="block text-sm font-medium text-stone-700">{{ __('Nom du groupe scolaire') }}</label>
                        <input type="text" id="foundation_name" wire:model="foundation_name" class="mt-1 block w-full rounded-lg border-stone-300 shadow-sm focus:border-stone-500 focus:ring-stone-500 sm:text-sm">
                        @error('foundation_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif
            </section>

            <p class="text-xs text-stone-500">{{ __("Votre demande sera examinée par l'équipe Nitsoft avant l'ouverture de votre accès.") }}</p>

            <button
                type="submit"
                class="w-full rounded-lg bg-orange-700 px-4 py-2 text-sm font-medium text-white hover:bg-orange-800"
                wire:loading.attr="disabled"
            >
                {{ __('Envoyer ma demande') }}
            </button>

            <p class="text-center text-sm text-stone-500">
                <a href="{{ route('register') }}" wire:navigate class="text-orange-700 hover:underline">{{ __('Retour') }}</a>
            </p>
        </form>
    @endif
</div>
