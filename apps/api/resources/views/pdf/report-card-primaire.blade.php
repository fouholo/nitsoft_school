<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Relevé de notes</title>
    <style>
        @page { margin-top: 0.6cm; margin-bottom: 0.6cm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #1e293b; }
        h1 { font-size: 13px; text-align: center; margin: 16px 0; text-transform: uppercase; }
        table.report-header { width: 100%; border-collapse: collapse; margin-bottom: 16px; table-layout: fixed; }
        table.report-header td { vertical-align: top; text-align: center; }
        table.report-header td.left { width: 68%; }
        table.report-header td.right { width: 32%; }
        table.report-header p { margin: 0; font-size: 7px; }
        table.report-header p.establishment-name { margin-top: 4px; font-size: 14px; font-weight: bold; }
        table.report-header img.logo { height: 45px; margin-top: 4px; }
        table.report-header img.armoirie { height: 65px; }
        .identity td { padding: 1px 0; }
        .identity td.label { color: #64748b; width: 80px; }
        table.grades { width: 100%; border-collapse: collapse; margin: 12px 0; }
        table.grades th, table.grades td { border: 1px solid #cbd5e1; padding: 4px 6px; text-align: left; }
        table.grades th { background-color: #f1f5f9; text-align: center; }
        table.grades tr.total td { background-color: #f1f5f9; font-weight: bold; }
        .notes-table th:nth-child(2), .notes-table td:nth-child(2),
        .notes-table th:nth-child(3), .notes-table td:nth-child(3) { text-align: center; }
        table.grades.results-table th, table.grades.results-table td { text-align: center; }
        .section-title { font-weight: bold; margin: 10px 0 4px; }
        .footer { margin-top: 24px; font-size: 10px; }
        .signatures td { text-align: center; vertical-align: top; padding-top: 8px; }
        .signatures p.label { font-weight: bold; margin: 0 0 36px; }
        .signatures p.name { margin: 0; }
    </style>
</head>
<body>
    @php
        $establishment = $reportCard->establishment;
        $headerLines = [];
        if ($generalInformation->nom_ministere) {
            $headerLines[] = \Illuminate\Support\Str::upper($generalInformation->nom_ministere);
        }
        if ($establishment->inspection?->direction) {
            $headerLines[] = 'DIRECTION REGIONALE '.\Illuminate\Support\Str::upper($establishment->inspection->direction->direction_name);
        }
        if ($establishment->type === \App\Domain\Establishments\Enums\EstablishmentType::PrescolairePrimaire && $establishment->inspection) {
            $headerLines[] = "INSPECTION DE L'ENSEIGNEMENT PRESCOLAIRE ET PRIMAIRE ".\Illuminate\Support\Str::upper($establishment->inspection->inspection_name);
        }
    @endphp
    <table class="report-header">
        <tr>
            <td class="left">
                @foreach ($headerLines as $line)
                    <p>{{ $line }}</p>
                @endforeach
                <p class="establishment-name">{{ $establishment->name }}</p>
                @if ($establishment->logo_path)
                    <img class="logo" src="{{ public_path('storage/'.$establishment->logo_path) }}">
                @endif
            </td>
            <td class="right">
                @if ($generalInformation->armoirie_path)
                    <img class="armoirie" src="{{ public_path('storage/'.$generalInformation->armoirie_path) }}">
                @endif
            </td>
        </tr>
    </table>

    <h1>Relevé de notes - Composition N°{{ $reportCard->composition_number }}</h1>

    @php
        $student = $reportCard->student;
        $classroom = $reportCard->classroom;
        $level = $classroom->level;
        $civilite = match ($student->gender) {
            'f' => 'Mademoiselle',
            'm' => 'Monsieur',
            default => "L'élève",
        };
        $formatNumber = fn (float $value): string => rtrim(rtrim(number_format($value, 2), '0'), '.');
    @endphp

    <table class="identity" style="width: 100%;">
        <tr>
            <td class="label">{{ $civilite }} :</td>
            <td colspan="3"><strong>{{ \Illuminate\Support\Str::upper($student->last_name.' '.$student->first_name) }}</strong></td>
        </tr>
        <tr>
            <td class="label">Née le :</td>
            <td style="width: 40%;"><strong>{{ $student->birth_date?->format('j/n/Y') }}</strong></td>
            <td class="label" style="width: 20px;">à :</td>
            <td><strong>{{ $student->birth_place }}</strong></td>
        </tr>
        <tr>
            <td class="label">Matricule :</td>
            <td><strong>{{ $student->student_number }}</strong></td>
        </tr>
        <tr>
            <td class="label">Cours :</td>
            <td><strong>{{ $classroom->name }}</strong></td>
            <td colspan="2">a obtenu :</td>
        </tr>
    </table>

    <p class="section-title">a- Notes</p>
    <table class="grades notes-table">
        <thead>
            <tr>
                <th>Épreuves</th>
                <th>Coefficients</th>
                <th>Notes</th>
                <th>Appréciations</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $grade)
                @php
                    $subject = $grade->primarySubject;
                    $bareme = $subject->bareme($level) ?? 20.0;
                    $coefficient = $subject->coefficientFor($level) ?? 0.0;
                @endphp
                <tr>
                    <td>{{ $subject->name }}</td>
                    <td>{{ $formatNumber($coefficient) }}</td>
                    <td>{{ $formatNumber((float) $grade->score) }} / {{ $formatNumber($bareme) }}</td>
                    <td>{{ \App\Domain\Grading\Models\AppreciationScale::forAverage((float) $grade->score, $bareme)?->appreciation }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">Aucune note disponible pour cette composition.</td>
                </tr>
            @endforelse
            @if ($rows->isNotEmpty())
                <tr class="total">
                    <td style="text-align: center;">Total</td>
                    <td>{{ $formatNumber($rows->sum(fn ($g) => $g->primarySubject->coefficientFor($level) ?? 0.0)) }}</td>
                    <td>{{ $formatNumber($rows->sum(fn ($g) => (float) $g->score)) }} / {{ $formatNumber($rows->sum(fn ($g) => $g->primarySubject->bareme($level) ?? 20.0)) }}</td>
                    <td></td>
                </tr>
            @endif
        </tbody>
    </table>

    <p class="section-title">b- Résultats</p>
    @php
        $passingAverage = $level->compositionAverageScale() / 2;
        $resultat = match (true) {
            $reportCard->average === null => 'Absence',
            (float) $reportCard->average >= $passingAverage => match ($student->gender) {
                'f' => 'Admise', 'm' => 'Admis', default => 'Admis(e)',
            },
            default => match ($student->gender) {
                'f' => 'Refusée', 'm' => 'Refusé', default => 'Refusé(e)',
            },
        };
    @endphp
    <table class="grades results-table">
        <thead>
            <tr>
                <th>Moyenne</th>
                <th>Rang</th>
                <th>Résultat</th>
                <th>Appréciation</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $reportCard->average !== null ? $formatNumber((float) $reportCard->average) : '—' }}</td>
                <td>{{ $reportCard->rank ?? '—' }}</td>
                <td>{{ $resultat }}</td>
                <td>{{ $reportCard->appreciation }}</td>
            </tr>
        </tbody>
    </table>

    <p class="footer">Fait à {{ $reportCard->establishment->address }}, le {{ now()->locale('fr')->translatedFormat('j F Y') }}</p>

    @php
        $teacher = \App\Domain\Academics\Models\TeacherAssignment::where('classroom_id', $classroom->id)
            ->whereNull('subject_id')
            ->first()?->teacher;
    @endphp
    <table class="signatures" style="width: 100%;">
        <tr>
            <td style="width: 33.33%;">
                <p class="label">Visa maître(sse)</p>
                <p class="name">{{ $teacher?->name }}</p>
            </td>
            <td style="width: 33.33%;">
                <p class="label">Visa directeur(trice)</p>
                <p class="name">{{ $reportCard->establishment->director()?->name }}</p>
            </td>
            <td style="width: 33.33%;">
                <p class="label">Visa du parent</p>
            </td>
        </tr>
    </table>
</body>
</html>
