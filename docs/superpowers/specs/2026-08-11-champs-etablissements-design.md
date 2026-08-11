# Champs complémentaires sur les établissements

**Date** : 2026-08-11
**Statut** : Approuvé

## Contexte

La table `establishments` ne porte aujourd'hui que les champs de base (`name`, `slug`, `type`, `address`, `phone`, `is_active`, plus les colonnes Syncable). L'utilisateur demande d'ajouter des informations administratives et de contact propres au contexte scolaire ivoirien : identifiant de la circonscription d'inspection pédagogique, codes officiels (ouverture, DSPS), localisation, contact e-mail, indicateur école arabe, logo.

## Décisions validées avec l'utilisateur

1. **`uid_inspection` devient une vraie référence**, pas un champ texte libre : nouvelle table `inspections` (même statut que `nationalites`/`levels`/`series` — globale, pas d'`establishment_id`, pas d'écran de gestion pour l'instant, peuplée par seeder). Colonne renommée `inspection_code` sur `establishments` (FK vers `inspections.code`), pour suivre la convention déjà en place (`Student.nationalite_code` → `Nationalite.code`) et éviter la confusion avec `uid_local`/`uid_serveur` (système de sync tout juste posé, sans rapport).
2. **`location` = coordonnées GPS** (`latitude`/`longitude`), pas une subdivision administrative en texte — `address` couvre déjà l'adresse textuelle.
3. **Gouvernance identique aux champs existants** : SaaS (`Livewire\Establishments\Index`) et fondateur/General Admin (`Staff\ManageOrganization::createEstablishment()`) peuvent tous deux saisir ces champs, aux mêmes conditions que `name`/`address`/`phone` aujourd'hui.
4. **`logo`** : upload d'image comme la photo élève (`Students\Index`), **et affiché dès ce chantier** en en-tête des bulletins (`report-card.blade.php`) et reçus de paiement (`receipt.blade.php`).
5. Tous les nouveaux champs sont **nullable** — aucun rendu obligatoire.

## Design

### 1. Nouvelle table `inspections`

Migration `create_inspections_table` :
```php
Schema::create('inspections', function (Blueprint $table): void {
    $table->string('code', 10)->primary();
    $table->string('libelle', 100);
    $table->timestamps();
});
```
Modèle `App\Domain\Establishments\Models\Inspection` — même gabarit que `Nationalite` (`$primaryKey = 'code'`, `$keyType = 'string'`, `$incrementing = false`, pas de trait spécial). Peuplée uniquement via `DatabaseSeeder` (quelques entrées d'exemple), pas de contrainte de test dessus (colonne nullable côté `establishments`, aucun test ne dépend d'une ligne existante).

### 2. Colonnes ajoutées sur `establishments`

Migration `add_administrative_fields_to_establishments_table` :
```php
Schema::table('establishments', function (Blueprint $table): void {
    $table->string('inspection_code', 10)->nullable();
    $table->foreign('inspection_code')->references('code')->on('inspections')->nullOnDelete();
    $table->string('opening_code')->nullable();
    $table->string('dsps_code')->nullable();
    $table->decimal('latitude', 10, 7)->nullable();
    $table->decimal('longitude', 10, 7)->nullable();
    $table->string('email')->nullable();
    $table->boolean('is_arabe')->default(false);
    $table->string('logo_path')->nullable();
});
```

`Establishment.php` : ajout au `$fillable` (`inspection_code`, `opening_code`, `dsps_code`, `latitude`, `longitude`, `email`, `is_arabe`, `logo_path`), cast `'is_arabe' => 'boolean'`, nouvelle relation `inspection(): BelongsTo` (avec le PHPDoc `@return BelongsTo<Inspection, $this>` préventif, piège Larastan déjà rencontré plusieurs fois sur ce projet pour les `belongsTo()` frais).

### 3. Formulaires

**`Livewire\Establishments\Index`** (SaaS, avec édition) : ajout de `WithFileUploads`, propriétés `inspection_code`, `opening_code`, `dsps_code`, `latitude`, `longitude`, `email`, `is_arabe`, `?TemporaryUploadedFile $logo`, `existingLogoPath` (gabarit identique à `Students\Index` pour le logo : suppression de l'ancien fichier avant stockage du nouveau). Validation : `inspection_code` nullable `exists:inspections,code` ; `opening_code`/`dsps_code` nullable string max 100 ; `latitude` nullable numeric between -90/90 ; `longitude` nullable numeric between -180/180 ; `email` nullable email max 255 ; `is_arabe` boolean ; `logo` nullable image mimes jpg/jpeg/png/webp/gif max 1024 (Ko — plus grand que la photo élève, 100 Ko, car destiné à l'impression d'en-tête). Vue : nouveaux champs dans le formulaire existant, select `inspection_code` (`Inspection::orderBy('libelle')->get()`), input file pour le logo avec aperçu de l'existant si présent.

**`Staff\ManageOrganization::createEstablishment()`** (fondateur, création uniquement — cet écran n'a aujourd'hui aucune capacité d'édition d'un établissement existant, pour aucun champ) : mêmes propriétés préfixées `new_establishment_*`, même validation, même traitement du logo à la création. Pas de gestion de suppression/remplacement du logo ici (pas de flux d'édition existant sur cet écran).

### 4. Logo sur les PDF

`report-card.blade.php` et `receipt.blade.php` : dans le bloc `.header`, avant le `<h1>` :
```blade
@if ($establishment->logo_path)
    <img src="{{ public_path('storage/'.$establishment->logo_path) }}" style="height: 60px; margin-bottom: 8px;">
@endif
```
Chemin disque local (`public_path()`), pas l'URL HTTP habituelle (`Storage::disk('public')->url()`) — dompdf ne charge pas fiablement les images distantes sans configuration `enable_remote` explicite (absente de ce projet), le chemin local fonctionne nativement.

### 5. Tests

- `InspectionTest` ou test inline : `Establishment::factory()->create(['inspection_code' => ...])` fonctionne, contrainte FK respectée.
- `Establishments\IndexTest` : création/édition avec les nouveaux champs (y compris upload de logo, gabarit `StudentsTest` existant pour l'upload).
- `ManageOrganizationTest` : le fondateur crée un établissement avec les nouveaux champs renseignés.
- Vérification que le bloc logo s'affiche dans le rendu Blade quand `logo_path` est renseigné, et est absent sinon (rendu direct de la vue, sans nécessairement générer le PDF).

## Hors périmètre (explicitement écarté)

- Écran de gestion de la table `inspections` (peuplée par seeder uniquement, comme `nationalites`/`levels`).
- Toute logique métier liée à `is_arabe` (filtrage, matières spécifiques, etc.) — seul le champ est posé.
- Édition du logo (ou de tout autre champ) d'un établissement existant depuis `ManageOrganization` — cet écran reste création-only, cohérent avec son état actuel.
- Affichage de la géolocalisation sur une carte — seules les colonnes `latitude`/`longitude` sont posées.
