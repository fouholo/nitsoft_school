<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-stone-900">{{ __('Niveaux arabes') }}</h1>

        @can('create', \App\Domain\Arabic\Models\ArabicLevel::class)
            <button type="button" wire:click="create" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                {{ __('Nouveau niveau') }}
            </button>
        @endcan
    </div>

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-4">
            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Code') }}</label>
                <input type="text" wire:model="code" placeholder="N1" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-stone-700">{{ __('Libellé (arabe)') }}</label>
                <input type="text" wire:model="wording" dir="rtl" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('wording') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Cycle') }}</label>
                <select wire:model="cycle" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                    <option value="">—</option>
                    @foreach ($cycles as $cycleOption)
                        <option value="{{ $cycleOption->value }}">{{ $cycleOption->label() }}</option>
                    @endforeach
                </select>
                @error('cycle') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-4">
                <label class="flex items-center gap-2 text-sm text-stone-700">
                    <input type="checkbox" wire:model="requires_series" class="rounded border-stone-300">
                    {{ __('Ce niveau nécessite une série arabe') }}
                </label>
            </div>

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

    <div class="mt-6 overflow-x-auto rounded-lg border border-stone-200 bg-white">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50">
                <tr>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Code') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Libellé') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Cycle') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Série requise') }}</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($arabicLevels as $arabicLevel)
                    <tr wire:key="arabic-level-{{ $arabicLevel->id }}">
                        <td class="px-4 py-2 text-stone-900">{{ $arabicLevel->code }}</td>
                        <td class="px-4 py-2 text-stone-900" dir="rtl">{{ $arabicLevel->wording }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ $arabicLevel->cycle->label() }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ $arabicLevel->requires_series ? __('Oui') : __('Non') }}</td>
                        <td class="px-4 py-2 text-end whitespace-nowrap">
                            @can('update', $arabicLevel)
                                <button wire:click="edit({{ $arabicLevel->id }})" class="text-stone-500 hover:text-stone-900">{{ __('Modifier') }}</button>
                            @endcan
                            @can('delete', $arabicLevel)
                                <button
                                    wire:click="delete({{ $arabicLevel->id }})"
                                    wire:confirm="{{ __('Supprimer ce niveau ?') }}"
                                    class="ms-3 text-red-500 hover:text-red-700"
                                >
                                    {{ __('Supprimer') }}
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-stone-500">{{ __('Aucun niveau arabe.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
