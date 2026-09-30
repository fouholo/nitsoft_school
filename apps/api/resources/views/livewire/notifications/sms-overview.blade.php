<div>
    <h1 class="text-2xl font-semibold text-stone-900">{{ __('Suivi des SMS') }}</h1>
    <p class="mt-1 text-sm text-stone-500">{{ __('Solde du compte Orange de la plateforme et consommation de chaque école.') }}</p>

    @if ($flash)
        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
            {{ $flash }}
        </div>
    @endif

    <section class="mt-6">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-stone-900">{{ __('Solde Orange') }}</h2>
            @if ($usesOrange)
                <button type="button" wire:click="refreshBalance" class="rounded-lg border border-stone-300 bg-white px-3 py-1.5 text-sm font-medium text-stone-700 hover:bg-stone-50">
                    {{ __('Actualiser') }}
                </button>
            @endif
        </div>

        @if (! $usesOrange)
            <p class="mt-2 rounded-lg border border-stone-200 bg-white px-4 py-3 text-sm text-stone-600">
                {{ __("Fournisseur de test : les SMS sont écrits dans les journaux, aucun solde à afficher. Renseignez SMS_PROVIDER=orange et les identifiants Orange dans le fichier .env pour envoyer de vrais SMS.") }}
            </p>
        @elseif ($balanceError)
            <p class="mt-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                {{ __('Solde indisponible : :error', ['error' => $balanceError]) }}
            </p>
        @else
            @if ($lowBalance)
                <p class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">
                    {{ __('Solde bas : moins de :threshold SMS disponibles sur un contrat actif. Rechargez le forfait Orange pour que les envois ne restent pas en attente.', ['threshold' => $threshold]) }}
                </p>
            @endif

            @if ($contracts === [])
                <p class="mt-2 text-sm text-stone-500">{{ __('Aucun contrat SMS actif sur le compte Orange.') }}</p>
            @else
                <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($contracts as $contract)
                        @php
                            $expiresAt = isset($contract['expirationDate']) ? \Carbon\CarbonImmutable::parse($contract['expirationDate']) : null;
                            $active = ($contract['status'] ?? null) === 'ACTIVE' && ! $expiresAt?->isPast();
                        @endphp
                        <div class="rounded-lg border border-stone-200 bg-white p-4" wire:key="contract-{{ $contract['id'] ?? $loop->index }}">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-medium text-stone-700">{{ $contract['country'] ?? '—' }} · {{ $contract['offerName'] ?? '—' }}</p>
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-emerald-100 text-emerald-700' => $active,
                                    'bg-red-100 text-red-700' => ! $active,
                                ])>{{ $active ? __('Actif') : __('Inactif') }}</span>
                            </div>
                            <p class="mt-2 text-2xl font-semibold text-stone-900">{{ number_format((int) ($contract['availableUnits'] ?? 0), 0, ',', ' ') }}</p>
                            <p class="text-sm text-stone-500">{{ __('SMS disponibles') }}</p>
                            @if ($expiresAt)
                                <p class="mt-1 text-xs text-stone-500">{{ __('Expire le :date', ['date' => $expiresAt->format('d/m/Y')]) }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
    </section>

    <section class="mt-8">
        <h2 class="text-lg font-semibold text-stone-900">{{ __('SMS en attente') }}</h2>
        <div class="mt-2 flex flex-wrap items-center gap-3 rounded-lg border border-stone-200 bg-white px-4 py-3">
            <p class="text-sm text-stone-700">
                {{ trans_choice('{0} Aucun SMS en attente.|{1} 1 SMS en attente, toutes écoles confondues.|[2,*] :count SMS en attente, toutes écoles confondues.', $pendingCount, ['count' => $pendingCount]) }}
            </p>
            @if ($pendingCount > 0)
                <button
                    type="button"
                    wire:click="resendPending"
                    wire:confirm="{{ __('Renvoyer tous les SMS en attente ?') }}"
                    class="ms-auto rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800"
                >
                    {{ __('Renvoyer les SMS en attente') }}
                </button>
            @endif
        </div>
    </section>

    <section class="mt-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h2 class="text-lg font-semibold text-stone-900">{{ __('Consommation par école') }}</h2>
            <div>
                <label for="month" class="block text-sm font-medium text-stone-700">{{ __('Mois') }}</label>
                <input id="month" type="month" wire:model.live="month" class="mt-1 rounded-lg border-stone-300 text-sm">
            </div>
        </div>

        <div class="mt-3 overflow-hidden rounded-lg border border-stone-200 bg-white">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-sm">
                    <thead class="bg-stone-50">
                        <tr>
                            <th scope="col" class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Établissement') }}</th>
                            <th scope="col" class="px-4 py-2 text-end font-medium text-stone-500">{{ __('Envoyés') }}</th>
                            <th scope="col" class="px-4 py-2 text-end font-medium text-stone-500">{{ __('En échec') }}</th>
                            <th scope="col" class="px-4 py-2 text-end font-medium text-stone-500">{{ __('En attente') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($consumption as $row)
                            <tr wire:key="consumption-{{ $loop->index }}">
                                <td class="px-4 py-2 text-stone-900">{{ $row['name'] }}</td>
                                <td class="px-4 py-2 text-end tabular-nums text-stone-900">{{ $row['sent'] }}</td>
                                <td class="px-4 py-2 text-end tabular-nums text-stone-600">{{ $row['failed'] }}</td>
                                <td class="px-4 py-2 text-end tabular-nums text-stone-600">{{ $row['queued'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-stone-500">{{ __('Aucun SMS sur ce mois.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($consumption->isNotEmpty())
                        <tfoot class="bg-stone-50">
                            <tr>
                                <th scope="row" class="px-4 py-2 text-start font-semibold text-stone-900">{{ __('Total') }}</th>
                                <td class="px-4 py-2 text-end font-semibold tabular-nums text-stone-900">{{ $consumption->sum('sent') }}</td>
                                <td class="px-4 py-2 text-end font-semibold tabular-nums text-stone-900">{{ $consumption->sum('failed') }}</td>
                                <td class="px-4 py-2 text-end font-semibold tabular-nums text-stone-900">{{ $consumption->sum('queued') }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </section>
</div>
