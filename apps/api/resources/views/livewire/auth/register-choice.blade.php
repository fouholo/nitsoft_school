<div>
    <h1 class="text-lg font-semibold text-stone-900">{{ __('Inscription') }}</h1>
    <p class="mt-1 mb-6 text-sm text-stone-500">{{ __('Choisissez le profil qui vous correspond.') }}</p>

    <div class="space-y-3">
        <section class="rounded-lg border border-stone-200 p-4">
            <h2 class="text-sm font-semibold text-stone-900">{{ __('Fondateur') }}</h2>
            <p class="mt-1 text-xs text-stone-500">{{ __("Vous dirigez une école ou un groupe scolaire.") }}</p>

            <div class="mt-3 space-y-2">
                <a
                    href="{{ route('register.school') }}"
                    wire:navigate
                    class="block rounded-lg bg-orange-700 px-3 py-2 text-center text-sm font-medium text-white hover:bg-orange-800"
                >
                    {{ __('Je crée mon école') }}
                </a>
                <a
                    href="{{ route('staff.register', ['role' => 'fondateur']) }}"
                    wire:navigate
                    class="block rounded-lg border border-stone-300 px-3 py-2 text-center text-sm font-medium text-stone-700 hover:bg-stone-50"
                >
                    {{ __("J'ai un identifiant fourni par la plateforme") }}
                </a>
            </div>
        </section>

        <a
            href="{{ route('staff.register') }}"
            wire:navigate
            class="block rounded-lg border border-stone-200 p-4 hover:border-stone-300 hover:bg-stone-50"
        >
            <h2 class="text-sm font-semibold text-stone-900">{{ __('Personnel de direction') }}</h2>
            <p class="mt-1 text-xs text-stone-500">{{ __("Directeur ou gestionnaire, vous rejoignez un établissement avec son identifiant.") }}</p>
        </a>

        <a
            href="{{ route('register.guardian') }}"
            wire:navigate
            class="block rounded-lg border border-stone-200 p-4 hover:border-stone-300 hover:bg-stone-50"
        >
            <h2 class="text-sm font-semibold text-stone-900">{{ __("Parent d'élève") }}</h2>
            <p class="mt-1 text-xs text-stone-500">{{ __('Suivez la scolarité de vos enfants.') }}</p>
        </a>
    </div>

    <p class="mt-6 text-center text-sm text-stone-500">
        {{ __('Déjà un compte ?') }} <a href="{{ route('login') }}" wire:navigate class="text-orange-700 hover:underline">{{ __('Se connecter') }}</a>
    </p>
</div>
