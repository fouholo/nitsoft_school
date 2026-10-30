<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>كشف الدرجات</title>
    <style>
        @font-face {
            font-family: 'NotoNaskhArabic';
            src: url('file://{{ $fontRegularPath }}') format('truetype');
            font-weight: normal;
        }
        @font-face {
            font-family: 'NotoNaskhArabic';
            src: url('file://{{ $fontBoldPath }}') format('truetype');
            font-weight: bold;
        }
        @page { margin: 0.6cm 0.9cm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 8.5px; color: #1e293b; line-height: 1.1; }
        .ar { font-family: 'NotoNaskhArabic', "DejaVu Sans", sans-serif; direction: rtl; }
        .page-break { page-break-after: always; }
        .school { text-align: center; font-weight: bold; font-size: 11px; margin: 0; }
        .year { text-align: center; font-size: 8px; color: #475569; margin: 1px 0 4px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #475569; padding: 1px 5px; vertical-align: middle; line-height: 10px; }
        .month td, .month td.ar { text-align: center; font-weight: bold; font-size: 9px; background-color: #f1f5f9; padding: 1px; }
        .identity { margin: 4px 0; }
        .identity td { border: none; padding: 0 2px; }
        .identity .value { font-weight: bold; }
        th { background-color: #f1f5f9; font-weight: bold; }
        .fr { text-align: left; }
        .note { text-align: center; width: 18%; }
        /* Noto Naskh agrandit la ligne : sans line-height réduit, chaque rang
           passe à 16 pt et le relevé ne tient plus sur une page A5. */
        td.ar, th.ar { text-align: right; font-size: 8.5px; line-height: 6px; }
        .ar { line-height: 6px; }
        .results { margin-top: 4px; }
        .results .value { text-align: center; font-weight: bold; width: 26%; }
        .signatures { margin-top: 4px; }
        .signatures td { text-align: center; width: 50%; height: 48px; vertical-align: top; font-size: 8px; }
        .appreciation { margin-top: 4px; }
        .appreciation td { text-align: center; font-size: 8px; }
        .appreciation .text { margin-top: 2px; text-align: left; min-height: 12px; }
    </style>
</head>
<body>
    @php
        $formatNumber = fn (mixed $value): string => rtrim(rtrim(number_format((float) $value, 2, ',', ''), '0'), ',');
        $rankLabel = fn (?int $rank): string => $rank === null ? '—' : ($rank === 1 ? '1er' : $rank.'e');
    @endphp

    @foreach ($reports as $report)
        @php
            $result = $report['result'];
            $student = $result->enrollment?->student;
            $mentionArabic = $result->mention ? __($result->mention, [], 'ar') : null;
        @endphp

        <div @class(['page-break' => ! $loop->last])>
            <p class="school">{{ $establishment->name }}</p>
            <p class="year">Année scolaire : {{ $arabicComposition->schoolYear?->label }}</p>

            <table class="month">
                <tr><td class="ar">{{ pdf_rtl('شهر '.$arabicComposition->month->arabicLabel()) }}</td></tr>
                <tr><td>MOIS DE {{ \Illuminate\Support\Str::upper($arabicComposition->month->frenchLabel()) }}</td></tr>
            </table>

            <table class="identity">
                <tr>
                    <td>Nom et prénoms : <span class="value">{{ \Illuminate\Support\Str::upper(trim($student?->last_name.' '.$student?->first_name)) }}</span></td>
                    <td>Matricule : <span class="value">{{ $student?->student_number }}</span></td>
                    <td>Classe : <span class="value">{{ $arabicClassroom->name }}</span></td>
                </tr>
            </table>

            {{-- Colonnes dans l'ordre visuel du livret : français à gauche,
                 arabe à droite (dompdf n'inverse pas les colonnes). --}}
            <table>
                <thead>
                    <tr>
                        <th class="fr">MATIÈRES</th>
                        <th class="note">NOTES <span class="ar">الدرجات</span></th>
                        <th class="ar">المواد</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report['rows'] as $row)
                        <tr>
                            <td class="fr">{{ $row['frenchName'] }}</td>
                            <td class="note">{{ $row['isAbsent'] ? 'Abs' : ($row['score'] !== null ? $formatNumber($row['score']) : '') }}</td>
                            <td class="ar">{{ pdf_rtl($row['arabicName']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <table class="results">
                <tr>
                    <td class="fr">TOTAL</td>
                    <td class="value">{{ $result->total !== null ? $formatNumber($result->total) : '—' }}</td>
                    <td class="ar">{{ pdf_rtl('مجموع الدرجات') }}</td>
                </tr>
                <tr>
                    <td class="fr">Observation</td>
                    <td class="value"></td>
                    <td class="ar">الملاحظات</td>
                </tr>
                <tr>
                    <td class="fr">Moyenne</td>
                    <td class="value">{{ $result->average !== null ? $formatNumber($result->average).' / 10' : '—' }}</td>
                    <td class="ar">المعدل</td>
                </tr>
                <tr>
                    <td class="fr">Mention</td>
                    <td class="value">
                        {{ $result->mention ?? '—' }}
                        @if ($mentionArabic && $mentionArabic !== $result->mention)
                            <span class="ar">/ {{ pdf_rtl($mentionArabic) }}</span>
                        @endif
                    </td>
                    <td class="ar">التقدير</td>
                </tr>
                <tr>
                    <td class="fr">Rang</td>
                    <td class="value">{{ $rankLabel($result->rank) }}</td>
                    <td class="ar">الترتيب</td>
                </tr>
                <tr>
                    <td class="fr">Effectif</td>
                    <td class="value">{{ $headcount }}</td>
                    <td class="ar">{{ pdf_rtl('عدد التلاميذ') }}</td>
                </tr>
            </table>

            <table class="signatures">
                <tr>
                    <td>Visa des parents <span class="ar">{{ pdf_rtl('توقيع الأهل') }}</span></td>
                    <td>Le Maître <span class="ar">{{ pdf_rtl('توقيع المعلم') }}</span></td>
                </tr>
            </table>

            <table class="appreciation">
                <tr>
                    <td>
                        Appréciation du Maître <span class="ar">{{ pdf_rtl('تقدير المعلم') }}</span>
                        <div class="text">{{ $result->appreciation ?: '..........................................................................................' }}</div>
                    </td>
                </tr>
            </table>
        </div>
    @endforeach
</body>
</html>
