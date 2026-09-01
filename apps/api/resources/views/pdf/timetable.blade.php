<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: "DejaVu Sans", sans-serif; font-size: 11px; color: #1e293b; }
        .subtitle { text-align: center; margin-bottom: 20px; font-weight: bold; font-size: 16px; text-decoration: underline; }
        table.grid { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.grid th, table.grid td { border: 1px solid #cbd5e1; padding: 4px 6px; text-align: left; vertical-align: top; }
        table.grid th { background-color: #f1f5f9; }
        .slot-label { font-weight: bold; }
        .slot-time { font-size: 9px; color: #64748b; }
        .session-subject { font-weight: bold; }
        .session-detail { font-size: 9px; color: #475569; }
        .break-row td { background-color: #f8fafc; text-align: center; font-size: 9px; color: #94a3b8; text-transform: uppercase; }
        .footer { margin-top: 40px; font-size: 10px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    @include('pdf.partials.reports-header', ['establishment' => $establishment, 'generalInformation' => $generalInformation])

    <p class="subtitle">{{ \Illuminate\Support\Str::upper($title) }}</p>

    <table class="grid">
        <thead>
            <tr>
                <th>{{ __('Créneau') }}</th>
                @foreach ($days as $day)
                    <th>{{ $day->label() }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($slots as $slot)
                <tr>
                    <td>
                        <div class="slot-label">{{ $slot->label }}</div>
                        <div class="slot-time">{{ substr($slot->start_time, 0, 5) }}–{{ substr($slot->end_time, 0, 5) }}</div>
                    </td>

                    @if ($slot->is_break)
                        <td colspan="{{ count($days) }}" class="break-row">{{ __('Pause') }}</td>
                    @else
                        @foreach ($days as $day)
                            @php $session = $sessions->get($day->value.'-'.$slot->id) @endphp
                            <td>
                                @if ($session)
                                    <div class="session-subject">{{ $session->subject?->name }}</div>
                                    <div class="session-detail">
                                        {{ $showClassroom ? $session->classroom?->name : $session->teacher?->name }}
                                    </div>
                                    @if ($session->room)
                                        <div class="session-detail">{{ __('Salle') }} {{ $session->room }}</div>
                                    @endif
                                @endif
                            </td>
                        @endforeach
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($days) + 1 }}">{{ __('Aucun créneau configuré.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @include('pdf.partials.director-stamp', ['establishment' => $establishment])

    <p class="footer">Généré le {{ now()->locale('fr')->translatedFormat('j F Y à H:i:s') }}</p>
</body>
</html>
