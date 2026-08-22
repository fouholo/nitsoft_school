<div class="mt-4">
    <p class="text-sm font-semibold uppercase tracking-wide text-stone-500">{{ __('Comparaison entre écoles') }}</p>

    @if ($noEstablishmentSelected)
        <p class="mt-3 text-sm text-stone-500">{{ __('Sélectionnez au moins une école.') }}</p>
    @else
        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($cards as $card)
                <div class="rounded-2xl border border-stone-200 bg-white p-5">
                    <p class="text-sm font-semibold text-stone-900">{{ $card['name'] }}</p>

                    <div class="mt-3 space-y-2 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-stone-500">{{ __('Élèves actifs') }}</span>
                            <span class="font-medium text-stone-900">{{ $card['studentsCount'] }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-stone-500">{{ __('Encaissé') }}</span>
                            <span class="font-medium text-stone-900">{{ money($card['collected']) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-stone-500">{{ __('Net') }}</span>
                            <span class="font-medium {{ $card['net'] >= 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ money($card['net']) }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
