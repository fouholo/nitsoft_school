<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bulletin de notes</title>
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
        @page { margin: 1.2cm 1.4cm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 11px; color: #1e293b; line-height: 1.2; }
        /* Noto Naskh agrandit la ligne : sans line-height réduit, chaque rang
           du tableau devient trop haut. */
        .ar { font-family: 'NotoNaskhArabic', "DejaVu Sans", sans-serif; direction: rtl; line-height: 9px; }
        table { width: 100%; border-collapse: collapse; }
        .head td { border: none; vertical-align: top; text-align: center; font-size: 10px; padding: 1px; }
        .head .fr-col, .head .ar-col { width: 38%; }
        .head .logo-col { width: 24%; }
        .head img { height: 70px; }
        .head p { margin: 2px 0; }
        .head .bold { font-weight: bold; }
        hr.rule { border: none; border-top: 3px solid #15803d; margin: 8px 0; }
        .title { text-align: center; margin: 8px 0 10px; }
        .title p { margin: 3px 0; font-weight: bold; font-size: 13px; }
        .bordered td, .bordered th { border: 1px solid #475569; padding: 5px 7px; vertical-align: middle; }
        .bordered th { background-color: #f1f5f9; font-weight: bold; }
        td.fr { text-align: left; }
        td.ar, th.ar { text-align: right; }
        .center, td.ar.center { text-align: center; }
        .identity { margin-bottom: 8px; }
        .note { width: 16%; text-align: center; font-weight: bold; }
        .side { width: 20%; text-align: center; vertical-align: middle; }
        .side p { margin: 4px 0; }
        /* Pas de deux-points dans les textes arabes : dompdf les place du
           mauvais côté du mot. */
        .side .value { font-weight: bold; }
        .results { margin-top: 8px; }
        .results .value { width: 20%; text-align: center; font-weight: bold; }
        .place { text-align: right; margin: 10px 0 18px; }
        .signatures td { border: none; text-align: center; width: 33%; vertical-align: top; font-weight: bold; text-decoration: underline; height: 70px; }
        .signatures p { margin: 2px 0; }
    </style>
</head>
<body>
    @php
        $establishment = $reportCard->establishment;
        $enrollment = $reportCard->enrollment;
        $student = $enrollment->student;
        $term = $reportCard->arabicTerm;
        $level = $enrollment->arabicClassroom?->arabicLevel;
        $schoolYearLabel = $term->schoolYear?->label;
        $latinName = trim($student->last_name.' '.$student->first_name);
        $average = (float) $reportCard->average;
        $isAdmitted = $average >= 10;
        $total = $breakdown->sum(fn ($row) => (float) $row->average);
        $mention = $reportCard->appreciation;
        $mentionArabic = $mention ? __($mention, [], 'ar') : null;
        $formatNumber = fn (float $value): string => rtrim(rtrim(number_format($value, 2, ',', ''), '0'), ',');
        $rank = (int) $reportCard->rank;
    @endphp

    <table class="head">
        <tr>
            <td class="fr-col">
                <p class="bold">République de Côte d'Ivoire</p>
                <p>Union - Discipline - Travail</p>
                <p class="bold">{{ $establishment->name }}</p>
                <p>Franco-Arabe</p>
                <p>Année Scolaire : <span class="bold">{{ $schoolYearLabel }}</span></p>
            </td>
            <td class="logo-col">
                @if ($establishment->logo_path)
                    <img src="{{ public_path('storage/'.$establishment->logo_path) }}">
                @endif
            </td>
            <td class="ar-col">
                <p class="ar bold">{{ pdf_rtl('جمهورية كوت ديفوار') }}</p>
                <p class="ar">{{ pdf_rtl('اتحاد - نظام - عمل') }}</p>
                @if ($establishment->name_ar)
                    <p class="ar bold">{{ pdf_rtl($establishment->name_ar) }}</p>
                @endif
                <p class="ar">{{ pdf_rtl('للعام '.$schoolYearLabel) }}</p>
            </td>
        </tr>
    </table>

    <hr class="rule">

    <div class="title">
        <p class="ar">{{ pdf_rtl(trim('كشف الدرجات '.$level?->wording)) }}</p>
        <p>BULLETIN DE NOTES {{ $level?->wording_fr }}</p>
    </div>

    {{-- Colonnes dans l'ordre visuel : français à gauche, arabe à droite
         (dompdf n'inverse pas les colonnes). --}}
    <table class="bordered identity">
        <tr>
            <td class="fr" style="width: 18%;">Nom et Prénoms</td>
            <td class="fr" style="width: 32%; font-weight: bold;">{{ $latinName }}</td>
            <td class="ar" style="width: 32%; font-weight: bold;">{{ $student->arabic_name ? pdf_rtl($student->arabic_name) : $latinName }}</td>
            <td class="ar" style="width: 18%;">{{ pdf_rtl('الاسم واللقب') }}</td>
        </tr>
        <tr>
            <td class="fr" colspan="2">{{ $term->label }}</td>
            <td class="ar" colspan="2">{{ $term->label_ar ? pdf_rtl($term->label_ar) : $term->label }}</td>
        </tr>
    </table>

    <table class="bordered">
        <thead>
            <tr>
                <th class="side"></th>
                <th>MATIERES</th>
                <th class="note">NOTE <span class="ar">الدرجات</span></th>
                <th class="ar">{{ pdf_rtl('المواد الدراسية') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($breakdown as $row)
                <tr>
                    @if ($loop->first)
                        <td class="side" rowspan="{{ $breakdown->count() }}">
                            <p class="ar">{{ pdf_rtl('عدد الطلبة') }}</p>
                            <p>Effectif : <span class="value">{{ $headcount }}</span></p>
                            <p>&nbsp;</p>
                            <p class="ar">{{ pdf_rtl('النتيجة '.($isAdmitted ? 'ناجح' : 'راسب')) }}</p>
                            <p>Résultat : <span class="value">{{ $isAdmitted ? 'Admis' : 'Non admis' }}</span></p>
                        </td>
                    @endif
                    <td class="fr">{{ $row->subject->name_fr }}</td>
                    <td class="note">{{ $row->average !== null ? $formatNumber($row->average) : '—' }}</td>
                    <td class="ar">{{ pdf_rtl($row->subject->name) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="center">Aucune note disponible pour cette période.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="bordered results">
        <tr>
            <td class="fr" style="width: 15%;">Moyenne</td>
            <td class="value">{{ $formatNumber($average) }}</td>
            <td class="ar" style="width: 15%;">المعدل</td>
            <td class="fr" style="width: 15%;">Total</td>
            <td class="value">{{ $formatNumber($total) }}</td>
            <td class="ar" style="width: 15%;">المجموع</td>
        </tr>
        <tr>
            <td class="fr">Rang</td>
            <td class="value">{{ $rank === 1 ? '1er' : $rank.'e' }}</td>
            <td class="ar">الترتيب</td>
            <td class="fr">Mention</td>
            <td class="value">
                {{ $mention ?? '—' }}
                @if ($mentionArabic && $mentionArabic !== $mention)
                    <span class="ar">/ {{ pdf_rtl($mentionArabic) }}</span>
                @endif
            </td>
            <td class="ar">التقدير</td>
        </tr>
    </table>

    <p class="place">
        {{ $establishment->city ? $establishment->city.', le' : 'Le' }}
        {{ now()->locale('fr')->translatedFormat('j F Y') }}
    </p>

    <table class="signatures">
        <tr>
            <td><p>Directeur général</p><p class="ar">{{ pdf_rtl('المدير العام') }}</p></td>
            <td><p>Cachet Officiel</p><p class="ar">{{ pdf_rtl('الختم الرسمي') }}</p></td>
            <td><p>Président du Jury</p><p class="ar">{{ pdf_rtl('رئيس لجنة الامتحان') }}</p></td>
        </tr>
    </table>
</body>
</html>
