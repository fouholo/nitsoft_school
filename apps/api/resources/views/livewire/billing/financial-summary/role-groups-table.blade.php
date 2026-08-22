@php
    $keyPrefix ??= 'main';
    $totalLabel ??= __('Total général');
@endphp

<table class="min-w-full divide-y divide-stone-200 text-sm">
    <thead class="bg-stone-50">
        <tr>
            <th scope="col" class="whitespace-nowrap px-4 py-2 text-start font-medium text-stone-500">{{ __('Rôle / Utilisateur') }}</th>
            <th scope="col" class="whitespace-nowrap px-4 py-2 text-end font-medium text-stone-500">{{ __('Encaissé') }}</th>
            <th scope="col" class="whitespace-nowrap px-4 py-2 text-end font-medium text-stone-500">{{ __('Dépensé') }}</th>
            <th scope="col" class="whitespace-nowrap px-4 py-2 text-end font-medium text-stone-500">{{ __('Net') }}</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-stone-100">
        @foreach ($groups as $group)
            <tr class="bg-stone-50" wire:key="{{ $keyPrefix }}-role-{{ $group['role'] ?? 'none' }}">
                <td class="whitespace-nowrap px-4 py-2 font-medium text-stone-800">{{ $group['roleLabel'] }}</td>
                <td class="whitespace-nowrap px-4 py-2 text-end font-medium text-stone-800">{{ money($group['collected']) }}</td>
                <td class="whitespace-nowrap px-4 py-2 text-end font-medium text-stone-800">{{ money($group['spent']) }}</td>
                <td class="whitespace-nowrap px-4 py-2 text-end font-medium text-stone-800">{{ money($group['net']) }}</td>
            </tr>
            @foreach ($group['rows'] as $row)
                <tr wire:key="{{ $keyPrefix }}-user-{{ $row['user_id'] }}">
                    <td class="whitespace-nowrap px-4 py-2 ps-8 text-stone-600">{{ $row['user_name'] }}</td>
                    <td class="whitespace-nowrap px-4 py-2 text-end text-stone-600">{{ money($row['collected']) }}</td>
                    <td class="whitespace-nowrap px-4 py-2 text-end text-stone-600">{{ money($row['spent']) }}</td>
                    <td class="whitespace-nowrap px-4 py-2 text-end text-stone-600">{{ money($row['net']) }}</td>
                </tr>
            @endforeach
        @endforeach
    </tbody>
    <tfoot class="border-t border-stone-200">
        <tr>
            <td class="whitespace-nowrap px-4 py-2 font-semibold text-stone-900">{{ $totalLabel }}</td>
            <td class="whitespace-nowrap px-4 py-2 text-end font-semibold text-stone-900">{{ money($totalCollected) }}</td>
            <td class="whitespace-nowrap px-4 py-2 text-end font-semibold text-stone-900">{{ money($totalSpent) }}</td>
            <td class="whitespace-nowrap px-4 py-2 text-end font-semibold text-stone-900">{{ money($totalNet) }}</td>
        </tr>
    </tfoot>
</table>
