<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Lettres de relance</title>
    @include('pdf.partials.payment-reminder-style')
    <style>
        @page { margin: 1cm; }
        .reminder-letter { page-break-after: always; }
        .reminder-letter:last-child { page-break-after: avoid; }
    </style>
</head>
<body>
    @php $reminderType = $reminderType ?? 'late'; @endphp
    @if ($letters->isEmpty())
        @if ($reminderType === 'upcoming' && ! $nextInstallment)
            <p>Aucune échéance à venir n'est configurée pour cette année scolaire.</p>
        @elseif ($reminderType === 'upcoming')
            <p>Tous les élèves ont déjà soldé la prochaine échéance sur ce périmètre.</p>
        @else
            <p>Aucun élève en retard sur ce périmètre.</p>
        @endif
    @else
        @foreach ($letters as $letter)
            @include('pdf.partials.payment-reminder-letter', [
                'student' => $letter['student'],
                'establishment' => $letter['establishment'],
                'classroom' => $letter['classroom'],
                'schoolYear' => $schoolYear,
                'rows' => $letter['rows'],
                'total' => $letter['total'],
                'generalInformation' => $generalInformation,
                'reminderType' => $reminderType,
                'nextInstallment' => $nextInstallment ?? null,
            ])
        @endforeach
    @endif
</body>
</html>
