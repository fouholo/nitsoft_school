# Filtres sur la liste des élèves d'une classe (PDF)

*(Design validé par l'utilisateur le 2026-08-21.)*

## Contexte

La liste des élèves d'une classe (`ClassroomStudentListPdfController`, `pdf/classroom-student-list.blade.php`), générée depuis Listes/Rapports, liste aujourd'hui tous les élèves actifs de la classe, triés par nom/prénom. L'utilisateur veut pouvoir en réduire le périmètre à un sous-ensemble avant génération, via des filtres combinables (et non un ré-ordonnancement — le mot "tri" employé initialement désignait en réalité des filtres) :

- **Sexe** — Masculin / Féminin.
- **Statut** — Affecté / Non affecté (secondaire uniquement).
- **Redoublement** — Redoublant / Non redoublant.
- **Bourse** — Boursier / Non boursier (secondaire uniquement).

Tous les champs nécessaires existent déjà, aucune migration :
- `Student.gender` (`'m'`/`'f'`/`null`).
- `Enrollment.is_assigned`, `Enrollment.is_repeating`, `Enrollment.is_scholarship` (booléens).
- `Establishment.type` (enum `EstablishmentType::Secondaire`/`PrescolairePrimaire`) détermine si "Statut" et "Bourse" sont pertinents.

Périmètre : uniquement le bouton "Liste des élèves (PDF)" du bloc "Liste des élèves d'une classe". Le bouton "Cartes d'identité scolaires (PDF)" du même bloc n'est pas concerné.

## 1. `app/Livewire/Reports/Index.php`

Quatre nouvelles propriétés publiques, valeurs `''` (Tous) par défaut, combinables (indépendantes) :

```php
public string $genderFilter = '';       // '', 'm', 'f'
public string $assignedFilter = '';     // '', '1', '0'
public string $repeatingFilter = '';    // '', '1', '0'
public string $scholarshipFilter = '';  // '', '1', '0'
```

`mount()` résout l'établissement courant (`Establishment::findOrFail((int) app('currentEstablishmentId'))`) et calcule `$this->isSecondaire = $establishment->type === EstablishmentType::Secondaire;` (propriété publique, lue par la vue pour afficher ou non "Statut"/"Bourse").

## 2. Vue `resources/views/livewire/reports/index.blade.php`

Dans le bloc "Liste des élèves d'une classe", sous la grille "Année scolaire"/"Classe" existante, une seconde grille de selects (`wire:model.live`) : Sexe (toujours), Redoublement (toujours), et Statut/Bourse dans un `@if ($isSecondaire)`.

Le lien "Liste des élèves (PDF)" passe les 4 filtres en query string :

```blade
<a href="{{ route('reports.classroom-students-pdf', [
    'classroom' => $classroom_id,
    'gender' => $genderFilter,
    'assigned' => $assignedFilter,
    'repeating' => $repeatingFilter,
    'scholarship' => $scholarshipFilter,
]) }}" target="_blank">Liste des élèves (PDF)</a>
```

Le lien "Cartes d'identité scolaires (PDF)" reste inchangé (pas de filtres transmis).

## 3. `ClassroomStudentListPdfController`

La requête des élèves passe de `pluck('student')` direct à des filtres appliqués sur `Enrollment`/`Student` avant résolution :

```php
$genderFilter = $request->query('gender');
$assignedFilter = $request->query('assigned');
$repeatingFilter = $request->query('repeating');
$scholarshipFilter = $request->query('scholarship');

$students = $classroom->enrollments()
    ->where('status', 'active')
    ->when($genderFilter, fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('gender', $genderFilter)))
    ->when($assignedFilter !== null && $assignedFilter !== '', fn ($q) => $q->where('is_assigned', $assignedFilter === '1'))
    ->when($repeatingFilter !== null && $repeatingFilter !== '', fn ($q) => $q->where('is_repeating', $repeatingFilter === '1'))
    ->when($scholarshipFilter !== null && $scholarshipFilter !== '', fn ($q) => $q->where('is_scholarship', $scholarshipFilter === '1'))
    ->with('student')
    ->get()
    ->pluck('student')
    ->sortBy([['last_name', 'asc'], ['first_name', 'asc']])
    ->values();
```

Aucun changement de mise en page du PDF (`pdf/classroom-student-list.blade.php` déjà correct — colonne "Sexe" déjà affichée, numérotation `$loop->iteration` déjà relative à la collection reçue donc automatiquement correcte sur le sous-ensemble filtré).

## 4. Tests

`tests/Feature/Http/ClassroomStudentListPdfTest.php` (existant, étendu) :
- Filtre sexe seul (m/f) exclut l'autre sexe.
- Filtre statut seul (affecté/non affecté) — établissement secondaire.
- Filtre redoublement seul.
- Filtre bourse seul — établissement secondaire.
- Deux filtres combinés (ex. sexe + redoublement) — intersection correcte.
- Aucun filtre → comportement actuel inchangé (tous les élèves actifs).
- Filtre appliqué à un élève sans correspondance → message "Aucun élève inscrit dans cette classe." (même état vide qu'une classe réellement vide, réutilise le `@empty` existant du blade).

`tests/Feature/Livewire/Reports/IndexTest.php` (existant, étendu) :
- "Statut"/"Bourse" visibles pour un établissement secondaire, absents pour préscolaire/primaire.
- "Sexe"/"Redoublement" toujours visibles.
- Le lien PDF reflète les filtres sélectionnés (query string).

## Vérification

1. `vendor/bin/pest` — suite complète verte.
2. `vendor/bin/phpstan analyse --memory-limit=512M` — clean.
3. Vérification manuelle (environnement scratch SQLite) : classe avec élèves de profils variés (sexe/statut/redoublement/bourse mixtes), filtres combinés, établissement secondaire vs préscolaire/primaire (menus masqués), rendu PDF inchangé visuellement hors sous-ensemble.
4. Commit puis mise à jour de la mémoire projet.
