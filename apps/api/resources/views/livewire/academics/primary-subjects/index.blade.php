<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-stone-900">{{ __('Matières du primaire') }}</h1>

        @can('create', \App\Domain\Academics\Models\PrimarySubject::class)
            <button type="button" wire:click="create" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                {{ __('Nouvelle matière') }}
            </button>
        @endcan
    </div>

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-4">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-stone-700">{{ __('Nom') }}</label>
                <input type="text" wire:model="name" placeholder="Mathématiques" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-stone-700">{{ __('Abréviation') }}</label>
                <input type="text" wire:model="abbreviation" placeholder="MATHS" maxlength="10" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('abbreviation') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-4">
                <span class="block text-sm font-medium text-stone-700">{{ __('Coefficient et barème par niveau (laisser vide si non applicable)') }}</span>
                <div class="mt-1 grid grid-cols-2 gap-4 sm:grid-cols-6">
                    @foreach (['cp1' => 'CP1', 'cp2' => 'CP2', 'ce1' => 'CE1', 'ce2' => 'CE2', 'cm1' => 'CM1', 'cm2' => 'CM2'] as $suffix => $label)
                        <div class="rounded-lg border border-stone-200 p-2">
                            <p class="text-xs font-medium text-stone-700">{{ $label }}</p>
                            <label class="mt-1 block text-xs text-stone-500">{{ __('Coef.') }}</label>
                            <input type="number" step="0.5" wire:model="coefficient_{{ $suffix }}" class="mt-0.5 block w-full rounded-lg border-stone-300 text-sm">
                            @error('coefficient_'.$suffix) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            <label class="mt-1 block text-xs text-stone-500">{{ __('Barème') }}</label>
                            <input type="number" step="1" wire:model="bareme_{{ $suffix }}" class="mt-0.5 block w-full rounded-lg border-stone-300 text-sm">
                            @error('bareme_'.$suffix) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
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
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Nom') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Abréviation') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">CP1</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">CP2</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">CE1</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">CE2</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">CM1</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">CM2</th>
                    <th class="px-4 py-2"></th>
                </tr>
                <tr class="bg-stone-50 text-xs text-stone-400">
                    <th class="px-4 pb-2 text-start font-normal"></th>
                    <th class="px-4 pb-2 text-start font-normal"></th>
                    <th class="px-4 pb-2 text-start font-normal" colspan="6">{{ __('coefficient / barème') }}</th>
                    <th class="px-4 pb-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($primarySubjects as $primarySubject)
                    <tr wire:key="primary-subject-{{ $primarySubject->id }}">
                        <td class="px-4 py-2 text-stone-900">{{ $primarySubject->name }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ $primarySubject->abbreviation }}</td>
                        @foreach (['cp1', 'cp2', 'ce1', 'ce2', 'cm1', 'cm2'] as $suffix)
                            <td class="px-4 py-2 text-stone-600">
                                {{ $primarySubject->{'coefficient_'.$suffix} ?? '—' }} / {{ $primarySubject->{'bareme_'.$suffix} ?? '—' }}
                            </td>
                        @endforeach
                        <td class="px-4 py-2 text-end whitespace-nowrap">
                            @can('update', $primarySubject)
                                <button wire:click="edit({{ $primarySubject->id }})" class="text-stone-500 hover:text-stone-900">{{ __('Modifier') }}</button>
                            @endcan
                            @can('delete', $primarySubject)
                                <button
                                    wire:click="delete({{ $primarySubject->id }})"
                                    wire:confirm="{{ __('Supprimer cette matière ?') }}"
                                    class="ms-3 text-red-500 hover:text-red-700"
                                >
                                    {{ __('Supprimer') }}
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-6 text-center text-stone-500">{{ __('Aucune matière.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
