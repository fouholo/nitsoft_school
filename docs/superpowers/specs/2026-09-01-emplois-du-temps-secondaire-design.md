# Emplois du temps du secondaire

*(Validé par l'utilisateur le 2026-09-01.)*

## Contexte

Aucun concept d'emploi du temps n'existe aujourd'hui dans le projet (recherche exhaustive du code : ni modèle, ni migration, ni vue, ni route). La structure académique du secondaire est en revanche déjà en place dans `App\Domain\Academics` : `Classroom` (établissement + année + niveau + série), `Subject` (matières), `TeacherAssignment` (table `teacher_classroom_subject` : quel enseignant enseigne quelle matière dans quelle classe, pour une année scolaire donnée — `subject_id` obligatoire au secondaire car spécialisation par matière, contrairement au préscolaire/primaire).

Ce chantier ajoute la planification hebdomadaire des séances de cours pour les classes de cycle secondaire (`Cycle::Secondaire`), en s'appuyant strictement sur les affectations `TeacherAssignment` déjà enregistrées.

## Périmètre

- Secondaire uniquement (cohérent avec la spécialisation par matière). Le préscolaire/primaire (enseignant généraliste par classe) est explicitement hors périmètre pour ce chantier — pas de modèle d'emploi du temps différent à concevoir maintenant, mais rien dans le schéma ne l'empêcherait plus tard.
- Modèle hebdomadaire récurrent, valable pour toute l'année scolaire (`SchoolYear`), pas de variation par trimestre, pas de gestion d'exceptions ponctuelles par date (jour férié, remplacement) — hors périmètre.
- Jours couverts : lundi à vendredi (5 jours fixes, pas de configuration par établissement).
- Salle : champ texte libre par séance (pas de catalogue de salles séparé), avec détection de conflit de double réservation.

## 1. Schéma

### `timetable_slots` (grille de créneaux, configurée une fois par établissement)

```php
Schema::create('timetable_slots', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
    $table->string('label');
    $table->time('start_time');
    $table->time('end_time');
    $table->unsignedInteger('sequence');
    $table->boolean('is_break')->default(false);
    $table->timestamps();
    $table->softDeletes();

    $table->unique(['establishment_id', 'sequence']);
});
```

Pas de `Syncable`/`uid_serveur` — table de configuration au même titre que `teacher_classroom_subject`, pas une fiche à scanner. `is_break` distingue les pauses/récréations (affichées dans la grille pour la lisibilité de l'emploi du temps complet, mais non sélectionnables comme créneau d'une séance).

### `timetable_sessions` (une séance = classe + matière + enseignant + jour + créneau)

```php
Schema::create('timetable_sessions', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
    $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
    $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
    $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('day_of_week');
    $table->foreignId('timetable_slot_id')->constrained()->cascadeOnDelete();
    $table->string('room')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

Pas de contrainte unique multi-colonnes en base (comme `teacher_classroom_subject` pour le cas `subject_id NULL` — voir spec du 2026-08-14) : la détection de conflit (classe/enseignant/salle déjà occupés sur un créneau) est une vérification applicative dans `save()`, pas une contrainte SQL. `day_of_week` stocke la valeur de l'enum `DayOfWeek` (string).

### `App\Domain\Timetable\Enums\DayOfWeek`

```php
enum DayOfWeek: string
{
    case Monday = 'monday';
    case Tuesday = 'tuesday';
    case Wednesday = 'wednesday';
    case Thursday = 'thursday';
    case Friday = 'friday';

    public function label(): string
    {
        return match ($this) {
            self::Monday => __('Lundi'),
            self::Tuesday => __('Mardi'),
            self::Wednesday => __('Mercredi'),
            self::Thursday => __('Jeudi'),
            self::Friday => __('Vendredi'),
        };
    }
}
```

### Modèles

`App\Domain\Timetable\Models\TimetableSlot` : `HasFactory`, `SoftDeletes`, `TenantScoped`. `$fillable` = `establishment_id`, `label`, `start_time`, `end_time`, `sequence`, `is_break`. `$casts` = `['start_time' => 'datetime:H:i', 'end_time' => 'datetime:H:i', 'is_break' => 'boolean']`.

`App\Domain\Timetable\Models\TimetableSession` : `HasFactory`, `SoftDeletes`, `TenantScoped`. `$fillable` = `establishment_id`, `school_year_id`, `classroom_id`, `subject_id`, `user_id`, `day_of_week`, `timetable_slot_id`, `room`. `$casts` = `['day_of_week' => DayOfWeek::class]`. Relations `classroom()`, `subject()`, `teacher(): BelongsTo(User::class, 'user_id')`, `slot(): BelongsTo(TimetableSlot::class, 'timetable_slot_id')`, `schoolYear()`.

## 2. Détection de conflits (`save()` du composant `Academics\Timetable\Index`)

Avant `TimetableSession::create()`/`update()`, pour le même `establishment_id` + `school_year_id` + `day_of_week` + `timetable_slot_id` (en excluant la séance courante si modification) :

1. **Classe déjà occupée** : une autre séance existe pour `classroom_id` → `$this->addError('conflict', __('Cette classe a déjà un cours sur ce créneau.'))`.
2. **Enseignant déjà occupé** : une autre séance existe pour `user_id` → `__("Cet enseignant a déjà un cours sur ce créneau.")`.
3. **Salle déjà occupée** (seulement si `room` renseigné) : une autre séance existe avec le même `room` → `__('Cette salle est déjà occupée sur ce créneau.')`.

Chaque vérification est un `TimetableSession::where(...)->where('id', '!=', $this->editingId)->exists()` — pas de requête combinée, pour que le message d'erreur identifie précisément la cause du conflit. Bloqué strictement : aucune des trois violations ne peut être forcée.

Avant ces trois vérifications, contrôle de cohérence avec les affectations existantes : `TeacherAssignment::where(['user_id' => ..., 'classroom_id' => ..., 'subject_id' => ..., 'school_year_id' => ...])->exists()` doit être vrai, sinon `__("Cet enseignant n'est pas affecté à cette matière pour cette classe.")`. Le formulaire ne propose d'ailleurs que les couples enseignant/matière déjà affectés à la classe sélectionnée (liste dérivée de `TeacherAssignment::where('classroom_id', ...)->with(['teacher', 'subject'])`), donc cette vérification est surtout une défense en profondeur contre un état de formulaire périmé (classe changée après chargement de la liste).

## 3. Autorisation

Deux nouvelles entrées dans `RolePermissions::MATRIX` :

```php
'timetable.manage' => ['fondateur', 'directeur', 'gestionnaire', 'educateur'],
```

`timetable.manage` gate la création/modification/suppression des séances et de la grille de créneaux. Pas d'entrée `timetable.view` dans la matrice : la consultation suit le même schéma que `TeacherAssignmentPolicy::viewAny` (`isAdminOfCurrentEstablishment`, qui couvre déjà `fondateur`/`directeur`/`gestionnaire`/`caissier`/`educateur`), élargi à l'enseignant via `isMemberOfCurrentEstablishment` + rôle `enseignant`.

`App\Policies\TimetableSlotPolicy` (à plat) :
```php
public function viewAny(User $user): bool
{
    return $this->isAdminOfCurrentEstablishment($user);
}

public function manage(User $user): bool // create/update/delete
{
    return $this->isLocalAdminOfCurrentEstablishment($user)
        && RolePermissions::can($user->currentRole(), 'timetable.manage');
}
```

`App\Policies\TimetableSessionPolicy` :
```php
public function viewAny(User $user): bool
{
    return $this->isAdminOfCurrentEstablishment($user)
        || $user->currentRole() === 'enseignant';
}

public function view(User $user, TimetableSession $session): bool
{
    if (! $this->belongsToSameEstablishment($user, $session->establishment_id)) {
        return false;
    }

    return $this->isAdminOfCurrentEstablishment($user)
        || $session->user_id === $user->id;
}

public function create(User $user): bool
{
    return $this->isLocalAdminOfCurrentEstablishment($user)
        && RolePermissions::can($user->currentRole(), 'timetable.manage');
}

public function delete(User $user, TimetableSession $session): bool
{
    return $this->belongsToSameEstablishment($user, $session->establishment_id)
        && $this->isLocalAdminOfCurrentEstablishment($user)
        && RolePermissions::can($user->currentRole(), 'timetable.manage');
}
```

Un enseignant passe `viewAny` mais n'a jamais `timetable.manage` (rôle absent de la matrice) : l'écran `Academics\Timetable\Index` (grille par classe) reste accessible en lecture pour lui comme pour tout le personnel administratif, boutons d'édition masqués si `! Gate::allows('create', TimetableSession::class)`. `MySchedule` (vue personnelle) reste le point d'entrée principal pour un enseignant — voir §4.

## 4. Écrans (Livewire)

Namespace `App\Livewire\Academics\Timetable`, routes ajoutées à `routes/academics.php` :

```php
Route::get('/timetable/slots', SlotsIndex::class)->name('timetable.slots.index');
Route::get('/timetable/{classroom}', TimetableIndex::class)->name('timetable.index');
Route::get('/my-timetable', MySchedule::class)->name('timetable.mine');
```

- **`SlotsIndex`** : CRUD de la grille de créneaux (`label`, `start_time`, `end_time`, `sequence`, `is_break`), réservé à `timetable.manage`. `mount()` → `$this->authorize('viewAny', TimetableSlot::class)`, actions gatées par `Gate::allows('manage', TimetableSlot::class)`.
- **`TimetableIndex`** (route paramétrée par classe, `Classroom $classroom` en binding) : `mount()` → `$this->authorize('viewAny', TimetableSession::class)`, puis vérifie `$classroom->level->cycle === Cycle::Secondaire` (sinon `abort(404)` — pas d'emploi du temps hors périmètre). Grille jours (colonnes) × créneaux non-pause (lignes), chaque case affiche la séance existante (matière + enseignant abrégé) ou reste vide. Un utilisateur avec `timetable.manage` clique une case vide pour ouvrir le formulaire d'ajout (`user_id`/`subject_id` limités aux couples de `TeacherAssignment` de cette classe, `day_of_week`/`timetable_slot_id` pré-remplis depuis la case cliquée, `room` libre), ou une case occupée pour modifier/supprimer. Sans `timetable.manage`, la grille est affichée sans interactions (pas de `wire:click` sur les cases).
- **`MySchedule`** : `mount()` → `$this->authorize('viewAny', TimetableSession::class)`, puis `render()` filtre `TimetableSession::where('user_id', auth()->id())` — un enseignant ne voit que ses propres séances, toutes classes confondues, jamais l'emploi du temps complet d'une classe où il n'intervient pas (choix validé). Même grille visuelle que `TimetableIndex` mais sans sélecteur de classe, purement en lecture.

Navigation : nouvelle entrée "Emplois du temps" dans le groupe "Académique" de `$navItems` (`resources/views/layouts/app.blade.php`), pointant vers `timetable.mine` pour un enseignant et vers la liste des classes (réutilise `academics.classrooms.index`, chaque ligne classe a désormais un lien vers `timetable.index`) pour le personnel administratif — pas de nouvel écran de sélection de classe dédié, cohérent avec l'absence d'écran "liste des emplois du temps" séparé.

## 5. Export PDF

Deux contrôleurs suivant le patron `ClassroomStudentListPdfController` :

- `TimetableClassroomPdfController` (route `academics.timetable.pdf.classroom`) : reçoit `Classroom $classroom`, charge `timetableSessions` avec `subject`/`teacher`/`slot`, rend `pdf.timetable-classroom` (grille jours × créneaux, en-tête via `pdf.partials.reports-header`).
- `TimetableTeacherPdfController` (route `academics.timetable.pdf.mine`) : reçoit l'utilisateur courant (`auth()->id()`), même gabarit de grille, filtré à ses séances.

Génération à la demande, aucun stockage — cohérent avec la règle "documents officiels non pré-générés" du projet.

## Hors périmètre

- Préscolaire/primaire (enseignant généraliste par classe) — pas de modèle d'emploi du temps équivalent dans ce chantier.
- Variation de l'emploi du temps par trimestre — une séance est rattachée à l'année scolaire (`school_year_id`), pas au trimestre.
- Exceptions ponctuelles par date (jour férié, remplacement) — le modèle est purement un gabarit hebdomadaire récurrent.
- Catalogue de salles (modèle dédié, capacité, équipements) — `room` reste un champ texte libre sur la séance.
- Jours de la semaine configurables par établissement (ex. samedi) — fixé à lundi–vendredi.
- Notification (SMS/email) d'un changement d'emploi du temps.

## Tests

`tests/Feature/Livewire/Academics/Timetable/SlotsIndexTest.php` :
- CRUD complet de la grille de créneaux par un admin habilité (`timetable.manage`).
- Un utilisateur sans `timetable.manage` (ex. caissier) ne peut pas créer/modifier de créneau.
- Cloisonnement multi-établissement (une grille d'un autre établissement est invisible).

`tests/Feature/Livewire/Academics/Timetable/IndexTest.php` :
- Création d'une séance valide (couple enseignant/matière déjà affecté à la classe).
- Rejet si le couple enseignant/matière n'est pas dans `TeacherAssignment` de cette classe.
- Rejet : classe déjà occupée sur ce créneau.
- Rejet : enseignant déjà occupé sur ce créneau (même classe ou une autre).
- Rejet : salle déjà occupée sur ce créneau.
- Modifier une séance existante ne se bloque pas elle-même (exclusion de l'id courant des vérifications de conflit).
- Une classe de cycle préscolaire/primaire renvoie 404 sur cette route.
- Un enseignant sans `timetable.manage` voit la grille en lecture seule (pas d'action de création/suppression disponible).
- Cloisonnement multi-établissement.

`tests/Feature/Livewire/Academics/Timetable/MyScheduleTest.php` :
- Un enseignant ne voit que ses propres séances, pas celles d'un collègue sur la même classe.
- Accessible aussi au personnel administratif (pas seulement aux enseignants).

`tests/Feature/Policies/TimetablePolicyTest.php` : matrice `timetable.manage` par rôle (fondateur/directeur/gestionnaire/éducateur autorisés, caissier/enseignant refusés en écriture), `view` d'une séance par son propre enseignant vs par un autre enseignant.

`tests/Feature/Http/TimetablePdfTest.php` : rendu de vue direct (gabarit `ClassroomStudentListPdfTest`) pour le PDF classe et le PDF enseignant, en-tête administratif présent.

## i18n

Suit la stratégie établie : `__('Phrase française exacte')`, clés ajoutées à `lang/en.json`/`lang/ar.json` (labels des jours, messages de conflit, libellés d'écran).

## Vérification

1. Basculer sur base SQLite jetable (`.env` sauvegardé/restauré), `php artisan migrate:fresh --seed`.
2. `vendor/bin/pest` — suite complète verte.
3. `vendor/bin/phpstan analyse --memory-limit=512M` — clean.
4. `vendor/bin/pint --test` scopé aux fichiers touchés.
5. Vérification manuelle Playwright : création d'une grille de créneaux, création de plusieurs séances pour une classe secondaire seedée, tentative de conflit (classe/enseignant/salle) affichant le bon message, vue "Mon emploi du temps" d'un enseignant seedé, génération des deux PDF.
6. Commit puis mise à jour de la mémoire projet si un piège notable est rencontré.
