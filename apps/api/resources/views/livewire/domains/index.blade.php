<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-stone-900">{{ __('Domaines') }}</h1>

        @can('create', \App\Domain\Academics\Models\Domain::class)
            <button type="button" wire:click="create" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                {{ __('Nouveau domaine') }}
            </button>
        @endcan
    </div>

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-4">
            <div class="sm:col-span-3">
                <label class="block text-sm font-medium text-stone-700">{{ __('Nom') }}</label>
                <input type="text" wire:model="name" placeholder="{{ __('Sciences') }}" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
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

    <div class="mt-6 overflow-hidden rounded-lg border border-stone-200 bg-white">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50">
                <tr>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Nom') }}</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($domains as $domain)
                    <tr wire:key="domain-{{ $domain->id }}">
                        <td class="px-4 py-2 text-stone-900">{{ $domain->name }}</td>
                        <td class="px-4 py-2 text-end">
                            @can('update', $domain)
                                <button wire:click="edit({{ $domain->id }})" class="text-stone-500 hover:text-stone-900">{{ __('Modifier') }}</button>
                            @endcan
                            @can('delete', $domain)
                                <button
                                    wire:click="delete({{ $domain->id }})"
                                    wire:confirm="{{ __('Supprimer ce domaine ?') }}"
                                    class="ms-3 text-red-500 hover:text-red-700"
                                >
                                    {{ __('Supprimer') }}
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="px-4 py-6 text-center text-stone-500">{{ __('Aucun domaine.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
