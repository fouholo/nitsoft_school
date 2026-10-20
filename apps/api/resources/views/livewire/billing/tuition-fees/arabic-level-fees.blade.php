<div>
    <div class="mt-4 overflow-hidden rounded-lg border border-stone-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-200 text-sm">
                <thead class="bg-stone-50">
                    <tr>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Niveau arabe') }}</th>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __("Frais d'inscription") }}</th>
                        <th class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Total scolarité configuré') }}</th>
                        <th class="whitespace-nowrap px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @php
                        $otherConfiguredLevelIds = $arabicLevelFees->keys()->reject(fn ($id) => $id === $configuringLevelId);
                    @endphp
                    @forelse ($arabicLevels as $arabicLevel)
                        @php
                            $arabicLevelFee = $arabicLevelFees->get($arabicLevel->id);
                            $total = $arabicLevelFee ? $arabicLevelFee->installmentAmounts->whereNotNull('amount')->sum('amount') : 0;
                        @endphp
                        <tr wire:key="arabic-level-{{ $arabicLevel->id }}">
                            <td class="whitespace-nowrap px-4 py-2 text-stone-900">
                                <span dir="rtl">{{ $arabicLevel->wording }}</span>
                                @if ($arabicLevel->wording_fr)
                                    <span class="text-stone-500">— {{ $arabicLevel->wording_fr }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ money((float) ($arabicLevelFee->registration_amount ?? 0)) }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-stone-600">{{ money((float) $total) }}</td>
                            <td class="whitespace-nowrap px-4 py-2 text-end">
                                @if ($justSaved && $lastSavedLevelId === $arabicLevel->id)
                                    <span class="me-3 rounded-lg bg-emerald-50 px-2 py-1 text-xs text-emerald-700">{{ __('Tarifs enregistrés.') }}</span>
                                @endif
                                @can('create', \App\Domain\Billing\Models\ArabicLevelFee::class)
                                    <button type="button" wire:click="configureLevel({{ $arabicLevel->id }})" class="text-stone-500 hover:text-stone-900">{{ __('Configurer') }}</button>
                                @endcan
                            </td>
                        </tr>

                        @if ($showForm && $configuringLevelId === $arabicLevel->id)
                            <tr>
                                <td colspan="4" class="bg-stone-50 px-4 py-4">
                                    <form wire:submit="save" class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                        @if ($otherConfiguredLevelIds->isNotEmpty())
                                            <div class="col-span-2 sm:col-span-4">
                                                <label for="arabicDuplicateSourceLevelId" class="block text-sm font-medium text-stone-700">{{ __('Dupliquer les montants depuis un autre niveau') }}</label>
                                                <select id="arabicDuplicateSourceLevelId" wire:model.live="duplicateSourceLevelId" class="mt-1 block w-full max-w-xs rounded-lg border-stone-300 text-sm">
                                                    <option value="">{{ __('— Choisir un niveau —') }}</option>
                                                    @foreach ($arabicLevels->whereIn('id', $otherConfiguredLevelIds) as $otherLevel)
                                                        <option value="{{ $otherLevel->id }}">{{ $otherLevel->wording_fr ?: $otherLevel->wording }}</option>
                                                    @endforeach
                                                </select>
                                                <p class="mt-1 text-xs text-stone-500">{{ __('Les montants seront pré-remplis ; vous pourrez les ajuster avant d\'enregistrer.') }}</p>
                                            </div>
                                        @endif

                                        <div>
                                            <label for="arabic_registration_amount" class="block text-sm font-medium text-stone-700">{{ __("Frais d'inscription") }} — F CFA</label>
                                            <input id="arabic_registration_amount" type="number" step="0.01" wire:model="registration_amount" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                                            @error('registration_amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                        </div>

                                        @foreach ($installments as $installment)
                                            <div>
                                                <label for="arabic_installment_amounts-{{ $installment->id }}" class="block text-sm font-medium text-stone-700">{{ $installment->label }} — F CFA</label>
                                                <input id="arabic_installment_amounts-{{ $installment->id }}" type="number" step="0.01" wire:model="installment_amounts.{{ $installment->id }}" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                                                @error('installment_amounts.' . $installment->id) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                            </div>
                                        @endforeach

                                        <div class="col-span-2 flex items-center justify-between rounded-lg bg-white px-3 py-2 text-sm sm:col-span-4" x-data>
                                            <span class="font-medium text-stone-700">{{ __('Total scolarité (aperçu)') }}</span>
                                            <span class="font-semibold text-stone-900" x-text="new Intl.NumberFormat('fr-FR').format(Object.values($wire.installment_amounts).reduce((sum, v) => sum + (parseFloat(v) || 0), 0)) + ' F CFA'"></span>
                                        </div>

                                        <div class="col-span-2 flex items-end gap-2 sm:col-span-4">
                                            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800 disabled:opacity-60">
                                                <span wire:loading.remove wire:target="save">{{ __('Enregistrer') }}</span>
                                                <span wire:loading wire:target="save">{{ __('Enregistrement…') }}</span>
                                            </button>
                                            <button type="button" wire:click="cancel" class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm text-stone-700 hover:bg-stone-50">
                                                {{ __('Annuler') }}
                                            </button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-stone-500">{{ __('Aucun niveau arabe pour ce type d’établissement.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
