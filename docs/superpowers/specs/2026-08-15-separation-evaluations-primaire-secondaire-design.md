# Séparation des écrans Évaluations/Bulletins par cycle (primaire vs secondaire)

*(Design validé par l'utilisateur le 2026-08-15, en deux temps : approche générale puis correction du modèle mono-cycle.)*

## Contexte

Aujourd'hui, un seul écran Livewire gère les évaluations (`Grading\GradeSheets\Index`) et un seul gère les bulletins (`Grading\ReportCards\Index`) pour les deux cycles (préscolaire/primaire vs secondaire), avec des champs conditionnels partout : période OU n° de composition, devoir/interrogation OU composition, filtrage de matières selon le cycle sélectionné. Comme un établissement n'a en pratique qu'un seul cycle de classes (voir Partie 0), toute cette complexité conditionnelle ne sert jamais qu'une seule branche pour un établissement donné — elle alourdit l'écran sans jamais être utile à l'utilisateur qui le remplit. Objectif : scinder ces deux écrans en versions dédiées par cycle, plus simples et plus directes ("plus opérationnel").

**Découverte en cours d'analyse** : rien dans le code n'empêche aujourd'hui un établissement de posséder des classes de plusieurs cycles à la fois (`Classrooms\Index` propose librement les 3 cycles sans filtrer selon `Establishment::type`). Le modèle conceptuel (`PRODUCT.md`, `TermPolicy`) suppose pourtant un établissement mono-cycle. Vérification sur les données réelles de la base WAMP : aucun établissement existant n'a de classes multi-cycles — l'invariant est respecté en pratique, juste pas appliqué en code. Décision utilisateur : combler ce gap maintenant (Partie 0), ce qui rend la Partie 1/2 (dispatch par `establishment.type`) valide.

## Partie 0 — Prérequis : garantir le mono-cycle par établissement

### `Establishment` (app/Domain/Establishments/Models/Establishment.php)

Nouvelles méthodes :

```php
public function isPrescolairePrimaire(): bool
{
    return $this->type === EstablishmentType::PrescolairePrimaire;
}

public function isSecondaire(): bool
{
    return $this->type === EstablishmentType::Secondaire;
}

/**
 * @return array<int, Cycle>
 */
public function allowedCycles(): array
{
    return $this->isPrescolairePrimaire()
        ? [Cycle::Prescolaire, Cycle::Primaire]
        : [Cycle::Secondaire];
}
```

Petit nettoyage associé : `TermPolicy::viewAny()` utilise déjà `$establishment?->type === EstablishmentType::Secondaire` — remplacer par `$establishment?->isSecondaire()` pour rester cohérent (comportement inchangé).

### `Livewire\Academics\Classrooms\Index`

- `mount()` : après l'`authorize`, initialiser `$this->cycle` avec le premier cycle autorisé de l'établissement courant (au lieu du `Cycle::Secondaire->value` codé en dur à la déclaration de la propriété).
- `resetForm()` : même correction (au lieu de remettre `Cycle::Secondaire->value`).
- `render()` : `'cycles' => $establishment->allowedCycles()` au lieu de `Cycle::cases()`. La vue (`classrooms/index.blade.php`) boucle déjà sur la variable `$cycles` passée — aucun changement de template nécessaire.
- `save()` : après validation, vérifier que le cycle du niveau choisi est bien autorisé pour l'établissement courant :
  ```php
  $level = Level::findOrFail($data['level_id']);
  $establishment = Establishment::findOrFail((int) app('currentEstablishmentId'));

  if (! in_array($level->cycle, $establishment->allowedCycles(), true)) {
      throw ValidationException::withMessages([
          'level_id' => "Ce niveau n'est pas disponible pour ce type d'établissement.",
      ]);
  }
  ```
  Ce contrôle serveur est nécessaire même avec le filtrage du select client, en défense en profondeur (payload manipulé).

### Tests impactés par la Partie 0

- `tests/Feature/Livewire/Academics/ClassroomsTest.php` : le `beforeEach` crée un établissement de type aléatoire (`Establishment::factory()->create()`) puis certains tests y créent une classe préscolaire (cycle non secondaire) et d'autres une classe terminale (cycle secondaire) — devient flaky une fois la Partie 0 appliquée. Fixer chaque groupe de tests avec un établissement explicitement typé :
  - Tests exerçant le cycle préscolaire/primaire (`une classe peut être créée avec un cycle préscolaire`, `le numéro n'est pas exigé...`, `éditer une classe hydrate correctement son niveau`) → `Establishment::factory()->create(['type' => EstablishmentType::PrescolairePrimaire])`.
  - Tests exerçant le cycle secondaire (`le numéro absent n'empêche pas la série...`, `la série est requise...`, `une classe de terminale avec série...`) → `Establishment::factory()->create(['type' => EstablishmentType::Secondaire])`.
  - `le niveau est requis` : peu importe le cycle, garder le type par défaut du `beforeEach` (à fixer à `Secondaire` par simplicité).
- Nouveau test : `save()` rejette un niveau dont le cycle n'est pas autorisé pour le type de l'établissement courant (ex. niveau secondaire dans un établissement `PrescolairePrimaire`) → `assertHasErrors(['level_id'])`.
- `tests/Feature/Livewire/Grading/GradeSheetsTest.php` : le `beforeEach` crée elle aussi un établissement mono-instance avec des classes des 3 cycles. Ce fichier est de toute façon remplacé par la Partie 1 (voir plus bas) — pas de correction isolée nécessaire, il est réécrit directement avec des établissements typés.

## Partie 1 — Split des Évaluations (`GradeSheets`)

### Architecture : dispatcher + deux écrans dédiés

Un seul point d'entrée (route `grading.grade-sheets.index`, lien de nav "Évaluations" inchangé). La classe actuelle devient un dispatcher fin qui inclut, selon le type d'établissement, l'un des deux écrans concrets via `@livewire(...)` — le même mécanisme déjà utilisé dans ce projet pour `@livewire('establishments.switcher')` (`layouts/app.blade.php:178`). Aucun changement de route, de policy, ni de nav.

**`app/Livewire/Grading/GradeSheets/Index.php`** (réécrit, dispatcher) :
```php
#[Layout('layouts.app')]
#[Title('Évaluations')]
class Index extends Component
{
    public function mount(): void
    {
        $this->authorize('viewAny', GradeSheet::class);
    }

    public function render()
    {
        $establishment = Establishment::find((int) app('currentEstablishmentId'));

        return view('livewire.grading.grade-sheets.index', [
            'isPrimaire' => $establishment?->isPrescolairePrimaire() ?? false,
        ]);
    }
}
```
Vue (`livewire/grading/grade-sheets/index.blade.php`, réécrite) :
```blade
<div>
    @if ($isPrimaire)
        @livewire('grading.grade-sheets.primaire.index')
    @else
        @livewire('grading.grade-sheets.secondaire.index')
    @endif
</div>
```

**`app/Livewire/Grading/GradeSheets/Primaire/Index.php`** (nouveau) — formulaire 100% primaire :
- Propriétés : `showForm`, `classroom_id`, `subject_id`, `composition_number`, `title`, `max_score`, `weight`, `graded_on`. **Pas de `term_id`, pas de `type` bindé** (le type est toujours `composition`, fixé côté serveur — plus besoin de champ dans le formulaire, juste un texte statique "Composition" dans la vue).
- `mount()` : `authorize('viewAny', GradeSheet::class)`.
- `create()` : reset des champs, `weight = 1.0`, `graded_on = now()`.
- `updatedClassroomId()` : reset `subject_id`, `composition_number`.
- `save()` : valide `classroom_id`, `subject_id`, `composition_number` (required, 1-10), `title`, `max_score`, `weight`, `graded_on`. Vérifie `$classroom->level->cycle === Cycle::Primaire` (défense en profondeur), l'affectation enseignant via `isAssignedToClassroom($classroom->id)` (sans matière — règle classe entière déjà en place), `isGradable()`, et que la matière est `is_prescolaire_primaire`. Crée le `GradeSheet` avec `'type' => 'composition'` codé en dur.
- `render()` : classes = `Classroom::gradable()->whereHas('level', fn ($q) => $q->where('cycle', Cycle::Primaire))`. Matières = toujours filtrées `is_prescolaire_primaire` (plus besoin de refiltrer selon la classe sélectionnée, puisque toutes les classes de cet écran sont primaire) — conserve la logique "affectation classe entière" (`subject_id === null`) qui autorise un enseignant à noter toutes les matières du cycle.

**`app/Livewire/Grading/GradeSheets/Secondaire/Index.php`** (nouveau) — formulaire 100% secondaire :
- Propriétés : `showForm`, `classroom_id`, `subject_id`, `term_id`, `title`, `type` (`devoir`/`interrogation`), `max_score`, `weight`, `graded_on`. **Pas de `composition_number`.**
- `create()` : `type = 'devoir'`, `weight = 2.0`.
- `updatedClassroomId()` : reset `subject_id`, `term_id`.
- `updatedType()` : poids par défaut selon type (devoir=2.0, interrogation=1.0) — logique inchangée.
- `save()` : valide `classroom_id`, `subject_id`, `term_id` (required), `title`, `type` (`in:devoir,interrogation`), `max_score`, `weight`, `graded_on`. Vérifie `$classroom->level->cycle === Cycle::Secondaire`, affectation via `isAssignedToClassroom($classroom->id, $subject_id)` (avec matière), `isGradable()`, matière `is_secondaire`.
- `render()` : classes = `Classroom::gradable()->whereHas('level', fn ($q) => $q->where('cycle', Cycle::Secondaire))`. Matières = toujours `is_secondaire` pour un admin ; pour un enseignant, simplement ses matières affectées (**suppression de la branche "affectation classe entière"**, qui ne s'applique jamais au secondaire — chaque affectation secondaire porte toujours une matière).
- Nécessite toujours `terms` en vue-data (`Term::orderBy('sequence')->get()`).

Vues : `livewire/grading/grade-sheets/primaire/index.blade.php` et `.../secondaire/index.blade.php`, dérivées de la vue actuelle en supprimant la branche `@if/@else` de cycle (chacune ne garde que sa propre partie). Le tableau des évaluations existantes garde la colonne "Période" avec son affichage conditionnel actuel (`$gradeSheet->term?->label ?? "Composition {n}"` ) — ce n'est pas un problème puisque, dans une classe mono-cycle, une seule des deux valeurs sera jamais renseignée pour les lignes affichées par cet écran ; on garde ce fallback tel quel par simplicité (pas besoin de le splitter, il n'affiche jamais l'autre branche en pratique).

### `app/Livewire/Grading/GradeSheets/Enter.php` : inchangé

Cet écran de saisie des notes travaille sur une instance `GradeSheet` déjà typée (via l'URL `/grade-sheets/{gradeSheet}/enter`), sans aucune branche conditionnelle sur le cycle. Rien à séparer.

## Partie 2 — Split des Bulletins (`ReportCards`)

Même architecture, en plus simple (pas de formulaire de création, juste un sélecteur + génération).

**`app/Livewire/Grading/ReportCards/Index.php`** (réécrit, dispatcher) — même forme que la Partie 1, `#[Title('Bulletins')]`, `authorize('viewAny', ReportCard::class)`.

**`app/Livewire/Grading/ReportCards/Primaire/Index.php`** (nouveau) :
- Propriétés : `classroom_id`, `composition_number`.
- `updatedClassroomId()` : reset `composition_number`.
- `generate(ReportCardService $service)` : `authorize('create', ReportCard::class)`, valide `classroom_id`, `composition_number` (required), appelle `$service->generateForClassroomAndComposition(...)`.
- `render()` : classes = `Classroom::gradable()->whereHas('level', fn ($q) => $q->where('cycle', Cycle::Primaire))`. `reportCards` filtrés par `composition_number` + `school_year_id` (logique actuelle inchangée).

**`app/Livewire/Grading/ReportCards/Secondaire/Index.php`** (nouveau) :
- Propriétés : `classroom_id`, `term_id`.
- `generate()` : valide `term_id` (required), appelle `$service->generateForClassroomAndTerm(...)`.
- `render()` : classes filtrées `Cycle::Secondaire`, `terms` en vue-data, `reportCards` filtrés par `term_id`.

Vues : `livewire/grading/report-cards/primaire/index.blade.php` et `.../secondaire/index.blade.php`, chacune sans la branche de l'autre cycle. Le texte d'état vide s'adapte ("Sélectionnez une classe et un n° de composition" / "...et une période").

`ReportCardService`, `ReportCardPolicy`, `ReportCardPdfController` : **inchangés** (déjà agnostiques au cycle, opèrent sur le modèle `ReportCard` directement).

## Tests à réorganiser

- Supprimer `tests/Feature/Livewire/Grading/GradeSheetsTest.php`, remplacé par :
  - `tests/Feature/Livewire/Grading/GradeSheets/PrimaireTest.php` (établissement `PrescolairePrimaire`, une seule classe primaire, tests actuels adaptés : création, matière incompatible rejetée, poids par défaut, affectation classe entière — sans les tests "rejette une période"/"rejette n° composition" qui n'ont plus de sens, un seul chemin existant).
  - `tests/Feature/Livewire/Grading/GradeSheets/SecondaireTest.php` (établissement `Secondaire`, même logique côté devoir/interrogation/période).
  - Nouveau, léger : `tests/Feature/Livewire/Grading/GradeSheets/IndexTest.php` — vérifie que le dispatcher inclut le bon sous-composant selon `establishment.type` (2 tests, via `assertSeeLivewire` ou équivalent Livewire testing helper).
- Même split pour `ReportCardsTest.php` → `ReportCards/PrimaireTest.php`, `ReportCards/SecondaireTest.php`, `ReportCards/IndexTest.php`.
- `EnterTest.php` : inchangé.
- `tests/Feature/Domain/ReportCardServiceTest.php` : inchangé (teste le service, pas les écrans).

## Hors périmètre

- `ReportCardService`, `ReportCardPdfController`, PDF bulletins : déjà cycle-agnostiques, aucun changement.
- Le correctif TeacherAssignment (règle classe entière vs matière spécifique) mentionné dans une session précédente n'est pas repris ici — cette spec suppose les règles d'affectation actuelles telles quelles.
- Pas de nouvelle route, pas de changement de policy, pas de changement de nav.

## Vérification

1. `php artisan migrate:fresh --seed` (aucune migration dans cette spec, mais on repart d'un état propre pour les tests manuels).
2. `vendor/bin/pest` — suite complète verte.
3. `vendor/bin/phpstan analyse --memory-limit=512M` — clean.
4. Vérification manuelle sur les données WAMP existantes : ouvrir "Évaluations" et "Bulletins" pour un établissement préscolaire/primaire seedé (doit afficher directement le formulaire composition, sans aucun champ période) et pour un établissement secondaire (doit afficher directement période + devoir/interrogation, sans aucun champ composition).
5. Commit puis mise à jour de la mémoire projet si pertinent.
