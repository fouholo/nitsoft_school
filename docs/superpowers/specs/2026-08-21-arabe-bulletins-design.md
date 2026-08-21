# Filière arabe — sous-chantier 3/3 : bulletins arabes (PDF RTL) — design

*Statut : approuvé par l'utilisateur le 2026-08-21.*

## Contexte

Dernier sous-chantier du chantier « filière arabe » (voir `2026-08-21-arabe-fondations-design.md`) :

1. Fondations arabe — terminé, committé (`1cf6619`).
2. Affectation des enseignants arabes + saisie des notes — terminé, committé (`8e558f6`).
3. **Bulletins arabes (génération + PDF en écriture arabe RTL)** — ce document.

Les deux premiers sous-chantiers sont committés localement (non poussés) et migrés/vérifiés sur la base réelle.

## Spike technique préalable : rendu RTL avec dompdf

Avant toute conception, un risque technique a été identifié et testé : dompdf (moteur PDF déjà utilisé dans tout le projet, via `barryvdh/laravel-dompdf` v3.1.2 / `dompdf/dompdf` v3.1.6) est réputé ne pas faire de liaison contextuelle des lettres arabes (« text shaping »).

**Résultat du spike** (deux PDF de test générés et inspectés visuellement, avec la police Noto Naskh Arabic — licence SIL Open Font License, libre de redistribution) :

- **Contrairement à l'hypothèse de départ**, dompdf 3.1.6 gère nativement la liaison des lettres arabes et l'ordre RTL correct à partir de texte UTF-8 brut (logique), avec simplement `direction: rtl` en CSS et une police arabe embarquée. **Aucune bibliothèque de reshaping n'est nécessaire** — un test avec la bibliothèque `khaled.alshamaa/ar-php` (`utf8Glyphs()`) a même produit un résultat **dégradé** (l'ordre des mots devient incorrect, car le texte est alors doublement retraité) : cette bibliothèque a été retirée après le test, elle n'apparaît dans aucune dépendance du projet.
- **Piège réel découvert** : chaque graisse de police utilisée (normal, gras...) doit avoir son propre `@font-face` explicite pointant vers un fichier de police contenant les glyphes arabes. Sans le `@font-face` gras, dompdf substitue une police sans glyphes arabes pour le texte en gras et affiche des `?` à la place des lettres. Confirmé en embarquant `NotoNaskhArabic-Regular.ttf` + `NotoNaskhArabic-Bold.ttf`.
- Le texte mixte (matières en arabe, notes numériques `18/20`) s'affiche correctement : les nombres et les segments latins restent dans le bon ordre au sein du texte RTL.

**Conséquence pour la conception** : pas de dépendance Composer supplémentaire, juste deux fichiers de police à embarquer dans le dépôt (licence libre) et un `@font-face` par graisse dans le gabarit PDF.

## Décisions validées

1. **`ArabicReportCard`** : modèle séparé du `ReportCard` français (deux bulletins distincts par élève, comme décidé en sous-chantier 1) — `establishment_id`, `enrollment_id` (pas `student_id`, cohérent avec `ArabicGrade`), `arabic_term_id`, `average`, `rank`, `appreciation`, `generated_at`. Pas de `pdf_path` : ce champ existe sur `ReportCard` mais n'est en réalité jamais utilisé (le PDF est toujours rendu à la volée, jamais stocké — voir `ReportCardPdfController`) ; ne pas reproduire ce champ mort. `TenantScoped`, pas `Syncable` (comme `ReportCard`). Rang calculé parmi les élèves du même groupe arabe (niveau/série), pas de la classe française.
2. **Calcul de moyenne** : `ArabicReportCardService::generate()` — un seul calcul unifié, **sans branchement `Cycle::Primaire`/`Secondaire`** (contrairement à `ReportCardService` français), puisque les coefficients arabes passent toujours par la table de jointure `ArabicSubjectCoefficient`. Réutilise `AppreciationScale::forAverage($average, 20.0)` tel quel (échelle toujours 20 — pas de notion CP1/CP2/CE1 côté arabe).
3. **Génération** : réservée à fondateur/directeur/gestionnaire (via `currentRole()`, pas `isAdminOfCurrentEstablishment()` — lacune fondateur-établissement-indépendant déjà connue) **+ educateur**, comme `ReportCardPolicy` côté français (`RolePermissions::MATRIX['report_cards.generate']`).
4. **PDF** : gabarit unique `pdf.arabic-report-card` (pas de split Secondaire/Primaire, cohérent avec la décision 2). En-tête établissement (ministère, direction, nom d'établissement) reste en français/LTR — seul le contenu pédagogique arabe (noms de matières, tableau des notes) passe en `dir="rtl"`.
5. **Police** : Noto Naskh Arabic (SIL OFL), stockée dans `resources/fonts/arabic/` avec son fichier de licence, embarquée en normal + gras via `@font-face`.

## 1. Modèle (`App\Domain\Arabic\Models\ArabicReportCard`)

```php
Schema::create('arabic_report_cards', function (Blueprint $table) {
    $table->id();
    $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
    $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
    $table->foreignId('arabic_term_id')->constrained()->cascadeOnDelete();
    $table->decimal('average', 5, 2)->nullable();
    $table->unsignedInteger('rank')->nullable();
    $table->string('appreciation')->nullable();
    $table->timestamp('generated_at')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->unique(['enrollment_id', 'arabic_term_id'], 'arabic_report_cards_unique');
    $table->index(['establishment_id', 'arabic_term_id']);
});
```

Contrairement aux autres tables arabes établissement-scopées (coefficients, grilles de notes), une contrainte unique réelle est possible ici : `enrollment_id` et `arabic_term_id` sont tous deux non-nullables (pas le problème NULL≠NULL rencontré ailleurs).

## 2. `ArabicReportCardService`

```php
class ArabicReportCardService
{
    /** @return Collection<int, ArabicReportCard> */
    public function generate(ArabicLevel $level, ?ArabicSerie $serie, ArabicTerm $term): Collection;

    /** @return Collection<int, SubjectAverage> */
    public function subjectBreakdown(ArabicReportCard $reportCard): Collection;
}
```

`generate()` : roster = `Enrollment` de l'établissement × année scolaire courante dont `arabic_level_id`/`arabic_serie_id` correspondent (comme `ArabicGradeSheets\Enter`). Notes = `ArabicGrade` liées via `ArabicGradeSheet.arabic_term_id = $term->id`. Moyenne pondérée par matière (poids de chaque `ArabicGradeSheet`) puis par coefficient (`ArabicSubjectCoefficient`), rang par ex-aequo — même algorithme que `ReportCardService::generalAverage()`/`weightedAverage()`, sans le branchement cycle. Erreur de validation si un coefficient manque pour une matière notée (même message que le français, adapté).

`subjectBreakdown()` : détail par matière à la volée pour l'affichage PDF (moyenne, coefficient, enseignant), non persisté.

## 3. PDF

`resources/fonts/arabic/NotoNaskhArabic-{Regular,Bold}.ttf` + `LICENSE.txt` (SIL OFL, texte de licence de Google Fonts).

`resources/views/pdf/arabic-report-card.blade.php` : structure miroir de `pdf/report-card.blade.php` — en-tête via le partial `reports-header` existant (français), méta élève, tableau des matières en `dir="rtl"` avec les deux `@font-face` (normal/gras), résumé moyenne/rang/appréciation, pied de page (partial `director-stamp` réutilisé tel quel).

`App\Http\Controllers\Arabic\ArabicReportCardPdfController` : mirroring `ReportCardPdfController` — `Gate::authorize('view', $arabicReportCard)`, rendu à la volée via `Barryvdh\DomPDF\Facade\Pdf`, paper A4, inline par défaut / `?download=1`, nom de fichier `Str::slug`.

## 4. Écrans et autorisations

**`Livewire\Arabic\ReportCards\Index`** : formulaire niveau arabe / série arabe (si `requires_series`) / période arabe → bouton « Générer », appelle `ArabicReportCardService::generate()`. Liste des bulletins générés pour la sélection courante, triée par rang, avec lien « Voir le PDF » par ligne (visible si `view` autorisé).

**`ArabicReportCardPolicy`** :
- `viewAny`/`view` = membre établissement `is_arabe` (pattern identique aux autres policies arabes).
- `create` = `in_array($user->currentRole(), ['fondateur', 'directeur', 'gestionnaire'], true) || RolePermissions::can($user->currentRole(), 'report_cards.generate')`.

**Navigation** : entrée « Bulletins » ajoutée au groupe « Arabe » existant (`arabic.report-cards.index`).

**Routes** (`routes/arabic.php`) : `arabic.report-cards.index`, `arabic.report-cards.pdf`.

**Préfixe uid** : `ArabicReportCard` n'est pas `Syncable` (comme `ArabicTeacherAssignment`) — pas de préfixe à allouer.

## 5. Tests

- **Domaine** (`ArabicReportCardTest.php`) : moyenne générale pondérée par coefficient sur un groupe arabe multi-classes-françaises ; rang par ex-aequo ; erreur si coefficient manquant ; `subjectBreakdown()` ; aucun branchement cycle nécessaire (un seul chemin de calcul testé, pas de dataset Primaire/Secondaire).
- **Livewire** (`Arabic\ReportCards\IndexTest.php`) : génération par fondateur/directeur/gestionnaire/educateur ; 403 pour enseignant et pour caissier ; 403 pour établissement non-`is_arabe` ; régression fondateur d'établissement indépendant (sans Foundation).
- **PDF** (`ArabicReportCardPdfTest.php`, rendu de vue directe comme `ClassroomStudentListPdfTest`) : présence des noms de matières arabes et de `dir="rtl"` dans le HTML rendu ; présence des deux `@font-face` (normal + gras) ; téléchargement vs affichage inline selon `?download=1`.

## Hors périmètre

- Notion d'appréciation arabe distincte — réutilise `AppreciationScale` tel quel.
- Export/impression groupée de tous les bulletins d'un groupe en un seul PDF — un bulletin = un PDF, comme le français.
- Traduction en arabe des informations d'établissement (ministère, direction, nom d'établissement) — reste en français/LTR dans l'en-tête ; seul le contenu pédagogique arabe passe en RTL.
- Présences spécifiques à la filière arabe, regroupement des matières arabes par domaine — évoqués comme pistes possibles en sous-chantier 1, non planifiés.

Ceci clôt la décomposition initiale du chantier « filière arabe » en 3 sous-chantiers.
