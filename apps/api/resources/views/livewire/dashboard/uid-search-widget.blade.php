<div
    x-data
    x-on:uid-search-failed.window="$nextTick(() => $refs.uidInput.focus())"
    class="mt-6 rounded-2xl border border-stone-200 bg-white p-5"
>
    <label class="text-sm font-medium text-stone-700">{{ __('Rechercher un élève (UID / code-barres)') }}</label>
    <form wire:submit="search" class="mt-2">
        <input
            type="text"
            wire:model="uid"
            x-ref="uidInput"
            autofocus
            autocomplete="off"
            placeholder="{{ __('Scannez ou saisissez un code...') }}"
            class="block w-full rounded-lg border-stone-300 text-sm"
        >
    </form>
    @if ($errorMessage)
        <p class="mt-2 text-sm text-red-600">{{ $errorMessage }}</p>
    @endif
</div>
