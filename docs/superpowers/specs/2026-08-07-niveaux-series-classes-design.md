# Refonte de la saisie des classes : niveaux, séries et numérotation

## Contexte

Aujourd'hui, une classe (`classrooms`) a un champ `level` en texte libre et un champ `cycle` (préscolaire/primaire/secondaire) choisi indépendamment par l'admin, sans validation croisée — rien n'empêche de créer un niveau "Terminale" avec le cycle "Préscolaire" par erreur. Le nom de la classe (`name`, ex. "6ème A") est saisi librement, sans structure.

Cette refonte introduit une table de référence `levels` (niveaux du programme scolaire national), et une table `series` (filières du second cycle secondaire : A, C, D, etc.), pour structurer la saisie et garantir la cohérence niveau/cycle.

## 1. Table `levels`

Référence **globale**, partagée par toute la plateforme (même famille que `nationalites` : liste standardisée par le programme scolaire national, pas par établissement).

```php
Schema::create('levels', function (Blueprint $table): void {
    $table->id();
    $table->string('level', 10)->unique();
    $table->string('level_wording', 50);
    $table->string('cycle');
    $table->boolean('requires_series')->default(false);
    $table->timestamps();
});
```

- `level` : code court (ex. `TLE`, `CP1`, `GS`).
- `level_wording` : libellé (ex. "Terminale", "CP1", "Grande Section").
- `cycle` : une des valeurs de l'enum `Cycle` existant (`prescolaire`/`primaire`/`secondaire`).
- `requires_series` : `true` uniquement pour les niveaux du second cycle secondaire (2nde, 1ère, Tle) — pilote l'affichage/obligation du champ série dans le formulaire classe.

Modèle `App\Domain\Academics\Models\Level` : fillable `['level', 'level_wording', 'cycle', 'requires_series']`, cast `cycle` => `Cycle::class`, `requires_series` => `boolean`.

**Gestion** : comme `nationalites`, aucun écran d'administration dans cette itération — réservé aux admins de la plateforme SaaS (`super_admin`) si un écran est construit plus tard, pas aux directeurs d'établissement. Peuplée via le seeder avec un jeu représentatif du programme ivoirien (PS/MS/GS préscolaire ; CP1/CP2/CE1/CE2/CM1/CM2 primaire ; 6ème/5ème/4ème/3ème/2nde/1ère/Tle secondaire, les trois derniers avec `requires_series=true`).

## 2. Table `series`

Référence **globale**, même statut que `levels`.

```php
Schema::create('series', function (Blueprint $table): void {
    $table->id();
    $table->string('serie', 10)->unique();
    $table->string('serie_wording', 50);
    $table->timestamps();
});
```

Modèle `App\Domain\Academics\Models\Serie` : fillable `['serie', 'serie_wording']`. Peuplée via le seeder avec les séries courantes du secondaire ivoirien (A1, A2, C, D, ...).

## 3. Champs ajoutés/retirés sur `classrooms`

```php
Schema::table('classrooms', function (Blueprint $table): void {
    $table->foreignId('level_id')->constrained('levels');
    $table->foreignId('serie_id')->nullable()->constrained('series')->nullOnDelete();
    $table->string('numero', 2);
});

Schema::table('classrooms', function (Blueprint $table): void {
    $table->dropColumn(['level', 'cycle']);
});
```

- `level_id` : **obligatoire**. Remplace le texte libre `level`. Le cycle de la classe n'est plus stocké directement — il est désormais **toujours lu via `classroom->level->cycle`**.
- `serie_id` : nullable en base, mais **obligatoire côté validation** si le niveau choisi a `requires_series=true` (2nde/1ère/Tle) ; absent/ignoré sinon.
- `numero` : identifiant de section, saisi via liste déroulante figée (codée en dur dans le composant, pas de table) — lettres `A` à `F` pour préscolaire/primaire, chiffres `1` à `10` pour secondaire, la liste affichée dépendant du cycle du niveau choisi.
- `name` : reste une colonne persistée (utilisée pour tri/recherche), mais n'est **plus saisie à la main** — calculée automatiquement à l'enregistrement : `{level_wording}` + (` {serie}` si une série est choisie) + ` {numero}`. Exemples : `"Terminale C 1"`, `"CP1 A"`, `"Grande Section A"`.

`Classroom` modèle :
```php
protected $fillable = [
    'establishment_id', 'school_year_id', 'name',
    'level_id', 'serie_id', 'numero',
    'capacity', 'uid', 'device_id', 'client_updated_at',
];

public function level(): BelongsTo
{
    return $this->belongsTo(Level::class);
}

public function serie(): BelongsTo
{
    return $this->belongsTo(Serie::class);
}

public function isGradable(): bool
{
    return $this->level->cycle !== Cycle::Prescolaire;
}

public function scopeGradable(Builder $query): Builder
{
    return $query->whereHas('level', fn (Builder $q) => $q->where('cycle', '!=', Cycle::Prescolaire));
}
```

## 4. Formulaire classe (`Livewire\Academics\Classrooms\Index`)

Remplace les champs actuels "Nom" (texte libre) + "Niveau" (texte libre) + "Cycle" (select) par un flux guidé :

1. **Cycle** (select, propriété UI `$cycle` non persistée) — filtre la liste des niveaux proposés.
2. **Niveau** (select `$level_id`, options = `Level::where('cycle', $this->cycle)->orderBy('level_wording')->get()`) — se réinitialise si le cycle change.
3. **Série** (select `$serie_id`, affiché uniquement si le niveau choisi a `requires_series=true`, options = `Serie::orderBy('serie')->get()`) — masqué et ignoré sinon.
4. **Numéro** (select `$numero`, options figées selon le cycle du niveau choisi : `['A','B','C','D','E','F']` pour préscolaire/primaire, `['1'..'10']` pour secondaire).
5. **Capacité** et **Année scolaire** inchangés.

Le nom n'est plus un champ du formulaire — il est composé côté serveur dans `save()` juste avant l'enregistrement, à partir du niveau/série/numéro validés.

Validation :
```php
$data = $this->validate([
    'level_id' => ['required', 'exists:levels,id'],
    'serie_id' => [Rule::requiredIf(fn () => $this->selectedLevelRequiresSeries()), 'nullable', 'exists:series,id'],
    'numero' => ['required', 'string', 'max:2'],
    'capacity' => ['nullable', 'integer', 'min:1'],
    'school_year_id' => ['required', 'exists:school_years,id'],
]);
```

## 5. Liste des classes

Colonnes "Niveau" et "Cycle" affichées via `$classroom->level->level_wording` et `$classroom->level->cycle->badgeClass()/label()` (au lieu des colonnes texte actuelles). Colonne "Nom" affiche le nom composé stocké.

## 6. Répercussions sur le filtrage par cycle (notes/bulletins)

Tout code qui filtrait directement sur `classrooms.cycle` doit passer par la relation `level` :
- `GradeSheets\Index` : `whereHas('classroom', fn ($q) => $q->where('cycle', '!=', Cycle::Prescolaire))` → `whereHas('classroom.level', fn ($q) => $q->where('cycle', '!=', Cycle::Prescolaire))`.
- `Classroom::scopeGradable()`/`isGradable()` déjà couverts en §3 — `ReportCardService` et `ReportCards\Index` qui les appellent n'ont rien à changer (encapsulation déjà en place).

## 7. Factories et seeder

- Nouvelles `LevelFactory` et `SerieFactory`.
- `ClassroomFactory` : `level_id` via un niveau secondaire par défaut sans série requise (ex. "6ème"), `numero` par défaut. États `prescolaire()`/`primaire()` existants adaptés pour attacher un niveau du bon cycle au lieu de fixer `cycle` directement. Nouvel état pour un niveau exigeant une série (ex. `terminale()`), utile pour tester la validation conditionnelle.
- `DatabaseSeeder` : peuple `levels` (jeu complet ci-dessus) et `series` avant de créer les classes de démonstration ("École La Pouponnière") ; ces classes référencent désormais `level_id`/`numero` au lieu de `level`/`cycle` en texte libre.

## 8. Tests à mettre à jour

- `tests/Feature/Domain/Academics/ClassroomTest.php`, `tests/Feature/Livewire/Academics/ClassroomsTest.php` : adaptés au nouveau flux niveau/série/numéro et à la composition automatique du nom.
- `tests/Feature/Livewire/Grading/GradeSheetsTest.php`, `ReportCardsTest.php` : les scénarios "classe préscolaire absente/rejetée" continuent de fonctionner via les factories mises à jour, à vérifier qu'ils passent toujours après le changement de source du cycle.
- Dataset `classrooms` de `AcademicsAndEnrollmentPolicyTest` : vérifier que la factory par défaut reste compatible.

## Hors périmètre

- Pas d'écran de gestion pour `levels`/`series` dans cette itération (cohérent avec `nationalites`).
- Pas de renommage/migration de données existantes au-delà du greenfield actuel (`migrate:fresh --seed` reste la méthode de reset).
- Le champ `numero` reste une liste figée codée en dur, pas une table de référence.
