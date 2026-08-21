<table class="summary">
    <thead>
        <tr>
            <th>Rôle / Utilisateur</th>
            <th class="amount">Encaissé</th>
            <th class="amount">Dépensé</th>
            <th class="amount">Net</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($groups as $group)
            <tr class="role-row">
                <td>{{ $group['roleLabel'] }}</td>
                <td class="amount">{{ money($group['collected']) }}</td>
                <td class="amount">{{ money($group['spent']) }}</td>
                <td class="amount">{{ money($group['net']) }}</td>
            </tr>
            @foreach ($group['rows'] as $row)
                <tr class="user-row">
                    <td>{{ $row['user_name'] }}</td>
                    <td class="amount">{{ money($row['collected']) }}</td>
                    <td class="amount">{{ money($row['spent']) }}</td>
                    <td class="amount">{{ money($row['net']) }}</td>
                </tr>
            @endforeach
        @endforeach
    </tbody>
    <tfoot>
        <tr class="total-row">
            <td>{{ $totalLabel }}</td>
            <td class="amount">{{ money($totalCollected) }}</td>
            <td class="amount">{{ money($totalSpent) }}</td>
            <td class="amount">{{ money($totalNet) }}</td>
        </tr>
    </tfoot>
</table>
