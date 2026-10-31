@foreach ([
    ['legend' => __('Scolarité'), 'typeField' => 'type', 'valueField' => 'value'],
    ['legend' => __("Frais d'inscription"), 'typeField' => 'registration_type', 'valueField' => 'registration_value'],
] as $block)
    <fieldset class="grid grid-cols-2 gap-4 sm:col-span-2" wire:key="discount-block-{{ $block['valueField'] }}">
        <legend class="col-span-2 text-sm font-semibold text-stone-900">{{ $block['legend'] }}</legend>

        <div>
            <label for="{{ $block['typeField'] }}" class="block text-sm font-medium text-stone-700">{{ __('Type') }}</label>
            <select id="{{ $block['typeField'] }}" wire:model="{{ $block['typeField'] }}" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                <option value="percentage">{{ __('Pourcentage') }}</option>
                <option value="fixed_amount">{{ __('Montant fixe') }}</option>
            </select>
        </div>

        <div>
            <label for="{{ $block['valueField'] }}" class="block text-sm font-medium text-stone-700">{{ __('Valeur') }}</label>
            <input id="{{ $block['valueField'] }}" type="number" step="0.01" wire:model="{{ $block['valueField'] }}" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
            @error($block['valueField']) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    </fieldset>
@endforeach

<p class="text-xs text-stone-500 sm:col-span-4">{{ __('Laissez la valeur vide pour ne pas accorder de réduction sur cette partie.') }}</p>
