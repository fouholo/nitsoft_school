<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Lettre de relance — {{ $student->last_name }} {{ $student->first_name }}</title>
    @include('pdf.partials.payment-reminder-style')
    <style>
        @page { margin: 1cm; }
    </style>
</head>
<body>
    @include('pdf.partials.payment-reminder-letter', [
        'student' => $student,
        'establishment' => $establishment,
        'classroom' => $classroom,
        'schoolYear' => $schoolYear,
        'rows' => $rows,
        'total' => $total,
        'generalInformation' => $generalInformation,
        'reminderType' => $reminderType ?? 'late',
        'nextInstallment' => $nextInstallment ?? null,
    ])
</body>
</html>
