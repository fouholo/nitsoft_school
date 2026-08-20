<div>
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-stone-900">Modèles SMS</h1>

        @can('create', \App\Domain\Notifications\Models\SmsTemplate::class)
            <button type="button" wire:click="create" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                Nouveau modèle
            </button>
        @endcan
    </div>

    <p class="mt-2 text-sm text-stone-500">
        @verbatim
        Placeholders disponibles : <code>{{guardian_name}}</code>, <code>{{student_name}}</code>.
        @endverbatim
        Code <code>attendance_absence</code> utilisé automatiquement lors d'une absence.
    </p>

    @if ($showForm)
        <form wire:submit="save" class="mt-4 grid grid-cols-1 gap-4 rounded-lg border border-stone-200 bg-white p-4 sm:grid-cols-3">
            <div>
                <label class="block text-sm font-medium text-stone-700">Code</label>
                <input type="text" wire:model="code" placeholder="attendance_absence" class="mt-1 block w-full rounded-lg border-stone-300 text-sm">
                @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-stone-700">Message</label>
                <textarea wire:model="body" rows="2" class="mt-1 block w-full rounded-lg border-stone-300 text-sm"></textarea>
                @error('body') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-stone-600 sm:col-span-3">
                <input type="checkbox" wire:model="is_active" class="rounded border-stone-300">
                Actif
            </label>

            <div class="flex gap-2 sm:col-span-3">
                <button type="submit" class="rounded-lg bg-orange-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-800">
                    Enregistrer
                </button>
                <button type="button" wire:click="cancel" class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm text-stone-700 hover:bg-stone-50">
                    Annuler
                </button>
            </div>
        </form>
    @endif

    <div class="mt-6 overflow-hidden rounded-lg border border-stone-200 bg-white">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-stone-500">Code</th>
                    <th class="px-4 py-2 text-left font-medium text-stone-500">Message</th>
                    <th class="px-4 py-2 text-left font-medium text-stone-500">Actif</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($smsTemplates as $smsTemplate)
                    <tr wire:key="sms-template-{{ $smsTemplate->id }}">
                        <td class="px-4 py-2 text-stone-900">{{ $smsTemplate->code }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ $smsTemplate->body }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ $smsTemplate->is_active ? 'Oui' : 'Non' }}</td>
                        <td class="px-4 py-2 text-right">
                            <button wire:click="edit({{ $smsTemplate->id }})" class="text-stone-500 hover:text-stone-900">Modifier</button>
                            <button
                                wire:click="delete({{ $smsTemplate->id }})"
                                wire:confirm="Supprimer ce modèle ?"
                                class="ml-3 text-red-500 hover:text-red-700"
                            >
                                Supprimer
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-stone-500">Aucun modèle.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
