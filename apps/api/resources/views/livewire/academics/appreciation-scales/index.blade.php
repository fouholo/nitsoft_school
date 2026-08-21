<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-stone-900">{{ __("Barème d'appréciations") }}</h1>

        @can('create', \App\Domain\Grading\Models\AppreciationScale::class)
            <button type="button" wire:click="create" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                {{ __('Nouvelle tranche') }}
            </button>
        @endcan
    </div>

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-6">
            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('% minimum') }}</label>
                <input type="number" step="1" wire:model="percentage" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('percentage') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-stone-700">{{ __('Appréciation') }}</label>
                <input type="text" wire:model="appreciation" placeholder="Excellent" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('appreciation') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-end gap-1">
                <label class="flex items-center gap-1 text-sm text-stone-700">
                    <input type="checkbox" wire:model="tableau_honneur" class="rounded border-stone-300 text-orange-700">
                    {{ __("Tableau d'honneur") }}
                </label>
            </div>

            <div class="flex items-end gap-1">
                <label class="flex items-center gap-1 text-sm text-stone-700">
                    <input type="checkbox" wire:model="tableau_excellence" class="rounded border-stone-300 text-orange-700">
                    {{ __("Tableau d'excellence") }}
                </label>
            </div>

            <div class="flex items-end gap-1">
                <label class="flex items-center gap-1 text-sm text-stone-700">
                    <input type="checkbox" wire:model="felicitation" class="rounded border-stone-300 text-orange-700">
                    {{ __('Félicitations') }}
                </label>
            </div>

            <div class="flex items-end gap-1 sm:col-span-6">
                <label class="flex items-center gap-1 text-sm text-stone-700">
                    <input type="checkbox" wire:model="encouragement" class="rounded border-stone-300 text-orange-700">
                    {{ __('Encouragements') }}
                </label>
            </div>

            <div class="flex gap-2 sm:col-span-6">
                <button type="submit" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                    {{ __('Enregistrer') }}
                </button>
                <button type="button" wire:click="cancel" class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm text-stone-700 hover:bg-stone-50">
                    {{ __('Annuler') }}
                </button>
            </div>
        </form>
    @endif

    <div class="mt-6 overflow-x-auto rounded-lg border border-stone-200 bg-white">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50">
                <tr>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('% minimum') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Appréciation') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Tab. honneur') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Tab. excellence') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Félicitations') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Encouragements') }}</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($appreciationScales as $appreciationScale)
                    <tr wire:key="appreciation-scale-{{ $appreciationScale->id }}">
                        <td class="px-4 py-2 text-stone-900">{{ $appreciationScale->percentage }}%</td>
                        <td class="px-4 py-2 text-stone-900">{{ $appreciationScale->appreciation }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ $appreciationScale->tableau_honneur ? __('Oui') : __('Non') }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ $appreciationScale->tableau_excellence ? __('Oui') : __('Non') }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ $appreciationScale->felicitation ? __('Oui') : __('Non') }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ $appreciationScale->encouragement ? __('Oui') : __('Non') }}</td>
                        <td class="px-4 py-2 text-end whitespace-nowrap">
                            @can('update', $appreciationScale)
                                <button wire:click="edit({{ $appreciationScale->id }})" class="text-stone-500 hover:text-stone-900">{{ __('Modifier') }}</button>
                            @endcan
                            @can('delete', $appreciationScale)
                                <button
                                    wire:click="delete({{ $appreciationScale->id }})"
                                    wire:confirm="{{ __('Supprimer cette tranche ?') }}"
                                    class="ms-3 text-red-500 hover:text-red-700"
                                >
                                    {{ __('Supprimer') }}
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-stone-500">{{ __('Aucune tranche configurée.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
