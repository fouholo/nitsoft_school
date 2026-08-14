# Matières : catalogue global SaaS admin + domaines disciplinaires

## Contexte

`Subject` (matière) est aujourd'hui une ressource par établissement (`establishment_id`, trait `TenantScoped`) : chaque établissement crée et gère sa propre liste de matières, sans lien avec un cycle (préscolaire/primaire vs secondaire) ni avec un regroupement disciplinaire. Rien n'empêche aujourd'hui d'assigner n'importe quelle matière à n'importe quel niveau via la grille de coefficients (`SubjectCoefficient`).

Ce chantier fait de `Subject` un catalogue de référence global, géré exclusivement par les administrateurs SaaS — même gouvernance que `Inspection`/`Direction`/`Level` (table de référence partagée par toute la plateforme, pas une ressource d'établissement). Deux ajouts motivent ce changement :

- **Deux booléens de cycle** (`is_prescolaire_primaire`, `is_secondaire`) pour indiquer à quel(s) cycle(s) une matière s'applique, et filtrer en conséquence les écrans de saisie d'évaluation et de coefficients.
- **Un domaine disciplinaire** (`domain_id`, vers une nouvelle table `domains`) pour regrouper les matières (Sciences, Lettres, etc.). Ce champ prépare un futur chantier de bilans par domaine sur les relevés de notes du secondaire — **le calcul/affichage de ce bilan n'est pas dans le périmètre de ce chantier-ci**, uniquement la donnée.

`domains` est elle-même une table de référence (pas un enum PHP) pour rester extensible sans déploiement de code, comme `Level`/`Serie`/`Inspection`.

## 1. Table `domains`

Nouvelle table, même gouvernance que `Inspection`/`Direction` : pas de `establishment_id`, pas de `TenantScoped`, uniquement `Syncable`.

```php
Schema::create('domains', function (Blueprint $table): void {
    $table->id();
    $table->string('uid_local', 20)->unique();
    $table->char('uid_serveur', 12)->nullable()->unique();
    $table->uuid('device_id')->nullable();
    $table->timestamp('client_updated_at')->nullable();
    $table->string('name', 100)->unique();
    $table->timestamps();
});
```

`App\Domain\Academics\Models\Domain` — `Syncable` uniquement (pas de `SoftDeletes`, cohérent avec `Inspection`). `uidPrefix()` : prochain préfixe disponible dans la séquence existante (voir les préfixes déjà utilisés — `214` Term, `215` Subject, `217` Inspection — choisir un préfixe libre en phase de plan).

`App\Policies\DomainPolicy` : toutes les abilities retournent `false` (accès exclusivement via le bypass `Gate::before` pour les SaaS admins, comme `InspectionPolicy`).

### Écran `Livewire\Domains\Index`

CRUD à un seul champ (`name`), calqué sur `Livewire\Inspections\Index`. Route `domains.index` (`routes/domains.php`, même structure que `routes/inspections.php`). Entrée de nav dans le bloc SaaS admin de `layouts/app.blade.php` (aux côtés de "Inspections", "Directions").

## 2. `Subject` devient global

Migration `add_cycle_and_domain_to_subjects_table` :

```php
Schema::table('subjects', function (Blueprint $table): void {
    $table->boolean('is_prescolaire_primaire')->default(true)->after('name');
    $table->boolean('is_secondaire')->default(true)->after('is_prescolaire_primaire');
    $table->foreignId('domain_id')->nullable()->after('is_secondaire')->constrained()->nullOnDelete();
});
```

Puis une seconde migration `drop_establishment_id_from_subjects_table` : suppression de la colonne `establishment_id` et de sa contrainte de clé étrangère.

- Le défaut `true`/`true` sur les deux booléens s'applique aux lignes existantes : aucune matière déjà utilisée ne disparaît des sélecteurs après la migration. Le SaaS admin affine ensuite manuellement via le nouvel écran.
- Pas de dédoublonnage automatique des matières homonymes créées séparément par plusieurs établissements (ex. deux lignes "Maths") — laissé à la main du SaaS admin après coup, comme pour les précédents chantiers de ce type (cf. absence de backfill sur le chantier de l'instantané financier).
- `domain_id` reste nullable : aucune matière existante n'a de domaine assigné par défaut ; c'est un attribut optionnel à renseigner au cas par cas.

`App\Domain\Academics\Models\Subject` : retrait du trait `TenantScoped`, conservation de `Syncable` et `SoftDeletes`. `$fillable` : retrait de `establishment_id`, ajout de `is_prescolaire_primaire`, `is_secondaire`, `domain_id`. Nouvelle relation `domain(): BelongsTo`.

Validation à la sauvegarde (`Livewire\Academics\Subjects\Index::save()`) : règle applicative garantissant qu'au moins un des deux booléens est `true` (une matière rattachée à aucun cycle n'a pas de sens) — via une règle de validation personnalisée ou un `Rule::closure()`, à trancher en phase de plan.

### `SubjectPolicy`

Réécrite à l'identique de `InspectionPolicy` : toutes les abilities (`viewAny`, `view`, `create`, `update`, `delete`) retournent `false`. Accès exclusivement SaaS admin via `Gate::before`.

### Écran `Livewire\Academics\Subjects\Index`

Reste en `App\Livewire\Academics\Subjects\Index` (pas de déplacement de namespace, seulement de nav — cohérent avec le fait que `SubjectCoefficient` reste dans le même dossier `Academics`). Ajouts au formulaire : deux cases à cocher (préscolaire/primaire, secondaire), un sélecteur de domaine (`Domain::orderBy('name')->get()`, option "—" pour `null`).

Route existante `academics.subjects.index` conservée telle quelle (pas de renommage de route pour limiter le risque de régression sur les liens existants). Dans `layouts/app.blade.php`, l'entrée "Matières" est retirée du groupe "Académique" (établissement) et ajoutée dans le bloc SaaS admin (`isSaasAdmin()`), aux côtés de "Inspections"/"Directions"/"Domaines".

## 3. Filtrage par cycle dans les écrans existants

**`Livewire\Grading\GradeSheets\Index`** : la liste `subjects` retournée par `render()` se filtre selon le cycle de la classe sélectionnée, en utilisant `selectedClassroomCycle()` (déjà existant) :

```php
$cycle = $this->selectedClassroomCycle();
$subjects = Subject::when($cycle === Cycle::Primaire, fn ($q) => $q->where('is_prescolaire_primaire', true))
    ->when($cycle === Cycle::Secondaire, fn ($q) => $q->where('is_secondaire', true))
    ->orderBy('name')->get();
```

Validation serveur en profondeur dans `save()` (même esprit que la vérification déjà en place pour `type` selon le cycle) : le `subject_id` soumis doit correspondre à une matière autorisée pour le cycle de la classe, sinon `ValidationException`.

**`Livewire\Academics\SubjectCoefficients\Index`** : même filtrage, basé sur le cycle du niveau sélectionné (`Level::whereKey($this->level_id)->value('cycle')`), appliqué à la fois dans `loadCoefficients()` (construction du tableau éditable) et dans `render()` (liste affichée).

## Hors périmètre

- Pas de calcul ni d'affichage du bilan par domaine sur le bulletin PDF — uniquement la donnée (`domain_id`) est posée dans ce chantier ; le calcul est un chantier futur distinct.
- Pas de dédoublonnage/fusion automatique des matières existantes en doublon entre établissements.
- Pas de renommage de la route `academics.subjects.index` ni de déplacement du namespace Livewire — seul le nav et la policy changent de gouvernance.
- Pas de filtrage rétroactif des `GradeSheet`/`SubjectCoefficient` déjà existants qui référenceraient une matière désormais incompatible avec leur cycle (les booléens par défaut `true`/`true` rendent ce cas improbable au moment de la migration).

## Tests à mettre à jour

- `tests/Feature/Livewire/Academics/SubjectCoefficientsTest.php` : Subject n'est plus établissement-scopé, à adapter (plus besoin de créer une matière par établissement).
- Nouveau `tests/Feature/Livewire/Domains/IndexTest.php` : CRUD + gouvernance SaaS admin (accès refusé à un non-SaaS-admin).
- Nouveau `tests/Feature/Livewire/Academics/SubjectsTest.php` : gouvernance SaaS admin (accès refusé à un directeur/gestionnaire d'établissement), validation "au moins un cycle coché", assignation d'un domaine.
- `tests/Feature/Livewire/Grading/GradeSheetsTest.php` : extension pour vérifier le filtrage de la liste de matières par cycle de classe, et le rejet serveur d'une matière incompatible.
