# Bulletin PDF dédié au primaire (A5)

*(Design validé par l'utilisateur le 2026-08-16, à partir d'un exemple réel de « Relevé de notes » fourni par l'utilisateur.)*

## Contexte

Le PDF de bulletin (`resources/views/pdf/report-card.blade.php`) est aujourd'hui partagé entre primaire et secondaire, au format A4. L'utilisateur veut un **gabarit dédié au primaire, au format A5**, calqué sur un exemple réel de « Relevé de notes » utilisé dans son établissement. Le secondaire garde son gabarit A4 actuel — chantier séparé, non traité ici.

## Partie 1 — Sélection du gabarit

`ReportCardPdfController::__invoke()` (`app/Http/Controllers/Grading/ReportCardPdfController.php`) choisit la vue selon le cycle du niveau de la classe :

```php
$reportCard->loadMissing(['student', 'classroom.level', 'establishment.inspection.direction', 'term.schoolYear', 'schoolYear']);

$isPrimaireStyle = $reportCard->classroom->level->cycle !== \App\Domain\Academics\Enums\Cycle::Secondaire;

$view = $isPrimaireStyle ? 'pdf.report-card-primaire' : 'pdf.report-card';
$paper = $isPrimaireStyle ? 'a5' : 'a4';

$viewData = ['reportCard' => $reportCard];

if ($isPrimaireStyle) {
    $viewData['rows'] = $reportCardService->primaryGradeRows($reportCard);
    $viewData['generalInformation'] = \App\Domain\Establishments\Models\GeneralInformation::current();
} else {
    $viewData['breakdown'] = $reportCardService->subjectBreakdown($reportCard);
}

$pdf = Pdf::loadView($view, $viewData)->setPaper($paper);
```

Test « au sens large » (`!== Secondaire` plutôt qu'un `=== Primaire` strict) : couvre aussi un éventuel bulletin préscolaire par la même mise en page, sans y penser explicitement — aucune classe préscolaire n'est notable aujourd'hui (`Classroom::isGradable()`), donc ce cas ne se présente pas en pratique, mais le gabarit A4 secondaire n'aurait de toute façon aucun sens pour elle si un jour la règle changeait.

## Partie 2 — Notes brutes par matière : `ReportCardService::primaryGradeRows()`

Le `subjectBreakdown()` existant renvoie une moyenne déjà normalisée sur 20 (`weightedAverage()`) — inadapté pour afficher « note / barème » brut (ex. « 46 / 50 »). Nouvelle méthode publique, à côté de `subjectBreakdown()` :

```php
/**
 * Notes brutes d'une composition primaire, une ligne par matière — pour le
 * bulletin A5 (contrairement à subjectBreakdown(), pas de normalisation :
 * la note et le barème bruts de chaque matière sont affichés tels quels).
 *
 * @return Collection<int, PrimaryGrade>
 */
public function primaryGradeRows(ReportCard $reportCard): Collection
{
    return PrimaryGrade::query()
        ->with('primarySubject')
        ->where('student_id', $reportCard->student_id)
        ->whereNotNull('score')
        ->whereHas('gradeSheet', fn ($query) => $query->where('composition_number', $reportCard->composition_number))
        ->get()
        ->sortBy(fn (PrimaryGrade $grade) => $grade->primarySubject->name)
        ->values();
}
```

(Même filtrage que la branche primaire de `subjectBreakdown()`, sans le calcul de moyenne pondérée.)

## Partie 3 — Gabarit `pdf.report-card-primaire` (A5)

**En-tête** : `@include('pdf.partials.reports-header', ['establishment' => $reportCard->establishment, 'generalInformation' => $generalInformation])` — réutilisé tel quel.

**Titre** : `RELEVE DE NOTES - COMPOSITION N°{{ $reportCard->composition_number }}`, centré.

**Bloc identité** (civilité genrée à partir de `Student.gender`) :

```php
$civilite = match ($reportCard->student->gender) {
    'f' => 'Mademoiselle',
    'm' => 'Monsieur',
    default => "L'élève",
};
```

Affiche civilité + nom complet, date/lieu de naissance, matricule, « Cours : {{ $reportCard->classroom->name }} » puis « a obtenu : ». Pas de champ « N° de Table » ni « Établissement » (déjà dans l'en-tête).

**Tableau « a- Notes »** (colonnes Épreuve | Coefficient | Note | Appréciation), une ligne par élément de `$rows`. Les notes acceptant des demi-points (pas de `0.5` à la saisie), l'affichage doit garder la précision réelle sans imposer de décimales artificielles (ex. « 8.5 » mais « 46 », pas « 46.00 ») — via un petit helper de formatage local au gabarit :

```blade
@php
    $formatNumber = fn (float $value): string => rtrim(rtrim(number_format($value, 2), '0'), '.');
@endphp

@foreach ($rows as $grade)
    @php
        $subject = $grade->primarySubject;
        $bareme = $subject->bareme($reportCard->classroom->level) ?? 20.0;
        $coefficient = $subject->coefficientFor($reportCard->classroom->level) ?? 0.0;
    @endphp
    <tr>
        <td>{{ $subject->name }}</td>
        <td>{{ $formatNumber($coefficient) }}</td>
        <td>{{ $formatNumber((float) $grade->score) }} / {{ $formatNumber($bareme) }}</td>
        <td>{{ \App\Domain\Grading\Models\AppreciationScale::forAverage((float) $grade->score, $bareme)?->appreciation }}</td>
    </tr>
@endforeach
```

Ligne Total : `$formatNumber($rows->sum(fn ($g) => $g->primarySubject->coefficientFor($reportCard->classroom->level) ?? 0.0))` pour les coefficients, et somme brute des notes / somme brute des barèmes (`$rows->sum(fn ($g) => (float) $g->score)` / `$rows->sum(fn ($g) => $g->primarySubject->bareme($reportCard->classroom->level) ?? 20.0)`, chacune passée à `$formatNumber`) — **sans pondération par coefficient**, conforme à l'exemple.

**Tableau « b- Résultats »** (colonnes Moyenne | Rang | Résultat | Appréciation) :

```php
$resultat = match (true) {
    $reportCard->average === null => 'Absence',
    (float) $reportCard->average >= $reportCard->classroom->level->compositionAverageScale() / 2 =>
        match ($reportCard->student->gender) { 'f' => 'Admise', 'm' => 'Admis', default => 'Admis(e)' },
    default =>
        match ($reportCard->student->gender) { 'f' => 'Refusée', 'm' => 'Refusé', default => 'Refusé(e)' },
};
```

Moyenne affichée sans suffixe d'échelle (`{{ $reportCard->average }}`, comme l'exemple), Rang = `$reportCard->rank`, Appréciation = `$reportCard->appreciation` (déjà stocké).

**Pied de page** : « Fait à {{ $reportCard->establishment->address }}, le {{ now()->locale('fr')->translatedFormat('j F Y') }} ». Bloc 3 colonnes :

- **Visa maître(sse)** : nom du `TeacherAssignment` « classe entière » (`subject_id` null) de la classe — `TeacherAssignment::where('classroom_id', $reportCard->classroom_id)->whereNull('subject_id')->first()?->teacher?->name`, vide si aucune affectation.
- **Visa directeur(trice)** : `$reportCard->establishment->director()?->name` (même source que le `director-stamp` actuel).
- **Visa du parent** : vide (case à signer à la main).

## Tests à écrire/adapter

- `tests/Feature/Http/ReportCardPdfTest.php` : nouveau groupe de tests pour le bulletin primaire — gabarit A5 chargé pour une classe primaire (vs A4 pour secondaire, non-régression), civilité/résultat genrés selon `Student.gender` (f/m/vide), tableau des notes avec note/barème bruts et appréciation par matière, ligne Total non pondérée par coefficient, Visa maître(sse) rempli quand une affectation classe entière existe et vide sinon, Visa directeur(trice) rempli.
- `tests/Feature/Domain/ReportCardServiceTest.php` : `primaryGradeRows()` retourne les notes brutes triées par nom de matière, exclut les notes non renseignées (`score` null, ex. absence).

## Vérification

1. `vendor/bin/pest` — suite complète verte.
2. `vendor/bin/phpstan analyse --memory-limit=512M` — clean.
3. Vérification manuelle sur les données WAMP existantes : générer le PDF d'un bulletin primaire déjà existant (classe CP1 ou CE1) et comparer visuellement au format A5 avec l'exemple fourni ; générer un bulletin secondaire et vérifier qu'il reste au format A4 inchangé.
