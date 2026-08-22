<div class="mt-6">
    <label class="block text-xs font-medium text-stone-500">{{ __('Écoles') }}</label>
    <div class="mt-1 flex flex-wrap gap-3">
        @foreach ($groupEstablishments as $establishment)
            <label class="flex items-center gap-1.5 text-sm text-stone-700">
                <input type="checkbox" wire:model.live="selected" value="{{ $establishment->id }}" class="rounded border-stone-300">
                {{ $establishment->name }}
            </label>
        @endforeach
    </div>
</div>
