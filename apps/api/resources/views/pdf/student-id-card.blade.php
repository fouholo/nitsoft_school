<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Carte d'identité scolaire — {{ $student->last_name }} {{ $student->first_name }}</title>
    @include('pdf.partials.student-id-card-style')
    <style>
        @page { margin: 0; }
        body { margin: 0; padding: 0; }
    </style>
</head>
<body>
    @include('pdf.partials.student-id-card', [
        'student' => $student,
        'establishment' => $establishment,
        'classroom' => $classroom,
        'schoolYear' => $schoolYear,
    ])
</body>
</html>
