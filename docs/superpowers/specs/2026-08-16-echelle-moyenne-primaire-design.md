# Échelle de la moyenne générale selon le niveau (primaire)

*(Design validé par l'utilisateur le 2026-08-16.)*

## Contexte

La moyenne générale d'une composition primaire (affichée dans le cadre « Résultats » de l'écran de saisie `EnterStudent`, et enregistrée sur `ReportCard.average` à la génération officielle) est aujourd'hui toujours calculée sur 20, quel que soit le niveau. L'utilisateur précise que ce n'est pas le cas dans son établissement : pour **CP1, CP2, CE1**, la moyenne générale d'une composition doit être sur **10** ; pour **CE2, CM1, CM2** (et le secondaire, inchangé), elle reste sur **20**.

Cette clarification affine une décision antérieure de la même journée (seuil de réussite « 5/10 pour CP1/CP2/CE1, 10/20 pour CE2/CM1/CM2 », alors interprétée comme la même fraction 50 % sur une échelle uniforme /20) : ce n'est pas seulement le seuil qui change, c'est l'échelle de présentation de la moyenne elle-même.

Point clé validé pendant le brainstorming : il n'existe pas de notion de « moyenne par matière » au primaire — seulement des notes par matière (une composition = une seule évaluation par matière, pas d'accumulation sur une période comme au secondaire). Le détail par matière (`ReportCardService::subjectBreakdown()`, colonne « Moyenne / 20 » du PDF) n'est donc **pas concerné** par ce changement d'échelle et reste inchangé, toujours calculé/affiché sur 20. Seule la moyenne générale (l'agrégat pondéré par coefficient, tous sujets confondus) change d'échelle selon le niveau.

## Partie 1 — `Level::compositionAverageScale()`

Nouvelle méthode publique sur `App\Domain\Academics\Models\Level` :

```php
public function compositionAverageScale(): float
{
    return in_array($this->level, ['CP1', 'CP2', 'CE1'], true) ? 10.0 : 20.0;
}
```

Retourne 20.0 pour CE2/CM1/CM2 et pour tous les niveaux secondaire (aucun n'est dans la liste CP1/CP2/CE1) — un seul point de vérité, pas de branchement par cycle nécessaire ailleurs.

## Partie 2 — Calcul de la moyenne générale

Le calcul interne (normalisation de chaque note sur 20 via le barème de la matière, pondération par coefficient) **ne change pas** — c'est la seule façon cohérente de combiner des matières à barèmes différents. Une fois la moyenne générale obtenue sur 20 (comme aujourd'hui), elle est ramenée à l'échelle du niveau par une multiplication finale :

```php
$scale = $classroom->level->compositionAverageScale(); // 10.0 ou 20.0
$average = $average20 !== null ? round($average20 * ($scale / 20), 2) : null;
```

**`ReportCardService::generalAverage()`** (`app/Domain/Grading/Services/ReportCardService.php`) : le calcul actuel (`$weightedSum / $totalCoefficient`) produit `$average20`. Appliquer la mise à l'échelle juste avant le `return`, avec `$scale` dérivé de `$classroom` (déjà un paramètre de la méthode).

**`EnterStudent::preview()`** (`app/Livewire/Grading/GradeSheets/Primaire/EnterStudent.php`) : même traitement — `$totalPoints / $totalCoefficient` produit `$average20`, mis à l'échelle via `$this->classroom->level->compositionAverageScale()` avant d'être retourné dans `'average'`.

`subjectBreakdown()`/`weightedAverage()`/`maxScoreFor()` **ne changent pas** — le détail par matière reste sur 20 dans tous les cas (primaire comme secondaire), conformément au point clé ci-dessus.

## Partie 3 — Seuil de réussite (Admis(e)/Refusé(e))

La constante `PASSING_AVERAGE = 10.0` sur `EnterStudent` est remplacée par un calcul relatif à l'échelle : `$scale / 2` (toujours 50 %, couvre 5/10 et 10/20 sans cas particulier) :

```php
$passingAverage = $scale / 2;
'result' => match (true) {
    $average === null => 'Absence',
    $average >= $passingAverage => 'Admis(e)',
    default => 'Refusé(e)',
},
```

## Partie 4 — `AppreciationScale::forAverage()`

Signature étendue avec un second paramètre `$scale = 20.0` :

```php
public static function forAverage(float $average, float $scale = 20.0): ?self
{
    $percentage = ($average / $scale) * 100;

    return static::query()
        ->where('percentage', '<=', $percentage)
        ->orderByDesc('percentage')
        ->first();
}
```

La table de barème elle-même (en pourcentages) reste inchangée — elle s'applique telle quelle à n'importe quelle échelle. Les deux points d'appel (`EnterStudent::preview()`, `ReportCardService::generate()`) passent désormais `$scale` en plus de `$average`.

## Partie 5 — Affichage

Trois libellés « Moyenne / 20 » codés en dur deviennent dynamiques :

- `resources/views/livewire/grading/grade-sheets/primaire/enter-student.blade.php` : label du cadre Résultats → `Moyenne / {{ $scale }}` (nouvelle variable `scale` passée par `EnterStudent::render()`, dérivée de `$this->classroom->level->compositionAverageScale()`).
- `resources/views/pdf/report-card.blade.php` : ligne « Moyenne générale » du récapitulatif → `{{ number_format(...) }} / {{ $reportCard->classroom->level->compositionAverageScale() }}` (calculable directement dans le template, `ReportCard.classroom_id` étant toujours renseigné). La colonne par-matière « Moyenne / 20 » du tableau détaillé **reste inchangée** (cf. Partie 2).
- `resources/views/livewire/grading/report-cards/primaire/index.blade.php` : en-tête de colonne « Moyenne / 20 » → dynamique selon le niveau de la classe sélectionnée (`$classroom_id`), calculable côté composant (`Classroom::find($this->classroom_id)?->level?->compositionAverageScale()`) et transmis à la vue.

Le secondaire (`report-cards/secondaire/index.blade.php`) n'est pas touché — toujours /20 par construction (`compositionAverageScale()` y retourne systématiquement 20.0).

## Partie 6 — Tests

**Point d'attention** : `Classroom::factory()->primaire()` assigne un niveau primaire en **round-robin** (`LevelFactory::PRIMAIRE_LEVELS = ['CP1','CP2','CE1','CE2','CM1','CM2']`, compteur statique incrémenté à chaque appel), pas un niveau fixe. Les tests existants qui vérifient une valeur de moyenne précise (`ReportCardServiceTest`, `PrimaireEnterStudentTest`, `ReportCards\PrimaireTest`) doivent désormais **fixer explicitement** le niveau de la classe (`Level::where('level', 'CM2')->first()` ou équivalent) plutôt que de laisser le round-robin décider — sans quoi une même assertion (`toBe(13.0)`) casserait selon l'ordre d'exécution une fois l'échelle introduite.

Nouveaux tests à ajouter :
- `EnterStudent` : un scénario CP1 (échelle 10, seuil 5, moyenne affichée « / 10 ») et un scénario CM2 (échelle 20, comportement actuel inchangé).
- `ReportCardServiceTest` : `generalAverage()` produit la bonne moyenne sur 10 pour un niveau CP1/CP2/CE1, sur 20 pour un niveau CE2/CM1/CM2, à partir des mêmes notes brutes.
- `AppreciationScaleTest`/`ReportCardServiceTest` : `forAverage()` avec `$scale = 10.0` retourne la tranche correcte (ex : moyenne 8/10 = 80 % → même tranche qu'une moyenne 16/20).

## Vérification

1. `vendor/bin/pest` — suite complète verte (y compris les tests existants adaptés pour fixer le niveau).
2. `vendor/bin/phpstan analyse --memory-limit=512M` — clean.
3. Vérification manuelle sur les données WAMP existantes : noter un élève en CP1/CP2/CE1 et vérifier que la moyenne affichée est sur 10 avec le bon résultat/appréciation ; noter un élève en CE2/CM1/CM2 (ou classe déjà utilisée aujourd'hui) et vérifier qu'aucun comportement ne change (toujours /20).
