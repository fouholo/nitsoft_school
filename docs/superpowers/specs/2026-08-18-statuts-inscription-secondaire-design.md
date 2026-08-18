# Statuts d'inscription spécifiques au secondaire (redoublant, boursier, internat, affecté(e))

*(Design validé par l'utilisateur le 2026-08-18.)*

## Contexte

Le formulaire d'inscription d'un élève (`app/Livewire/Students/Show.php`, section "Nouvelle inscription" de la fiche élève) ne capture aujourd'hui que classe, année scolaire et date d'inscription. L'utilisateur souhaite y ajouter 4 statuts propres au cycle secondaire :

- **Redoublant** : l'élève reprend le même niveau.
- **Boursier** : l'élève bénéficie d'une bourse.
- **Internat** : l'élève est interne (hébergé par l'établissement).
- **Affecté(e)** : l'élève a été affecté(e) dans l'établissement (affectation post-BEPC, typique du secondaire ivoirien).

Ces statuts varient d'une année scolaire à l'autre pour un même élève (un redoublant une année peut ne plus l'être la suivante) — ils appartiennent donc à l'**inscription** (`Enrollment`, une ligne par élève/classe/année scolaire), pas à la fiche élève (`Student`, permanente).

Champ actuel de `enrollments` (`app/Domain/Enrollment/Models/Enrollment.php`) : `establishment_id`, `student_id`, `classroom_id`, `school_year_id`, `enrolled_on`, `status` + colonnes de synchronisation (`Syncable`, `TenantScoped`).

Le cycle d'un niveau est déjà modélisé via `Level.cycle` (enum `Cycle` : Prescolaire|Primaire|Secondaire), accessible via `Classroom::level`.

## 1. Migration

Nouvelle migration ajoutant 4 colonnes booléennes nullables (défaut `false`) à `enrollments` :

```php
Schema::table('enrollments', function (Blueprint $table): void {
    $table->boolean('is_repeating')->default(false);
    $table->boolean('is_scholarship')->default(false);
    $table->boolean('is_boarding')->default(false);
    $table->boolean('is_assigned')->default(false);
});
```

`Enrollment::$fillable` gagne ces 4 colonnes ; `Enrollment::$casts` gagne leur cast `boolean`.

## 2. `Students\Show.php` — capture conditionnelle au secondaire

Ajout de 4 propriétés publiques booléennes : `$is_repeating`, `$is_scholarship`, `$is_boarding`, `$is_assigned`, remises à `false` dans `addEnrollment()` (comme les autres champs du formulaire d'inscription) et à la fin de `saveEnrollment()`.

Le `<select>` Classe passe de `wire:model="classroom_id"` à `wire:model.live="classroom_id"` : sans ce changement, la sélection d'une classe ne déclenche aucune requête serveur avant la soumission du formulaire, et les cases à cocher n'apparaîtraient donc jamais avant l'enregistrement.

`render()` calcule, à partir de `$this->classroom_id` courant, si la classe sélectionnée est de cycle secondaire, et passe ce booléen à la vue sous `isSecondaireClassroom` :

```php
$isSecondaireClassroom = $this->classroom_id
    ? Classroom::find($this->classroom_id)?->level?->cycle === Cycle::Secondaire
    : false;
```

`saveEnrollment()` valide les 4 champs comme `['boolean']` et les inclut dans le tableau passé à `$this->student->enrollments()->create([...])` — leur valeur reste `false` si la classe n'est pas au secondaire (l'utilisateur n'a pas pu les modifier, les cases n'étant pas affichées).

## 3. Vue — cases à cocher conditionnelles

Dans `resources/views/livewire/students/show.blade.php`, section `@if ($showEnrollmentForm)`, juste après le champ "Date d'inscription" :

```blade
@if ($isSecondaireClassroom)
    <div class="sm:col-span-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" wire:model="is_repeating"> Redoublant
        </label>
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" wire:model="is_scholarship"> Boursier
        </label>
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" wire:model="is_boarding"> Internat
        </label>
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" wire:model="is_assigned"> Affecté(e)
        </label>
    </div>
@endif
```

`wire:model` (non `.live`) suffit : la classe est déjà sélectionnée quand ces champs deviennent visibles, `saveEnrollment()` lit leur valeur au submit.

## 4. Tableau "Inscriptions" — badges

Dans la même vue, section tableau des inscriptions existantes, une cellule "Statuts" est ajoutée après la colonne "Statut" (utilisée pour `active`/`withdrawn`), affichant un badge par statut vrai :

```blade
<td class="px-4 py-2">
    <div class="flex flex-wrap gap-1">
        @if ($enrollment->is_repeating)
            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">Redoublant</span>
        @endif
        @if ($enrollment->is_scholarship)
            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800">Boursier</span>
        @endif
        @if ($enrollment->is_boarding)
            <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs text-indigo-800">Internat</span>
        @endif
        @if ($enrollment->is_assigned)
            <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs text-slate-700">Affecté(e)</span>
        @endif
    </div>
</td>
```

Aucune condition de cycle nécessaire à l'affichage : les inscriptions préscolaire/primaire n'auront jamais ces booléens à `true` puisque le formulaire ne les expose jamais pour ces cycles — la cellule sera simplement vide pour elles.

## 5. Tests

Nouveau fichier ou extension de `tests/Feature/Livewire/Students/ShowTest.php` (à vérifier/créer selon l'existant) :

- Les 4 cases à cocher n'apparaissent pas quand `classroom_id` correspond à une classe primaire/préscolaire (`assertDontSee`).
- Les 4 cases à cocher apparaissent pour une classe secondaire (`assertSee`).
- `saveEnrollment()` avec une classe secondaire et les 4 statuts cochés persiste correctement les 4 colonnes sur `Enrollment`.
- `saveEnrollment()` avec une classe primaire ignore silencieusement toute tentative de statut (les propriétés ne sont de toute façon pas modifiables depuis la vue pour ce cas, mais on vérifie que la valeur enregistrée reste `false`).
- Le tableau "Inscriptions" affiche les badges correspondant aux statuts vrais d'une inscription secondaire.

## Vérification

1. `php artisan migrate` (WAMP + suite de tests).
2. `vendor/bin/pest` — suite complète verte.
3. `vendor/bin/phpstan analyse --memory-limit=512M` — clean.
4. Vérification manuelle sur données réelles WAMP : créer une inscription secondaire avec les 4 statuts, confirmer l'affichage des badges ; créer une inscription primaire, confirmer l'absence des cases à cocher et de badges.
5. Commit puis mise à jour de la mémoire projet si pertinent.
