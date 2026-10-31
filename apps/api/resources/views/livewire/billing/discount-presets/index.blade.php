<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-stone-900">{{ __('Réductions types') }}</h1>

        <button type="button" wire:click="create" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
            {{ __('Nouvelle réduction type') }}
        </button>
    </div>

    <p class="mt-2 text-sm text-stone-500">{{ __('Modifier une réduction type ne change pas les réductions déjà accordées : il faut la choisir de nouveau pour chaque élève concerné.') }}</p>

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-4">
            <div class="sm:col-span-4">
                <label for="name" class="block text-sm font-medium text-stone-700">{{ __('Nom') }}</label>
                <input id="name" type="text" wire:model="name" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            @include('livewire.billing.discounts.partials.amount-blocks')

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
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Nom') }}</th>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Scolarité') }}</th>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __("Frais d'inscription") }}</th>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('État') }}</th>
                        <th class="whitespace-nowrap px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($presets as $preset)
                        <tr wire:key="preset-{{ $preset->id }}">
                            <td class="whitespace-nowrap px-4 py-2 text-stone-900">{{ $preset->name }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ \App\Domain\Billing\Models\Discount::formatReduction($preset->type, $preset->value) }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ \App\Domain\Billing\Models\Discount::formatReduction($preset->registration_type, $preset->registration_value) }}</td>
                            <td class="whitespace-nowrap px-4 py-2">
                                @if ($preset->is_active)
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">{{ __('Active') }}</span>
                                @else
                                    <span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-medium text-stone-600">{{ __('Inactive') }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-end">
                                <button wire:click="edit({{ $preset->id }})" class="inline-flex min-h-11 items-center text-stone-500 hover:text-stone-900">{{ __('Modifier') }}</button>
                                <button wire:click="toggleActive({{ $preset->id }})" class="ms-3 inline-flex min-h-11 items-center text-stone-500 hover:text-stone-900">
                                    {{ $preset->is_active ? __('Désactiver') : __('Réactiver') }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-stone-500">{{ __('Aucune réduction type.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
