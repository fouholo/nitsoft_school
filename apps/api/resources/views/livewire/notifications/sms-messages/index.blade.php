<div>
    <h1 class="text-2xl font-semibold text-stone-900">{{ __('Journal SMS') }}</h1>

    <div class="mt-6 overflow-hidden rounded-lg border border-stone-200 bg-white">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50">
                <tr>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Date') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Destinataire') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Message') }}</th>
                    <th class="px-4 py-2 text-start font-medium text-stone-500">{{ __('Statut') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($smsMessages as $smsMessage)
                    <tr wire:key="sms-message-{{ $smsMessage->id }}">
                        <td class="px-4 py-2 text-stone-900">{{ $smsMessage->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-2 text-stone-600">{{ $smsMessage->guardian?->first_name }} {{ $smsMessage->guardian?->last_name }} ({{ $smsMessage->phone }})</td>
                        <td class="px-4 py-2 text-stone-600">{{ $smsMessage->body_rendered }}</td>
                        <td class="px-4 py-2">
                            @php
                                $statusLabel = match ($smsMessage->status) {
                                    'sent' => __('Envoyé'),
                                    'delivered' => __('Délivré'),
                                    'queued' => __('En file'),
                                    'failed' => __('Échoué'),
                                    default => $smsMessage->status,
                                };
                            @endphp
                            <span @class([
                                'rounded-full px-2 py-0.5 text-xs font-medium',
                                'bg-emerald-100 text-emerald-700' => in_array($smsMessage->status, ['sent', 'delivered']),
                                'bg-stone-100 text-stone-700' => $smsMessage->status === 'queued',
                                'bg-red-100 text-red-700' => $smsMessage->status === 'failed',
                            ])>
                                {{ $statusLabel }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-stone-500">{{ __('Aucun SMS envoyé.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="border-t border-stone-200 px-4 py-3">
            {{ $smsMessages->links() }}
        </div>
    </div>
</div>
